<?php

/**
 * Chronological stock snapshots and ledger sync from inventory_logs.
 */
class InventoryLedgerHelper
{
    public static function movementTypeFromReason(string $reason): string
    {
        $map = [
            'sale'       => 'sale',
            'restock'    => 'adjustment',
            'damage'     => 'damaged',
            'correction' => 'adjustment',
            'transfer'   => 'transfer_out',
        ];

        return $map[$reason] ?? 'adjustment';
    }

    /**
     * Build opening / in / out / current per inventory_log id using chronological replay.
     *
     * @return array<string, array<string, mixed>>
     */
    public static function computeStockSnapshots(PDO $db, array $productIds): array
    {
        if (empty($productIds)) {
            return [];
        }

        $productIds = array_values(array_unique(array_map('intval', $productIds)));
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $snapshots = [];

        $stmt = $db->prepare("SELECT id, stock_quantity, cost, price FROM products WHERE id IN ($placeholders)");
        $stmt->execute($productIds);
        $products = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $product) {
            $products[(int) $product['id']] = $product;
        }

        $stmt = $db->prepare("
            SELECT id, product_id, change_amount, reason, created_at
            FROM inventory_logs
            WHERE product_id IN ($placeholders)
            ORDER BY created_at ASC, id ASC
        ");
        $stmt->execute($productIds);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byProduct = [];
        foreach ($logs as $log) {
            $byProduct[(int) $log['product_id']][] = $log;
        }

        foreach ($byProduct as $productId => $productLogs) {
            $product = $products[$productId] ?? null;
            $currentStock = (int) ($product['stock_quantity'] ?? 0);
            $cost = (float) ($product['cost'] ?? 0);
            $price = (float) ($product['price'] ?? 0);

            // Walk backwards from actual product stock so the latest row always matches reality.
            $running = $currentStock;
            $reversed = array_reverse($productLogs);

            foreach ($reversed as $log) {
                $delta = (int) $log['change_amount'];
                $current = $running;
                $opening = $current - $delta;
                $stockIn = max(0, $delta);
                $stockOut = max(0, -$delta);
                $running = $opening;
                $movementType = self::movementTypeFromReason((string) ($log['reason'] ?? 'correction'));

                $snapshots['log:' . $log['id']] = self::stockFields(
                    $opening,
                    $stockIn,
                    $stockOut,
                    $current,
                    $cost,
                    $price,
                    $movementType
                );
            }
        }

        self::appendLedgerOnlySnapshots($db, $productIds, $products, $snapshots);

        return $snapshots;
    }

    /**
     * Ledger rows without a matching log snapshot (replay stored deltas chronologically).
     */
    private static function appendLedgerOnlySnapshots(PDO $db, array $productIds, array $products, array &$snapshots): void
    {
        $placeholders = implode(',', array_fill(0, count($productIds), '?'));
        $stmt = $db->prepare("
            SELECT id, product_id, reference_id, reference_type, stock_in, stock_out, movement_date
            FROM inventory_ledger
            WHERE product_id IN ($placeholders)
            ORDER BY movement_date ASC, id ASC
        ");
        $stmt->execute($productIds);
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $byProduct = [];
        foreach ($entries as $entry) {
            $refType = $entry['reference_type'] ?? '';
            $refId = $entry['reference_id'] ?? '';
            if ($refType === 'inventory_log' && $refId !== '' && isset($snapshots['log:' . $refId])) {
                continue;
            }
            $byProduct[(int) $entry['product_id']][] = $entry;
        }

        foreach ($byProduct as $productId => $productEntries) {
            $product = $products[$productId] ?? null;
            $cost = (float) ($product['cost'] ?? 0);
            $price = (float) ($product['price'] ?? 0);
            $currentStock = (int) ($product['stock_quantity'] ?? 0);

            $totalDelta = 0;
            foreach ($productEntries as $entry) {
                $totalDelta += (int) ($entry['stock_in'] ?? 0) - (int) ($entry['stock_out'] ?? 0);
            }

            $running = $currentStock;
            $reversed = array_reverse($productEntries);

            foreach ($reversed as $entry) {
                if (($entry['reference_type'] ?? '') === 'sale_return') {
                    continue;
                }
                $delta = (int) ($entry['stock_in'] ?? 0) - (int) ($entry['stock_out'] ?? 0);
                if ($delta === 0) {
                    continue;
                }

                $current = $running;
                $opening = $current - $delta;
                $stockIn = max(0, $delta);
                $stockOut = max(0, -$delta);
                $running = $opening;
                $movementType = (string) ($entry['movement_type'] ?? 'adjustment');

                $snapshots['ledger:' . $entry['id']] = self::stockFields(
                    $opening,
                    $stockIn,
                    $stockOut,
                    $current,
                    $cost,
                    $price,
                    $movementType
                );
            }
        }
    }

    private static function stockFields(int $opening, int $stockIn, int $stockOut, int $current, float $cost, float $price, string $movementType = 'adjustment'): array
    {
        return [
            'opening_stock'       => $opening,
            'stock_in'            => $stockIn,
            'stock_out'           => $stockOut,
            'current_stock'       => $current,
            'opening_stock_value' => round($opening * $cost, 4),
            'stock_in_value'      => round($stockIn * $cost, 4),
            'stock_out_value'     => round($stockOut * $price, 4),
            'current_stock_value' => round($current * $cost, 4),
            'estimated_profit'    => self::estimateProfit($movementType, $stockIn, $stockOut, $cost, $price),
            'purchase_price'      => $cost,
            'selling_price'       => $price,
            'margin_percent'      => $price > 0 ? round((($price - $cost) / $price) * 100, 2) : 0,
            'unit_margin'         => round($price - $cost, 4),
        ];
    }

    /**
     * Movement-aware profit / loss estimate.
     */
    public static function estimateProfit(string $movementType, int $stockIn, int $stockOut, float $cost, float $price): float
    {
        switch ($movementType) {
            case 'sale':
                return round($stockOut * ($price - $cost), 4);
            case 'return':
                return round($stockIn * ($price - $cost), 4);
            case 'damaged':
            case 'expired':
                return round(-$stockOut * $cost, 4);
            case 'transfer_out':
                return round(-$stockOut * $cost, 4);
            case 'purchase':
            case 'transfer_in':
            case 'adjustment':
            case 'manual_edit':
            default:
                if ($stockOut > 0 && $stockIn === 0) {
                    return round(-$stockOut * $cost, 4);
                }
                return 0.0;
        }
    }

    /**
     * Recompute financial columns from stock flow + unit prices (after snapshot merge).
     */
    public static function finalizeRowFinancials(array $row): array
    {
        $cost = (float) ($row['purchase_price'] ?? $row['cost_price'] ?? 0);
        $price = (float) ($row['selling_price'] ?? $row['sale_price'] ?? 0);
        $opening = (int) ($row['opening_stock'] ?? 0);
        $stockIn = (int) ($row['stock_in'] ?? 0);
        $stockOut = (int) ($row['stock_out'] ?? 0);
        $current = (int) ($row['current_stock'] ?? 0);
        if ($current === 0 && ($opening + $stockIn + $stockOut) > 0) {
            $current = max(0, $opening + $stockIn - $stockOut);
            $row['current_stock'] = $current;
        }
        $movementType = (string) ($row['movement_type'] ?? 'adjustment');

        $row['purchase_price'] = $cost;
        $row['selling_price'] = $price;
        $row['opening_stock_value'] = round($opening * $cost, 4);
        $row['stock_in_value'] = round($stockIn * $cost, 4);
        $row['stock_out_value'] = round($stockOut * $price, 4);
        $row['current_stock_value'] = round($current * $cost, 4);
        $row['estimated_profit'] = self::estimateProfit($movementType, $stockIn, $stockOut, $cost, $price);
        $row['margin_percent'] = $price > 0 ? round((($price - $cost) / $price) * 100, 2) : 0;
        $row['unit_margin'] = round($price - $cost, 4);
        $row['profit_label_key'] = self::profitLabelKey($movementType, $stockIn, $stockOut);

        return $row;
    }

    public static function enrichFinancialColumns(array $rows): array
    {
        foreach ($rows as &$row) {
            $row = self::finalizeRowFinancials($row);
        }
        unset($row);

        return $rows;
    }

    public static function profitLabelKey(string $movementType, int $stockIn, int $stockOut): string
    {
        if (in_array($movementType, ['sale', 'return'], true)) {
            return 'trace_profit_sale';
        }
        if (in_array($movementType, ['damaged', 'expired', 'transfer_out'], true) || ($stockOut > 0 && $stockIn === 0 && $movementType === 'adjustment')) {
            return 'trace_profit_loss';
        }
        return 'trace_profit_neutral';
    }

    public static function formatLogNote(string $reason, int $logId): string
    {
        $labels = [
            'sale'       => 'Sale',
            'restock'    => 'Restock',
            'damage'     => 'Damaged write-off',
            'correction' => 'Stock correction',
            'transfer'   => 'Store transfer',
        ];
        $label = $labels[$reason] ?? ucfirst(str_replace('_', ' ', $reason));

        return sprintf('%s — inventory log #%d', $label, $logId);
    }

    public static function applyStockSnapshots(array $rows, array $snapshots): array
    {
        foreach ($rows as &$row) {
            $key = self::snapshotKeyForRow($row);
            if ($key && isset($snapshots[$key])) {
                $row = array_merge($row, $snapshots[$key]);
            }
        }
        unset($row);

        return $rows;
    }

    public static function snapshotKeyForRow(array $row): ?string
    {
        if (($row['reference_type'] ?? '') === 'inventory_log' && !empty($row['reference_id'])) {
            return 'log:' . $row['reference_id'];
        }

        if (!empty($row['id']) && ($row['reference_type'] ?? '') === 'inventory_log' && empty($row['reference_id'])) {
            return 'log:' . $row['id'];
        }

        if (!empty($row['id'])) {
            return 'ledger:' . $row['id'];
        }

        return null;
    }

    /**
     * Insert a ledger row for an inventory_log movement (idempotent per log id).
     */
    public static function syncLogToLedger(
        PDO $db,
        int $logId,
        int $productId,
        int $changeAmount,
        string $reason,
        int $userId,
        int $storeId,
        string $movementType = '',
        ?string $notes = null
    ): ?int {
        try {
            $db->query('SELECT 1 FROM inventory_ledger LIMIT 1');
        } catch (PDOException $e) {
            return null;
        }

        $check = $db->prepare(
            "SELECT id FROM inventory_ledger
             WHERE reference_type = 'inventory_log'
               AND reference_id = CAST(? AS CHAR CHARACTER SET utf8mb4) COLLATE utf8mb4_unicode_ci
             LIMIT 1"
        );
        $check->execute([(string) $logId]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing) {
            return (int) $existing['id'];
        }

        $pStmt = $db->prepare('SELECT stock_quantity, cost, price FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }

        $snapshots = self::computeStockSnapshots($db, [$productId]);
        $stock = $snapshots['log:' . $logId] ?? null;

        if (!$stock) {
            $currentStock = (int) ($product['stock_quantity'] ?? 0);
            $openingStock = max(0, $currentStock - $changeAmount);
            $stockIn = max(0, $changeAmount);
            $stockOut = max(0, -$changeAmount);
            $cost = (float) ($product['cost'] ?? 0);
            $price = (float) ($product['price'] ?? 0);
            $stock = self::stockFields($openingStock, $stockIn, $stockOut, $currentStock, $cost, $price, $movementType);
        }

        $movementType = $movementType !== '' ? $movementType : self::movementTypeFromReason($reason);
        $notes = $notes ?? sprintf('Synced from inventory_logs #%d (%s)', $logId, $reason);

        $stmt = $db->prepare(
            'INSERT INTO inventory_ledger (
                product_id, store_id, user_id, movement_type, reference_id, reference_type,
                opening_stock, stock_in, stock_out, current_stock,
                purchase_price, selling_price, opening_stock_value, stock_out_value,
                current_stock_value, estimated_profit, notes, movement_date
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $productId,
            $storeId,
            $userId,
            $movementType,
            (string) $logId,
            'inventory_log',
            (int) $stock['opening_stock'],
            (int) $stock['stock_in'],
            (int) $stock['stock_out'],
            (int) $stock['current_stock'],
            (float) $stock['purchase_price'],
            (float) $stock['selling_price'],
            (float) $stock['opening_stock_value'],
            (float) $stock['stock_out_value'],
            (float) $stock['current_stock_value'],
            (float) $stock['estimated_profit'],
            $notes,
        ]);

        $ledgerId = (int) $db->lastInsertId();

        try {
            require_once __DIR__ . '/../Notifications/StockAlertNotifier.php';
            $previousQty = max(0, (int) $stock['current_stock'] - $changeAmount);
            StockAlertNotifier::checkStoreProduct($db, $productId, $storeId, $previousQty);
        } catch (Throwable $e) {
            // Non-blocking
        }

        return $ledgerId;
    }

    public static function sortRowsByDateDesc(array $rows): array
    {
        usort($rows, static function ($a, $b) {
            $dateA = strtotime($a['movement_date'] ?? $a['created_at'] ?? '0');
            $dateB = strtotime($b['movement_date'] ?? $b['created_at'] ?? '0');
            if ($dateA === $dateB) {
                return ((int) ($b['id'] ?? 0)) <=> ((int) ($a['id'] ?? 0));
            }

            return $dateB <=> $dateA;
        });

        return $rows;
    }

    public static function productIdsFromRows(array $rows): array
    {
        return array_values(array_unique(array_filter(array_map(static function ($row) {
            return isset($row['product_id']) ? (int) $row['product_id'] : null;
        }, $rows))));
    }

    /**
     * Record a damaged return (no stock restock — ledger audit only).
     */
    public static function recordReturnDamage(
        PDO $db,
        int $productId,
        int $qty,
        int $userId,
        int $storeId,
        int $saleId,
        string $receiptLabel = ''
    ): ?int {
        if ($qty <= 0) {
            return null;
        }

        try {
            $db->query('SELECT 1 FROM inventory_ledger LIMIT 1');
        } catch (PDOException $e) {
            return null;
        }

        $pStmt = $db->prepare('SELECT stock_quantity, cost, price FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }

        $currentStock = (int) ($product['stock_quantity'] ?? 0);
        $cost = (float) ($product['cost'] ?? 0);
        $price = (float) ($product['price'] ?? 0);
        $stockOutValue = round($qty * $price, 4);
        $currentValue = round($currentStock * $cost, 4);
        $receiptPart = $receiptLabel !== '' ? " ({$receiptLabel})" : '';
        $notes = sprintf('Return damage — sale #%d%s', $saleId, $receiptPart);

        $stmt = $db->prepare(
            'INSERT INTO inventory_ledger (
                product_id, store_id, user_id, movement_type, reference_id, reference_type,
                opening_stock, stock_in, stock_out, current_stock,
                purchase_price, selling_price, opening_stock_value, stock_out_value,
                current_stock_value, estimated_profit, notes, movement_date
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $productId,
            $storeId,
            $userId,
            'damaged',
            (string) $saleId,
            'sale_return',
            $currentStock,
            0,
            $qty,
            $currentStock,
            $cost,
            $price,
            $currentValue,
            $stockOutValue,
            $currentValue,
            round($qty * ($price - $cost), 4),
            $notes,
        ]);

        return (int) $db->lastInsertId();
    }
}

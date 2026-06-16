<?php
declare(strict_types=1);

require_once __DIR__ . '/../Wms/Repositories/WarehouseInventoryRepository.php';
require_once __DIR__ . '/../Wms/Repositories/WarehouseMovementRepository.php';
require_once __DIR__ . '/../Database/Database.php';

/**
 * Warehouse stock movements + optional inventory_ledger / store product sync.
 */
class WmsLedgerHelper
{
    public static function applyMovement(
        PDO $db,
        int $warehouseId,
        int $productId,
        int $quantityDelta,
        string $movementType,
        float $unitCost,
        int $userId,
        ?string $referenceType = null,
        ?int $referenceId = null,
        ?string $notes = null,
        ?int $locationId = null,
        ?int $batchId = null
    ): array {
        $invRepo = new WarehouseInventoryRepository($db);
        $movRepo = new WarehouseMovementRepository($db);

        $invRepo->upsertStock($warehouseId, $productId, $quantityDelta, $unitCost, $locationId, $batchId);
        $row = $invRepo->find($warehouseId, $productId);
        $balance = (int) ($row['quantity'] ?? 0);
        $value = round($balance * (float) ($row['unit_cost'] ?? $unitCost), 4);

        $movId = $movRepo->record([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'batch_id' => $batchId,
            'movement_type' => $movementType,
            'quantity' => $quantityDelta,
            'balance_after' => $balance,
            'unit_cost' => $unitCost,
            'stock_value' => $value,
            'reference_type' => $referenceType,
            'reference_id' => $referenceId,
            'notes' => $notes,
            'created_by' => $userId,
        ]);

        self::syncStoreProductStock($db, $warehouseId, $productId);
        self::syncInventoryLedger($db, $warehouseId, $productId, $quantityDelta, $movementType, $unitCost, $userId, $referenceType, $referenceId, $notes, $balance);

        return ['movement_id' => $movId, 'balance' => $balance, 'stock_value' => $value];
    }

    private static function syncStoreProductStock(PDO $db, int $warehouseId, int $productId): void
    {
        $stmt = $db->prepare('SELECT store_id FROM warehouses WHERE id = ? LIMIT 1');
        $stmt->execute([$warehouseId]);
        $storeId = $stmt->fetchColumn();
        if (!$storeId) {
            return;
        }
        $sum = $db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM warehouse_inventory WHERE warehouse_id = ? AND product_id = ?');
        $sum->execute([$warehouseId, $productId]);
        $qty = (int) $sum->fetchColumn();
        $upd = $db->prepare('UPDATE products SET stock_quantity = ? WHERE id = ? AND store_id = ?');
        $upd->execute([$qty, $productId, (int) $storeId]);
    }

    private static function syncInventoryLedger(
        PDO $db,
        int $warehouseId,
        int $productId,
        int $delta,
        string $movementType,
        float $unitCost,
        int $userId,
        ?string $referenceType,
        ?int $referenceId,
        ?string $notes,
        int $balance
    ): void {
        try {
            $db->query('SELECT 1 FROM inventory_ledger LIMIT 1');
        } catch (Throwable $e) {
            return;
        }
        $stmt = $db->prepare('SELECT store_id, cost, price FROM products WHERE id = ? LIMIT 1');
        $stmt->execute([$productId]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return;
        }
        $storeId = (int) $product['store_id'];
        $cost = $unitCost > 0 ? $unitCost : (float) ($product['cost'] ?? 0);
        $price = (float) ($product['price'] ?? 0);
        $stockIn = max(0, $delta);
        $stockOut = max(0, -$delta);
        $opening = max(0, $balance - $delta);

        $ledgerType = match ($movementType) {
            'receipt_in', 'purchase' => 'purchase',
            'transfer_in' => 'transfer_in',
            'transfer_out', 'dispatch_out' => 'transfer_out',
            'damaged' => 'damaged',
            'expired' => 'expired',
            'sale' => 'sale',
            default => 'adjustment',
        };

        $hasWh = false;
        try {
            $c = $db->query("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'inventory_ledger' AND COLUMN_NAME = 'warehouse_id'");
            $hasWh = (int) $c->fetchColumn() > 0;
        } catch (Throwable $e) {
        }

        if ($hasWh) {
            $ins = $db->prepare(
                "INSERT INTO inventory_ledger
                    (product_id, store_id, warehouse_id, user_id, movement_type, reference_id, reference_type,
                     opening_stock, stock_in, stock_out, current_stock, purchase_price, selling_price,
                     opening_stock_value, stock_out_value, current_stock_value, estimated_profit, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $productId, $storeId, $warehouseId, $userId, $ledgerType,
                $referenceId ? (string) $referenceId : null, $referenceType,
                $opening, $stockIn, $stockOut, $balance, $cost, $price,
                $opening * $cost, $stockOut * $price, $balance * $cost,
                $stockOut * ($price - $cost), $notes,
            ]);
        } else {
            $ins = $db->prepare(
                "INSERT INTO inventory_ledger
                    (product_id, store_id, user_id, movement_type, reference_id, reference_type,
                     opening_stock, stock_in, stock_out, current_stock, purchase_price, selling_price,
                     opening_stock_value, stock_out_value, current_stock_value, estimated_profit, notes)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([
                $productId, $storeId, $userId, $ledgerType,
                $referenceId ? (string) $referenceId : null, $referenceType,
                $opening, $stockIn, $stockOut, $balance, $cost, $price,
                $opening * $cost, $stockOut * $price, $balance * $cost,
                $stockOut * ($price - $cost), $notes,
            ]);
        }
    }
}

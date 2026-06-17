<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationEvents.php';
require_once __DIR__ . '/NotificationManager.php';
require_once __DIR__ . '/../Wms/WmsNotifier.php';

/**
 * Fire low-stock / out-of-stock notifications when thresholds are crossed.
 */
class StockAlertNotifier
{
    private const DEFAULT_MIN_STOCK = 5;

    public static function checkStoreProduct(PDO $db, int $productId, int $storeId, ?int $previousQty = null): void
    {
        try {
            $stmt = $db->prepare(
                'SELECT name, stock_quantity, min_stock_level FROM products
                 WHERE id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $current = (int) $row['stock_quantity'];
            $min = (int) ($row['min_stock_level'] ?? self::DEFAULT_MIN_STOCK);
            if ($min < 1) {
                $min = self::DEFAULT_MIN_STOCK;
            }
            $name = (string) $row['name'];
            $prev = $previousQty ?? ($current + 1);

            if ($current <= 0 && $prev > 0) {
                NotificationEvents::outOfStock($storeId, $name);
                return;
            }

            if ($current > 0 && $current <= $min && $prev > $min) {
                NotificationEvents::lowStock($storeId, $name, $current);
            }
        } catch (Throwable $e) {
            // Non-blocking
        }
    }

    public static function checkWarehouseProduct(
        PDO $db,
        int $warehouseId,
        int $productId,
        ?int $previousQty = null
    ): void {
        try {
            $stmt = $db->prepare(
                'SELECT wi.quantity, wi.reorder_level, p.name AS product_name
                 FROM warehouse_inventory wi
                 INNER JOIN products p ON p.id = wi.product_id
                 WHERE wi.warehouse_id = ? AND wi.product_id = ?
                 LIMIT 1'
            );
            $stmt->execute([$warehouseId, $productId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row) {
                return;
            }

            $current = (int) $row['quantity'];
            $reorder = (int) ($row['reorder_level'] ?? self::DEFAULT_MIN_STOCK);
            if ($reorder < 1) {
                $reorder = self::DEFAULT_MIN_STOCK;
            }
            $name = (string) $row['product_name'];
            $prev = $previousQty ?? ($current + 1);

            if ($current <= 0 && $prev > 0) {
                NotificationEvents::outOfStock(0, $name, $warehouseId);
                return;
            }

            if ($current > 0 && $current <= $reorder && $prev > $reorder) {
                WmsNotifier::lowStock($warehouseId, $name, $current);
            }
        } catch (Throwable $e) {
            // Non-blocking
        }
    }
}

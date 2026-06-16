<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseInventoryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function listByWarehouse(int $warehouseId, ?string $search = null, ?string $stockFilter = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT wi.*, p.name AS product_name, p.sku, p.barcode, p.price,
                       wl.location_code, bt.batch_number, bt.expiry_date,
                       w.name AS warehouse_name,
                       (wi.quantity - wi.reserved_qty) AS available_qty,
                       CASE
                         WHEN wi.quantity = 0 THEN 'out'
                         WHEN wi.quantity <= wi.reorder_level THEN 'low'
                         WHEN wi.damaged_qty > 0 OR wi.expired_qty > 0 THEN 'alert'
                         ELSE 'ok'
                       END AS stock_status
                FROM warehouse_inventory wi
                INNER JOIN products p ON p.id = wi.product_id
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                LEFT JOIN warehouse_locations wl ON wl.id = wi.location_id
                LEFT JOIN batch_tracking bt ON bt.id = wi.batch_id
                WHERE wi.warehouse_id = ?";
        $params = [$warehouseId];
        if ($search) {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR wl.location_code LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($stockFilter === 'low') {
            $sql .= ' AND wi.quantity > 0 AND wi.quantity <= wi.reorder_level';
        } elseif ($stockFilter === 'out') {
            $sql .= ' AND wi.quantity = 0';
        } elseif ($stockFilter === 'damaged') {
            $sql .= ' AND (wi.damaged_qty > 0 OR wi.expired_qty > 0)';
        }
        $sql .= ' ORDER BY p.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findDetail(int $warehouseId, int $productId): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT wi.*, p.name AS product_name, p.sku, p.barcode, p.price,
                    wl.location_code, wl.zone, wl.aisle, wl.rack,
                    bt.batch_number, bt.expiry_date,
                    w.name AS warehouse_name,
                    (wi.quantity - wi.reserved_qty) AS available_qty,
                    CASE
                      WHEN wi.quantity = 0 THEN 'out'
                      WHEN wi.quantity <= wi.reorder_level THEN 'low'
                      WHEN wi.damaged_qty > 0 OR wi.expired_qty > 0 THEN 'alert'
                      ELSE 'ok'
                    END AS stock_status
             FROM warehouse_inventory wi
             INNER JOIN products p ON p.id = wi.product_id
             INNER JOIN warehouses w ON w.id = wi.warehouse_id
             LEFT JOIN warehouse_locations wl ON wl.id = wi.location_id
             LEFT JOIN batch_tracking bt ON bt.id = wi.batch_id
             WHERE wi.warehouse_id = ? AND wi.product_id = ?
             LIMIT 1"
        );
        $stmt->execute([$warehouseId, $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function summary(int $warehouseId): array
    {
        if (!WmsSchema::ready()) {
            return ['sku_count' => 0, 'total_units' => 0, 'total_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS sku_count,
                    COALESCE(SUM(quantity), 0) AS total_units,
                    COALESCE(SUM(stock_value), 0) AS total_value,
                    SUM(CASE WHEN quantity > 0 AND quantity <= reorder_level THEN 1 ELSE 0 END) AS low_stock,
                    SUM(CASE WHEN quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock,
                    COALESCE(SUM(damaged_qty), 0) AS damaged_qty,
                    COALESCE(SUM(expired_qty), 0) AS expired_qty
             FROM warehouse_inventory WHERE warehouse_id = ?"
        );
        $stmt->execute([$warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'sku_count' => (int) ($row['sku_count'] ?? 0),
            'total_units' => (int) ($row['total_units'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
            'damaged_qty' => (int) ($row['damaged_qty'] ?? 0),
            'expired_qty' => (int) ($row['expired_qty'] ?? 0),
        ];
    }

    public function find(int $warehouseId, int $productId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM warehouse_inventory WHERE warehouse_id = ? AND product_id = ? LIMIT 1'
        );
        $stmt->execute([$warehouseId, $productId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function upsertStock(int $warehouseId, int $productId, int $delta, float $unitCost, ?int $locationId = null, ?int $batchId = null): void
    {
        $existing = $this->find($warehouseId, $productId);
        if ($existing) {
            $newQty = (int) $existing['quantity'] + $delta;
            $cost = $unitCost > 0 ? $unitCost : (float) $existing['unit_cost'];
            $stmt = $this->db->prepare(
                'UPDATE warehouse_inventory SET quantity = ?, unit_cost = ?, stock_value = ?, location_id = COALESCE(?, location_id),
                 batch_id = COALESCE(?, batch_id), last_movement_at = NOW(), updated_at = NOW() WHERE id = ?'
            );
            $stmt->execute([max(0, $newQty), $cost, max(0, $newQty) * $cost, $locationId, $batchId, (int) $existing['id']]);
            return;
        }
        $qty = max(0, $delta);
        $stmt = $this->db->prepare(
            'INSERT INTO warehouse_inventory (warehouse_id, product_id, quantity, unit_cost, stock_value, location_id, batch_id, last_movement_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([$warehouseId, $productId, $qty, $unitCost, $qty * $unitCost, $locationId, $batchId]);
    }

    public function countLowStock(?int $warehouseId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM warehouse_inventory WHERE quantity <= reorder_level AND quantity >= 0';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countDamagedExpired(?int $warehouseId = null): array
    {
        $sql = 'SELECT COALESCE(SUM(damaged_qty), 0), COALESCE(SUM(expired_qty), 0) FROM warehouse_inventory WHERE 1=1';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
        return ['damaged' => (int) $row[0], 'expired' => (int) $row[1]];
    }
}

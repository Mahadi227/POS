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

    /**
     * Product-centric catalog — aggregated stock across warehouses (optionally scoped to one).
     */
    public function listProductCatalog(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $stockFilter,
        ?int $categoryId,
        int $limit = 50,
        int $offset = 0
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$baseSql, $params, $having] = $this->productCatalogQueryParts($warehouseId, $storeId, $search, $stockFilter, $categoryId);
        $sql = $baseSql . $having . ' ORDER BY p.name ASC LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countProductCatalog(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $stockFilter,
        ?int $categoryId
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$baseSql, $params, $having] = $this->productCatalogQueryParts($warehouseId, $storeId, $search, $stockFilter, $categoryId);
        $sql = 'SELECT COUNT(*) FROM (' . $baseSql . $having . ') AS catalog_rows';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function productCatalogSummary(?int $warehouseId, ?int $storeId): array
    {
        if (!WmsSchema::ready()) {
            return [
                'product_count' => 0, 'in_stock' => 0, 'low_stock' => 0, 'out_of_stock' => 0,
                'total_units' => 0, 'total_value' => 0, 'warehouse_skus' => 0,
            ];
        }
        $wiJoin = 'LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id';
        $wJoin = 'LEFT JOIN warehouses w ON w.id = wi.warehouse_id';
        $params = [];
        if ($warehouseId) {
            $wiJoin = 'LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id AND wi.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $where = ['p.deleted_at IS NULL'];
        if ($storeId) {
            $where[] = '(p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
            $where[] = '(w.id IS NULL OR w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql = "SELECT COUNT(DISTINCT p.id) AS product_count,
                       COUNT(DISTINCT CASE WHEN COALESCE(wi.quantity, 0) > 0 THEN p.id END) AS in_stock,
                       COUNT(DISTINCT CASE WHEN wi.quantity > 0 AND wi.quantity <= wi.reorder_level THEN p.id END) AS low_stock,
                       COUNT(DISTINCT CASE WHEN COALESCE(wi.quantity, 0) = 0 OR wi.id IS NULL THEN p.id END) AS out_of_stock,
                       COALESCE(SUM(wi.quantity), 0) AS total_units,
                       COALESCE(SUM(wi.stock_value), 0) AS total_value,
                       COUNT(DISTINCT wi.id) AS warehouse_skus
                FROM products p
                {$wiJoin}
                {$wJoin}
                WHERE " . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'product_count' => (int) ($row['product_count'] ?? 0),
            'in_stock' => (int) ($row['in_stock'] ?? 0),
            'low_stock' => (int) ($row['low_stock'] ?? 0),
            'out_of_stock' => (int) ($row['out_of_stock'] ?? 0),
            'total_units' => (int) ($row['total_units'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
            'warehouse_skus' => (int) ($row['warehouse_skus'] ?? 0),
        ];
    }

    public function findProductCatalog(int $productId, ?int $storeId): ?array
    {
        if (!WmsSchema::ready() || $productId <= 0) {
            return null;
        }
        $where = ['p.id = ?', 'p.deleted_at IS NULL'];
        $params = [$productId];
        if ($storeId) {
            $where[] = '(p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare(
            'SELECT p.*, c.name AS category_name FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
        );
        $stmt->execute($params);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }
        $product['warehouses'] = $this->productStockByWarehouses($productId, $storeId);
        $totals = ['total_qty' => 0, 'reserved_qty' => 0, 'stock_value' => 0.0, 'warehouse_count' => count($product['warehouses'])];
        foreach ($product['warehouses'] as $wh) {
            $totals['total_qty'] += (int) ($wh['quantity'] ?? 0);
            $totals['reserved_qty'] += (int) ($wh['reserved_qty'] ?? 0);
            $totals['stock_value'] += (float) ($wh['stock_value'] ?? 0);
        }
        $product['totals'] = $totals;
        return $product;
    }

    public function productStockByWarehouses(int $productId, ?int $storeId): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT wi.*, w.name AS warehouse_name, w.warehouse_code,
                       (wi.quantity - wi.reserved_qty) AS available_qty,
                       wl.location_code,
                       CASE
                         WHEN wi.quantity = 0 THEN 'out'
                         WHEN wi.quantity <= wi.reorder_level THEN 'low'
                         WHEN wi.damaged_qty > 0 OR wi.expired_qty > 0 THEN 'alert'
                         ELSE 'ok'
                       END AS stock_status
                FROM warehouse_inventory wi
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                LEFT JOIN warehouse_locations wl ON wl.id = wi.location_id
                WHERE wi.product_id = ?";
        $params = [$productId];
        if ($storeId) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY w.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listCategoriesForCatalog(?int $storeId): array
    {
        $sql = 'SELECT DISTINCT c.id, c.name FROM categories c
                INNER JOIN products p ON p.category_id = c.id
                WHERE p.deleted_at IS NULL';
        $params = [];
        if ($storeId) {
            $sql .= ' AND (p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY c.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{0: string, 1: array<int, mixed>, 2: string} */
    private function productCatalogQueryParts(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $stockFilter,
        ?int $categoryId
    ): array {
        $params = [];
        $wiJoin = 'LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id';
        $wJoin = 'LEFT JOIN warehouses w ON w.id = wi.warehouse_id';
        if ($warehouseId) {
            $wiJoin = 'LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id AND wi.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $where = ['p.deleted_at IS NULL'];
        if ($storeId) {
            $where[] = '(p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
            $where[] = '(w.id IS NULL OR w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        if ($search) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }
        if ($categoryId) {
            $where[] = 'p.category_id = ?';
            $params[] = $categoryId;
        }
        $having = '';
        if ($stockFilter === 'in_stock') {
            $having = ' HAVING total_qty > 0';
        } elseif ($stockFilter === 'low') {
            $having = ' HAVING total_qty > 0 AND low_lines > 0';
        } elseif ($stockFilter === 'out') {
            $having = ' HAVING total_qty = 0';
        } elseif ($stockFilter === 'alert') {
            $having = ' HAVING damaged_qty > 0 OR expired_qty > 0';
        } elseif ($stockFilter === 'no_wh') {
            $having = ' HAVING warehouse_count = 0';
        }
        $sql = "SELECT p.id, p.name, p.sku, p.barcode, p.price, p.cost, p.category_id,
                       c.name AS category_name,
                       COALESCE(SUM(wi.quantity), 0) AS total_qty,
                       COALESCE(SUM(wi.reserved_qty), 0) AS reserved_qty,
                       COALESCE(SUM(wi.stock_value), 0) AS stock_value,
                       COALESCE(SUM(wi.damaged_qty), 0) AS damaged_qty,
                       COALESCE(SUM(wi.expired_qty), 0) AS expired_qty,
                       COUNT(DISTINCT wi.warehouse_id) AS warehouse_count,
                       MAX(wi.reorder_level) AS reorder_level,
                       SUM(CASE WHEN wi.quantity > 0 AND wi.quantity <= wi.reorder_level THEN 1 ELSE 0 END) AS low_lines,
                       CASE
                         WHEN COALESCE(SUM(wi.quantity), 0) = 0 THEN 'out'
                         WHEN SUM(CASE WHEN wi.quantity > 0 AND wi.quantity <= wi.reorder_level THEN 1 ELSE 0 END) > 0 THEN 'low'
                         WHEN COALESCE(SUM(wi.damaged_qty), 0) > 0 OR COALESCE(SUM(wi.expired_qty), 0) > 0 THEN 'alert'
                         ELSE 'ok'
                       END AS stock_status
                FROM products p
                LEFT JOIN categories c ON c.id = p.category_id
                {$wiJoin}
                {$wJoin}
                WHERE " . implode(' AND ', $where) . '
                GROUP BY p.id, p.name, p.sku, p.barcode, p.price, p.cost, p.category_id, c.name';
        return [$sql, $params, $having];
    }

    /**
     * Stock level monitoring — on-hand vs reorder threshold per SKU (optional warehouse scope).
     */
    public function listStockLevels(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $levelFilter,
        int $limit = 50,
        int $offset = 0
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$sql, $params] = $this->stockLevelsSelectSql($warehouseId, $storeId, $search, $levelFilter);
        $sql .= ' ORDER BY w.name ASC, p.name ASC LIMIT ' . max(1, min(200, $limit)) . ' OFFSET ' . max(0, $offset);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countStockLevels(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $levelFilter
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$sql, $params] = $this->stockLevelsSelectSql($warehouseId, $storeId, $search, $levelFilter);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM (' . $sql . ') AS level_rows');
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function stockLevelsSummary(?int $warehouseId, ?int $storeId): array
    {
        if (!WmsSchema::ready()) {
            return [
                'sku_count' => 0, 'ok_count' => 0, 'low_count' => 0, 'out_count' => 0,
                'needs_reorder' => 0, 'total_reorder_gap' => 0,
            ];
        }
        $where = ['1=1'];
        $params = [];
        if ($warehouseId) {
            $where[] = 'wi.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($storeId) {
            $where[] = '(w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
            $where[] = '(p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql = 'SELECT COUNT(*) AS sku_count,
                       SUM(CASE WHEN wi.quantity > wi.reorder_level THEN 1 ELSE 0 END) AS ok_count,
                       SUM(CASE WHEN wi.quantity > 0 AND wi.quantity <= wi.reorder_level THEN 1 ELSE 0 END) AS low_count,
                       SUM(CASE WHEN wi.quantity = 0 THEN 1 ELSE 0 END) AS out_count,
                       SUM(CASE WHEN (wi.quantity - wi.reserved_qty) <= wi.reorder_level THEN 1 ELSE 0 END) AS needs_reorder,
                       COALESCE(SUM(GREATEST(0, wi.reorder_level - (wi.quantity - wi.reserved_qty))), 0) AS total_reorder_gap
                FROM warehouse_inventory wi
                INNER JOIN products p ON p.id = wi.product_id AND p.deleted_at IS NULL
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'sku_count' => (int) ($row['sku_count'] ?? 0),
            'ok_count' => (int) ($row['ok_count'] ?? 0),
            'low_count' => (int) ($row['low_count'] ?? 0),
            'out_count' => (int) ($row['out_count'] ?? 0),
            'needs_reorder' => (int) ($row['needs_reorder'] ?? 0),
            'total_reorder_gap' => (int) ($row['total_reorder_gap'] ?? 0),
        ];
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function stockLevelsSelectSql(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search,
        ?string $levelFilter
    ): array {
        $where = ['p.deleted_at IS NULL'];
        $params = [];
        if ($warehouseId) {
            $where[] = 'wi.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($storeId) {
            $where[] = '(w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
            $where[] = '(p.store_id = ? OR p.store_id IS NULL)';
            $params[] = $storeId;
        }
        if ($search) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR w.name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like]);
        }
        if ($levelFilter === 'ok') {
            $where[] = 'wi.quantity > wi.reorder_level';
        } elseif ($levelFilter === 'low') {
            $where[] = 'wi.quantity > 0 AND wi.quantity <= wi.reorder_level';
        } elseif ($levelFilter === 'out') {
            $where[] = 'wi.quantity = 0';
        } elseif ($levelFilter === 'needs_reorder') {
            $where[] = '(wi.quantity - wi.reserved_qty) <= wi.reorder_level';
        }
        $sql = "SELECT wi.id, wi.warehouse_id, wi.product_id, wi.quantity, wi.reserved_qty, wi.reorder_level,
                       wi.unit_cost, wi.stock_value, wi.last_movement_at,
                       p.name AS product_name, p.sku, p.barcode,
                       w.name AS warehouse_name, w.warehouse_code,
                       (wi.quantity - wi.reserved_qty) AS available_qty,
                       GREATEST(0, wi.reorder_level - (wi.quantity - wi.reserved_qty)) AS reorder_gap,
                       CASE
                         WHEN wi.reorder_level > 0 THEN LEAST(100, ROUND(wi.quantity * 100.0 / wi.reorder_level, 1))
                         ELSE 100
                       END AS fill_pct,
                       CASE
                         WHEN wi.quantity = 0 THEN 'out'
                         WHEN wi.quantity <= wi.reorder_level THEN 'low'
                         ELSE 'ok'
                       END AS level_status
                FROM warehouse_inventory wi
                INNER JOIN products p ON p.id = wi.product_id
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE " . implode(' AND ', $where);
        return [$sql, $params];
    }
}

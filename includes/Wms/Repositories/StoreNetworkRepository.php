<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../WmsSchema.php';

/**
 * Multi-branch store network with linked warehouse metrics (enterprise WMS view).
 */
class StoreNetworkRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $scopeStoreId, ?string $search, ?string $status, int $limit = 100, int $offset = 0): array
    {
        $allowed = StoreScope::accessibleStoreIds($this->db);
        if ($allowed === []) {
            return [];
        }

        $wmsReady = WmsSchema::ready();
        $params = [];
        $where = ['1=1'];

        if ($this->hasColumn('stores', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }
        if ($allowed !== null) {
            $ph = implode(',', array_fill(0, count($allowed), '?'));
            $where[] = "s.id IN ({$ph})";
            $params = array_merge($params, $allowed);
        }
        if ($scopeStoreId) {
            $where[] = 's.id = ?';
            $params[] = $scopeStoreId;
        }
        if ($status === 'active') {
            $where[] = $this->hasColumn('stores', 'is_active') ? 's.is_active = 1' : '1=1';
        } elseif ($status === 'inactive' && $this->hasColumn('stores', 'is_active')) {
            $where[] = 's.is_active = 0';
        }
        if ($search) {
            $where[] = '(s.name LIKE ? OR s.code LIKE ? OR s.location LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.currency LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }

        $whAgg = $wmsReady
            ? "(SELECT COUNT(*) FROM warehouses w WHERE w.store_id = s.id AND w.deleted_at IS NULL)"
            : '0';
        $whActive = $wmsReady
            ? "(SELECT COUNT(*) FROM warehouses w WHERE w.store_id = s.id AND w.deleted_at IS NULL AND w.status = 'active')"
            : '0';
        $whUnits = $wmsReady
            ? "(SELECT COALESCE(SUM(wi.quantity), 0) FROM warehouse_inventory wi
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE w.store_id = s.id AND w.deleted_at IS NULL)"
            : '0';
        $whValue = $wmsReady
            ? "(SELECT COALESCE(SUM(wi.stock_value), 0) FROM warehouse_inventory wi
                INNER JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE w.store_id = s.id AND w.deleted_at IS NULL)"
            : '0';
        $countries = $wmsReady
            ? "(SELECT GROUP_CONCAT(DISTINCT w.country ORDER BY w.country SEPARATOR ', ')
                FROM warehouses w WHERE w.store_id = s.id AND w.deleted_at IS NULL AND w.country IS NOT NULL AND w.country != '')"
            : 'NULL';

        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);

        $sql = "SELECT s.*,
                       (SELECT COUNT(*) FROM users u WHERE u.store_id = s.id" .
                       ($this->hasColumn('users', 'deleted_at') ? ' AND u.deleted_at IS NULL' : '') . ") AS staff_count,
                       (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id" .
                       ($this->hasColumn('products', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '') . ") AS product_count,
                       {$whAgg} AS warehouse_count,
                       {$whActive} AS active_warehouse_count,
                       {$whUnits} AS warehouse_units,
                       {$whValue} AS warehouse_value,
                       {$countries} AS warehouse_countries
                FROM stores s
                WHERE " . implode(' AND ', $where) . "
                ORDER BY s.name ASC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row) => $this->formatRow($row, $wmsReady), $rows);
    }

    public function count(?int $scopeStoreId, ?string $search, ?string $status): int
    {
        $allowed = StoreScope::accessibleStoreIds($this->db);
        if ($allowed === []) {
            return 0;
        }

        $params = [];
        $where = ['1=1'];
        if ($this->hasColumn('stores', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }
        if ($allowed !== null) {
            $ph = implode(',', array_fill(0, count($allowed), '?'));
            $where[] = "s.id IN ({$ph})";
            $params = array_merge($params, $allowed);
        }
        if ($scopeStoreId) {
            $where[] = 's.id = ?';
            $params[] = $scopeStoreId;
        }
        if ($status === 'active' && $this->hasColumn('stores', 'is_active')) {
            $where[] = 's.is_active = 1';
        } elseif ($status === 'inactive' && $this->hasColumn('stores', 'is_active')) {
            $where[] = 's.is_active = 0';
        }
        if ($search) {
            $where[] = '(s.name LIKE ? OR s.code LIKE ? OR s.location LIKE ? OR s.phone LIKE ? OR s.email LIKE ? OR s.currency LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }

        $sql = 'SELECT COUNT(*) FROM stores s WHERE ' . implode(' AND ', $where);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function summary(?int $scopeStoreId): array
    {
        $allowed = StoreScope::accessibleStoreIds($this->db);
        if ($allowed === []) {
            return [
                'store_count' => 0, 'active_stores' => 0, 'inactive_stores' => 0,
                'warehouse_count' => 0, 'active_warehouses' => 0,
                'total_units' => 0, 'currency_count' => 0, 'countries' => [],
                'by_currency' => [],
            ];
        }

        $wmsReady = WmsSchema::ready();
        $params = [];
        $storeWhere = ['1=1'];
        if ($this->hasColumn('stores', 'deleted_at')) {
            $storeWhere[] = 's.deleted_at IS NULL';
        }
        if ($allowed !== null) {
            $ph = implode(',', array_fill(0, count($allowed), '?'));
            $storeWhere[] = "s.id IN ({$ph})";
            $params = array_merge($params, $allowed);
        }
        if ($scopeStoreId) {
            $storeWhere[] = 's.id = ?';
            $params[] = $scopeStoreId;
        }
        $whereSql = implode(' AND ', $storeWhere);

        $activeCol = $this->hasColumn('stores', 'is_active') ? 's.is_active' : '1';
        $sql = "SELECT COUNT(*) AS store_count,
                       SUM(CASE WHEN {$activeCol} = 1 THEN 1 ELSE 0 END) AS active_stores,
                       SUM(CASE WHEN {$activeCol} = 0 THEN 1 ELSE 0 END) AS inactive_stores,
                       COUNT(DISTINCT NULLIF(TRIM(s.currency), '')) AS currency_count
                FROM stores s WHERE {$whereSql}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $whCount = 0;
        $whActive = 0;
        $totalUnits = 0;
        $byCurrency = [];
        $countries = [];

        if ($wmsReady) {
            $whSql = "SELECT COUNT(*) AS total,
                             SUM(CASE WHEN w.status = 'active' THEN 1 ELSE 0 END) AS active
                      FROM warehouses w
                      INNER JOIN stores s ON s.id = w.store_id
                      WHERE w.deleted_at IS NULL AND {$whereSql}";
            $whStmt = $this->db->prepare($whSql);
            $whStmt->execute($params);
            $whRow = $whStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $whCount = (int) ($whRow['total'] ?? 0);
            $whActive = (int) ($whRow['active'] ?? 0);

            $unitsSql = "SELECT COALESCE(SUM(wi.quantity), 0)
                         FROM warehouse_inventory wi
                         INNER JOIN warehouses w ON w.id = wi.warehouse_id
                         INNER JOIN stores s ON s.id = w.store_id
                         WHERE w.deleted_at IS NULL AND {$whereSql}";
            $uStmt = $this->db->prepare($unitsSql);
            $uStmt->execute($params);
            $totalUnits = (int) $uStmt->fetchColumn();

            require_once __DIR__ . '/WarehouseRepository.php';
            $byCurrency = (new WarehouseRepository($this->db))->inventoryValueByStore($scopeStoreId);

            $cStmt = $this->db->prepare(
                "SELECT DISTINCT w.country FROM warehouses w
                 INNER JOIN stores s ON s.id = w.store_id
                 WHERE w.deleted_at IS NULL AND w.country IS NOT NULL AND w.country != '' AND {$whereSql}
                 ORDER BY w.country ASC"
            );
            $cStmt->execute($params);
            $countries = array_column($cStmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'country');
        }

        return [
            'store_count' => (int) ($row['store_count'] ?? 0),
            'active_stores' => (int) ($row['active_stores'] ?? 0),
            'inactive_stores' => (int) ($row['inactive_stores'] ?? 0),
            'warehouse_count' => $whCount,
            'active_warehouses' => $whActive,
            'total_units' => $totalUnits,
            'currency_count' => (int) ($row['currency_count'] ?? 0),
            'countries' => $countries,
            'by_currency' => $byCurrency,
        ];
    }

    /** @return list<array<string, mixed>> */
    public function warehousesForStore(int $storeId): array
    {
        if (!WmsSchema::ready() || $storeId <= 0) {
            return [];
        }
        if (!StoreScope::canAccessStore($this->db, $storeId)) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT w.id, w.warehouse_code, w.name, w.warehouse_type, w.city, w.country, w.status,
                    (SELECT COALESCE(SUM(wi.quantity), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS total_units,
                    (SELECT COALESCE(SUM(wi.stock_value), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS stock_value
             FROM warehouses w
             WHERE w.store_id = ? AND w.deleted_at IS NULL
             ORDER BY w.name ASC"
        );
        $stmt->execute([$storeId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function formatRow(array $row, bool $wmsReady): array
    {
        $countries = [];
        if (!empty($row['warehouse_countries'])) {
            $countries = array_values(array_filter(array_map('trim', explode(',', (string) $row['warehouse_countries']))));
        }

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['name'] ?? ''),
            'code' => $row['code'] ?? null,
            'location' => $row['location'] ?? null,
            'phone' => $row['phone'] ?? null,
            'email' => $row['email'] ?? null,
            'tax_rate' => isset($row['tax_rate']) ? (float) $row['tax_rate'] : 18.0,
            'currency' => CurrencyHelper::normalize($row['currency'] ?? 'FCFA'),
            'is_active' => !isset($row['is_active']) || (bool) $row['is_active'],
            'staff_count' => (int) ($row['staff_count'] ?? 0),
            'product_count' => (int) ($row['product_count'] ?? 0),
            'warehouse_count' => (int) ($row['warehouse_count'] ?? 0),
            'active_warehouse_count' => (int) ($row['active_warehouse_count'] ?? 0),
            'warehouse_units' => (int) ($row['warehouse_units'] ?? 0),
            'warehouse_value' => round((float) ($row['warehouse_value'] ?? 0), 2),
            'countries' => $countries,
            'wms_ready' => $wmsReady,
        ];
    }

    private function hasColumn(string $table, string $column): bool
    {
        static $cache = [];
        $key = "{$table}.{$column}";
        if (isset($cache[$key])) {
            return $cache[$key];
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return $cache[$key] = (bool) $stmt->fetchColumn();
    }
}

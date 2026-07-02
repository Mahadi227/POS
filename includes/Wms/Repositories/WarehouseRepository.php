<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(
        ?int $storeId = null,
        ?string $status = null,
        ?string $q = null,
        ?string $type = null,
        ?int $filterStoreId = null,
        int $limit = 200,
        int $offset = 0
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($storeId, $status, $q, $type, $filterStoreId);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT w.*, s.name AS store_name, s.currency AS store_currency, u.name AS manager_name,
                       (SELECT COALESCE(SUM(wi.stock_value), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS stock_value,
                       (SELECT COALESCE(SUM(wi.quantity), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS total_units,
                       (SELECT COUNT(*) FROM warehouse_locations wl WHERE wl.warehouse_id = w.id) AS location_count,
                       (SELECT COUNT(*) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS sku_count
                FROM warehouses w
                LEFT JOIN stores s ON s.id = w.store_id
                LEFT JOIN users u ON u.id = w.manager_id
                WHERE w.deleted_at IS NULL {$where}
                ORDER BY w.name ASC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $row) => $this->formatListRow($row), $rows);
    }

    public function count(
        ?int $storeId = null,
        ?string $status = null,
        ?string $q = null,
        ?string $type = null,
        ?int $filterStoreId = null
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($storeId, $status, $q, $type, $filterStoreId);
        $sql = "SELECT COUNT(*)
                FROM warehouses w
                LEFT JOIN stores s ON s.id = w.store_id
                LEFT JOIN users u ON u.id = w.manager_id
                WHERE w.deleted_at IS NULL {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT w.*, s.name AS store_name, u.name AS manager_name
             FROM warehouses w
             LEFT JOIN stores s ON s.id = w.store_id
             LEFT JOIN users u ON u.id = w.manager_id
             WHERE w.id = ? AND w.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO warehouses
                (store_id, warehouse_code, name, warehouse_type, manager_id, address, city, country,
                 phone, email, status, capacity_units, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            !empty($data['store_id']) ? (int) $data['store_id'] : null,
            (string) $data['warehouse_code'],
            (string) $data['name'],
            (string) ($data['warehouse_type'] ?? 'central'),
            !empty($data['manager_id']) ? (int) $data['manager_id'] : null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? 'Senegal',
            $data['phone'] ?? null,
            $data['email'] ?? null,
            (string) ($data['status'] ?? 'active'),
            (int) ($data['capacity_units'] ?? 0),
            $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE warehouses SET name = ?, warehouse_type = ?, manager_id = ?, address = ?, city = ?,
             country = ?, phone = ?, email = ?, status = ?, capacity_units = ?, notes = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        return $stmt->execute([
            (string) $data['name'],
            (string) ($data['warehouse_type'] ?? 'central'),
            !empty($data['manager_id']) ? (int) $data['manager_id'] : null,
            $data['address'] ?? null,
            $data['city'] ?? null,
            $data['country'] ?? null,
            $data['phone'] ?? null,
            $data['email'] ?? null,
            (string) ($data['status'] ?? 'active'),
            (int) ($data['capacity_units'] ?? 0),
            $data['notes'] ?? null,
            $id,
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare("UPDATE warehouses SET deleted_at = NOW(), status = 'inactive' WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function countSummary(?int $storeId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'total_value' => 0, 'total_units' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                       SUM(CASE WHEN status != 'active' THEN 1 ELSE 0 END) AS inactive
                FROM warehouses WHERE deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND (store_id = ? OR store_id IS NULL)';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $valSql = 'SELECT COALESCE(SUM(stock_value), 0), COALESCE(SUM(quantity), 0) FROM warehouse_inventory wi';
        if ($storeId !== null) {
            $valSql .= ' INNER JOIN warehouses w ON w.id = wi.warehouse_id WHERE w.store_id = ? OR w.store_id IS NULL';
        }
        $vStmt = $this->db->prepare($valSql);
        $vStmt->execute($storeId !== null ? [$storeId] : []);
        [$totalValue, $totalUnits] = $vStmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'total_value' => round((float) $totalValue, 2),
            'total_units' => (int) $totalUnits,
        ];
    }

    public function networkSummary(?int $storeId = null): array
    {
        $base = $this->countSummary($storeId);
        if (!WmsSchema::ready()) {
            return array_merge($base, [
                'country_count' => 0,
                'countries' => [],
                'total_capacity' => 0,
                'capacity_used_pct' => 0,
                'by_type' => [],
                'by_store' => [],
            ]);
        }

        $scope = '';
        $params = [];
        if ($storeId !== null) {
            $scope = ' AND (store_id = ? OR store_id IS NULL)';
            $params[] = $storeId;
        }

        $capStmt = $this->db->prepare(
            "SELECT COALESCE(SUM(capacity_units), 0) FROM warehouses WHERE deleted_at IS NULL {$scope}"
        );
        $capStmt->execute($params);
        $totalCapacity = (int) $capStmt->fetchColumn();
        $usedPct = $totalCapacity > 0
            ? min(100, round(($base['total_units'] / $totalCapacity) * 100, 1))
            : 0;

        $countryStmt = $this->db->prepare(
            "SELECT DISTINCT country FROM warehouses
             WHERE deleted_at IS NULL AND country IS NOT NULL AND country != '' {$scope}
             ORDER BY country ASC"
        );
        $countryStmt->execute($params);
        $countries = array_column($countryStmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'country');

        $typeStmt = $this->db->prepare(
            "SELECT warehouse_type, COUNT(*) AS cnt
             FROM warehouses WHERE deleted_at IS NULL {$scope}
             GROUP BY warehouse_type ORDER BY cnt DESC"
        );
        $typeStmt->execute($params);
        $byType = array_map(static fn (array $r) => [
            'type' => (string) ($r['warehouse_type'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $typeStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return array_merge($base, [
            'country_count' => count($countries),
            'countries' => $countries,
            'total_capacity' => $totalCapacity,
            'capacity_used_pct' => $usedPct,
            'by_type' => $byType,
            'by_store' => $this->inventoryValueByStore($storeId),
        ]);
    }

    /** @return array{0: string, 1: array} */
    private function filterClause(
        ?int $storeId,
        ?string $status,
        ?string $q,
        ?string $type,
        ?int $filterStoreId
    ): array {
        $sql = '';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        if ($filterStoreId) {
            $sql .= ' AND w.store_id = ?';
            $params[] = $filterStoreId;
        }
        if ($status !== null && $status !== 'all') {
            $sql .= ' AND w.status = ?';
            $params[] = $status;
        }
        if ($type !== null && $type !== 'all' && $type !== '') {
            $sql .= ' AND w.warehouse_type = ?';
            $params[] = $type;
        }
        if ($q !== null && $q !== '') {
            $sql .= ' AND (w.warehouse_code LIKE ? OR w.name LIKE ? OR w.city LIKE ? OR w.country LIKE ?
                      OR s.name LIKE ? OR u.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }
        return [$sql, $params];
    }

    private function formatListRow(array $row): array
    {
        $capacity = (int) ($row['capacity_units'] ?? 0);
        $units = (int) ($row['total_units'] ?? 0);
        $capacityPct = $capacity > 0 ? min(100, round($units / $capacity * 100, 1)) : null;

        return array_merge($row, [
            'stock_value' => round((float) ($row['stock_value'] ?? 0), 2),
            'total_units' => $units,
            'location_count' => (int) ($row['location_count'] ?? 0),
            'sku_count' => (int) ($row['sku_count'] ?? 0),
            'capacity_pct' => $capacityPct,
            'store_currency' => CurrencyHelper::normalize($row['store_currency'] ?? 'FCFA'),
        ]);
    }

    /**
     * Inventory valuation grouped by store / currency (multi-country dashboard).
     *
     * @return list<array{store_id: int, store_name: string, currency: string, stock_value: float, warehouse_count: int, country: ?string}>
     */
    public function inventoryValueByStore(?int $storeId = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT COALESCE(s.id, 0) AS store_id,
                       COALESCE(s.name, 'Unassigned') AS store_name,
                       COALESCE(NULLIF(TRIM(s.currency), ''), 'FCFA') AS currency,
                       COALESCE(SUM(wi.stock_value), 0) AS stock_value,
                       COUNT(DISTINCT w.id) AS warehouse_count,
                       MAX(w.country) AS country
                FROM warehouse_inventory wi
                INNER JOIN warehouses w ON w.id = wi.warehouse_id AND w.deleted_at IS NULL
                LEFT JOIN stores s ON s.id = w.store_id AND s.deleted_at IS NULL
                WHERE 1=1";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY s.id, s.name, s.currency ORDER BY stock_value DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static function (array $row): array {
            return [
                'store_id' => (int) ($row['store_id'] ?? 0),
                'store_name' => (string) ($row['store_name'] ?? ''),
                'currency' => CurrencyHelper::normalize($row['currency'] ?? 'FCFA'),
                'stock_value' => round((float) ($row['stock_value'] ?? 0), 2),
                'warehouse_count' => (int) ($row['warehouse_count'] ?? 0),
                'country' => $row['country'] ?? null,
            ];
        }, $rows);
    }
}

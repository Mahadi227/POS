<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $storeId = null, ?string $status = null, ?string $q = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT w.*, s.name AS store_name, u.name AS manager_name,
                       (SELECT COALESCE(SUM(wi.stock_value), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS stock_value,
                       (SELECT COALESCE(SUM(wi.quantity), 0) FROM warehouse_inventory wi WHERE wi.warehouse_id = w.id) AS total_units
                FROM warehouses w
                LEFT JOIN stores s ON s.id = w.store_id
                LEFT JOIN users u ON u.id = w.manager_id
                WHERE w.deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        if ($status !== null && $status !== 'all') {
            $sql .= ' AND w.status = ?';
            $params[] = $status;
        }
        if ($q !== null && $q !== '') {
            $sql .= ' AND (w.warehouse_code LIKE ? OR w.name LIKE ? OR w.city LIKE ? OR s.name LIKE ? OR u.name LIKE ?)';
            $like = '%' . $q . '%';
            $params = array_merge($params, array_fill(0, 5, $like));
        }
        $sql .= ' ORDER BY w.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
}

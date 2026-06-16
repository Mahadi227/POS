<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseRequestRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $storeId = null, ?string $status = null, ?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT r.*, s.name AS store_name, w.name AS warehouse_name, u.name AS requested_by_name,
                       mu.name AS manager_name, wu.name AS warehouse_approved_name,
                       (SELECT COUNT(*) FROM warehouse_request_items ri WHERE ri.request_id = r.id) AS total_items,
                       (SELECT COALESCE(SUM(ri.quantity_requested), 0) FROM warehouse_request_items ri WHERE ri.request_id = r.id) AS total_qty
                FROM warehouse_requests r
                INNER JOIN stores s ON s.id = r.store_id
                INNER JOIN warehouses w ON w.id = r.warehouse_id
                LEFT JOIN users u ON u.id = r.requested_by
                LEFT JOIN users mu ON mu.id = r.manager_id
                LEFT JOIN users wu ON wu.id = r.warehouse_approved_by
                WHERE 1=1";
        $params = [];
        if ($storeId) {
            $sql .= ' AND r.store_id = ?';
            $params[] = $storeId;
        }
        if ($warehouseId) {
            $sql .= ' AND r.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }
        if ($search) {
            $sql .= ' AND (r.request_number LIKE ? OR s.name LIKE ? OR w.name LIKE ? OR u.name LIKE ? OR r.notes LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 5, $like));
        }
        $sql .= ' ORDER BY FIELD(r.priority, \'urgent\', \'high\', \'normal\', \'low\'), r.created_at DESC LIMIT 150';
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
            "SELECT r.*, s.name AS store_name, w.name AS warehouse_name, u.name AS requested_by_name,
                    mu.name AS manager_name, wu.name AS warehouse_approved_name
             FROM warehouse_requests r
             INNER JOIN stores s ON s.id = r.store_id
             INNER JOIN warehouses w ON w.id = r.warehouse_id
             LEFT JOIN users u ON u.id = r.requested_by
             LEFT JOIN users mu ON mu.id = r.manager_id
             LEFT JOIN users wu ON wu.id = r.warehouse_approved_by
             WHERE r.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            "SELECT ri.*, p.name AS product_name, p.sku
             FROM warehouse_request_items ri
             INNER JOIN products p ON p.id = ri.product_id
             WHERE ri.request_id = ?
             ORDER BY p.name ASC"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $storeId = null, ?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'pending' => 0, 'approved' => 0, 'urgent' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                       SUM(CASE WHEN status IN ('manager_approved','warehouse_approved','dispatched') THEN 1 ELSE 0 END) AS approved,
                       SUM(CASE WHEN status = 'pending' AND priority IN ('urgent','high') THEN 1 ELSE 0 END) AS urgent
                FROM warehouse_requests WHERE 1=1";
        $params = [];
        if ($storeId) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'approved' => (int) ($row['approved'] ?? 0),
            'urgent' => (int) ($row['urgent'] ?? 0),
        ];
    }

    public function create(array $data, array $items): int
    {
        $num = (string) ($data['request_number'] ?? ('SR-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_requests (request_number, store_id, warehouse_id, status, priority, notes, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $num,
            (int) $data['store_id'],
            (int) $data['warehouse_id'],
            (string) ($data['status'] ?? 'pending'),
            (string) ($data['priority'] ?? 'normal'),
            $data['notes'] ?? null,
            (int) $data['requested_by'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            'INSERT INTO warehouse_request_items (request_id, product_id, quantity_requested) VALUES (?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([$id, (int) $item['product_id'], (int) ($item['quantity'] ?? 0)]);
        }
        return $id;
    }

    public function updateStatus(int $id, string $status, ?int $userId = null, string $role = 'manager'): bool
    {
        if ($role === 'warehouse') {
            $stmt = $this->db->prepare('UPDATE warehouse_requests SET status = ?, warehouse_approved_by = ? WHERE id = ?');
            return $stmt->execute([$status, $userId, $id]);
        }
        $stmt = $this->db->prepare('UPDATE warehouse_requests SET status = ?, manager_id = ? WHERE id = ?');
        return $stmt->execute([$status, $userId, $id]);
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseDispatchRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $warehouseId = null, ?string $status = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT d.*, w.name AS from_warehouse_name, s.name AS to_store_name, tw.name AS to_warehouse_name,
                       u.name AS created_by_name,
                       (SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                        FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id) AS total_value
                FROM warehouse_dispatches d
                INNER JOIN warehouses w ON w.id = d.from_warehouse_id
                LEFT JOIN stores s ON s.id = d.to_store_id
                LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
                LEFT JOIN users u ON u.id = d.created_by
                WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND d.from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            $sql .= ' AND d.status = ?';
            $params[] = $status;
        }
        if ($search) {
            $sql .= ' AND (d.dispatch_number LIKE ? OR d.driver_name LIKE ? OR d.vehicle_number LIKE ?
                      OR w.name LIKE ? OR s.name LIKE ? OR tw.name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }
        $sql .= ' ORDER BY d.created_at DESC LIMIT 150';
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
            "SELECT d.*, w.name AS from_warehouse_name, s.name AS to_store_name, tw.name AS to_warehouse_name,
                    u.name AS created_by_name, ru.name AS received_by_name,
                    (SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                     FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id) AS total_value
             FROM warehouse_dispatches d
             INNER JOIN warehouses w ON w.id = d.from_warehouse_id
             LEFT JOIN stores s ON s.id = d.to_store_id
             LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
             LEFT JOIN users u ON u.id = d.created_by
             LEFT JOIN users ru ON ru.id = d.received_by
             WHERE d.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            "SELECT di.*, p.name AS product_name, p.sku, bt.batch_number
             FROM warehouse_dispatch_items di
             INNER JOIN products p ON p.id = di.product_id
             LEFT JOIN batch_tracking bt ON bt.id = di.batch_id
             WHERE di.dispatch_id = ?
             ORDER BY p.name ASC"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'outgoing' => 0, 'delivered' => 0, 'draft' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status IN ('picking','packed','dispatched','in_transit') THEN 1 ELSE 0 END) AS outgoing,
                       SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                       SUM(CASE WHEN status IN ('draft','picking','packed') THEN 1 ELSE 0 END) AS draft
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'outgoing' => (int) ($row['outgoing'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
        ];
    }

    public function create(array $data, array $items): int
    {
        $num = (string) ($data['dispatch_number'] ?? ('DSP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_dispatches
                (dispatch_number, from_warehouse_id, to_store_id, to_warehouse_id, status, driver_name, vehicle_number,
                 delivery_date, total_items, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $num,
            (int) $data['from_warehouse_id'],
            $data['to_store_id'] ?? null,
            $data['to_warehouse_id'] ?? null,
            (string) ($data['status'] ?? 'draft'),
            $data['driver_name'] ?? null,
            $data['vehicle_number'] ?? null,
            $data['delivery_date'] ?? null,
            count($items),
            $data['notes'] ?? null,
            (int) $data['created_by'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            'INSERT INTO warehouse_dispatch_items (dispatch_id, product_id, batch_id, quantity, unit_cost) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                $id,
                (int) $item['product_id'],
                $item['batch_id'] ?? null,
                (int) ($item['quantity'] ?? 0),
                round((float) ($item['unit_cost'] ?? 0), 4),
            ]);
        }
        return $id;
    }

    public function updateStatus(int $id, string $status, ?int $receivedBy = null): bool
    {
        $extra = $status === 'delivered' ? ', received_at = NOW(), received_by = ?' : '';
        if ($extra) {
            $stmt = $this->db->prepare("UPDATE warehouse_dispatches SET status = ? {$extra} WHERE id = ?");
            return $stmt->execute([$status, $receivedBy, $id]);
        }
        $stmt = $this->db->prepare('UPDATE warehouse_dispatches SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function countOutgoing(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM warehouse_dispatches WHERE status IN ('dispatched','in_transit','picking','packed')");
        return (int) $stmt->fetchColumn();
    }
}

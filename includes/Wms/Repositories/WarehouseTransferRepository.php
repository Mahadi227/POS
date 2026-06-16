<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseTransferRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?string $status = null, ?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT t.*, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name,
                       fs.name AS from_store_name, ts.name AS to_store_name,
                       ru.name AS requested_by_name, au.name AS approved_by_name,
                       (SELECT COUNT(*) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_items,
                       (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0)
                        FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                LEFT JOIN users au ON au.id = t.approved_by
                WHERE 1=1";
        $params = [];
        if ($status && $status !== 'all') {
            $sql .= ' AND t.status = ?';
            $params[] = $status;
        }
        if ($warehouseId) {
            $sql .= ' AND (t.from_warehouse_id = ? OR t.to_warehouse_id = ?)';
            $params[] = $warehouseId;
            $params[] = $warehouseId;
        }
        if ($search) {
            $sql .= ' AND (t.transfer_number LIKE ? OR t.reason LIKE ? OR fw.name LIKE ? OR tw.name LIKE ?
                      OR fs.name LIKE ? OR ts.name LIKE ? OR ru.name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 7, $like));
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 150';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data, array $items): int
    {
        $num = (string) ($data['transfer_number'] ?? ('WT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_transfers
                (transfer_number, transfer_type, from_warehouse_id, to_warehouse_id, from_store_id, to_store_id,
                 status, reason, requested_by, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $num,
            (string) $data['transfer_type'],
            $data['from_warehouse_id'] ?? null,
            $data['to_warehouse_id'] ?? null,
            $data['from_store_id'] ?? null,
            $data['to_store_id'] ?? null,
            (string) ($data['status'] ?? 'requested'),
            $data['reason'] ?? null,
            (int) $data['requested_by'],
            (string) ($data['sync_status'] ?? 'synced'),
            $data['local_uuid'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            'INSERT INTO warehouse_transfer_items (transfer_id, product_id, batch_id, quantity_requested, unit_cost) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                $id,
                (int) $item['product_id'],
                $item['batch_id'] ?? null,
                (int) ($item['quantity'] ?? $item['quantity_requested'] ?? 0),
                round((float) ($item['unit_cost'] ?? 0), 4),
            ]);
        }
        return $id;
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT t.*, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name,
                    fs.name AS from_store_name, ts.name AS to_store_name,
                    ru.name AS requested_by_name, au.name AS approved_by_name, rcv.name AS received_by_name,
                    (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0)
                     FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_value
             FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN stores fs ON fs.id = t.from_store_id
             LEFT JOIN stores ts ON ts.id = t.to_store_id
             LEFT JOIN users ru ON ru.id = t.requested_by
             LEFT JOIN users au ON au.id = t.approved_by
             LEFT JOIN users rcv ON rcv.id = t.received_by
             WHERE t.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            'SELECT ti.*, p.name AS product_name, p.sku, bt.batch_number
             FROM warehouse_transfer_items ti
             INNER JOIN products p ON p.id = ti.product_id
             LEFT JOIN batch_tracking bt ON bt.id = ti.batch_id
             WHERE ti.transfer_id = ?
             ORDER BY p.name ASC'
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'requested' => 0, 'in_progress' => 0, 'completed' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) AS requested,
                       SUM(CASE WHEN status IN ('approved','picking','in_transit','received') THEN 1 ELSE 0 END) AS in_progress,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM warehouse_transfers WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
            $params[] = $warehouseId;
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'requested' => (int) ($row['requested'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }

    public function updateStatus(int $id, string $status, ?int $userId = null, string $field = 'approved_by'): bool
    {
        $allowed = ['approved_by', 'received_by'];
        if (!in_array($field, $allowed, true)) {
            $field = 'approved_by';
        }
        $extra = $status === 'approved' ? ', approved_at = NOW()' : ($status === 'completed' ? ', completed_at = NOW()' : '');
        $stmt = $this->db->prepare("UPDATE warehouse_transfers SET status = ?, {$field} = ? {$extra} WHERE id = ?");
        return $stmt->execute([$status, $userId, $id]);
    }

    public function countPending(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM warehouse_transfers WHERE status IN ('requested','approved','in_transit')");
        return (int) $stmt->fetchColumn();
    }
}

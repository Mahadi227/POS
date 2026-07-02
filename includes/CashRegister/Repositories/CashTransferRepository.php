<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashTransferRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cash_transfers
                (store_id, transfer_type, from_register_id, to_register_id, from_store_id, to_store_id,
                 amount, reason, status, requested_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)"
        );
        $stmt->execute([
            (int) $data['store_id'],
            (string) $data['transfer_type'],
            !empty($data['from_register_id']) ? (int) $data['from_register_id'] : null,
            !empty($data['to_register_id']) ? (int) $data['to_register_id'] : null,
            !empty($data['from_store_id']) ? (int) $data['from_store_id'] : null,
            !empty($data['to_store_id']) ? (int) $data['to_store_id'] : null,
            round((float) $data['amount'], 2),
            $data['reason'] ?? null,
            (int) $data['requested_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function list(?int $storeId, array $filters = [], int $limit = 100): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $sql = "SELECT ct.*, fr.name AS from_register_name, tr.name AS to_register_name,
                       ru.name AS requested_by_name, au.name AS approved_by_name, rv.name AS received_by_name
                FROM cash_transfers ct
                LEFT JOIN cash_registers fr ON fr.id = ct.from_register_id
                LEFT JOIN cash_registers tr ON tr.id = ct.to_register_id
                LEFT JOIN users ru ON ru.id = ct.requested_by
                LEFT JOIN users au ON au.id = ct.approved_by
                LEFT JOIN users rv ON rv.id = ct.received_by
                WHERE 1=1";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND ct.store_id = ?';
            $params[] = $storeId;
        }
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== 'all') {
            $sql .= ' AND ct.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND ct.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND ct.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $like = '%' . trim((string) $filters['q']) . '%';
            $sql .= ' AND (ct.transfer_type LIKE ? OR COALESCE(ct.reason, \'\') LIKE ? OR fr.name LIKE ? OR tr.name LIKE ? OR ru.name LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY ct.created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function updateStatus(int $id, string $status, ?int $userId = null, string $field = 'approved_by'): bool
    {
        $allowed = ['approved_by', 'received_by'];
        if (!in_array($field, $allowed, true)) {
            $field = 'approved_by';
        }
        $completed = in_array($status, ['completed', 'rejected', 'cancelled'], true) ? ', completed_at = NOW()' : '';
        $stmt = $this->db->prepare(
            "UPDATE cash_transfers SET status = ?, {$field} = ?{$completed} WHERE id = ?"
        );
        return $stmt->execute([$status, $userId, $id]);
    }
}

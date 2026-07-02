<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashReconciliationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cash_reconciliation
                (store_id, register_id, session_id, expected_cash, physical_cash, difference, status, notes)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['store_id'],
            (int) $data['register_id'],
            (int) $data['session_id'],
            round((float) $data['expected_cash'], 2),
            round((float) $data['physical_cash'], 2),
            round((float) $data['difference'], 2),
            (string) ($data['status'] ?? 'pending'),
            $data['notes'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function list(?int $storeId, array $filters = []): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 200)));
        $sql = "SELECT cr.*, r.name AS register_name, r.register_code, s.name AS store_name,
                       u.name AS cashier_name, m.name AS manager_name, a.name AS admin_name,
                       COALESCE(a.name, m.name) AS reviewer_name,
                       COALESCE(cr.admin_note, cr.manager_note) AS review_note
                FROM cash_reconciliation cr
                INNER JOIN cash_registers r ON r.id = cr.register_id
                INNER JOIN stores s ON s.id = cr.store_id
                INNER JOIN cash_register_sessions crs ON crs.id = cr.session_id
                INNER JOIN users u ON u.id = crs.user_id
                LEFT JOIN users m ON m.id = cr.manager_id
                LEFT JOIN users a ON a.id = cr.admin_id
                WHERE 1=1";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND cr.store_id = ?';
            $params[] = $storeId;
        }
        $status = $filters['status'] ?? null;
        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql .= ' AND cr.status = ?';
            $params[] = $status;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(cr.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(cr.created_at) <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (r.name LIKE ? OR r.register_code LIKE ? OR u.name LIKE ? OR s.name LIKE ? OR cr.notes LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY FIELD(cr.status, \'pending\', \'rejected\', \'approved\'), cr.created_at DESC LIMIT ' . $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function review(int $id, string $status, int $reviewerId, string $role, ?string $note = null): bool
    {
        $col = $role === 'admin' ? 'admin_id' : 'manager_id';
        $noteCol = $role === 'admin' ? 'admin_note' : 'manager_note';
        $stmt = $this->db->prepare(
            "UPDATE cash_reconciliation
             SET status = ?, {$col} = ?, {$noteCol} = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$status, $reviewerId, $note, $id]);
    }

    public function countPending(?int $storeId): int
    {
        if (!CashRegisterSchema::ready()) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM cash_reconciliation WHERE status = 'pending'";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }
}

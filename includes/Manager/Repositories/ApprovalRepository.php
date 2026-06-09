<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../ManagerAuth.php';

class ApprovalRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM manager_approvals LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function countPending(?int $storeId): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        [$sql, $params] = $this->storeFilter($storeId);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM manager_approvals WHERE status = 'pending' {$sql}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listPending(?int $storeId, ?string $type = null, int $limit = 50): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        [$sql, $params] = $this->storeFilter($storeId);
        if ($type) {
            $sql .= ' AND type = ?';
            $params[] = $type;
        }
        $stmt = $this->db->prepare(
            "SELECT a.*, u.name AS requester_name
             FROM manager_approvals a
             JOIN users u ON u.id = a.requested_by
             WHERE a.status = 'pending' {$sql}
             ORDER BY a.created_at ASC
             LIMIT " . (int) $limit
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function review(int $id, int $reviewerId, string $status, ?string $note = null): bool
    {
        if (!$this->tableExists()) {
            return false;
        }
        $stmt = $this->db->prepare(
            "UPDATE manager_approvals
             SET status = ?, reviewed_by = ?, manager_note = ?, reviewed_at = NOW()
             WHERE id = ? AND status = 'pending'"
        );
        return $stmt->execute([$status, $reviewerId, $note, $id]);
    }

    private function storeFilter(?int $storeId): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        return ['AND store_id = ?', [$storeId]];
    }
}

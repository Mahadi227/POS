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
        [$sql, $params] = $this->storeFilter($storeId, 'a');
        if ($type) {
            $sql .= ' AND a.type = ?';
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

    public function findPendingById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT a.*, u.name AS requester_name
             FROM manager_approvals a
             JOIN users u ON u.id = a.requested_by
             WHERE a.id = ? AND a.status = 'pending'
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        if (!$this->tableExists()) {
            return 0;
        }
        $payload = $data['payload'] ?? [];
        $stmt = $this->db->prepare(
            'INSERT INTO manager_approvals
                (store_id, type, status, reference_type, reference_id, requested_by, amount, reason, payload)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['store_id'],
            (string) $data['type'],
            'pending',
            $data['reference_type'] ?? null,
            isset($data['reference_id']) ? (int) $data['reference_id'] : null,
            (int) $data['requested_by'],
            (float) ($data['amount'] ?? 0),
            $data['reason'] ?? null,
            json_encode($payload, JSON_UNESCAPED_UNICODE),
        ]);
        return (int) $this->db->lastInsertId();
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

    private function storeFilter(?int $storeId, string $alias = ''): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        $column = $alias !== '' ? "{$alias}.store_id" : 'store_id';
        return ["AND {$column} = ?", [$storeId]];
    }
}

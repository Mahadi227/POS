<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../AccountingSchema.php';

class ExpenseRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $storeId, array $filters = [], int $limit = 200): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT e.*, u.name AS created_by_name, a.name AS approver_name
                FROM acc_expense_records e
                INNER JOIN users u ON u.id = e.created_by
                LEFT JOIN users a ON a.id = e.approved_by
                WHERE e.deleted_at IS NULL';
        $params = [];
        $this->applyListFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY e.expense_date DESC, e.id DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function stats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_count' => 0, 'total_amount' => 0,
                'pending_count' => 0, 'pending_amount' => 0,
                'approved_count' => 0, 'approved_amount' => 0,
                'rejected_count' => 0, 'rejected_amount' => 0,
            ];
        }
        $where = 'e.deleted_at IS NULL';
        $params = [];
        if ($storeId !== null) {
            $where .= ' AND e.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $where .= ' AND e.expense_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $where .= ' AND e.expense_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['category'])) {
            $where .= ' AND e.category = ?';
            $params[] = $filters['category'];
        }
        $sql = "SELECT
                    COUNT(*) AS total_count,
                    COALESCE(SUM(e.amount), 0) AS total_amount,
                    SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pending_count,
                    COALESCE(SUM(CASE WHEN e.status = 'pending' THEN e.amount ELSE 0 END), 0) AS pending_amount,
                    SUM(CASE WHEN e.status = 'approved' THEN 1 ELSE 0 END) AS approved_count,
                    COALESCE(SUM(CASE WHEN e.status = 'approved' THEN e.amount ELSE 0 END), 0) AS approved_amount,
                    SUM(CASE WHEN e.status = 'rejected' THEN 1 ELSE 0 END) AS rejected_count,
                    COALESCE(SUM(CASE WHEN e.status = 'rejected' THEN e.amount ELSE 0 END), 0) AS rejected_amount
                FROM acc_expense_records e
                WHERE {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total_count' => (int) ($row['total_count'] ?? 0),
            'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
            'pending_count' => (int) ($row['pending_count'] ?? 0),
            'pending_amount' => round((float) ($row['pending_amount'] ?? 0), 2),
            'approved_count' => (int) ($row['approved_count'] ?? 0),
            'approved_amount' => round((float) ($row['approved_amount'] ?? 0), 2),
            'rejected_count' => (int) ($row['rejected_count'] ?? 0),
            'rejected_amount' => round((float) ($row['rejected_amount'] ?? 0), 2),
        ];
    }

    public function categories(?int $storeId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT DISTINCT category FROM acc_expense_records WHERE deleted_at IS NULL';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY category';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'category');
    }

    private function applyListFilters(string &$sql, array &$params, ?int $storeId, array $filters): void
    {
        if ($storeId !== null) {
            $sql .= ' AND e.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND e.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND e.expense_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND e.expense_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['category'])) {
            $sql .= ' AND e.category = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (e.description LIKE ? OR e.category LIKE ? OR u.name LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_expense_records (store_id, category, amount, description, expense_date, payment_method, account_id, status, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['store_id'],
            $data['category'],
            round((float) $data['amount'], 2),
            $data['description'] ?? null,
            $data['expense_date'] ?? date('Y-m-d'),
            $data['payment_method'] ?? 'cash',
            $data['account_id'] ?? null,
            $data['status'] ?? 'pending',
            (int) $data['created_by'],
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM acc_expense_records WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function updateStatus(int $id, string $status, ?int $approvedBy = null, ?int $journalEntryId = null): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE acc_expense_records SET status = ?, approved_by = ?, approved_at = NOW(), journal_entry_id = COALESCE(?, journal_entry_id) WHERE id = ?'
        );
        return $stmt->execute([$status, $approvedBy, $journalEntryId, $id]);
    }

    public function pendingTotal(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(amount), 0) FROM acc_expense_records WHERE status = 'pending' AND deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }
}

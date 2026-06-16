<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class ShiftRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM cashier_shifts LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function listOpen(?int $storeId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $sql = "SELECT s.*, u.name AS cashier_name
                FROM cashier_shifts s
                JOIN users u ON u.id = s.user_id
                WHERE s.status = 'open'";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY s.opened_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findOpenByUser(int $userId, int $storeId): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT s.*, u.name AS cashier_name
             FROM cashier_shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.user_id = ? AND s.store_id = ? AND s.status = 'open'
             ORDER BY s.opened_at DESC
             LIMIT 1"
        );
        $stmt->execute([$userId, $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createShift(int $storeId, int $userId, float $openingFloat, ?string $notes = null): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cashier_shifts
                (store_id, user_id, status, opening_float, total_sales, transaction_count, notes)
             VALUES (?, ?, 'open', ?, 0, 0, ?)"
        );
        $stmt->execute([$storeId, $userId, $openingFloat, $notes]);
        return (int) $this->db->lastInsertId();
    }

    public function linkRegister(int $shiftId, int $registerId, int $sessionId): bool
    {
        if (!$this->hasRegisterColumns()) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE cashier_shifts SET register_id = ?, session_id = ? WHERE id = ? AND status = \'open\''
        );
        return $stmt->execute([$registerId, $sessionId, $shiftId]);
    }

    private function hasRegisterColumns(): bool
    {
        static $cached = null;
        if ($cached !== null) {
            return $cached;
        }
        try {
            $stmt = $this->db->query(
                "SELECT COUNT(*) FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = 'cashier_shifts'
                   AND COLUMN_NAME = 'register_id'"
            );
            $cached = (int) $stmt->fetchColumn() > 0;
        } catch (Throwable $e) {
            $cached = false;
        }
        return $cached;
    }

    public function closeShift(
        int $id,
        float $expectedCash,
        float $countedCash,
        float $variance,
        ?string $notes = null
    ): bool {
        $stmt = $this->db->prepare(
            "UPDATE cashier_shifts
             SET status = 'closed',
                 closed_at = NOW(),
                 expected_cash = ?,
                 counted_cash = ?,
                 variance = ?,
                 notes = ?
             WHERE id = ? AND status = 'open'"
        );
        return $stmt->execute([$expectedCash, $countedCash, $variance, $notes, $id]);
    }

    public function incrementTotals(int $id, float $amount): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cashier_shifts
             SET total_sales = COALESCE(total_sales, 0) + ?,
                 transaction_count = COALESCE(transaction_count, 0) + 1
             WHERE id = ? AND status = 'open'"
        );
        return $stmt->execute([$amount, $id]);
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT s.*, u.name AS cashier_name
             FROM cashier_shifts s
             JOIN users u ON u.id = s.user_id
             WHERE s.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Shifts for cash reconciliation (open + recently closed).
     *
     * @return array<int, array<string, mixed>>
     */
    public function listForReconciliation(?int $storeId, string $scope = 'open'): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $allowed = ['open', 'closed', 'all'];
        if (!in_array($scope, $allowed, true)) {
            $scope = 'open';
        }

        $sql = "SELECT s.*, u.name AS cashier_name
                FROM cashier_shifts s
                JOIN users u ON u.id = s.user_id
                WHERE 1=1";
        $params = [];

        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }

        if ($scope === 'open') {
            $sql .= " AND s.status = 'open'";
        } elseif ($scope === 'closed') {
            $sql .= " AND s.status = 'closed' AND s.closed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)";
        } else {
            $sql .= " AND (s.status = 'open' OR (s.status = 'closed' AND s.closed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)))";
        }

        $sql .= ' ORDER BY s.status ASC, s.opened_at DESC LIMIT 100';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Sum of successful cash payments for sales during a shift window.
     */
    public function cashSalesForShift(int $userId, int $storeId, string $openedAt, ?string $closedAt): float
    {
        if (!$this->tableExists()) {
            return 0.0;
        }

        $sql = "SELECT COALESCE(SUM(p.amount), 0)
                FROM sales s
                INNER JOIN payments p ON p.sale_id = s.id
                WHERE s.user_id = ?
                  AND s.store_id = ?
                  AND s.status = 'completed'
                  AND s.deleted_at IS NULL
                  AND s.created_at >= ?
                  AND p.method = 'cash'
                  AND p.status = 'success'";
        $params = [$userId, $storeId, $openedAt];

        if ($closedAt !== null && $closedAt !== '') {
            $sql .= ' AND s.created_at <= ?';
            $params[] = $closedAt;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (float) $stmt->fetchColumn();
    }
}

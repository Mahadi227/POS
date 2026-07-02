<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../AccountingSchema.php';

class AccountingAuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public static function log(
        string $action,
        ?int $storeId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        if (!AccountingSchema::ready()) {
            return;
        }
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                'INSERT INTO acc_accounting_logs (store_id, user_id, action, entity_type, entity_id, details, ip_address)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $storeId,
                $userId,
                $action,
                $entityType,
                $entityId,
                $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                $_SERVER['REMOTE_ADDR'] ?? null,
            ]);
        } catch (Throwable) {
            // non-blocking
        }
    }

    public function list(?int $storeId, array $filters = [], int $limit = 100): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT l.*, u.name AS user_name FROM acc_accounting_logs l
                LEFT JOIN users u ON u.id = l.user_id WHERE 1=1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['action'])) {
            $sql .= ' AND l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(l.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(l.created_at) <= ?';
            $params[] = $filters['to'];
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function auditLogsPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $rows = $this->listFiltered($storeId, $filters);
        $stats = $this->auditStats($storeId, $filters);

        return [
            'module_ready' => true,
            'rows' => $rows,
            'stats' => $stats,
            'charts' => [
                'by_action' => $this->auditByAction($storeId, $filters),
                'by_entity' => $this->auditByEntity($storeId, $filters),
                'trend' => $this->auditTrend($storeId, $filters),
            ],
            'insights' => [
                'event_count' => $stats['total_events'],
                'unique_users' => $stats['unique_users'],
                'unique_actions' => $stats['unique_actions'],
                'top_action' => $stats['top_action'] ?? '—',
            ],
            'actions' => $this->distinctActions($storeId),
            'entity_types' => $this->distinctEntityTypes($storeId),
        ];
    }

    public function listFiltered(?int $storeId, array $filters = [], int $limit = 500): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT l.*, u.name AS user_name FROM acc_accounting_logs l
                LEFT JOIN users u ON u.id = l.user_id WHERE 1=1';
        $params = [];
        $this->applyAuditFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . max(1, min(500, (int) ($filters['limit'] ?? $limit)));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static function (array $row) {
            if (!empty($row['details']) && is_string($row['details'])) {
                $decoded = json_decode($row['details'], true);
                if (is_array($decoded)) {
                    $row['details'] = $decoded;
                }
            }
            return $row;
        }, $rows);
    }

    public function auditStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_events' => 0, 'journal_events' => 0, 'expense_events' => 0,
                'treasury_events' => 0, 'unique_users' => 0, 'unique_actions' => 0, 'top_action' => null,
            ];
        }

        $baseSql = ' FROM acc_accounting_logs l WHERE 1=1';
        $params = [];
        $this->applyAuditFilters($baseSql, $params, $storeId, $filters, false);

        $stmt = $this->db->prepare('SELECT COUNT(*)' . $baseSql);
        $stmt->execute($params);
        $total = (int) $stmt->fetchColumn();

        $journalSql = "SELECT COUNT(*)" . $baseSql . " AND l.action IN ('journal_posted','auto_post_sale')";
        $stmt = $this->db->prepare($journalSql);
        $stmt->execute($params);
        $journalEvents = (int) $stmt->fetchColumn();

        $expenseSql = "SELECT COUNT(*)" . $baseSql . " AND l.action IN ('expense_created','expense_approved','expense_rejected')";
        $stmt = $this->db->prepare($expenseSql);
        $stmt->execute($params);
        $expenseEvents = (int) $stmt->fetchColumn();

        $treasurySql = "SELECT COUNT(*)" . $baseSql . " AND l.action IN ('cash_transaction','cash_account_created','bank_account_created','mobile_wallet_created')";
        $stmt = $this->db->prepare($treasurySql);
        $stmt->execute($params);
        $treasuryEvents = (int) $stmt->fetchColumn();

        $usersSql = 'SELECT COUNT(DISTINCT l.user_id)' . $baseSql . ' AND l.user_id IS NOT NULL';
        $stmt = $this->db->prepare($usersSql);
        $stmt->execute($params);
        $uniqueUsers = (int) $stmt->fetchColumn();

        $actionsSql = 'SELECT COUNT(DISTINCT l.action)' . $baseSql;
        $stmt = $this->db->prepare($actionsSql);
        $stmt->execute($params);
        $uniqueActions = (int) $stmt->fetchColumn();

        $topSql = 'SELECT l.action, COUNT(*) AS cnt' . $baseSql . ' GROUP BY l.action ORDER BY cnt DESC LIMIT 1';
        $stmt = $this->db->prepare($topSql);
        $stmt->execute($params);
        $topRow = $stmt->fetch(PDO::FETCH_ASSOC);
        $topAction = $topRow['action'] ?? null;

        return [
            'total_events' => $total,
            'journal_events' => $journalEvents,
            'expense_events' => $expenseEvents,
            'treasury_events' => $treasuryEvents,
            'unique_users' => $uniqueUsers,
            'unique_actions' => $uniqueActions,
            'top_action' => $topAction,
        ];
    }

    private function auditByAction(?int $storeId, array $filters): array
    {
        $sql = 'SELECT l.action, COUNT(*) AS count FROM acc_accounting_logs l WHERE 1=1';
        $params = [];
        $this->applyAuditFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY l.action ORDER BY count DESC LIMIT 10';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'action' => $r['action'],
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function auditByEntity(?int $storeId, array $filters): array
    {
        $sql = "SELECT COALESCE(l.entity_type, 'other') AS entity_type, COUNT(*) AS count
                FROM acc_accounting_logs l WHERE 1=1";
        $params = [];
        $this->applyAuditFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY l.entity_type ORDER BY count DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'entity_type' => $r['entity_type'],
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function auditTrend(?int $storeId, array $filters): array
    {
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $sql = 'SELECT DATE(l.created_at) AS day, COUNT(*) AS count
                FROM acc_accounting_logs l
                WHERE DATE(l.created_at) BETWEEN ? AND ?';
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['category']) && $filters['category'] !== 'all') {
            $this->appendCategoryFilter($sql, $filters['category']);
        }
        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $sql .= ' AND l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'all') {
            $sql .= ' AND l.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        $sql .= ' GROUP BY DATE(l.created_at) ORDER BY day ASC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'day' => $r['day'],
                'count' => (int) $r['count'],
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function distinctActions(?int $storeId): array
    {
        $sql = 'SELECT DISTINCT action FROM acc_accounting_logs WHERE 1=1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY action';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'action');
    }

    private function distinctEntityTypes(?int $storeId): array
    {
        $sql = "SELECT DISTINCT entity_type FROM acc_accounting_logs WHERE entity_type IS NOT NULL AND entity_type != ''";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY entity_type';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'entity_type');
    }

    private function applyAuditFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeCategory = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(l.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(l.created_at) <= ?';
            $params[] = $filters['to'];
        }
        if ($includeCategory && !empty($filters['category']) && $filters['category'] !== 'all') {
            $this->appendCategoryFilter($sql, $filters['category']);
        }
        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $sql .= ' AND l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'all') {
            $sql .= ' AND l.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['search'])) {
            $this->ensureUserJoin($sql);
            $sql .= ' AND (l.action LIKE ? OR l.entity_type LIKE ? OR l.ip_address LIKE ? OR u.name LIKE ? OR CAST(l.details AS CHAR) LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    private function ensureUserJoin(string &$sql): void
    {
        if (strpos($sql, 'LEFT JOIN users u') === false) {
            $sql = preg_replace(
                '/FROM acc_accounting_logs l\b/',
                'FROM acc_accounting_logs l LEFT JOIN users u ON u.id = l.user_id',
                $sql,
                1
            );
        }
    }

    private function appendCategoryFilter(string &$sql, string $category): void
    {
        if ($category === 'journal') {
            $sql .= " AND l.action IN ('journal_posted','auto_post_sale')";
        } elseif ($category === 'expense') {
            $sql .= " AND l.action IN ('expense_created','expense_approved','expense_rejected')";
        } elseif ($category === 'treasury') {
            $sql .= " AND l.action IN ('cash_transaction','cash_account_created','bank_account_created','mobile_wallet_created')";
        } elseif ($category === 'accounts') {
            $sql .= " AND l.action IN ('account_created')";
        }
    }

    public function queueOffline(int $storeId, string $action, array $payload, string $localUuid): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_offline_queue (store_id, action, payload, local_uuid, status) VALUES (?, ?, ?, ?, \'pending\')'
        );
        $stmt->execute([$storeId, $action, json_encode($payload, JSON_UNESCAPED_UNICODE), $localUuid]);
        return (int) $this->db->lastInsertId();
    }

    public function listPendingOffline(int $limit = 50): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM acc_offline_queue WHERE status = 'pending' ORDER BY created_at ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markOfflineSynced(int $id): void
    {
        $this->db->prepare(
            "UPDATE acc_offline_queue SET status = 'synced', synced_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }
}

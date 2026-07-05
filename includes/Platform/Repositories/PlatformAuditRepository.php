<?php
declare(strict_types=1);

final class PlatformAuditRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function log(
        string $action,
        ?int $platformUserId = null,
        ?int $tenantId = null,
        ?array $details = null,
        ?string $ip = null,
    ): void {
        if (!$this->tableExists()) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO platform_audit_log (platform_user_id, tenant_id, action, details_json, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $platformUserId,
            $tenantId,
            $action,
            $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    }

    public function listForTenant(int $tenantId, int $limit = 30): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT pal.id, pal.action, pal.details_json, pal.ip_address, pal.created_at,
                    pu.name AS platform_user_name, pu.email AS platform_user_email
             FROM platform_audit_log pal
             LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
             WHERE pal.tenant_id = ?
             ORDER BY pal.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listForPlatformUser(int $platformUserId, int $limit = 15): array
    {
        if (!$this->tableExists() || $platformUserId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT pal.id, pal.action, pal.details_json, pal.ip_address, pal.created_at, pal.tenant_id,
                    t.name AS tenant_name
             FROM platform_audit_log pal
             LEFT JOIN tenants t ON t.id = pal.tenant_id
             WHERE pal.platform_user_id = ?
             ORDER BY pal.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $platformUserId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'recent' => $this->listLogs(15, 0),
            'actions' => $this->actionBreakdown(),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        if (!$this->tableExists()) {
            return ['total' => 0, 'today' => 0, 'users' => 0, 'tenants' => 0];
        }

        return [
            'total' => (int) $this->db->query('SELECT COUNT(*) FROM platform_audit_log')->fetchColumn(),
            'today' => (int) $this->db->query(
                'SELECT COUNT(*) FROM platform_audit_log WHERE DATE(created_at) = CURDATE()'
            )->fetchColumn(),
            'users' => (int) $this->db->query(
                'SELECT COUNT(DISTINCT platform_user_id) FROM platform_audit_log WHERE platform_user_id IS NOT NULL'
            )->fetchColumn(),
            'tenants' => (int) $this->db->query(
                'SELECT COUNT(DISTINCT tenant_id) FROM platform_audit_log WHERE tenant_id IS NOT NULL'
            )->fetchColumn(),
        ];
    }

    /** @return array<int, array{action: string, count: int}> */
    public function actionBreakdown(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT action, COUNT(*) AS cnt
             FROM platform_audit_log
             GROUP BY action
             ORDER BY cnt DESC
             LIMIT 12'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'action' => (string) ($r['action'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $rows);
    }

    /** @return array<int, string> */
    public function knownActions(): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT DISTINCT action FROM platform_audit_log ORDER BY action ASC'
        )->fetchAll(PDO::FETCH_COLUMN) ?: [];

        return array_values(array_filter(array_map('strval', $rows)));
    }

    /** @return array<int, array<string, mixed>> */
    public function listLogs(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $action = null,
        ?int $tenantId = null,
    ): array {
        if (!$this->tableExists()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pal.action LIKE ? OR pu.name LIKE ? OR pu.email LIKE ? OR t.name LIKE ? OR pal.ip_address LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like, $like, $like]);
        }
        if ($action !== null && $action !== '') {
            $where[] = 'pal.action = ?';
            $params[] = $action;
        }
        if ($tenantId !== null && $tenantId > 0) {
            $where[] = 'pal.tenant_id = ?';
            $params[] = $tenantId;
        }

        $sql = 'SELECT pal.id, pal.action, pal.details_json, pal.ip_address, pal.created_at,
                       pal.platform_user_id, pal.tenant_id,
                       pu.name AS platform_user_name, pu.email AS platform_user_email,
                       t.name AS tenant_name
                FROM platform_audit_log pal
                LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
                LEFT JOIN tenants t ON t.id = pal.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pal.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT pal.id, pal.action, pal.details_json, pal.ip_address, pal.created_at,
                    pal.platform_user_id, pal.tenant_id,
                    pu.name AS platform_user_name, pu.email AS platform_user_email,
                    t.name AS tenant_name, t.slug AS tenant_slug
             FROM platform_audit_log pal
             LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
             LEFT JOIN tenants t ON t.id = pal.tenant_id
             WHERE pal.id = ?
             LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['platform_audit_log']);
        return (bool) $stmt->fetchColumn();
    }
}

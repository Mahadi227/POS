<?php
declare(strict_types=1);

final class PlatformLogsRepository
{
    /** @var array<string, string> */
    public const CHANNELS = [
        'application' => 'application',
        'email' => 'email',
        'sms' => 'sms',
        'webhook' => 'webhook',
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'recent' => $this->listEntries(15, null, null, null),
            'channels' => array_keys(self::CHANNELS),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $stats = [
            'total' => 0,
            'today' => 0,
            'errors' => 0,
            'errors_today' => 0,
            'email' => 0,
            'sms' => 0,
            'webhook_failed' => 0,
        ];

        if ($this->tableExists('platform_application_logs')) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                        SUM(CASE WHEN level IN ('error','critical') THEN 1 ELSE 0 END) AS errors,
                        SUM(CASE WHEN level IN ('error','critical') AND DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS errors_today
                 FROM platform_application_logs"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total'] += (int) ($row['total'] ?? 0);
            $stats['today'] += (int) ($row['today'] ?? 0);
            $stats['errors'] += (int) ($row['errors'] ?? 0);
            $stats['errors_today'] += (int) ($row['errors_today'] ?? 0);
        }

        if ($this->tableExists('transactional_email_log')) {
            $stats['email'] = (int) $this->db->query('SELECT COUNT(*) FROM transactional_email_log')->fetchColumn();
        }
        if ($this->tableExists('platform_sms_log')) {
            $stats['sms'] = (int) $this->db->query('SELECT COUNT(*) FROM platform_sms_log')->fetchColumn();
        }
        if ($this->tableExists('webhook_deliveries')) {
            $stats['webhook_failed'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM webhook_deliveries WHERE failed_at IS NOT NULL'
            )->fetchColumn();
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listEntries(
        int $limit = 50,
        ?string $channel = null,
        ?string $level = null,
        ?string $search = null,
    ): array {
        $entries = [];

        if ($channel === null || $channel === '' || $channel === 'application') {
            $entries = array_merge($entries, $this->applicationEntries($limit, $level, $search));
        }
        if ($channel === null || $channel === '' || $channel === 'email') {
            $entries = array_merge($entries, $this->emailEntries($limit, $search));
        }
        if ($channel === null || $channel === '' || $channel === 'sms') {
            $entries = array_merge($entries, $this->smsEntries($limit, $search));
        }
        if ($channel === null || $channel === '' || $channel === 'webhook') {
            $entries = array_merge($entries, $this->webhookEntries($limit, $search));
        }

        usort($entries, static function (array $a, array $b): int {
            return strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? ''));
        });

        if ($level !== null && $level !== '' && ($channel === 'application' || $channel === null || $channel === '')) {
            $entries = array_values(array_filter(
                $entries,
                static fn (array $e) => ($e['channel'] ?? '') !== 'application' || ($e['level'] ?? '') === $level
            ));
        }

        return array_slice($entries, 0, $limit);
    }

    public function write(
        string $level,
        string $channel,
        string $message,
        ?array $context = null,
        ?int $tenantId = null,
        ?int $platformUserId = null,
        ?string $ip = null,
    ): void {
        if (!$this->tableExists('platform_application_logs')) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO platform_application_logs
                (level, channel, message, context_json, tenant_id, platform_user_id, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $level,
            $channel,
            $message,
            $context !== null ? json_encode($context, JSON_UNESCAPED_UNICODE) : null,
            $tenantId,
            $platformUserId,
            $ip,
        ]);
    }

    /** @return array<string, mixed>|null */
    public function findEntry(string $ref): ?array
    {
        if (!str_contains($ref, ':')) {
            return null;
        }

        [$channel, $id] = explode(':', $ref, 2);
        $id = (int) $id;
        if ($id <= 0) {
            return null;
        }

        if ($channel === 'application' && $this->tableExists('platform_application_logs')) {
            $stmt = $this->db->prepare(
                'SELECT pal.id, pal.level, pal.channel, pal.message, pal.context_json, pal.ip_address,
                        pal.created_at, pal.tenant_id, pal.platform_user_id,
                        t.name AS tenant_name, pu.name AS platform_user_name
                 FROM platform_application_logs pal
                 LEFT JOIN tenants t ON t.id = pal.tenant_id
                 LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
                 WHERE pal.id = ? LIMIT 1'
            );
            $stmt->execute([$id]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            return $row ? $this->normalizeApplicationRow($row) : null;
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    private function applicationEntries(int $limit, ?string $level, ?string $search): array
    {
        if (!$this->tableExists('platform_application_logs')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if ($level !== null && $level !== '') {
            $where[] = 'pal.level = ?';
            $params[] = $level;
        }
        if ($search !== null && $search !== '') {
            $where[] = '(pal.message LIKE ? OR pal.channel LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT pal.id, pal.level, pal.channel, pal.message, pal.context_json, pal.ip_address,
                       pal.created_at, pal.tenant_id, t.name AS tenant_name
                FROM platform_application_logs pal
                LEFT JOIN tenants t ON t.id = pal.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pal.id DESC
                LIMIT ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => $this->normalizeApplicationRow($r), $rows);
    }

    /** @return array<string, mixed> */
    private function normalizeApplicationRow(array $row): array
    {
        return [
            'ref' => 'application:' . ($row['id'] ?? 0),
            'channel' => 'application',
            'level' => (string) ($row['level'] ?? 'info'),
            'subchannel' => (string) ($row['channel'] ?? ''),
            'message' => (string) ($row['message'] ?? ''),
            'tenant_name' => (string) ($row['tenant_name'] ?? ''),
            'ip_address' => (string) ($row['ip_address'] ?? ''),
            'context_json' => $row['context_json'] ?? null,
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    private function emailEntries(int $limit, ?string $search): array
    {
        if (!$this->tableExists('transactional_email_log')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if ($search !== null && $search !== '') {
            $where[] = '(tel.template_key LIKE ? OR tel.recipient LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT tel.id, tel.template_key, tel.recipient, tel.sent_at, t.name AS tenant_name
                FROM transactional_email_log tel
                LEFT JOIN tenants t ON t.id = tel.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY tel.id DESC LIMIT ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $r): array {
            return [
                'ref' => 'email:' . ($r['id'] ?? 0),
                'channel' => 'email',
                'level' => 'info',
                'subchannel' => (string) ($r['template_key'] ?? ''),
                'message' => 'Email sent to ' . ($r['recipient'] ?? ''),
                'tenant_name' => (string) ($r['tenant_name'] ?? ''),
                'ip_address' => '',
                'context_json' => json_encode(['recipient' => $r['recipient'] ?? '', 'template' => $r['template_key'] ?? '']),
                'created_at' => (string) ($r['sent_at'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<int, array<string, mixed>> */
    private function smsEntries(int $limit, ?string $search): array
    {
        if (!$this->tableExists('platform_sms_log')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if ($search !== null && $search !== '') {
            $where[] = '(psl.template_key LIKE ? OR psl.recipient LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT psl.id, psl.template_key, psl.recipient, psl.status, psl.message, psl.sent_at,
                       t.name AS tenant_name
                FROM platform_sms_log psl
                LEFT JOIN tenants t ON t.id = psl.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY psl.id DESC LIMIT ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $r): array {
            $status = (string) ($r['status'] ?? 'sent');
            return [
                'ref' => 'sms:' . ($r['id'] ?? 0),
                'channel' => 'sms',
                'level' => $status === 'failed' ? 'error' : 'info',
                'subchannel' => (string) ($r['template_key'] ?? ''),
                'message' => (string) ($r['message'] ?? ('SMS to ' . ($r['recipient'] ?? ''))),
                'tenant_name' => (string) ($r['tenant_name'] ?? ''),
                'ip_address' => '',
                'context_json' => json_encode(['recipient' => $r['recipient'] ?? '', 'status' => $status]),
                'created_at' => (string) ($r['sent_at'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array<int, array<string, mixed>> */
    private function webhookEntries(int $limit, ?string $search): array
    {
        if (!$this->tableExists('webhook_deliveries')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];
        if ($search !== null && $search !== '') {
            $where[] = '(wd.event_type LIKE ? OR wd.delivery_uuid LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $sql = 'SELECT wd.id, wd.event_type, wd.delivery_uuid, wd.response_status, wd.attempts,
                       wd.delivered_at, wd.failed_at, wd.created_at, t.name AS tenant_name
                FROM webhook_deliveries wd
                LEFT JOIN tenants t ON t.id = wd.tenant_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY wd.id DESC LIMIT ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static function (array $r): array {
            $failed = !empty($r['failed_at']);
            $delivered = !empty($r['delivered_at']);
            $level = $failed ? 'error' : ($delivered ? 'info' : 'warning');
            $message = $failed
                ? 'Webhook delivery failed: ' . ($r['event_type'] ?? '')
                : ($delivered ? 'Webhook delivered: ' . ($r['event_type'] ?? '') : 'Webhook pending: ' . ($r['event_type'] ?? ''));

            return [
                'ref' => 'webhook:' . ($r['id'] ?? 0),
                'channel' => 'webhook',
                'level' => $level,
                'subchannel' => (string) ($r['event_type'] ?? ''),
                'message' => $message,
                'tenant_name' => (string) ($r['tenant_name'] ?? ''),
                'ip_address' => '',
                'context_json' => json_encode([
                    'uuid' => $r['delivery_uuid'] ?? '',
                    'status' => $r['response_status'] ?? null,
                    'attempts' => $r['attempts'] ?? 0,
                ]),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

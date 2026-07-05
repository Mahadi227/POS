<?php
declare(strict_types=1);

final class PlatformSmsRepository
{
    /** @var array<string, array{icon: string, category: string}> */
    public const TEMPLATES = [
        'account_locked' => ['icon' => 'lock', 'category' => 'security'],
        'trial_ending' => ['icon' => 'schedule', 'category' => 'billing'],
        'payment_failed' => ['icon' => 'error', 'category' => 'billing'],
        'security_alert' => ['icon' => 'shield', 'category' => 'security'],
        'otp_verification' => ['icon' => 'pin', 'category' => 'security'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $stats = [
            'total' => 0,
            'today' => 0,
            'failed' => 0,
            'templates' => count(self::TEMPLATES),
        ];

        if (!$this->tableExists('platform_sms_log')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed
             FROM platform_sms_log"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['today'] = (int) ($row['today_cnt'] ?? 0);
        $stats['failed'] = (int) ($row['failed'] ?? 0);

        return $stats;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'templates' => $this->templateCatalog(),
            'logs' => $this->listLogs(50, 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function listLogs(int $limit = 50, int $offset = 0, ?string $search = null, ?string $template = null): array
    {
        if (!$this->tableExists('platform_sms_log')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(psl.recipient LIKE ? OR psl.template_key LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($template !== null && $template !== '') {
            $where[] = 'psl.template_key = ?';
            $params[] = $template;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = psl.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) t ON 1=0';

        $sql = 'SELECT psl.id, psl.tenant_id, psl.template_key, psl.recipient, psl.status,
                       psl.message, psl.sent_at, t.name AS tenant_name
                FROM platform_sms_log psl
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY psl.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    private function templateCatalog(): array
    {
        $counts = $this->templateCounts();
        $items = [];

        foreach (self::TEMPLATES as $key => $meta) {
            $items[] = [
                'key' => $key,
                'icon' => $meta['icon'],
                'category' => $meta['category'],
                'sent_count' => (int) ($counts[$key] ?? 0),
            ];
        }

        return $items;
    }

    /** @return array<string, int> */
    private function templateCounts(): array
    {
        if (!$this->tableExists('platform_sms_log')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT template_key, COUNT(*) AS cnt FROM platform_sms_log GROUP BY template_key'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(string) ($row['template_key'] ?? '')] = (int) ($row['cnt'] ?? 0);
        }
        return $out;
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

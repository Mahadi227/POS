<?php
declare(strict_types=1);

final class PlatformEmailsRepository
{
    /** @var array<string, array{icon: string, category: string}> */
    public const TEMPLATES = [
        'welcome' => ['icon' => 'waving_hand', 'category' => 'onboarding'],
        'trial_ending_7' => ['icon' => 'schedule', 'category' => 'billing'],
        'trial_ending_3' => ['icon' => 'schedule', 'category' => 'billing'],
        'trial_ending_1' => ['icon' => 'warning', 'category' => 'billing'],
        'payment_failed' => ['icon' => 'error', 'category' => 'billing'],
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
            'templates' => count(self::TEMPLATES),
            'tenants' => 0,
        ];

        if (!$this->tableExists('transactional_email_log')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN DATE(sent_at) = CURDATE() THEN 1 ELSE 0 END) AS today_cnt,
                    COUNT(DISTINCT tenant_id) AS tenants
             FROM transactional_email_log"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['today'] = (int) ($row['today_cnt'] ?? 0);
        $stats['tenants'] = (int) ($row['tenants'] ?? 0);

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
        if (!$this->tableExists('transactional_email_log')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(tel.recipient LIKE ? OR tel.template_key LIKE ? OR t.name LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($template !== null && $template !== '') {
            $where[] = 'tel.template_key LIKE ?';
            $params[] = $template . '%';
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = tel.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) t ON 1=0';

        $sql = 'SELECT tel.id, tel.tenant_id, tel.user_id, tel.template_key, tel.recipient, tel.sent_at,
                       t.name AS tenant_name
                FROM transactional_email_log tel
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY tel.id DESC
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
            $sent = 0;
            foreach ($counts as $tpl => $cnt) {
                if ($tpl === $key || str_starts_with($tpl, $key)) {
                    $sent += $cnt;
                }
            }
            $items[] = [
                'key' => $key,
                'icon' => $meta['icon'],
                'category' => $meta['category'],
                'sent_count' => $sent,
            ];
        }

        return $items;
    }

    /** @return array<string, int> */
    private function templateCounts(): array
    {
        if (!$this->tableExists('transactional_email_log')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT template_key, COUNT(*) AS cnt FROM transactional_email_log GROUP BY template_key'
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

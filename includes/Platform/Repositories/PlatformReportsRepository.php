<?php
declare(strict_types=1);

final class PlatformReportsRepository
{
    /** @var array<string, array{category: string, icon: string}> */
    public const REGISTRY = [
        'tenants' => ['category' => 'core', 'icon' => 'business'],
        'subscriptions' => ['category' => 'billing', 'icon' => 'autorenew'],
        'billing' => ['category' => 'billing', 'icon' => 'receipt_long'],
        'revenue_monthly' => ['category' => 'billing', 'icon' => 'trending_up'],
        'usage' => ['category' => 'operations', 'icon' => 'speed'],
        'licenses' => ['category' => 'product', 'icon' => 'vpn_key'],
        'audit' => ['category' => 'security', 'icon' => 'history'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int> */
    public function reportStats(): array
    {
        $categories = [];
        $rows = 0;

        foreach (self::REGISTRY as $key => $meta) {
            $categories[$meta['category']] = true;
            $rows += $this->rowCount($key);
        }

        return [
            'reports' => count(self::REGISTRY),
            'categories' => count($categories),
            'rows' => $rows,
            'formats' => 1,
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function catalog(): array
    {
        $items = [];

        foreach (self::REGISTRY as $key => $meta) {
            $items[] = [
                'key' => $key,
                'category' => $meta['category'],
                'icon' => $meta['icon'],
                'format' => 'csv',
                'rows' => $this->rowCount($key),
                'available' => $this->isAvailable($key),
            ];
        }

        return $items;
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>, total: int}|null */
    public function preview(string $key, int $limit = 25): ?array
    {
        if (!isset(self::REGISTRY[$key]) || !$this->isAvailable($key)) {
            return null;
        }

        $data = $this->reportData($key);
        $total = count($data['rows']);

        return [
            'key' => $key,
            'columns' => $data['columns'],
            'rows' => array_slice($data['rows'], 0, max(1, $limit)),
            'total' => $total,
        ];
    }

    public function exportCsv(string $key): ?string
    {
        if (!isset(self::REGISTRY[$key]) || !$this->isAvailable($key)) {
            return null;
        }

        $data = $this->reportData($key);
        $out = fopen('php://temp', 'r+');
        if ($out === false) {
            return null;
        }

        fputcsv($out, $data['columns']);
        foreach ($data['rows'] as $row) {
            $line = [];
            foreach ($data['columns'] as $col) {
                $line[] = $row[$col] ?? '';
            }
            fputcsv($out, $line);
        }

        rewind($out);
        $csv = stream_get_contents($out);
        fclose($out);

        return $csv === false ? null : "\xEF\xBB\xBF" . $csv;
    }

    private function isAvailable(string $key): bool
    {
        return match ($key) {
            'tenants' => $this->tableExists('tenants'),
            'subscriptions' => $this->tableExists('tenant_subscriptions') && $this->tableExists('tenants'),
            'billing' => $this->tableExists('billing_events'),
            'revenue_monthly' => $this->tableExists('billing_events'),
            'usage' => $this->tableExists('usage_metrics'),
            'licenses' => $this->tableExists('tenant_licenses'),
            'audit' => $this->tableExists('platform_audit_log'),
            default => false,
        };
    }

    private function rowCount(string $key): int
    {
        if (!$this->isAvailable($key)) {
            return 0;
        }

        return match ($key) {
            'tenants' => (int) $this->db->query(
                'SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL'
            )->fetchColumn(),
            'subscriptions' => (int) $this->db->query(
                'SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL'
            )->fetchColumn(),
            'billing' => (int) $this->db->query('SELECT COUNT(*) FROM billing_events')->fetchColumn(),
            'revenue_monthly' => (int) $this->db->query(
                "SELECT COUNT(DISTINCT DATE_FORMAT(created_at, '%Y-%m'))
                 FROM billing_events WHERE type = 'payment'"
            )->fetchColumn(),
            'usage' => (int) $this->db->query(
                'SELECT COUNT(*) FROM usage_metrics WHERE period = ' . $this->db->quote(date('Y-m-01'))
            )->fetchColumn(),
            'licenses' => (int) $this->db->query('SELECT COUNT(*) FROM tenant_licenses')->fetchColumn(),
            'audit' => (int) $this->db->query('SELECT COUNT(*) FROM platform_audit_log')->fetchColumn(),
            default => 0,
        };
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function reportData(string $key): array
    {
        return match ($key) {
            'tenants' => $this->tenantsReport(),
            'subscriptions' => $this->subscriptionsReport(),
            'billing' => $this->billingReport(),
            'revenue_monthly' => $this->revenueMonthlyReport(),
            'usage' => $this->usageReport(),
            'licenses' => $this->licensesReport(),
            'audit' => $this->auditReport(),
            default => ['columns' => [], 'rows' => []],
        };
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function tenantsReport(): array
    {
        $columns = ['name', 'slug', 'status', 'plan', 'stores', 'users', 'currency', 'created_at'];
        $rows = (new TenantRepository($this->db))->listTenants(5000, 0);

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'name' => (string) ($r['name'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'plan' => (string) ($r['plan_name'] ?? $r['plan_code'] ?? ''),
                'stores' => (int) ($r['store_count'] ?? 0),
                'users' => (int) ($r['user_count'] ?? 0),
                'currency' => (string) ($r['default_currency'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ], $rows),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function subscriptionsReport(): array
    {
        $columns = [
            'organization', 'slug', 'tenant_status', 'subscription_status',
            'plan', 'price_monthly', 'currency', 'period_end',
        ];
        $rows = (new SubscriptionRepository($this->db))->listSubscriptions(5000, 0);

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'organization' => (string) ($r['name'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
                'tenant_status' => (string) ($r['tenant_status'] ?? ''),
                'subscription_status' => (string) ($r['subscription_status'] ?? ''),
                'plan' => (string) ($r['plan_name'] ?? $r['plan_code'] ?? ''),
                'price_monthly' => (float) ($r['price_monthly'] ?? 0),
                'currency' => (string) ($r['currency'] ?? ''),
                'period_end' => (string) ($r['current_period_end'] ?? ''),
            ], $rows),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function billingReport(): array
    {
        $columns = ['id', 'organization', 'slug', 'type', 'amount', 'currency', 'external_id', 'created_at'];
        $rows = (new BillingRepository($this->db))->listEvents(5000, 0);

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'id' => (int) ($r['id'] ?? 0),
                'organization' => (string) ($r['tenant_name'] ?? ''),
                'slug' => (string) ($r['tenant_slug'] ?? ''),
                'type' => (string) ($r['type'] ?? ''),
                'amount' => (float) ($r['amount'] ?? 0),
                'currency' => (string) ($r['currency'] ?? ''),
                'external_id' => (string) ($r['external_id'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ], $rows),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function revenueMonthlyReport(): array
    {
        $columns = ['month', 'payments', 'amount', 'currency'];
        $rows = $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS payments,
                    COALESCE(SUM(amount), 0) AS amount,
                    MAX(currency) AS currency
             FROM billing_events
             WHERE type = 'payment'
             GROUP BY month
             ORDER BY month DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'month' => (string) ($r['month'] ?? ''),
                'payments' => (int) ($r['payments'] ?? 0),
                'amount' => (float) ($r['amount'] ?? 0),
                'currency' => (string) ($r['currency'] ?? 'EUR'),
            ], $rows),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function usageReport(): array
    {
        $columns = ['organization', 'slug', 'metric', 'value', 'period'];
        $period = date('Y-m-01');

        if (!$this->tableExists('usage_metrics') || !$this->tableExists('tenants')) {
            return ['columns' => $columns, 'rows' => []];
        }

        $rows = $this->db->prepare(
            'SELECT t.name AS organization, t.slug, um.metric, um.value, um.period
             FROM usage_metrics um
             INNER JOIN tenants t ON t.id = um.tenant_id AND t.deleted_at IS NULL
             WHERE um.period = ?
             ORDER BY um.metric ASC, t.name ASC'
        );
        $rows->execute([$period]);
        $data = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'organization' => (string) ($r['organization'] ?? ''),
                'slug' => (string) ($r['slug'] ?? ''),
                'metric' => (string) ($r['metric'] ?? ''),
                'value' => (int) ($r['value'] ?? 0),
                'period' => (string) ($r['period'] ?? ''),
            ], $data),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function licensesReport(): array
    {
        $columns = [
            'organization', 'slug', 'key_prefix', 'license_type', 'status',
            'plan_code', 'max_seats', 'expires_at', 'created_at',
        ];
        $rows = (new LicenseRepository($this->db))->listLicenses(5000, 0);

        return [
            'columns' => $columns,
            'rows' => array_map(static fn (array $r) => [
                'organization' => (string) ($r['tenant_name'] ?? ''),
                'slug' => (string) ($r['tenant_slug'] ?? ''),
                'key_prefix' => (string) ($r['key_prefix'] ?? ''),
                'license_type' => (string) ($r['license_type'] ?? ''),
                'status' => (string) ($r['status'] ?? ''),
                'plan_code' => (string) ($r['plan_code'] ?? ''),
                'max_seats' => (int) ($r['max_seats'] ?? 0),
                'expires_at' => (string) ($r['expires_at'] ?? ''),
                'created_at' => (string) ($r['created_at'] ?? ''),
            ], $rows),
        ];
    }

    /** @return array{columns: array<int, string>, rows: array<int, array<string, mixed>>} */
    private function auditReport(): array
    {
        $columns = ['id', 'action', 'platform_user', 'organization', 'ip_address', 'created_at'];
        $rows = $this->db->query(
            'SELECT pal.id, pal.action, pal.ip_address, pal.created_at,
                    pu.name AS platform_user_name, pu.email AS platform_user_email,
                    t.name AS tenant_name
             FROM platform_audit_log pal
             LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
             LEFT JOIN tenants t ON t.id = pal.tenant_id
             ORDER BY pal.id DESC
             LIMIT 5000'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'columns' => $columns,
            'rows' => array_map(static function (array $r) {
                $user = trim((string) ($r['platform_user_name'] ?? ''));
                if ($user === '' && !empty($r['platform_user_email'])) {
                    $user = (string) $r['platform_user_email'];
                }

                return [
                    'id' => (int) ($r['id'] ?? 0),
                    'action' => (string) ($r['action'] ?? ''),
                    'platform_user' => $user,
                    'organization' => (string) ($r['tenant_name'] ?? ''),
                    'ip_address' => (string) ($r['ip_address'] ?? ''),
                    'created_at' => (string) ($r['created_at'] ?? ''),
                ];
            }, $rows),
        ];
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

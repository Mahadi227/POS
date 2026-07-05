<?php
declare(strict_types=1);

final class PlatformAnalyticsRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, int|float|string> */
    public function stats(): array
    {
        $tenants = new TenantRepository($this->db);
        $byStatus = $tenants->countByStatus();
        $subs = (new SubscriptionRepository($this->db))->subscriptionStats();
        $billing = (new BillingRepository($this->db))->billingStats();

        $stores = 0;
        $users = 0;
        if ($this->columnExists('stores', 'tenant_id')) {
            $stores = (int) $this->db->query(
                'SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL'
            )->fetchColumn();
        }
        if ($this->columnExists('users', 'tenant_id')) {
            $users = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'
            )->fetchColumn();
        }

        return [
            'tenants' => (int) ($byStatus['total'] ?? 0),
            'tenants_active' => (int) ($byStatus['active'] ?? 0),
            'mrr' => (float) ($subs['mrr'] ?? 0),
            'subscriptions' => (int) ($subs['active'] ?? 0),
            'revenue' => (float) ($billing['collected'] ?? 0),
            'currency' => (string) ($billing['currency'] ?? 'EUR'),
            'stores' => $stores,
            'users' => $users,
            'api_calls' => $this->usageMetricTotal('api_calls'),
        ];
    }

    /** @return array<string, mixed> */
    public function overview(): array
    {
        $tenants = new TenantRepository($this->db);
        $subs = (new SubscriptionRepository($this->db))->subscriptionStats();

        return [
            'tenant_growth' => $this->tenantGrowthSeries(6),
            'revenue_trend' => $this->revenueTrendSeries(6),
            'plan_breakdown' => $this->planBreakdown(),
            'subscription_status' => [
                'active' => (int) ($subs['active'] ?? 0),
                'trial' => (int) ($subs['trial'] ?? 0),
                'past_due' => (int) ($subs['past_due'] ?? 0),
                'cancelled' => (int) ($subs['cancelled'] ?? 0),
            ],
            'top_tenants' => $this->topTenants(10),
            'usage_metrics' => $this->usageByMetric(),
        ];
    }

    /** @return array<int, array{month: string, count: int}> */
    private function tenantGrowthSeries(int $months): array
    {
        if (!$this->tableExists('tenants')) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month, COUNT(*) AS count
             FROM tenants
             WHERE deleted_at IS NULL
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)
             GROUP BY month
             ORDER BY month ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'month' => (string) ($r['month'] ?? ''),
            'count' => (int) ($r['count'] ?? 0),
        ], $rows);
    }

    /** @return array<int, array{month: string, amount: float}> */
    private function revenueTrendSeries(int $months): array
    {
        if (!$this->tableExists('billing_events')) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COALESCE(SUM(amount), 0) AS amount
             FROM billing_events
             WHERE type = 'payment'
               AND created_at >= DATE_SUB(CURDATE(), INTERVAL {$months} MONTH)
             GROUP BY month
             ORDER BY month ASC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'month' => (string) ($r['month'] ?? ''),
            'amount' => (float) ($r['amount'] ?? 0),
        ], $rows);
    }

    /** @return array<int, array{code: string, name: string, count: int}> */
    private function planBreakdown(): array
    {
        if (!$this->tableExists('tenant_subscriptions') || !$this->tableExists('subscription_plans')) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT sp.code, sp.name, COUNT(*) AS count
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id
             INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
             WHERE ts.status IN ('active', 'trial')
             GROUP BY sp.id, sp.code, sp.name
             ORDER BY count DESC"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'code' => (string) ($r['code'] ?? ''),
            'name' => (string) ($r['name'] ?? ''),
            'count' => (int) ($r['count'] ?? 0),
        ], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function topTenants(int $limit): array
    {
        $repo = new TenantRepository($this->db);
        $rows = $repo->listTenants($limit, 0);
        usort($rows, static function (array $a, array $b) {
            $scoreA = (int) ($a['store_count'] ?? 0) + (int) ($a['user_count'] ?? 0);
            $scoreB = (int) ($b['store_count'] ?? 0) + (int) ($b['user_count'] ?? 0);
            return $scoreB <=> $scoreA;
        });
        return array_slice($rows, 0, $limit);
    }

    /** @return array<int, array{metric: string, total: int}> */
    private function usageByMetric(): array
    {
        if (!$this->tableExists('usage_metrics')) {
            return [];
        }

        $period = date('Y-m-01');
        $rows = $this->db->prepare(
            'SELECT metric, SUM(value) AS total FROM usage_metrics WHERE period = ? GROUP BY metric ORDER BY total DESC'
        );
        $rows->execute([$period]);
        $data = $rows->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $r) => [
            'metric' => (string) ($r['metric'] ?? ''),
            'total' => (int) ($r['total'] ?? 0),
        ], $data);
    }

    private function usageMetricTotal(string $metric): int
    {
        if (!$this->tableExists('usage_metrics')) {
            return 0;
        }
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(value), 0) FROM usage_metrics WHERE metric = ? AND period = ?'
        );
        $stmt->execute([$metric, date('Y-m-01')]);
        return (int) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

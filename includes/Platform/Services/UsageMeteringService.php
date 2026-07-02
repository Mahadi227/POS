<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/EntitlementService.php';

final class UsageMeteringService
{
    /** @var array<string, string> metric → entitlement limit key */
    private const LIMIT_MAP = [
        'stores.count' => 'stores',
        'users.count' => 'users',
        'sales.count' => 'sales_per_month',
    ];

    private PDO $db;
    private UsageMeteringRepository $metrics;
    private EntitlementService $entitlements;

    public function __construct(PDO $db, UsageMeteringRepository $metrics, EntitlementService $entitlements)
    {
        $this->db = $db;
        $this->metrics = $metrics;
        $this->entitlements = $entitlements;
    }

    public function increment(int $tenantId, string $metric, int $amount = 1): void
    {
        if ($tenantId <= 0) {
            return;
        }
        $this->metrics->increment($tenantId, $metric, $amount);
        $this->evaluateAlerts($tenantId, $metric);
    }

    public function syncTenant(int $tenantId, ?string $period = null): void
    {
        $period = $period ?? date('Y-m-01');

        if ($this->hasColumn('stores', 'tenant_id')) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND deleted_at IS NULL');
            $stmt->execute([$tenantId]);
            $this->metrics->setValue($tenantId, 'stores.count', (int) $stmt->fetchColumn(), $period);
        }

        if ($this->hasColumn('users', 'tenant_id')) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL');
            $stmt->execute([$tenantId]);
            $this->metrics->setValue($tenantId, 'users.count', (int) $stmt->fetchColumn(), $period);
        }

        if ($this->hasColumn('sales', 'tenant_id')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM sales WHERE tenant_id = ? AND created_at >= ? AND created_at < DATE_ADD(?, INTERVAL 1 MONTH)"
            );
            $stmt->execute([$tenantId, $period, $period]);
            $this->metrics->setValue($tenantId, 'sales.count', (int) $stmt->fetchColumn(), $period);
        } elseif ($this->tableExists('sales') && $this->hasColumn('stores', 'tenant_id')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM sales s
                 INNER JOIN stores st ON st.id = s.store_id
                 WHERE st.tenant_id = ? AND s.created_at >= ? AND s.created_at < DATE_ADD(?, INTERVAL 1 MONTH)"
            );
            $stmt->execute([$tenantId, $period, $period]);
            $this->metrics->setValue($tenantId, 'sales.count', (int) $stmt->fetchColumn(), $period);
        }

        foreach (array_keys(self::LIMIT_MAP) as $metric) {
            $this->evaluateAlerts($tenantId, $metric, $period);
        }
    }

    public function syncAllTenants(?string $period = null): int
    {
        if (!$this->tableExists('tenants')) {
            return 0;
        }
        $ids = $this->db->query('SELECT id FROM tenants WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        foreach ($ids as $id) {
            $this->syncTenant((int) $id, $period);
        }
        return count($ids);
    }

    public function getReport(int $tenantId, ?string $period = null): array
    {
        $period = $period ?? date('Y-m-01');
        $raw = $this->metrics->getPeriodMetrics($tenantId, $period);
        $sub = $this->entitlements->getSubscriptionSummary($tenantId);
        $planModules = $sub['modules'] ?? [];

        $items = [];
        foreach (self::LIMIT_MAP as $metric => $limitKey) {
            $used = (int) ($raw[$metric] ?? 0);
            $limit = $this->resolveLimit($tenantId, $limitKey, $planModules, $sub['limits'] ?? []);
            $pct = ($limit !== null && $limit > 0) ? min(100, (int) round(($used / $limit) * 100)) : null;
            $items[] = [
                'metric' => $metric,
                'label' => $limitKey,
                'used' => $used,
                'limit' => $limit,
                'percent' => $pct,
                'alert_80' => $this->metrics->hasAlert($tenantId, $metric, 80, $period),
                'alert_100' => $this->metrics->hasAlert($tenantId, $metric, 100, $period),
            ];
        }

        $apiCalls = (int) ($raw['api.calls'] ?? 0);
        $items[] = [
            'metric' => 'api.calls',
            'label' => 'api_calls',
            'used' => $apiCalls,
            'limit' => null,
            'percent' => null,
            'alert_80' => false,
            'alert_100' => false,
        ];

        return [
            'period' => $period,
            'items' => $items,
            'raw' => $raw,
        ];
    }

    public function trackApiCall(int $tenantId): void
    {
        $this->increment($tenantId, 'api.calls');
    }

    private function evaluateAlerts(int $tenantId, string $metric, ?string $period = null): void
    {
        $period = $period ?? date('Y-m-01');
        $limitKey = self::LIMIT_MAP[$metric] ?? null;
        if ($limitKey === null) {
            return;
        }

        $raw = $this->metrics->getPeriodMetrics($tenantId, $period);
        $used = (int) ($raw[$metric] ?? 0);
        $sub = $this->entitlements->getSubscriptionSummary($tenantId);
        $limit = $this->resolveLimit($tenantId, $limitKey, $sub['modules'] ?? [], $sub['limits'] ?? []);
        if ($limit === null || $limit <= 0) {
            return;
        }

        $pct = (int) round(($used / $limit) * 100);
        if ($pct >= 80 && !$this->metrics->hasAlert($tenantId, $metric, 80, $period)) {
            $this->metrics->recordAlert($tenantId, $metric, 80, $period);
        }
        if ($pct >= 100 && !$this->metrics->hasAlert($tenantId, $metric, 100, $period)) {
            $this->metrics->recordAlert($tenantId, $metric, 100, $period);
        }
    }

    /** @param array<string, mixed> $planModules @param array<string, int|null> $limits */
    private function resolveLimit(int $tenantId, string $limitKey, array $planModules, array $limits): ?int
    {
        if ($limitKey === 'sales_per_month') {
            $v = $planModules['max_sales_per_month'] ?? null;
            return $v !== null ? (int) $v : null;
        }
        return isset($limits[$limitKey]) ? (int) $limits[$limitKey] : null;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

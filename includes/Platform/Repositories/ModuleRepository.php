<?php
declare(strict_types=1);

final class ModuleRepository
{
    /** @var array<string, array{workspace: string, icon: string}> */
    public const REGISTRY = [
        'pos' => ['workspace' => 'cashier', 'icon' => 'point_of_sale'],
        'inventory' => ['workspace' => 'admin', 'icon' => 'inventory_2'],
        'cash_registers' => ['workspace' => 'cash_registers', 'icon' => 'payments'],
        'manager' => ['workspace' => 'manager', 'icon' => 'supervisor_account'],
        'warehouse' => ['workspace' => 'warehouse', 'icon' => 'warehouse'],
        'accounting' => ['workspace' => 'accounting', 'icon' => 'account_balance'],
        'api_access' => ['workspace' => 'api', 'icon' => 'api'],
        'white_label' => ['workspace' => 'branding', 'icon' => 'palette'],
        'ecommerce' => ['workspace' => 'ecommerce', 'icon' => 'storefront'],
    ];

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array{modules: array<int, array<string, mixed>>, plans: array<int, array<string, mixed>>} */
    public function catalog(): array
    {
        $plans = $this->loadPlans();
        $overrideStats = $this->overrideStatsByModule();
        $planTenantCounts = $this->tenantCountByPlan();

        $modules = [];
        foreach (self::REGISTRY as $key => $meta) {
            $planInclusion = [];
            $tenantsOnPlan = 0;

            foreach ($plans as $plan) {
                $included = !empty($plan['modules'][$key]);
                $planInclusion[] = [
                    'code' => $plan['code'],
                    'name' => $plan['name'],
                    'included' => $included,
                ];
                if ($included) {
                    $tenantsOnPlan += (int) ($planTenantCounts[$plan['code']] ?? 0);
                }
            }

            $ov = $overrideStats[$key] ?? ['on' => 0, 'off' => 0];
            $modules[] = [
                'key' => $key,
                'workspace' => $meta['workspace'],
                'icon' => $meta['icon'],
                'plans' => $planInclusion,
                'tenants_on_plan' => $tenantsOnPlan,
                'overrides_on' => (int) $ov['on'],
                'overrides_off' => (int) $ov['off'],
            ];
        }

        return [
            'modules' => $modules,
            'plans' => array_map(static fn (array $p) => [
                'code' => $p['code'],
                'name' => $p['name'],
            ], $plans),
        ];
    }

    /** @return array<string, int> */
    public function moduleStats(): array
    {
        $stats = [
            'modules' => count(self::REGISTRY),
            'plans' => 0,
            'overrides' => 0,
            'tenants' => 0,
        ];

        if ($this->tableExists('subscription_plans')) {
            $stats['plans'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM subscription_plans WHERE is_active = 1'
            )->fetchColumn();
        }

        if ($this->tableExists('tenant_module_overrides')) {
            $stats['overrides'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM tenant_module_overrides'
            )->fetchColumn();
        }

        if ($this->tableExists('tenants')) {
            $stats['tenants'] = (int) $this->db->query(
                'SELECT COUNT(*) FROM tenants WHERE deleted_at IS NULL'
            )->fetchColumn();
        }

        return $stats;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadPlans(): array
    {
        if (!$this->tableExists('subscription_plans')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT code, name, modules_json FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $mods = json_decode((string) ($row['modules_json'] ?? '{}'), true);
            $row['modules'] = is_array($mods) ? $mods : [];
            unset($row['modules_json']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, int> */
    private function tenantCountByPlan(): array
    {
        if (!$this->tableExists('tenant_subscriptions') || !$this->tableExists('subscription_plans')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT sp.code AS plan_code, COUNT(DISTINCT ts.tenant_id) AS cnt
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id
             INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
             WHERE ts.status NOT IN (\'cancelled\')
             GROUP BY sp.code'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['plan_code']] = (int) ($row['cnt'] ?? 0);
        }
        return $out;
    }

    /** @return array<string, array{on: int, off: int}> */
    private function overrideStatsByModule(): array
    {
        if (!$this->tableExists('tenant_module_overrides')) {
            return [];
        }

        $rows = $this->db->query(
            'SELECT module_key,
                    SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) AS overrides_on,
                    SUM(CASE WHEN enabled = 0 THEN 1 ELSE 0 END) AS overrides_off
             FROM tenant_module_overrides
             GROUP BY module_key'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = [];
        foreach ($rows as $row) {
            $out[(string) $row['module_key']] = [
                'on' => (int) ($row['overrides_on'] ?? 0),
                'off' => (int) ($row['overrides_off'] ?? 0),
            ];
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

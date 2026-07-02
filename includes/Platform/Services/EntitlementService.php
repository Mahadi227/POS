<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';

final class EntitlementService
{
    /** @var array<string, string> workspace → module key */
    private const WORKSPACE_MODULES = [
        'warehouse' => 'warehouse',
        'accounting' => 'accounting',
        'cash_registers' => 'cash_registers',
        'manager' => 'manager',
        'cashier' => 'pos',
    ];

    private PDO $db;
    private SubscriptionRepository $subscriptions;

    public function __construct(PDO $db, SubscriptionRepository $subscriptions)
    {
        $this->db = $db;
        $this->subscriptions = $subscriptions;
    }

    public function hasModule(int $tenantId, string $module): bool
    {
        $override = $this->getOverride($tenantId, $module);
        if ($override !== null) {
            return $override;
        }

        $sub = $this->subscriptions->getActiveSubscription($tenantId);
        if (!$sub) {
            return $tenantId === 1;
        }

        if (in_array($sub['status'] ?? '', ['cancelled'], true)) {
            return false;
        }

        $modules = $this->decodeModules($sub['modules_json'] ?? '{}');
        return !empty($modules[$module]);
    }

    public function assertModule(int $tenantId, string $module): void
    {
        if (!$this->hasModule($tenantId, $module)) {
            throw new RuntimeException('Module not available on current plan: ' . $module);
        }
    }

    public function assertWorkspace(int $tenantId, string $workspace): void
    {
        $module = self::WORKSPACE_MODULES[$workspace] ?? null;
        if ($module === null) {
            return;
        }
        $this->assertModule($tenantId, $module);
    }

    public function getLimit(int $tenantId, string $metric): ?int
    {
        $sub = $this->subscriptions->getActiveSubscription($tenantId);
        if (!$sub) {
            return null;
        }
        return match ($metric) {
            'stores' => isset($sub['max_stores']) ? (int) $sub['max_stores'] : null,
            'users' => isset($sub['max_users']) ? (int) $sub['max_users'] : null,
            default => null,
        };
    }

    public function getUsage(int $tenantId, string $metric): int
    {
        return match ($metric) {
            'stores' => $this->countStores($tenantId),
            'users' => $this->countUsers($tenantId),
            default => 0,
        };
    }

    public function isWithinLimit(int $tenantId, string $metric): bool
    {
        $limit = $this->getLimit($tenantId, $metric);
        if ($limit === null) {
            return true;
        }
        return $this->getUsage($tenantId, $metric) < $limit;
    }

    public function getSubscriptionSummary(int $tenantId): array
    {
        $sub = $this->subscriptions->getActiveSubscription($tenantId);
        $tenant = $this->getTenantRow($tenantId);
        $modules = $sub ? $this->decodeModules($sub['modules_json'] ?? '{}') : [];

        return [
            'tenant_id' => $tenantId,
            'tenant_status' => $tenant['status'] ?? 'active',
            'trial_ends_at' => $tenant['trial_ends_at'] ?? null,
            'plan_code' => $sub['plan_code'] ?? 'legacy',
            'plan_name' => $sub['plan_name'] ?? 'Legacy',
            'subscription_status' => $sub['status'] ?? 'active',
            'price_monthly' => (float) ($sub['price_monthly'] ?? 0),
            'currency' => $sub['currency'] ?? 'EUR',
            'modules' => $modules,
            'usage' => [
                'stores' => $this->getUsage($tenantId, 'stores'),
                'users' => $this->getUsage($tenantId, 'users'),
            ],
            'limits' => [
                'stores' => $this->getLimit($tenantId, 'stores'),
                'users' => $this->getLimit($tenantId, 'users'),
            ],
        ];
    }

    /** @return array<string, bool> */
    public function modulesForTenant(int $tenantId): array
    {
        $keys = ['pos', 'inventory', 'cash_registers', 'manager', 'warehouse', 'accounting', 'api_access', 'white_label'];
        $out = [];
        foreach ($keys as $key) {
            $out[$key] = $this->hasModule($tenantId, $key);
        }
        return $out;
    }

    private function getOverride(int $tenantId, string $module): ?bool
    {
        if (!$this->tableExists('tenant_module_overrides')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT enabled FROM tenant_module_overrides WHERE tenant_id = ? AND module_key = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $module]);
        $val = $stmt->fetchColumn();
        if ($val === false) {
            return null;
        }
        return (bool) $val;
    }

    private function countStores(int $tenantId): int
    {
        if (!$this->hasColumn('stores', 'tenant_id')) {
            return (int) $this->db->query('SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL')->fetchColumn();
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM stores WHERE tenant_id = ? AND deleted_at IS NULL');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private function countUsers(int $tenantId): int
    {
        if (!$this->hasColumn('users', 'tenant_id')) {
            return (int) $this->db->query('SELECT COUNT(*) FROM users WHERE deleted_at IS NULL')->fetchColumn();
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM users WHERE tenant_id = ? AND deleted_at IS NULL');
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    private function getTenantRow(int $tenantId): array
    {
        $stmt = $this->db->prepare(
            'SELECT id, status, trial_ends_at, slug, name FROM tenants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, bool> */
    private function decodeModules(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }
        $out = [];
        foreach ($decoded as $k => $v) {
            $out[$k] = (bool) $v;
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

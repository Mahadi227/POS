<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/TenantRepository.php';
require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/EntitlementService.php';
require_once __DIR__ . '/UsageMeteringService.php';
require_once __DIR__ . '/../Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../SaaSPhase2Migrator.php';
require_once __DIR__ . '/../SaaSPhase3Migrator.php';
require_once __DIR__ . '/../SaaSPhase4Migrator.php';
require_once __DIR__ . '/../SaaSPhase6Migrator.php';
require_once __DIR__ . '/WebhookDispatcherService.php';

final class PlatformTenantService
{
    private PDO $db;
    private TenantRepository $tenants;
    private SubscriptionRepository $subscriptions;
    private PlatformAuditRepository $audit;
    private EntitlementService $entitlements;
    private UsageMeteringService $metering;

    public function __construct(
        PDO $db,
        TenantRepository $tenants,
        SubscriptionRepository $subscriptions,
        PlatformAuditRepository $audit,
        EntitlementService $entitlements,
        UsageMeteringService $metering,
    ) {
        $this->db = $db;
        $this->tenants = $tenants;
        $this->subscriptions = $subscriptions;
        $this->audit = $audit;
        $this->entitlements = $entitlements;
        $this->metering = $metering;
    }

    public function getDetail(int $tenantId): ?array
    {
        $tenant = $this->tenants->findDetailById($tenantId);
        if (!$tenant) {
            return null;
        }

        $subscription = $this->entitlements->getSubscriptionSummary($tenantId);
        $modules = $this->entitlements->modulesForTenant($tenantId);
        $overrides = $this->tenants->getModuleOverrides($tenantId);
        $billingEvents = $this->tenants->listBillingEvents($tenantId, 15);
        $auditLog = $this->audit->listForTenant($tenantId, 20);
        $featureFlags = $this->tenants->getFeatureFlags($tenantId);
        $stores = $this->tenants->listStores($tenantId, 10);
        $this->metering->syncTenant($tenantId);
        $usageReport = $this->metering->getReport($tenantId);

        return [
            'tenant' => $tenant,
            'subscription' => $subscription,
            'modules' => $modules,
            'module_overrides' => $overrides,
            'billing_events' => $billingEvents,
            'audit_log' => $auditLog,
            'feature_flags' => $featureFlags,
            'stores' => $stores,
            'usage' => $usageReport,
        ];
    }

    public function updateStatus(int $tenantId, string $status, int $platformUserId, ?string $ip = null): void
    {
        $allowed = ['trial', 'active', 'suspended', 'cancelled'];
        if (!in_array($status, $allowed, true)) {
            throw new InvalidArgumentException('Invalid status');
        }

        $tenant = $this->tenants->findById($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found');
        }

        $oldStatus = (string) ($tenant['status'] ?? '');

        $this->db->prepare('UPDATE tenants SET status = ?, updated_at = NOW() WHERE id = ?')
            ->execute([$status, $tenantId]);

        if ($status === 'suspended') {
            $this->subscriptions->updateSubscriptionStatus($tenantId, 'cancelled');
        } elseif (in_array($status, ['active', 'trial'], true)) {
            $this->subscriptions->updateSubscriptionStatus($tenantId, $status === 'trial' ? 'trial' : 'active');
        }

        $this->audit->log('tenant.status_change', $platformUserId, $tenantId, [
            'from' => $oldStatus,
            'to' => $status,
        ], $ip);

        if ($status === 'suspended' && $oldStatus !== 'suspended') {
            WebhookDispatcherService::dispatch($this->db, $tenantId, 'tenant.suspended', [
                'tenant_id' => $tenantId,
                'previous_status' => $oldStatus,
            ]);
        } elseif (in_array($status, ['active', 'trial'], true) && $oldStatus === 'suspended') {
            WebhookDispatcherService::dispatch($this->db, $tenantId, 'tenant.restored', [
                'tenant_id' => $tenantId,
                'status' => $status,
            ]);
        }
    }

    public function extendTrial(int $tenantId, int $days, int $platformUserId, ?string $ip = null): string
    {
        if ($days < 1 || $days > 90) {
            throw new InvalidArgumentException('Days must be between 1 and 90');
        }

        $tenant = $this->tenants->findDetailById($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found');
        }

        $base = $tenant['trial_ends_at'] ?? null;
        $from = ($base && strtotime($base) > time()) ? $base : date('Y-m-d H:i:s');
        $newEnd = date('Y-m-d H:i:s', strtotime($from . ' +' . $days . ' days'));

        $this->db->prepare(
            "UPDATE tenants SET trial_ends_at = ?, status = 'trial', updated_at = NOW() WHERE id = ?"
        )->execute([$newEnd, $tenantId]);

        $this->subscriptions->updateSubscriptionStatus($tenantId, 'trial');

        $this->audit->log('tenant.trial_extended', $platformUserId, $tenantId, [
            'days' => $days,
            'trial_ends_at' => $newEnd,
        ], $ip);

        return $newEnd;
    }

    public function changePlan(int $tenantId, string $planCode, int $platformUserId, ?string $ip = null): void
    {
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found');
        }

        $tenant = $this->tenants->findById($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found');
        }

        $this->subscriptions->changePlan($tenantId, (int) $plan['id']);

        if ($this->hasColumn('tenants', 'plan_id')) {
            $this->db->prepare('UPDATE tenants SET plan_id = ?, updated_at = NOW() WHERE id = ?')
                ->execute([(int) $plan['id'], $tenantId]);
        }

        $this->audit->log('tenant.plan_change', $platformUserId, $tenantId, [
            'plan_code' => $planCode,
            'plan_name' => $plan['name'] ?? $planCode,
        ], $ip);
    }

    /** @param array<string, bool|null> $overrides null = remove override */
    public function setModuleOverrides(
        int $tenantId,
        array $overrides,
        int $platformUserId,
        ?string $ip = null,
    ): void {
        if (!$this->tableExists('tenant_module_overrides')) {
            throw new RuntimeException('Module overrides not available');
        }

        $tenant = $this->tenants->findById($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found');
        }

        $upsert = $this->db->prepare(
            'INSERT INTO tenant_module_overrides (tenant_id, module_key, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        );
        $delete = $this->db->prepare(
            'DELETE FROM tenant_module_overrides WHERE tenant_id = ? AND module_key = ?'
        );

        foreach ($overrides as $module => $enabled) {
            if ($enabled === null) {
                $delete->execute([$tenantId, $module]);
            } else {
                $upsert->execute([$tenantId, $module, $enabled ? 1 : 0]);
            }
        }

        $this->audit->log('tenant.module_overrides', $platformUserId, $tenantId, [
            'overrides' => $overrides,
        ], $ip);
    }

    /** @param array<string, bool> $flags */
    public function setFeatureFlags(
        int $tenantId,
        array $flags,
        int $platformUserId,
        ?string $ip = null,
    ): void {
        if (!$this->tableExists('tenant_feature_flags')) {
            throw new RuntimeException('Feature flags not available');
        }

        $stmt = $this->db->prepare(
            'INSERT INTO tenant_feature_flags (tenant_id, key_name, enabled)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE enabled = VALUES(enabled)'
        );
        foreach ($flags as $key => $enabled) {
            $stmt->execute([$tenantId, $key, $enabled ? 1 : 0]);
        }

        $this->audit->log('tenant.feature_flags', $platformUserId, $tenantId, [
            'flags' => $flags,
        ], $ip);
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

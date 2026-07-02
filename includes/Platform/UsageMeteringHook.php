<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/UsageMeteringService.php';

final class UsageMeteringHook
{
    public static function trackApiRequest(?string $resource): void
    {
        if ($resource === null || in_array($resource, ['auth', 'platform', 'tenant-signup', 'billing', 'branding'], true)) {
            return;
        }
        if (empty($_SESSION['tenant_id']) && empty($_SESSION['user_id'])) {
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            if (!TenantSchemaMigrator::isReady($db)) {
                return;
            }
            SaaSPhase2Migrator::ensure($db);
            SaaSPhase4Migrator::ensure($db);
            TenantScope::loadFromSession($db);
            $tenantId = TenantScope::id();
            if ($tenantId <= 0) {
                return;
            }
            $subs = new SubscriptionRepository($db);
            $svc = new UsageMeteringService($db, new UsageMeteringRepository($db), new EntitlementService($db, $subs));
            $svc->trackApiCall($tenantId);
        } catch (Throwable) {
            // non-blocking
        }
    }

    public static function trackSale(int $tenantId): void
    {
        if ($tenantId <= 0) {
            return;
        }
        try {
            $db = Database::getInstance()->getConnection();
            SaaSPhase2Migrator::ensure($db);
            SaaSPhase4Migrator::ensure($db);
            $subs = new SubscriptionRepository($db);
            $svc = new UsageMeteringService($db, new UsageMeteringRepository($db), new EntitlementService($db, $subs));
            $svc->increment($tenantId, 'sales.count');
        } catch (Throwable) {
        }
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/TenantResolver.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/TenantDomainRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/TenantBrandingService.php';

/**
 * Early tenant resolution + branding for public/tenant pages.
 */
final class TenantBootstrap
{
    public static function resolveTenant(?PDO $db = null, bool $persistCookie = true): ?array
    {
        $db = $db ?? Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($db);
        SaaSPhase2Migrator::ensure($db);
        SaaSPhase4Migrator::ensure($db);

        $tenant = TenantResolver::resolve($db, $persistCookie);
        if ($tenant) {
            TenantScope::set((int) $tenant['id'], $tenant);
        }
        return $tenant;
    }

    /** @return array<string, mixed> */
    public static function branding(?PDO $db = null): array
    {
        $db = $db ?? Database::getInstance()->getConnection();
        TenantScope::loadFromSession($db);
        $tenantId = TenantScope::id();
        $subs = new SubscriptionRepository($db);
        $svc = new TenantBrandingService(
            $db,
            new EntitlementService($db, $subs),
            new TenantDomainRepository($db),
        );
        return $svc->getBranding($tenantId);
    }
}

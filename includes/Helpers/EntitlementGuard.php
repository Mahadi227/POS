<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';

/**
 * Enforce SaaS plan entitlements on workspace entry.
 */
final class EntitlementGuard
{
    public static function requireModule(string $module, ?string $billingPath = '../billing.php'): void
    {
        $tenantId = TenantScope::id();
        if ($tenantId <= 0) {
            return;
        }

        try {
            $db = Database::getInstance()->getConnection();
            TenantSchemaMigrator::ensure($db);
            SaaSPhase2Migrator::ensure($db);

            $entitlements = new EntitlementService($db, new SubscriptionRepository($db));
            if ($entitlements->hasModule($tenantId, $module)) {
                return;
            }
        } catch (Throwable) {
            return;
        }

        if (self::wantsJson()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'error',
                'code' => 'module_not_subscribed',
                'message' => 'This module is not included in your current plan.',
                'upgrade_url' => $billingPath,
            ]);
            exit;
        }

        header('Location: ' . ($billingPath ?? '../billing.php') . '?upgrade=' . urlencode($module));
        exit;
    }

    public static function requireWorkspace(string $workspace, ?string $billingPath = '../billing.php'): void
    {
        $map = [
            'warehouse' => 'warehouse',
            'accounting' => 'accounting',
            'cash_registers' => 'cash_registers',
            'manager' => 'manager',
        ];
        $module = $map[$workspace] ?? null;
        if ($module) {
            self::requireModule($module, $billingPath);
        }
    }

    /** @return array<string, bool> */
    public static function modulesForCurrentTenant(): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            SaaSPhase2Migrator::ensure($db);
            $svc = new EntitlementService($db, new SubscriptionRepository($db));
            return $svc->modulesForTenant(TenantScope::id());
        } catch (Throwable) {
            return [];
        }
    }

    private static function wantsJson(): bool
    {
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        return str_contains($accept, 'application/json')
            || str_contains($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '', 'XMLHttpRequest');
    }
}

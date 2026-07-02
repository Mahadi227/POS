<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase3Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/TenantRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/../includes/Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../includes/Platform/Services/PlatformTenantService.php';

$db = Database::getInstance()->getConnection();
TenantSchemaMigrator::ensure($db);
SaaSPhase2Migrator::ensure($db);
SaaSPhase3Migrator::ensure($db);

$subs = new SubscriptionRepository($db);
$svc = new PlatformTenantService(
    $db,
    new TenantRepository($db),
    $subs,
    new PlatformAuditRepository($db),
    new EntitlementService($db, $subs),
);

$detail = $svc->getDetail(2);
if (!$detail) {
    echo "Tenant 2 not found\n";
    exit(1);
}

echo 'Tenant: ' . ($detail['tenant']['slug'] ?? '?') . "\n";
echo 'Feature flags: ' . count($detail['feature_flags']) . "\n";
echo 'Modules: ' . json_encode($detail['modules']) . "\n";

$svc->extendTrial(2, 7, 1, '127.0.0.1');
echo "Trial extended OK\n";

<?php
declare(strict_types=1);

/**
 * Nightly usage aggregation worker.
 * Usage: php tools/usage-meter.php
 */
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../includes/Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../includes/Platform/Services/UsageMeteringService.php';

$db = Database::getInstance()->getConnection();
TenantSchemaMigrator::ensure($db);
SaaSPhase2Migrator::ensure($db);
SaaSPhase4Migrator::ensure($db);

$subs = new SubscriptionRepository($db);
$svc = new UsageMeteringService($db, new UsageMeteringRepository($db), new EntitlementService($db, $subs));

$period = $argv[1] ?? date('Y-m-01');
$count = $svc->syncAllTenants($period);

echo "Usage meter synced {$count} tenant(s) for period {$period}\n";

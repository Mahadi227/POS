<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../includes/Platform/Services/TenantProvisioningService.php';
require_once __DIR__ . '/../includes/Platform/Services/EntitlementService.php';

$db = Database::getInstance()->getConnection();
TenantSchemaMigrator::ensure($db);
SaaSPhase2Migrator::ensure($db);

$slug = 'demo-shop-' . time();
$svc = new TenantProvisioningService($db, new SubscriptionRepository($db));
$result = $svc->provision([
    'org_name' => 'Demo Shop Test',
    'slug' => $slug,
    'admin_name' => 'Demo Admin',
    'admin_email' => $slug . '@retailpos.local',
    'password' => 'password123',
    'plan_code' => 'starter',
]);

$ent = new EntitlementService($db, new SubscriptionRepository($db));
$mods = $ent->modulesForTenant($result['tenant_id']);

echo "Provisioned tenant {$result['tenant_id']} slug={$result['slug']}\n";
echo 'Modules: ' . json_encode($mods) . "\n";

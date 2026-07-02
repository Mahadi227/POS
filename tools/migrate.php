<?php
declare(strict_types=1);

/**
 * CLI migration runner — applies runtime schema migrators.
 * Usage: php tools/migrate.php
 */
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Auth/RbacSchemaMigrator.php';
require_once __DIR__ . '/../includes/Database/StoreSchemaMigrator.php';

echo "RetailPOS migrate\n";
echo str_repeat('-', 40) . "\n";

$db = Database::getInstance()->getConnection();

StoreSchemaMigrator::ensure($db);
echo "[ok] StoreSchemaMigrator\n";

RbacSchemaMigrator::ensure($db);
echo "[ok] RbacSchemaMigrator\n";

TenantSchemaMigrator::ensure($db);
echo "[ok] TenantSchemaMigrator (" . TenantSchemaMigrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase2Migrator.php';
SaaSPhase2Migrator::ensure($db);
echo "[ok] SaaSPhase2Migrator (" . SaaSPhase2Migrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase3Migrator.php';
SaaSPhase3Migrator::ensure($db);
echo "[ok] SaaSPhase3Migrator (" . SaaSPhase3Migrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase4Migrator.php';
SaaSPhase4Migrator::ensure($db);
echo "[ok] SaaSPhase4Migrator (" . SaaSPhase4Migrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase5Migrator.php';
SaaSPhase5Migrator::ensure($db);
echo "[ok] SaaSPhase5Migrator (" . SaaSPhase5Migrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase6Migrator.php';
SaaSPhase6Migrator::ensure($db);
echo "[ok] SaaSPhase6Migrator (" . SaaSPhase6Migrator::VERSION . ")\n";

require_once __DIR__ . '/../includes/Platform/SaaSPhase7Migrator.php';
SaaSPhase7Migrator::ensure($db);
echo "[ok] SaaSPhase7Migrator (" . SaaSPhase7Migrator::VERSION . ")\n";

if (TenantSchemaMigrator::isReady($db)) {
    $tenants = (int) $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
    $platformUsers = (int) $db->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
    echo "\nTenants: {$tenants}\n";
    echo "Platform users: {$platformUsers}\n";
    echo "\nPlatform login: public/platform/login.php\n";
    echo "  Email: platform@retailpos.local\n";
    echo "  Password: PlatformAdmin2026!\n";
}

echo "\nDone.\n";

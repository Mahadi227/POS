<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../../../includes/Platform/SaaSPhase3Migrator.php';
require_once __DIR__ . '/../../../includes/Platform/PlatformGuard.php';
require_once __DIR__ . '/../../../includes/Platform/PlatformSessionAuth.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';

$db = Database::getInstance()->getConnection();
TenantSchemaMigrator::ensure($db);
SaaSPhase3Migrator::ensure($db);

LanguageMiddleware::bootstrap();

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$assetsBase = '../../assets';
$apiBase = '../../api/v1/index.php';
$apiV2Base = '../../api/v2/index.php';
$changeUrl = '../change_language.php';
$initial = strtoupper(substr($_SESSION['platform_name'] ?? 'P', 0, 1));

function plat_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'platform');
    }
    return $out;
}

$platCommonI18nKeys = [
    'plat_title', 'plat_nav_dashboard', 'plat_nav_tenants', 'plat_nav_status', 'plat_logout',
    'loading', 'refresh', 'logout', 'menu', 'theme', 'last_updated', 'load_error',
];

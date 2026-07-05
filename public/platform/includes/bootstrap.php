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

function plat_path_depth(): int
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!preg_match('#/platform/(.+)$#', $script, $m)) {
        return 0;
    }
    $sub = trim(dirname($m[1]), '.');
    if ($sub === '' || $sub === '.') {
        return 0;
    }
    return substr_count($sub, '/') + 1;
}

$platDepth = plat_path_depth();
$platRootUp = str_repeat('../', $platDepth + 2);
$assetsBase = $platRootUp . 'assets';
$apiBase = $platRootUp . 'api/v1/index.php';
$apiV2Base = $platRootUp . 'api/v2/index.php';
$changeUrl = str_repeat('../', $platDepth + 1) . 'change_language.php';
$initial = strtoupper(substr($_SESSION['platform_name'] ?? 'P', 0, 1));
$themePortal = 'platform';
$themeAccent = '#7c3aed';

require_once __DIR__ . '/navigation.php';
require_once __DIR__ . '/module-page.php';

function plat_web_root(): string
{
    static $root = null;
    if ($root !== null) {
        return $root;
    }
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/public/platform/index.php');
    if (preg_match('#^(.*)/platform(?:/.*)?$#', $script, $m)) {
        $root = $m[1] . '/platform';
    } else {
        $root = '/platform';
    }
    return $root;
}

function plat_href(string $path): string
{
    $path = str_replace('\\', '/', ltrim($path, '/'));
    $segments = explode('/', $path);
    $encoded = implode('/', array_map('rawurlencode', $segments));
    return plat_web_root() . '/' . $encoded;
}

function plat_public_href(string $path): string
{
    $depth = (int) plat_layout('platDepth', 0);
    $path = ltrim(str_replace('\\', '/', $path), '/');
    return str_repeat('../', $depth + 1) . $path;
}

function plat_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'platform');
    }
    return $out;
}

/** @return list<string> */
function plat_common_i18n_keys(): array
{
    return [
        'plat_title', 'plat_nav_dashboard', 'plat_nav_companies', 'plat_nav_monitoring', 'plat_logout',
        'loading', 'refresh', 'logout', 'menu', 'theme', 'last_updated', 'load_error',
        'plat_module_coming_soon',
    ];
}

/** @param array<string, mixed> $context */
function plat_init_layout_context(array $context): void
{
    $GLOBALS['plat_layout'] = $context;
}

/** @return mixed */
function plat_layout(string $key, mixed $default = null): mixed
{
    return $GLOBALS['plat_layout'][$key] ?? $default;
}

plat_init_layout_context([
    'activeLang' => $activeLang,
    'assetsBase' => $assetsBase,
    'apiBase' => $apiBase,
    'apiV2Base' => $apiV2Base,
    'changeUrl' => $changeUrl,
    'initial' => $initial,
    'themeAccent' => $themeAccent,
    'themePortal' => $themePortal,
    'platDepth' => $platDepth,
]);

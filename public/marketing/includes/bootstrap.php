<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../../../includes/Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../../../includes/Platform/Services/MarketingPricingService.php';

LanguageMiddleware::bootstrap();

try {
    $mktDb = Database::getInstance()->getConnection();
    TenantSchemaMigrator::ensure($mktDb);
    SaaSPhase2Migrator::ensure($mktDb);
    MarketingPricingService::bootstrap($mktDb);
} catch (Throwable) {
    MarketingPricingService::plans();
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');

function mkt_path_depth(): int
{
    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    if (!preg_match('#/marketing/(.+)$#', $script, $m)) {
        return 0;
    }
    $sub = trim(dirname($m[1]), '.');
    if ($sub === '' || $sub === '.') {
        return 0;
    }
    return substr_count($sub, '/') + 1;
}

$mktDepth = mkt_path_depth();
$mktRootUp = str_repeat('../', $mktDepth);
$assetsBase = str_repeat('../', $mktDepth + 2) . 'assets';
$publicRoot = str_repeat('../', $mktDepth + 1);
$changeUrl = str_repeat('../', $mktDepth + 1) . 'change_language.php';

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/seo.php';

function mkt_href(string $path): string
{
    return $path;
}

function mkt_url(string $path = ''): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/marketing'), '/\\');
    if (str_ends_with($base, '/marketing')) {
        $base = preg_replace('#/marketing.*$#', '/marketing', $base) ?: '/marketing';
    }
    return $scheme . '://' . $host . $base . ($path ? '/' . ltrim($path, '/') : '');
}

function mkt_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'marketing');
    }
    return $out;
}

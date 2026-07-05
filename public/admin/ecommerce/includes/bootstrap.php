<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../../includes/Config/session.php';
require_once __DIR__ . '/../../../../includes/Config/config.php';
require_once __DIR__ . '/../../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../../includes/Helpers/StoreScope.php';
require_once __DIR__ . '/../../../../includes/Helpers/EntitlementGuard.php';
require_once __DIR__ . '/../../../../includes/Helpers/OnboardingGuard.php';
require_once __DIR__ . '/../../../../includes/Platform/TenantScope.php';
require_once __DIR__ . '/../../../../includes/Platform/SaaSPhase15Migrator.php';
require_once __DIR__ . '/../../../../includes/Platform/SaaSPhase16Migrator.php';
require_once __DIR__ . '/../../../../includes/Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../../../../includes/Platform/TenantResolver.php';
require_once __DIR__ . '/../../../../includes/Ecommerce/Repositories/EcommerceAdminRepository.php';
require_once __DIR__ . '/../../../../includes/Ecommerce/Repositories/EcommerceCatalogRepository.php';
require_once __DIR__ . '/../../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../../languages/helpers.php';

$ecomDepth = 0;
$ecomScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (preg_match('#/admin/ecommerce/(.+)$#', $ecomScript, $ecomMatch)) {
    $ecomDepth = max(0, substr_count($ecomMatch[1], '/'));
}
$ecomRootPrefix = $ecomDepth > 0 ? str_repeat('../', $ecomDepth) : '';
$ecomAdminPrefix = str_repeat('../', 1 + $ecomDepth);
$ecomPublicPrefix = str_repeat('../', 2 + $ecomDepth);
$assetsBase = str_repeat('../', 3 + $ecomDepth) . 'assets';
$apiBase = str_repeat('../', 3 + $ecomDepth) . 'api/v1/index.php';
$changeUrl = $ecomPublicPrefix . 'change_language.php';
$ecomLogoutUrl = $ecomPublicPrefix . 'logout.php';
$ecomAdminUrl = $ecomAdminPrefix . 'index.php';
$ecomPosUrl = $ecomPublicPrefix . 'cashier/pos.php';

RbacGuard::workspace('ecommerce', $ecomPublicPrefix . 'login.php');
EntitlementGuard::requireModule('ecommerce', $ecomPublicPrefix . 'billing.php');
OnboardingGuard::enforceForAdmin();
LanguageMiddleware::bootstrap();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$canManageEcom = in_array($roleSlug, ['super_admin', 'admin'], true);
$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$initial = strtoupper(substr($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'E', 0, 1));
$tenantId = TenantScope::id();
$tenantSlug = (string) ($_SESSION['tenant_slug'] ?? '');

$db = Database::getInstance()->getConnection();
SaaSPhase15Migrator::ensure($db);
SaaSPhase16Migrator::ensure($db);

$ecomRepo = new EcommerceAdminRepository($db);
$catalog = new EcommerceCatalogRepository($db);
$ecomSettings = $ecomRepo->getSettings($tenantId);
$storeId = (int) ($ecomSettings['default_store_id'] ?? 0);
if ($storeId <= 0) {
    $storeId = StoreScope::resolveStoreId($db);
}
if ($storeId <= 0) {
    $storeId = $catalog->defaultStoreId($tenantId);
}

require __DIR__ . '/../../includes/admin-branding.php';
$ecomAccent = $adminAccent;
$ecomBrandName = $adminBrandName;
$ecomLogoUrl = $adminLogoUrl;

$storeName = '';
$storeCurrency = (string) ($ecomSettings['currency'] ?? 'EUR');
try {
    if ($storeId > 0) {
        $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$storeId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $storeName = (string) ($row['name'] ?? '');
            if (!empty($row['currency'])) {
                $storeCurrency = (string) $row['currency'];
            }
        }
    }
} catch (Throwable) {
}

$ecomCanSwitchStore = StoreScope::isSuperAdmin() || StoreScope::canManageStores();

$ecomNav = [
    ['id' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'ecom_nav_dashboard'],
    ['id' => 'products', 'href' => 'products.php', 'icon' => 'inventory_2', 'label' => 'ecom_nav_products'],
    ['id' => 'orders', 'href' => 'orders.php', 'icon' => 'shopping_bag', 'label' => 'ecom_nav_orders'],
    ['id' => 'brands', 'href' => 'brands.php', 'icon' => 'sell', 'label' => 'ecom_nav_brands'],
    ['id' => 'blog', 'href' => 'blog.php', 'icon' => 'article', 'label' => 'ecom_nav_blog'],
    ['id' => 'customers', 'href' => 'customers.php', 'icon' => 'group', 'label' => 'ecom_nav_customers'],
];

function ecom_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'admin');
    }
    return $out;
}

$ecomCommonI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error', 'delete',
    'load_error', 'connection_error', 'last_updated', 'no_data', 'search', 'prev_page', 'next_page',
    'ecom_section', 'ecom_title', 'ecom_back_admin', 'ecom_open_storefront', 'ecom_nav_dashboard',
    'ecom_nav_products', 'ecom_nav_orders', 'ecom_nav_brands', 'ecom_nav_blog', 'ecom_nav_customers',
    'ecom_nav_settings', 'nav_system', 'dash_all_stores',
];

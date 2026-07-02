<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../includes/Helpers/StoreScope.php';
require_once __DIR__ . '/../../../includes/Helpers/WarehousePortalAuth.php';
require_once __DIR__ . '/../../../includes/Helpers/EntitlementGuard.php';
require_once __DIR__ . '/../../../includes/Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';
require_once __DIR__ . '/../../../includes/Wms/WmsSchema.php';

/** Subdirectory depth of the current page under public/warehouse/ (0 = portal root). */
$whDepth = 0;
$whScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
if (preg_match('#/warehouse/(.+)$#', $whScript, $whMatch)) {
    $whDepth = max(0, substr_count($whMatch[1], '/'));
}
$whRootPrefix = $whDepth > 0 ? str_repeat('../', $whDepth) : '';
$whPublicPrefix = str_repeat('../', 1 + $whDepth);
$assetsBase = str_repeat('../', 2 + $whDepth) . 'assets';
$apiBase = str_repeat('../', 2 + $whDepth) . 'api/v1/index.php';
$changeUrl = $whPublicPrefix . 'change_language.php';
$whManifest = $whPublicPrefix . 'manifest.json';
$whLogoutUrl = $whPublicPrefix . 'logout.php';
$whAdminUrl = $whPublicPrefix . 'admin/index.php';
$whCanAccessAdmin = in_array(
    RoleRedirect::slug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''),
    RoleRedirect::workspaceRoles('admin'),
    true
);

RbacGuard::workspace('warehouse', $whPublicPrefix . 'login.php');
EntitlementGuard::requireModule('warehouse', $whPublicPrefix . 'billing.php');
LanguageMiddleware::bootstrap();

$roleSlug = WarehousePortalAuth::roleSlug();
$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$initial = strtoupper(substr($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'W', 0, 1));
$warehouseId = (int) ($_SESSION['warehouse_id'] ?? 0);
$storeId = StoreScope::activeStoreId();
$storeName = '';
$warehouseName = '';
$storeCurrency = 'FCFA';
$whIsGlobalView = StoreScope::isGlobalView();
$whCanSwitchStore = StoreScope::isSuperAdmin() || StoreScope::canManageStores();

try {
    $db = Database::getInstance()->getConnection();
    $currencyCtx = CurrencyHelper::portalContext($db, $storeId, $whIsGlobalView);
    $storeCurrency = $currencyCtx['currency'];
    if ($storeId) {
        $stmt = $db->prepare('SELECT name FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$storeId]);
        $storeName = (string) ($stmt->fetchColumn() ?: '');
    }
    if ($warehouseId) {
        $stmt = $db->prepare('SELECT name FROM warehouses WHERE id = ? LIMIT 1');
        $stmt->execute([$warehouseId]);
        $warehouseName = (string) ($stmt->fetchColumn() ?: '');
    }
} catch (Throwable) {
}

$whCurrencyCatalog = CurrencyHelper::catalogForJs(
    array_unique(array_merge([$storeCurrency], ['FCFA', 'XOF', 'XAF', 'EUR', 'USD', 'NGN', 'GHS', 'KES', 'MAD']))
);

$whCanManage = WarehousePortalAuth::canManage();
$whCanReceive = WarehousePortalAuth::canReceive();
$whCanDispatch = WarehousePortalAuth::canDispatch();
$whCanInventory = WarehousePortalAuth::canInventory();
$whCanTransfer = WarehousePortalAuth::canTransfer();
$whCanReports = WarehousePortalAuth::canReports();
$whReadOnly = WarehousePortalAuth::isReadOnly();
$whNav = WarehousePortalAuth::navigation();
$canManageWms = $whCanManage;

function wh_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'warehouse');
    }
    return $out;
}

/** WMS module strings (shared with legacy wms-*.js) */
function wms_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'wms');
    }
    return $out;
}

$whCommonI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'connection_error', 'last_updated', 'no_data', 'search', 'clear_search',
    'prev_page', 'next_page', 'records', 'export_csv', 'export_pdf', 'print',
];

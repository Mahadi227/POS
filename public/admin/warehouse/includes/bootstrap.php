<?php
/**
 * WMS admin — shared bootstrap
 */
if (!function_exists('requireLogin')) {
    require_once __DIR__ . '/../../../../includes/Config/session.php';
    require_once __DIR__ . '/../../../../includes/Config/config.php';
    require_once __DIR__ . '/../../../../includes/Database/Database.php';
    requireLogin();
}

require_once __DIR__ . '/../../../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../../login.php');
    exit;
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../../change_language.php';
$assetsBase = '../../../assets';
$apiBase = '../../../api/v1/index.php';

$storeId = isset($_SESSION['active_store_id']) ? (int) $_SESSION['active_store_id'] : (int) ($_SESSION['store_id'] ?? 1);
$storeName = '';
$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeName = $row['name'] ?? '';
        $storeCurrency = $row['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$canManageWms = in_array($roleSlug, ['super_admin', 'admin'], true);

function wms_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'wms');
    }
    return $out;
}

$wmsCommonI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'last_updated', 'view_all', 'col_status', 'col_date',
    'nav_main', 'nav_dashboard', 'nav_sales', 'nav_inventory', 'nav_management', 'nav_system', 'nav_pos',
    'wms_migration_hint', 'wms_nav_dashboard', 'wms_nav_warehouses', 'wms_nav_inventory', 'wms_nav_locations',
    'wms_nav_receipts', 'wms_nav_dispatch', 'wms_nav_requests', 'wms_nav_transfers', 'wms_nav_batches',
    'wms_nav_expiry', 'wms_nav_audit', 'wms_nav_reports', 'wms_nav_analytics', 'wms_nav_logs', 'wms_nav_settings',
];

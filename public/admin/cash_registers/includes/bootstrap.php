<?php
/**
 * Cash registers admin — shared bootstrap
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
$crBase = '';
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

require __DIR__ . '/../../includes/admin-branding.php';

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$canManageRegisters = in_array($roleSlug, ['super_admin', 'admin'], true);

function cr_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'admin');
    }
    return $out;
}

$crCommonI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'connection_error', 'last_updated',
    'nav_main', 'nav_dashboard', 'nav_sales', 'nav_inventory', 'nav_management',
    'nav_stores', 'nav_users', 'nav_analytics', 'nav_inventory_analytics', 'nav_sync', 'nav_system', 'nav_pos',
    'nav_cash_registers', 'cr_nav_dashboard', 'cr_nav_registers', 'cr_nav_reconciliation',
    'cr_nav_movements', 'cr_nav_transfers', 'cr_nav_shifts', 'cr_nav_reports', 'cr_nav_analytics', 'cr_nav_logs',
    'cr_migration_hint',
];

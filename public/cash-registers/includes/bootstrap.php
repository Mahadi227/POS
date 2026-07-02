<?php
/**
 * Cash Registers portal (Caisses) — shared bootstrap
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../includes/Helpers/StoreScope.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';
require_once __DIR__ . '/../../../includes/CashRegister/CashRegisterSchema.php';

/** Subdirectory depth under public/cash-registers/ (divided by /). */
$crDepth = 0;
$crScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? '');
if (preg_match('#/cash-registers/(.+)$#', $crScript, $crMatch)) {
    $crDepth = max(0, substr_count($crMatch[1], '/'));
}
$crRootPrefix = $crDepth > 0 ? str_repeat('../', $crDepth) : '';
$crPublicPrefix = str_repeat('../', 1 + $crDepth);
$assetsBase = str_repeat('../', 2 + $crDepth) . 'assets';
$apiBase = str_repeat('../', 2 + $crDepth) . 'api/v1/index.php';
$changeUrl = $crPublicPrefix . 'change_language.php';
$crManifest = $crPublicPrefix . 'manifest.json';
$crLogoutUrl = $crPublicPrefix . 'logout.php';
$crAdminUrl = $crPublicPrefix . 'admin/index.php';
$crPosUrl = $crPublicPrefix . 'cashier/pos.php';

RbacGuard::workspace('cash_registers', $crPublicPrefix . 'login.php');
LanguageMiddleware::bootstrap();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$initial = strtoupper(substr($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'C', 0, 1));
$canManageRegisters = in_array($roleSlug, ['super_admin', 'admin'], true);
$crCanSwitchStore = StoreScope::isSuperAdmin() || StoreScope::canManageStores();

$storeId = StoreScope::activeStoreId();
$storeName = '';
$storeCurrency = 'FCFA';
$crModuleReady = CashRegisterSchema::ready();
try {
    $db = Database::getInstance()->getConnection();
    if ($storeId) {
        $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$storeId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $storeName = $row['name'] ?? '';
            $storeCurrency = $row['currency'] ?? 'FCFA';
        }
    }
} catch (Throwable) {
}

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
    'cr_migration_hint', 'cr_section', 'cr_nav_dashboard', 'cr_nav_registers', 'cr_nav_reconciliation',
    'cr_nav_movements', 'cr_nav_transfers', 'cr_nav_shifts', 'cr_nav_reports', 'cr_nav_analytics',
    'cr_nav_logs', 'cr_nav_settings', 'cr_back_admin', 'cr_open_pos',
];

$crNav = [
    ['id' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'cr_nav_dashboard'],
    ['id' => 'registers', 'href' => 'registers.php', 'icon' => 'storefront', 'label' => 'cr_nav_registers'],
    ['id' => 'reconciliation', 'href' => 'reconciliation.php', 'icon' => 'account_balance_wallet', 'label' => 'cr_nav_reconciliation'],
    ['id' => 'movements', 'href' => 'cash_movements.php', 'icon' => 'swap_horiz', 'label' => 'cr_nav_movements'],
    ['id' => 'transfers', 'href' => 'cash_transfers.php', 'icon' => 'sync_alt', 'label' => 'cr_nav_transfers'],
    ['id' => 'shifts', 'href' => 'shift_management.php', 'icon' => 'schedule', 'label' => 'cr_nav_shifts'],
    ['id' => 'reports', 'href' => 'reports.php', 'icon' => 'summarize', 'label' => 'cr_nav_reports'],
    ['id' => 'analytics', 'href' => 'analytics.php', 'icon' => 'analytics', 'label' => 'cr_nav_analytics'],
    ['id' => 'logs', 'href' => 'logs.php', 'icon' => 'history', 'label' => 'cr_nav_logs'],
];

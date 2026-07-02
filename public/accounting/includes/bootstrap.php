<?php
/**
 * Accounting workspace — shared bootstrap
 */
declare(strict_types=1);

require_once __DIR__ . '/../../../includes/Config/session.php';
require_once __DIR__ . '/../../../includes/Config/config.php';
require_once __DIR__ . '/../../../includes/Database/Database.php';
require_once __DIR__ . '/../../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../../includes/Helpers/StoreScope.php';
require_once __DIR__ . '/../../../includes/Middleware/LanguageMiddleware.php';
require_once __DIR__ . '/../../../languages/helpers.php';
require_once __DIR__ . '/../../../includes/Helpers/EntitlementGuard.php';
require_once __DIR__ . '/../../../includes/Accounting/AccountingSchema.php';

RbacGuard::workspace('accounting', '../login.php');
EntitlementGuard::requireModule('accounting', '../billing.php');
LanguageMiddleware::bootstrap();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$canManageAccounting = in_array($roleSlug, ['super_admin', 'admin', 'accountant'], true);
$canApproveExpenses = in_array($roleSlug, ['super_admin', 'admin', 'accountant'], true);

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';
$assetsBase = '../../assets';
$apiBase = '../../api/v1/index.php';

$storeId = StoreScope::activeStoreId();
$storeName = '';
$storeCurrency = 'FCFA';
$accModuleReady = false;
try {
    $db = Database::getInstance()->getConnection();
    $accModuleReady = AccountingSchema::ensure($db);
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

$initial = strtoupper(substr($_SESSION['name'] ?? $_SESSION['full_name'] ?? 'A', 0, 1));

function acc_i18n(array $keys): array
{
    $out = [];
    foreach ($keys as $key) {
        $out[$key] = __t($key, 'accounting');
    }
    return $out;
}

$accCommonI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'connection_error', 'last_updated', 'col_date', 'col_status', 'cr_export_csv',
    'cr_no_data', 'start_date', 'end_date', 'prev_page', 'next_page', 'clear_search',
];

$accNav = [
    ['id' => 'dashboard', 'href' => 'dashboard.php', 'icon' => 'dashboard', 'label' => 'nav_dashboard'],
    ['id' => 'accounts', 'href' => 'chart_of_accounts.php', 'icon' => 'account_tree', 'label' => 'nav_chart_of_accounts'],
    ['id' => 'journal', 'href' => 'journal_entries.php', 'icon' => 'menu_book', 'label' => 'nav_journal'],
    ['id' => 'revenues', 'href' => 'revenues.php', 'icon' => 'trending_up', 'label' => 'nav_revenues'],
    ['id' => 'expenses', 'href' => 'expenses.php', 'icon' => 'receipt_long', 'label' => 'nav_expenses'],
    ['id' => 'cash', 'href' => 'cash_management.php', 'icon' => 'payments', 'label' => 'nav_cash'],
    ['id' => 'banks', 'href' => 'bank_accounts.php', 'icon' => 'account_balance', 'label' => 'nav_banks'],
    ['id' => 'mobile', 'href' => 'mobile_money.php', 'icon' => 'smartphone', 'label' => 'nav_mobile_money'],
    ['id' => 'receivables', 'href' => 'accounts_receivable.php', 'icon' => 'request_quote', 'label' => 'nav_receivables'],
    ['id' => 'payables', 'href' => 'accounts_payable.php', 'icon' => 'credit_score', 'label' => 'nav_payables'],
    ['id' => 'inventory', 'href' => 'inventory_accounting.php', 'icon' => 'inventory_2', 'label' => 'nav_inventory'],
    ['id' => 'profit_loss', 'href' => 'profit_loss.php', 'icon' => 'assessment', 'label' => 'nav_profit_loss'],
    ['id' => 'balance_sheet', 'href' => 'balance_sheet.php', 'icon' => 'balance', 'label' => 'nav_balance_sheet'],
    ['id' => 'cashflow', 'href' => 'cashflow.php', 'icon' => 'waterfall_chart', 'label' => 'nav_cashflow'],
    ['id' => 'reports', 'href' => 'reports.php', 'icon' => 'summarize', 'label' => 'nav_reports'],
    ['id' => 'analytics', 'href' => 'analytics.php', 'icon' => 'analytics', 'label' => 'nav_analytics'],
    ['id' => 'audit', 'href' => 'audit_logs.php', 'icon' => 'history', 'label' => 'nav_audit'],
];

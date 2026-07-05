<?php

/**
 * Sales history — cashier (search, filters, reprint).
 */
require_once '../../includes/Config/session.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/pos-config.php';
require_once __DIR__ . '/includes/cashier-branding.php';

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$displayName = htmlspecialchars($_SESSION['name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8');
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
$currencySymbol = htmlspecialchars($posConfig['settings']['currency_symbol'] ?? 'FCFA', ENT_QUOTES, 'UTF-8');

$salesI18nKeys = [
    'loading', 'loading_title', 'loading_message', 'loading_history', 'error', 'load_error',
    'no_sales', 'no_search_match', 'no_sales_today', 'no_sales_found',
    'results_count', 'tickets_count', 'filtered_of_total', 'period_today_label', 'period_all_label',
    'view_detail', 'reprint', 'detail', 'print', 'popup_blocked',
    'pay_cash', 'pay_card', 'pay_mobile_money', 'pay_split', 'walk_in', 'last_updated',
];
$salesI18n = [];
foreach ($salesI18nKeys as $key) {
    $salesI18n[$key] = __t($key, 'sales');
}

$posConfig['lang'] = $activeLang;
$posConfig['locale'] = $locale;

$changeUrl = '../change_language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="cashier" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/cashier-head-theme.php'; ?>
    <title><?php echo __t('history_title', 'sales'); ?> — <?php echo $brandName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-sales-history.css?v=4">
    <?php echo cashier_theme_css_block($adminAccent); ?>
</head>

<body class="sh-page sh-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header sh-page-header">
                <div class="header-left sh-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn sh-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'sales'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('history_heading', 'sales'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="shHeaderDate">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="sh-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools sh-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="sh-header-user user-profile">
                        <div class="user-info">
                            <span class="name"><?php echo $displayName; ?></span>
                            <span class="role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions sh-header-actions">
                    <a href="pos.php" class="sh-header-pos" title="<?php echo __t('open_pos', 'sales'); ?>">
                        <span class="material-icons-round">point_of_sale</span>
                        <span class="sh-header-pos__label"><?php echo __t('open_pos', 'sales'); ?></span>
                    </a>
                    <button type="button" class="sh-refresh-btn sh-header-refresh" id="salesRefreshBtn" title="<?php echo __t('refresh', 'sales'); ?>" aria-label="<?php echo __t('refresh', 'sales'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="sh-refresh-btn__label"><?php echo __t('refresh', 'sales'); ?></span>
                    </button>
                    <?php $themeToggleClass = 'sh-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="sh-error-banner" id="salesError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="sh-error-text"></span>
                </div>

                <nav class="sh-quick-nav" aria-label="<?php echo __t('menu', 'sales'); ?>">
                    <a href="dashboard.php" class="sh-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'cashier'); ?></span>
                    </a>
                    <a href="pos.php" class="sh-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="sh-quick-nav__item sh-quick-nav__item--accent">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="sh-quick-nav__item">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="sh-quick-nav__item">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                </nav>
                <div class="sh-toolbar">
                    <div class="sh-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="salesSearch" placeholder="<?php echo __t('search_placeholder', 'sales'); ?>"
                            autocomplete="off">
                        <button type="button" class="sh-search-clear" id="salesSearchClear" aria-label="<?php echo __t('clear_search', 'sales'); ?>">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                    <div class="sh-filters" role="tablist" aria-label="<?php echo __t('period_label', 'sales'); ?>">
                        <button type="button" class="sh-filter-btn active" data-period="today"><?php echo __t('period_today', 'sales'); ?></button>
                        <button type="button" class="sh-filter-btn" data-period="all"><?php echo __t('period_all', 'sales'); ?></button>
                    </div>
                </div>

                <div class="sh-summary sh-summary-cards">
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--blue">
                            <span class="material-icons-round">receipt_long</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label"><?php echo __t('tickets_shown', 'sales'); ?></div>
                            <div class="sh-summary-card__value" id="summaryCount">0</div>
                        </div>
                    </div>
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--green">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label"><?php echo __t('filtered_total', 'sales'); ?></div>
                            <div class="sh-summary-card__value" id="summaryRevenue">0 <?php echo $currencySymbol; ?></div>
                        </div>
                    </div>
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--slate">
                            <span class="material-icons-round">filter_list</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label"><?php echo __t('period_summary', 'sales'); ?></div>
                            <div class="sh-summary-card__value sh-summary-card__value--sm" id="summaryFiltered">—</div>
                        </div>
                    </div>
                </div>

                <section class="sh-panel">
                    <div class="sh-panel__head">
                        <h2><?php echo __t('ticket_list', 'sales'); ?></h2>
                        <span class="sh-count" id="salesCountLabel"><?php echo __t('loading', 'sales'); ?></span>
                    </div>

                    <div class="sh-table-wrap">
                        <table class="sh-table sh-sales-table" id="salesTable">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_receipt', 'sales'); ?></th>
                                    <th><?php echo __t('col_datetime', 'sales'); ?></th>
                                    <th><?php echo __t('col_total', 'sales'); ?></th>
                                    <th><?php echo __t('col_payment', 'sales'); ?></th>
                                    <th><?php echo __t('col_customer', 'sales'); ?></th>
                                    <th><?php echo __t('col_actions', 'sales'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody">
                                <tr class="sh-loading-row">
                                    <td colspan="6"><?php echo __t('loading_history', 'sales'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        window.SALES_CONFIG = <?php echo json_encode([
            'lang' => $activeLang,
            'locale' => $locale,
            'period' => 'today',
        ], JSON_UNESCAPED_UNICODE); ?>;
        window.SALES_I18N = <?php echo json_encode($salesI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/sales-history.js?v=3"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>

<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Config/config.php';
require_once '../../includes/Database/Database.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';

$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$storeName = 'RetailPOS';
$storeCurrency = 'FCFA';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, name, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeName = $row['name'] ?? 'RetailPOS';
        $storeCurrency = $row['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
}

$analyticsI18nKeys = [
    'loading', 'loading_analytics', 'load_error', 'connection_error', 'error', 'last_updated',
    'refresh', 'theme', 'menu', 'logout', 'period_label', 'period_today', 'period_week',
    'period_month', 'period_90d', 'analytics_subtitle',
    'analytics_section_reports', 'analytics_kpi_revenue_meta', 'analytics_kpi_tx_meta', 'per_transaction',
    'nav_dashboard', 'nav_sales', 'nav_analytics', 'nav_inventory_analytics', 'nav_main', 'dash_all_stores',
    'stat_revenue', 'stat_transactions', 'stat_avg_ticket', 'active_customers', 'new_customers_period',
    'tab_daily_sales', 'tab_branches', 'tab_cashiers', 'tab_inventory', 'tab_customers',
    'chart_sales_evolution', 'chart_transaction_count', 'chart_payment_mix', 'chart_daily_summary',
    'col_date', 'col_revenue', 'col_transactions',
    'chart_branch_revenue', 'chart_branch_tx', 'branch_detail', 'col_branch', 'col_code', 'col_avg_ticket',
    'chart_cashier_revenue', 'chart_cashier_tx', 'cashier_ranking', 'col_rank', 'col_cashier',
    'stat_products', 'stat_out_of_stock', 'low_stock', 'stat_stock_value',
    'chart_inv_category', 'chart_inv_status', 'top_products_sold', 'col_product', 'col_qty_sold', 'col_revenue_generated',
    'chart_customer_growth', 'chart_customer_split', 'top_customers', 'col_customer', 'col_phone', 'col_visits', 'col_total_spent',
    'export_report', 'export_load_first', 'no_sales', 'no_branch_data', 'no_cashier_sales', 'no_product_sales',
    'no_identified_customers', 'global_view', 'store_scope', 'all_branches_scope',
    'chart_revenue_label', 'chart_transactions_label', 'chart_new_customers',
    'stock_in_stock', 'stock_out', 'customer_identified', 'customer_anonymous', 'no_chart_data',
    'load_report_error', 'report_csv_title', 'report_section_daily', 'report_section_branches',
    'report_section_cashiers', 'report_section_inventory', 'report_section_customers',
    'report_total_revenue', 'report_avg_ticket', 'report_from', 'report_to',
    'pay_cash', 'pay_card', 'pay_mobile_money',
    'doc_title', 'doc_subtitle', 'doc_prepared_by', 'doc_generated_on', 'doc_store', 'doc_period',
    'doc_reference', 'doc_currency', 'doc_date_range', 'doc_confidential', 'doc_executive_summary',
    'doc_grand_total', 'doc_total', 'doc_footer',
    'report_payment_mix', 'col_payment_method', 'col_share', 'col_amount',
    'report_inventory_snapshot', 'report_top_products_section', 'customer_loyalty_split',
    'stat_new_customers', 'stat_total_customers', 'export_success',
    'exporting_excel', 'export_excel_error',
];
$analyticsI18n = [];
foreach ($analyticsI18nKeys as $key) {
    $analyticsI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'analytics';
require __DIR__ . '/includes/admin-branding.php';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="admin" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/admin-head-theme.php'; ?>
    <title><?php echo __t('analytics_title', 'admin'); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=13">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-analytics.css?v=5">
    <?php require __DIR__ . '/includes/admin-tail-theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="ar-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>
            <ul class="nav-menu">
                <li class="nav-section"><?php echo __t('nav_main', 'admin'); ?></li>
                <li>
                    <a href="index.php" class="nav-link">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="sales.php" class="nav-link">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_sales', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_inventory', 'admin'); ?></span>
                    </a>
                </li>
                <?php include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
                <li>
                    <a href="../cashier/pos.php" class="nav-link">
                        <span class="material-icons-round">shopping_cart</span>
                        <span><?php echo __t('nav_pos', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="nav-link" style="color: var(--danger); margin-top: 12px;">
                        <span class="material-icons-round">logout</span>
                        <span><?php echo __t('logout', 'admin'); ?></span>
                    </a>
                </li>
            </ul>
            <div class="user-profile-widget">
                <span class="avatar-initial"><?php echo htmlspecialchars($initial); ?></span>
                <div class="user-info">
                    <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></p>
                    <p class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
                </div>
            </div>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header admin-page-header ad-page-header">
                <div class="header-left ad-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'admin'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('analytics_heading', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="analytics-period-label"><?php echo __t('loading', 'admin'); ?></span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="ih-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools ad-header-tools">
                    <div id="headerStoreSlot" class="header-store-slot"></div>
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                </div>

                <div class="header-actions ad-header-actions">
                    <button type="button" class="inv-btn inv-btn-outline ar-export-btn" id="exportReportBtn" title="<?php echo __t('export_report', 'admin'); ?>">
                        <span class="material-icons-round">download</span>
                        <span class="btn-label"><?php echo __t('export_report', 'admin'); ?></span>
                    </button>
                    <button type="button" class="ad-refresh-btn" id="refreshAnalytics" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="analyticsError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="arHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="arHeroTitle"><?php echo __t('analytics_subtitle', 'admin'); ?></h2>
                        <p class="ad-dash-hero__period" id="arHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="arHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero ar-summary-cards" id="arSummaryCards" role="group" aria-label="<?php echo __t('analytics_heading', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ar-kpi-revenue">
                            <span class="ad-kpi__label"><?php echo __t('stat_revenue', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="ar-revenue-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('analytics_kpi_revenue_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ar-kpi-transactions">
                            <span class="ad-kpi__label"><?php echo __t('stat_transactions', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="ar-transactions-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('analytics_kpi_tx_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="ar-kpi-avg">
                            <span class="ad-kpi__label"><?php echo __t('stat_avg_ticket', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="ar-avg-ticket-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('per_transaction', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="ar-kpi-customers">
                            <span class="ad-kpi__label"><?php echo __t('active_customers', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="ar-active-customers-val">—</strong>
                            <span class="ad-kpi__meta" id="ar-new-customers">—</span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_main', 'admin'); ?>">
                        <a href="index.php" class="ad-quick-btn"><span class="material-icons-round">dashboard</span><?php echo __t('nav_dashboard', 'admin'); ?></a>
                        <a href="sales.php" class="ad-quick-btn"><span class="material-icons-round">point_of_sale</span><?php echo __t('nav_sales', 'admin'); ?></a>
                        <a href="inventory_analytics.php" class="ad-quick-btn"><span class="material-icons-round">bar_chart</span><?php echo __t('nav_inventory_analytics', 'admin'); ?></a>
                        <button type="button" class="ad-quick-btn ad-quick-btn--accent" id="exportReportBtnHero">
                            <span class="material-icons-round">download</span><?php echo __t('export_report', 'admin'); ?>
                        </button>
                    </nav>
                </section>

                <div class="ar-dash-toolbar">
                    <div class="ar-dash-toolbar__top">
                        <div class="inv-chips ar-chips" role="tablist" aria-label="<?php echo __t('period_label', 'admin'); ?>">
                            <button type="button" class="inv-chip" data-period="today" role="tab"><?php echo __t('period_today', 'admin'); ?></button>
                            <button type="button" class="inv-chip" data-period="week" role="tab"><?php echo __t('period_week', 'admin'); ?></button>
                            <button type="button" class="inv-chip active" data-period="month" role="tab" aria-selected="true"><?php echo __t('period_month', 'admin'); ?></button>
                            <button type="button" class="inv-chip" data-period="90d" role="tab"><?php echo __t('period_90d', 'admin'); ?></button>
                        </div>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="arReportsTitle">
                    <h3 class="ad-dash-section__title" id="arReportsTitle"><?php echo __t('analytics_section_reports', 'admin'); ?></h3>
                <div class="ar-tabs" role="tablist">
                    <button type="button" class="ar-tab active" data-panel="daily"><?php echo __t('tab_daily_sales', 'admin'); ?></button>
                    <button type="button" class="ar-tab" data-panel="branches"><?php echo __t('tab_branches', 'admin'); ?></button>
                    <button type="button" class="ar-tab" data-panel="cashiers"><?php echo __t('tab_cashiers', 'admin'); ?></button>
                    <button type="button" class="ar-tab" data-panel="inventory"><?php echo __t('tab_inventory', 'admin'); ?></button>
                    <button type="button" class="ar-tab" data-panel="customers"><?php echo __t('tab_customers', 'admin'); ?></button>
                </div>

                <section id="panel-daily" class="ar-panel">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_sales_evolution', 'admin'); ?></h3>
                            <div class="ar-chart-wrap"><canvas id="dailyRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_transaction_count', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="dailyCountChart"></canvas></div>
                        </div>
                    </div>
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_payment_mix', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="paymentMixChart"></canvas></div>
                        </div>
                        <div class="card table-widget">
                            <h3><?php echo __t('chart_daily_summary', 'admin'); ?></h3>
                            <div class="table-responsive ar-table-wrap">
                                <table class="modern-table ar-analytics-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __t('col_date', 'admin'); ?></th>
                                            <th><?php echo __t('col_revenue', 'admin'); ?></th>
                                            <th><?php echo __t('col_transactions', 'admin'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="dailyTableBody">
                                        <tr><td colspan="3" class="ad-empty-row"><?php echo __t('loading_analytics', 'admin'); ?></td></tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <section id="panel-branches" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_branch_revenue', 'admin'); ?></h3>
                            <div class="ar-chart-wrap"><canvas id="branchRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_branch_tx', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="branchTxChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3><?php echo __t('branch_detail', 'admin'); ?></h3>
                        <div class="table-responsive ar-table-wrap">
                            <table class="modern-table ar-analytics-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_branch', 'admin'); ?></th>
                                        <th><?php echo __t('col_code', 'admin'); ?></th>
                                        <th><?php echo __t('col_revenue', 'admin'); ?></th>
                                        <th><?php echo __t('col_transactions', 'admin'); ?></th>
                                        <th><?php echo __t('col_avg_ticket', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="branchTableBody">
                                    <tr><td colspan="5" class="ad-empty-row"><?php echo __t('loading_analytics', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-cashiers" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_cashier_revenue', 'admin'); ?></h3>
                            <div class="ar-chart-wrap"><canvas id="cashierRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_cashier_tx', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="cashierCountChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3><?php echo __t('cashier_ranking', 'admin'); ?></h3>
                        <div class="table-responsive ar-table-wrap">
                            <table class="modern-table ar-analytics-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_rank', 'admin'); ?></th>
                                        <th><?php echo __t('col_cashier', 'admin'); ?></th>
                                        <th><?php echo __t('col_revenue', 'admin'); ?></th>
                                        <th><?php echo __t('col_transactions', 'admin'); ?></th>
                                        <th><?php echo __t('col_avg_ticket', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="cashierTableBody">
                                    <tr><td colspan="5" class="ad-empty-row"><?php echo __t('loading_analytics', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-inventory" class="ar-panel hidden">
                    <div class="ad-kpi-grid ar-inv-mini" id="arInvMini">
                        <article class="ad-kpi ad-kpi--primary">
                            <span class="ad-kpi__label"><?php echo __t('stat_products', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="inv-total-val">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--warn">
                            <span class="ad-kpi__label"><?php echo __t('stat_out_of_stock', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="inv-out-val">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--warn">
                            <span class="ad-kpi__label"><?php echo __t('low_stock', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="inv-low-val">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral">
                            <span class="ad-kpi__label"><?php echo __t('stat_stock_value', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="inv-value-val">—</strong>
                        </article>
                    </div>
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_inv_category', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="invCategoryChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_inv_status', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="invStockChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3><?php echo __t('top_products_sold', 'admin'); ?></h3>
                        <div class="table-responsive ar-table-wrap">
                            <table class="modern-table ar-analytics-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_product', 'admin'); ?></th>
                                        <th><?php echo __t('col_qty_sold', 'admin'); ?></th>
                                        <th><?php echo __t('col_revenue_generated', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="invMovingBody">
                                    <tr><td colspan="3" class="ad-empty-row"><?php echo __t('loading_analytics', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-customers" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_customer_growth', 'admin'); ?></h3>
                            <div class="ar-chart-wrap"><canvas id="customerGrowthChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3><?php echo __t('chart_customer_split', 'admin'); ?></h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="customerSplitChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3><?php echo __t('top_customers', 'admin'); ?></h3>
                        <div class="table-responsive ar-table-wrap">
                            <table class="modern-table ar-analytics-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_customer', 'admin'); ?></th>
                                        <th><?php echo __t('col_phone', 'admin'); ?></th>
                                        <th><?php echo __t('col_visits', 'admin'); ?></th>
                                        <th><?php echo __t('col_total_spent', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="customerTopBody">
                                    <tr><td colspan="4" class="ad-empty-row"><?php echo __t('loading_analytics', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                </section>
            </div>
        </main>
    </div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;
        window.ADMIN_PAGE.locale = <?php echo json_encode($locale); ?>;
        window.ADMIN_PAGE.lang = <?php echo json_encode($activeLang); ?>;
        window.ADMIN_PAGE.userName = <?php echo json_encode($_SESSION['name'] ?? 'Admin'); ?>;
        window.ADMIN_CONFIG = {
            locale: <?php echo json_encode($locale); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            storeName: <?php echo json_encode($storeName); ?>,
            userName: <?php echo json_encode($_SESSION['name'] ?? 'Admin'); ?>,
            accent: <?php echo json_encode($adminAccent); ?>,
        };
        window.ADMIN_ANALYTICS_I18N = <?php echo json_encode($analyticsI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/analytics-report-export.js?v=2"></script>
    <script src="../../assets/js/admin/analytics.js?v=7"></script>
    <script>
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

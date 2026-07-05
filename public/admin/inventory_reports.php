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

$userId = (int) ($_SESSION['user_id'] ?? 0);
$storeId = (int) ($_SESSION['store_id'] ?? 0);
$storeName = $_SESSION['store_name'] ?? '';
$storeCurrency = 'FCFA';
$userName = $_SESSION['name'] ?? 'Admin';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (!empty($row['name'])) {
            $storeName = $row['name'];
        }
        if (!empty($row['currency'])) {
            $storeCurrency = $row['currency'];
        }
    }
} catch (Throwable $e) {
}

$reportsI18nKeys = [
    'loading', 'loading_reports', 'load_error', 'connection_error', 'error', 'last_updated',
    'period_today', 'period_week', 'period_month', 'period_90d', 'period_all', 'period_label',
    'reports_subtitle', 'export_pdf', 'export_csv', 'print_report',
    'report_valuation', 'report_low_stock', 'report_top_moving', 'report_category_breakdown',
    'report_ledger_summary', 'report_generated',
    'stat_total_products', 'stat_cost_value', 'stat_retail_value', 'units_in_stock',
    'stock_status_in_stock', 'stock_status_low', 'stock_status_out',
    'col_product', 'col_sku', 'col_category', 'stock', 'col_cost_value', 'col_retail_value',
    'col_min_stock', 'col_qty_sold', 'col_revenue', 'col_quantity',
    'stat_total_in', 'stat_total_out', 'ledger_entries',
    'no_report_data',     'no_low_stock', 'no_top_moving', 'export_success', 'export_error',
    'valuation_table_summary', 'uncategorized', 'prev_page', 'next_page',
    'doc_title', 'doc_subtitle', 'doc_prepared_by', 'doc_generated_on', 'doc_store',
    'doc_period', 'doc_reference', 'doc_currency', 'doc_confidential', 'doc_executive_summary',
    'doc_grand_total', 'doc_total', 'doc_page', 'doc_footer', 'exporting_pdf', 'pdf_fallback_print',
    'export_full_csv', 'reports_heading', 'store_fallback',
    'reports_section_ledger', 'reports_section_categories', 'reports_section_top_moving',
    'reports_section_low_stock', 'reports_section_valuation',
    'ir_kpi_products_meta', 'ir_kpi_units_meta', 'ir_kpi_cost_meta', 'ir_kpi_retail_meta',
    'ir_stock_health', 'low_stock_alert', 'back_analytics', 'link_movements', 'nav_products', 'link_history',
];
$reportsI18n = [];
foreach ($reportsI18nKeys as $key) {
    $reportsI18n[$key] = __t($key, 'inventory');
}
foreach (['menu', 'refresh', 'theme', 'col_date'] as $key) {
    $reportsI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
require __DIR__ . '/includes/admin-branding.php';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="admin" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/admin-head-theme.php'; ?>
    <title><?php echo __t('reports_title', 'inventory'); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=18">
    <link rel="stylesheet" href="../../assets/css/admin-inventory-reports.css?v=2">
    <?php require __DIR__ . '/includes/admin-tail-theme.php'; ?>
</head>

<body class="ir-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar ir-no-print">            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>
            <ul class="nav-menu">
                <li class="nav-section"><?php echo __t('nav_inventory_section', 'inventory'); ?></li>
                <li>
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_products', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_history.php" class="nav-link">
                        <span class="material-icons-round">history</span>
                        <span><?php echo __t('link_history', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="stock_movements.php" class="nav-link">
                        <span class="material-icons-round">swap_horiz</span>
                        <span><?php echo __t('link_movements', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="stock_transfers.php" class="nav-link">
                        <span class="material-icons-round">compare_arrows</span>
                        <span><?php echo __t('link_transfers', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_reports.php" class="nav-link active">
                        <span class="material-icons-round">article</span>
                        <span><?php echo __t('link_reports', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_analytics.php" class="nav-link">
                        <span class="material-icons-round">bar_chart</span>
                        <span><?php echo __t('link_analytics', 'inventory'); ?></span>
                    </a>
                </li>
                <?php $activePage = 'inventory';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
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

        <div class="sidebar-overlay ir-no-print" id="sidebarOverlay"></div>
        <main class="main-content">
            <header class="top-header admin-page-header ad-page-header ir-no-print">
                <div class="header-left ad-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'admin'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('reports_heading', 'inventory'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="reportsDate">—</span>
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
                    <button type="button" class="ad-refresh-btn" id="refreshReportsBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <a href="inventory_analytics.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('back_analytics', 'inventory'); ?>">
                        <span class="material-icons-round">bar_chart</span>
                        <span class="btn-label"><?php echo __t('back_analytics', 'inventory'); ?></span>
                    </a>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner ir-no-print" id="reportsError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="irHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="irHeroTitle"><?php echo __t('reports_subtitle', 'inventory'); ?></h2>
                        <p class="ad-dash-hero__period" id="irHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="irHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero ir-summary-cards" id="irSummaryCards" role="group" aria-label="<?php echo __t('reports_heading', 'inventory'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ir-kpi-products">
                            <span class="ad-kpi__label"><?php echo __t('stat_total_products', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-products-val">—</strong>
                            <span class="ad-kpi__meta" id="ir-kpi-products-meta"><?php echo __t('ir_kpi_products_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="ir-kpi-units">
                            <span class="ad-kpi__label"><?php echo __t('stock', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-units-val">—</strong>
                            <span class="ad-kpi__meta" id="ir-kpi-units-meta"><?php echo __t('ir_kpi_units_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="ir-kpi-cost">
                            <span class="ad-kpi__label"><?php echo __t('stat_cost_value', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-cost-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('ir_kpi_cost_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ir-kpi-retail">
                            <span class="ad-kpi__label"><?php echo __t('stat_retail_value', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-retail-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('ir_kpi_retail_meta', 'inventory'); ?></span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions ir-no-print" aria-label="<?php echo __t('nav_inventory_section', 'inventory'); ?>">
                        <a href="inventory.php" class="ad-quick-btn"><span class="material-icons-round">inventory_2</span><?php echo __t('nav_products', 'inventory'); ?></a>
                        <a href="inventory_history.php" class="ad-quick-btn"><span class="material-icons-round">history</span><?php echo __t('link_history', 'inventory'); ?></a>
                        <a href="stock_movements.php" class="ad-quick-btn"><span class="material-icons-round">swap_horiz</span><?php echo __t('link_movements', 'inventory'); ?></a>
                        <a href="inventory_analytics.php" class="ad-quick-btn ad-quick-btn--accent"><span class="material-icons-round">bar_chart</span><?php echo __t('link_analytics', 'inventory'); ?></a>
                    </nav>
                </section>

                <a href="inventory.php" class="ad-alert-strip ad-alert-strip--warn ad-dash-alert ir-no-print" id="irLowStockAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">warning_amber</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('report_low_stock', 'inventory'); ?></strong>
                        <span class="ad-alert-strip__msg" id="irLowStockAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <div class="ir-dash-toolbar ir-no-print">
                    <div class="inv-chips ir-chips" role="tablist" aria-label="<?php echo __t('period_label', 'inventory'); ?>">
                        <button type="button" class="inv-chip" data-period="today" role="tab"><?php echo __t('period_today', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="week" role="tab"><?php echo __t('period_week', 'inventory'); ?></button>
                        <button type="button" class="inv-chip active" data-period="month" role="tab" aria-selected="true"><?php echo __t('period_month', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="90d" role="tab"><?php echo __t('period_90d', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="all" role="tab"><?php echo __t('period_all', 'inventory'); ?></button>
                    </div>
                    <div class="ir-export-bar">
                        <button type="button" class="inv-btn inv-btn-primary" id="exportPdfBtn">
                            <span class="material-icons-round">picture_as_pdf</span>
                            <?php echo __t('export_pdf', 'inventory'); ?>
                        </button>
                        <button type="button" class="inv-btn inv-btn-outline" id="exportCsvBtn">
                            <span class="material-icons-round">table_view</span>
                            <?php echo __t('export_full_csv', 'inventory'); ?>
                        </button>
                        <button type="button" class="inv-btn inv-btn-outline" id="exportAlertsCsvBtn">
                            <span class="material-icons-round">warning</span>
                            <?php echo __t('report_low_stock', 'inventory'); ?> CSV
                        </button>
                        <button type="button" class="inv-btn inv-btn-outline" id="printReportBtn">
                            <span class="material-icons-round">print</span>
                            <?php echo __t('print_report', 'inventory'); ?>
                        </button>
                    </div>
                </div>

                <div class="ir-stock-health" role="group" aria-label="<?php echo __t('ir_stock_health', 'inventory'); ?>">
                    <span class="ir-pill is-in" id="pill-in-stock">—</span>
                    <span class="ir-pill is-low" id="pill-low-stock">—</span>
                    <span class="ir-pill is-out" id="pill-out-stock">—</span>
                </div>

                <section class="ad-dash-section" aria-labelledby="irLedgerTitle">
                    <h3 class="ad-dash-section__title" id="irLedgerTitle"><?php echo __t('reports_section_ledger', 'inventory'); ?></h3>
                    <div class="ad-kpi-grid ir-ledger-grid">
                        <article class="ad-kpi ad-kpi--neutral">
                            <span class="ad-kpi__label"><?php echo __t('stat_total_in', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="ledger-in">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral">
                            <span class="ad-kpi__label"><?php echo __t('stat_total_out', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="ledger-out">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--primary">
                            <span class="ad-kpi__label"><?php echo __t('report_ledger_summary', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="ledger-entries">—</strong>
                        </article>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="irCategoriesTitle">
                    <h3 class="ad-dash-section__title" id="irCategoriesTitle"><?php echo __t('reports_section_categories', 'inventory'); ?></h3>
                    <div class="ad-panel ir-table-panel">
                        <div class="ir-section-head">
                            <h3><span class="material-icons-round">category</span><?php echo __t('report_category_breakdown', 'inventory'); ?></h3>
                        </div>
                        <div class="table-responsive ih-table-wrap">
                            <table class="modern-table ir-report-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_category', 'inventory'); ?></th>
                                        <th><?php echo __t('stat_total_products', 'inventory'); ?></th>
                                        <th><?php echo __t('stock', 'inventory'); ?></th>
                                        <th><?php echo __t('col_cost_value', 'inventory'); ?></th>
                                        <th><?php echo __t('col_retail_value', 'inventory'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="categoryBreakdownBody">
                                    <tr><td colspan="5" class="ad-empty-row"><?php echo __t('loading_reports', 'inventory'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="irTopMovingTitle">
                    <h3 class="ad-dash-section__title" id="irTopMovingTitle"><?php echo __t('reports_section_top_moving', 'inventory'); ?></h3>
                    <div class="ad-panel ir-table-panel">
                        <div class="ir-section-head">
                            <h3><span class="material-icons-round">trending_up</span><?php echo __t('report_top_moving', 'inventory'); ?></h3>
                        </div>
                        <div class="table-responsive ih-table-wrap">
                            <table class="modern-table ir-report-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_product', 'inventory'); ?></th>
                                        <th><?php echo __t('col_sku', 'inventory'); ?></th>
                                        <th><?php echo __t('col_qty_sold', 'inventory'); ?></th>
                                        <th><?php echo __t('col_revenue', 'inventory'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="topMovingBody">
                                    <tr><td colspan="4" class="ad-empty-row"><?php echo __t('loading_reports', 'inventory'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="irLowStockTitle">
                    <h3 class="ad-dash-section__title" id="irLowStockTitle"><?php echo __t('reports_section_low_stock', 'inventory'); ?></h3>
                    <div class="ad-panel ir-table-panel">
                        <div class="ir-section-head">
                            <h3><span class="material-icons-round">warning</span><?php echo __t('report_low_stock', 'inventory'); ?></h3>
                        </div>
                        <div class="table-responsive ih-table-wrap">
                            <table class="modern-table ir-report-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_product', 'inventory'); ?></th>
                                        <th><?php echo __t('col_sku', 'inventory'); ?></th>
                                        <th><?php echo __t('col_category', 'inventory'); ?></th>
                                        <th><?php echo __t('stock', 'inventory'); ?></th>
                                        <th><?php echo __t('col_min_stock', 'inventory'); ?></th>
                                        <th><?php echo __t('col_cost_value', 'inventory'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="lowStockBody">
                                    <tr><td colspan="6" class="ad-empty-row"><?php echo __t('loading_reports', 'inventory'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="irValuationTitle">
                    <h3 class="ad-dash-section__title" id="irValuationTitle"><?php echo __t('reports_section_valuation', 'inventory'); ?></h3>
                    <div class="ad-panel ir-table-panel">
                        <div class="inv-table-meta ih-table-meta ir-no-print">
                            <span id="valuationSummary"><?php echo __t('loading_reports', 'inventory'); ?></span>
                            <div class="inv-pagination">
                                <button type="button" id="valPagePrev" disabled aria-label="<?php echo __t('prev_page', 'inventory'); ?>">
                                    <span class="material-icons-round">chevron_left</span>
                                </button>
                                <span id="valPageInfo">1 / 1</span>
                                <button type="button" id="valPageNext" disabled aria-label="<?php echo __t('next_page', 'inventory'); ?>">
                                    <span class="material-icons-round">chevron_right</span>
                                </button>
                            </div>
                        </div>
                        <div class="ir-section-head">
                            <h3><span class="material-icons-round">inventory</span><?php echo __t('report_valuation', 'inventory'); ?></h3>
                        </div>
                        <div class="table-responsive ih-table-wrap">
                            <table class="modern-table ir-report-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_product', 'inventory'); ?></th>
                                        <th><?php echo __t('col_sku', 'inventory'); ?></th>
                                        <th><?php echo __t('col_category', 'inventory'); ?></th>
                                        <th><?php echo __t('stock', 'inventory'); ?></th>
                                        <th><?php echo __t('col_cost_value', 'inventory'); ?></th>
                                        <th><?php echo __t('col_retail_value', 'inventory'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="valuationBody">
                                    <tr><td colspan="6" class="ad-empty-row"><?php echo __t('loading_reports', 'inventory'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="invToast" class="inv-toast ir-no-print" role="status" aria-live="polite"></div>

    <script>
        window.INVENTORY_CONFIG = {
            userId: <?php echo json_encode($userId); ?>,
            storeId: <?php echo json_encode($storeId); ?>,
            storeName: <?php echo json_encode($storeName); ?>,
            userName: <?php echo json_encode($userName); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
        };
        window.INVENTORY_I18N = <?php echo json_encode($reportsI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_CONFIG = {
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
            accent: <?php echo json_encode($adminAccent); ?>,
        };
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/inventory-report-export.js?v=2"></script>
    <script src="../../assets/js/admin/inventory-reports.js?v=5"></script>
    <script>

        const themeBtn = document.getElementById('theme-toggle');
        const savedTheme = localStorage.getItem('admin-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            const icon = themeBtn?.querySelector('.material-icons-round');
            if (icon) icon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';
        }
        themeBtn?.addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
            const icon = themeBtn.querySelector('.material-icons-round');
            if (icon) icon.textContent = isDark ? 'dark_mode' : 'light_mode';
        });
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

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

$analyticsI18nKeys = [
    'loading', 'loading_analytics', 'load_error', 'connection_error', 'error', 'last_updated',
    'period_today', 'period_week', 'period_month', 'period_90d', 'period_all', 'period_label',
    'analytics_subtitle', 'analytics_section_charts', 'analytics_section_movements', 'analytics_section_products',
    'ia_kpi_movements_meta', 'ia_kpi_profit_meta', 'ia_kpi_low_meta', 'ia_kpi_value_meta',
    'low_stock_alert', 'report_generated',
    'stat_movements_period', 'stat_estimated_profit', 'low_stock', 'stat_inventory_value',
    'stat_total_in', 'stat_total_out',
    'chart_movement_trend', 'chart_movement_types', 'chart_top_products', 'chart_stock_status',
    'col_movement_type', 'col_count', 'col_product', 'col_sold_units', 'col_sold_value', 'col_profit',
    'stock_status_in_stock', 'stock_status_low', 'stock_status_out',
    'no_analytics_data', 'store_fallback',
    'mov_purchase', 'mov_sale', 'mov_return', 'mov_transfer_in', 'mov_transfer_out',
    'mov_adjustment', 'mov_damaged', 'mov_expired', 'mov_manual_edit', 'type_transfer',
    'reason_sale', 'reason_restock', 'reason_damage', 'reason_correction', 'reason_transfer',
];
$analyticsI18n = [];
foreach ($analyticsI18nKeys as $key) {
    $analyticsI18n[$key] = __t($key, 'inventory');
}
foreach (['menu', 'refresh', 'theme', 'col_date', 'dash_all_stores', 'nav_dashboard', 'nav_analytics'] as $key) {
    $analyticsI18n[$key] = __t($key, 'admin');
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
    <title><?php echo __t('analytics_title', 'inventory'); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-inventory-analytics.css?v=2">
    <?php require __DIR__ . '/includes/admin-tail-theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="ia-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>
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
                    <a href="inventory_reports.php" class="nav-link">
                        <span class="material-icons-round">article</span>
                        <span><?php echo __t('link_reports', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_analytics.php" class="nav-link active">
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

        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <main class="main-content">
            <header class="top-header admin-page-header ad-page-header">
                <div class="header-left ad-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'admin'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('analytics_heading', 'inventory'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="analyticsDate">—</span>
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
                    <button type="button" class="ad-refresh-btn" id="refreshAnalyticsBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <a href="inventory_reports.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('back_reports', 'inventory'); ?>">
                        <span class="material-icons-round">article</span>
                        <span class="btn-label"><?php echo __t('back_reports', 'inventory'); ?></span>
                    </a>
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

                <section class="ad-dash-hero" aria-labelledby="iaHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="iaHeroTitle"><?php echo __t('analytics_subtitle', 'inventory'); ?></h2>
                        <p class="ad-dash-hero__period" id="iaHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="iaHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero ia-summary-cards" id="iaSummaryCards" role="group" aria-label="<?php echo __t('analytics_heading', 'inventory'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ia-kpi-movements">
                            <span class="ad-kpi__label"><?php echo __t('stat_movements_period', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-movements-val">—</strong>
                            <span class="ad-kpi__meta" id="ia-kpi-flow-meta"><?php echo __t('ia_kpi_movements_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="ia-kpi-profit">
                            <span class="ad-kpi__label"><?php echo __t('stat_estimated_profit', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-profit-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('ia_kpi_profit_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="ia-kpi-low">
                            <span class="ad-kpi__label"><?php echo __t('low_stock', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-low-stock-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('ia_kpi_low_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="ia-kpi-value">
                            <span class="ad-kpi__label"><?php echo __t('stat_inventory_value', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-inventory-value-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('ia_kpi_value_meta', 'inventory'); ?></span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_inventory_section', 'inventory'); ?>">
                        <a href="inventory.php" class="ad-quick-btn"><span class="material-icons-round">inventory_2</span><?php echo __t('nav_products', 'inventory'); ?></a>
                        <a href="inventory_history.php" class="ad-quick-btn"><span class="material-icons-round">history</span><?php echo __t('link_history', 'inventory'); ?></a>
                        <a href="stock_movements.php" class="ad-quick-btn"><span class="material-icons-round">swap_horiz</span><?php echo __t('link_movements', 'inventory'); ?></a>
                        <a href="inventory_reports.php" class="ad-quick-btn ad-quick-btn--accent"><span class="material-icons-round">article</span><?php echo __t('link_reports', 'inventory'); ?></a>
                    </nav>
                </section>

                <a href="inventory.php" class="ad-alert-strip ad-alert-strip--warn ad-dash-alert" id="iaLowStockAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">warning_amber</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('low_stock', 'inventory'); ?></strong>
                        <span class="ad-alert-strip__msg" id="iaLowStockAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <div class="ia-dash-toolbar">
                    <div class="inv-chips ia-chips" role="tablist" aria-label="<?php echo __t('period_label', 'inventory'); ?>">
                        <button type="button" class="inv-chip" data-period="today" role="tab"><?php echo __t('period_today', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="week" role="tab"><?php echo __t('period_week', 'inventory'); ?></button>
                        <button type="button" class="inv-chip active" data-period="month" role="tab" aria-selected="true"><?php echo __t('period_month', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="90d" role="tab"><?php echo __t('period_90d', 'inventory'); ?></button>
                        <button type="button" class="inv-chip" data-period="all" role="tab"><?php echo __t('period_all', 'inventory'); ?></button>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="iaChartsTitle">
                    <h3 class="ad-dash-section__title" id="iaChartsTitle"><?php echo __t('analytics_section_charts', 'inventory'); ?></h3>
                <div class="ia-charts-grid">
                    <div class="ad-panel ia-chart-panel">
                        <div class="ia-chart-panel__head">
                            <h3><?php echo __t('chart_movement_trend', 'inventory'); ?></h3>
                        </div>
                        <div class="chart-wrapper tall"><canvas id="movementTrendChart"></canvas></div>
                    </div>
                    <div class="ad-panel ia-chart-panel">
                        <div class="ia-chart-panel__head">
                            <h3><?php echo __t('chart_stock_status', 'inventory'); ?></h3>
                        </div>
                        <div class="chart-wrapper tall"><canvas id="stockStatusChart"></canvas></div>
                    </div>
                </div>

                <div class="ia-charts-row">
                    <div class="ad-panel ia-chart-panel">
                        <div class="ia-chart-panel__head">
                            <h3><?php echo __t('chart_movement_types', 'inventory'); ?></h3>
                        </div>
                        <div class="chart-wrapper"><canvas id="movementTypesChart"></canvas></div>
                    </div>
                    <div class="ad-panel ia-chart-panel">
                        <div class="ia-chart-panel__head">
                            <h3><?php echo __t('chart_top_products', 'inventory'); ?></h3>
                        </div>
                        <div class="chart-wrapper"><canvas id="topProductsChart"></canvas></div>
                    </div>
                </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="iaMovementsTitle">
                    <h3 class="ad-dash-section__title" id="iaMovementsTitle"><?php echo __t('analytics_section_movements', 'inventory'); ?></h3>
                    <div class="ad-panel ia-table-panel">
                        <div class="ad-panel__body table-responsive ih-table-wrap">
                            <table class="modern-table ia-analytics-table">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_movement_type', 'inventory'); ?></th>
                                    <th><?php echo __t('col_count', 'inventory'); ?></th>
                                    <th><?php echo __t('stat_total_in', 'inventory'); ?></th>
                                    <th><?php echo __t('stat_total_out', 'inventory'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="movementTypesBody">
                                <tr><td colspan="4" class="ad-empty-row"><?php echo __t('loading_analytics', 'inventory'); ?></td></tr>
                            </tbody>
                        </table>
                        </div>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="iaProductsTitle">
                    <h3 class="ad-dash-section__title" id="iaProductsTitle"><?php echo __t('analytics_section_products', 'inventory'); ?></h3>
                    <div class="ad-panel ia-table-panel">
                        <div class="ad-panel__body table-responsive ih-table-wrap">
                            <table class="modern-table ia-analytics-table">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_product', 'inventory'); ?></th>
                                    <th><?php echo __t('col_sold_units', 'inventory'); ?></th>
                                    <th><?php echo __t('col_sold_value', 'inventory'); ?></th>
                                    <th><?php echo __t('col_profit', 'inventory'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="topProductsBody">
                                <tr><td colspan="4" class="ad-empty-row"><?php echo __t('loading_analytics', 'inventory'); ?></td></tr>
                            </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>

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
        window.INVENTORY_I18N = <?php echo json_encode($analyticsI18n, JSON_UNESCAPED_UNICODE); ?>;
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
    <script src="../../assets/js/admin/inventory-analytics.js?v=5"></script>
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
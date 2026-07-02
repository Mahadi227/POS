<?php
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../includes/Helpers/StoreScope.php';
RbacGuard::workspace('admin', '../login.php');

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$storeId = 1;
$storeName = '';
$storeCurrency = 'FCFA';
try {
    require_once '../../includes/Database/Database.php';
    $db = Database::getInstance()->getConnection();
    $storeId = StoreScope::resolveStoreId($db);
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    $storeRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($storeRow) {
        $storeName = $storeRow['name'] ?? '';
        $storeCurrency = $storeRow['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
    // ignore and fallback to defaults
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';

$adminI18nKeys = [
    'loading', 'today_prefix', 'new_customers_today', 'chart_revenue_label',
    'no_transactions', 'walk_in', 'status_completed', 'no_sales_30d', 'sold_count',
    'load_dashboard_error', 'load_error_hint', 'vs_yesterday', 'trend_neutral', 'customer_base_hint',
    'dash_subtitle', 'dash_low_stock_alert', 'low_stock', 'dash_section_charts', 'dash_section_activity', 'dash_all_stores',
    'pay_cash', 'pay_card', 'pay_mobile_money', 'items', 'last_updated',
    'col_receipt', 'col_customer', 'col_date', 'col_amount', 'col_status', 'col_payment',
    'nav_sales', 'nav_inventory', 'nav_pos', 'nav_analytics',
    'notif_title', 'notif_empty', 'view_all', 'mark_all_read', 'unread', 'tab_all', 'tab_unread',
    'priority_critical', 'preferences', 'alerts_widget', 'loading',
];
$adminI18n = [];
foreach ($adminI18nKeys as $key) {
    $section = (str_starts_with($key, 'notif_') || in_array($key, [
        'view_all', 'mark_all_read', 'unread', 'tab_all', 'tab_unread',
        'priority_critical', 'preferences', 'alerts_widget',
    ], true)) ? 'notifications' : 'admin';
    $adminI18n[$key] = __t($key, $section);
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('title', 'admin'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=7">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="ad-page">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">storefront</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section"><?php echo __t('nav_main', 'admin'); ?></li>
                <li>
                    <a href="index.php" class="nav-link active">
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
                        <span class="badge warning hidden" id="sidebar-low-stock-badge">0</span>
                    </a>
                </li>
                <?php $activePage = 'dashboard';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
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
                <span class="avatar-initial" id="sidebar-user-avatar"><?php echo htmlspecialchars($initial); ?></span>
                <div class="user-info">
                    <p class="name" id="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>
                    </p>
                    <p class="role" id="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
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
                        <h1><?php echo __t('overview', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="current-date"><?php echo __t('loading', 'admin'); ?></span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="ih-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools ad-header-tools">
                    <div id="headerStoreSlot" class="header-store-slot"></div>
                    <span class="ad-store-pill hidden" id="store-pill">
                        <span class="material-icons-round">store</span>
                        <span id="store-pill-text"></span>
                    </span>
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                </div>

                <div class="header-actions ad-header-actions">
                    <?php include __DIR__ . '/includes/notification-bell.php'; ?>
                    <button type="button" class="ad-refresh-btn" id="refreshDashboard" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="dashboardError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="adDashHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="adDashHeroTitle"><?php echo __t('dash_subtitle', 'admin'); ?></h2>
                        <p class="ad-dash-hero__period" id="adDashPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="adDashStoreScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero" role="group" aria-label="<?php echo __t('overview', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading">
                            <span class="ad-kpi__label"><?php echo __t('revenue_today', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="revenue-today-val">—</strong>
                            <span class="ad-kpi__meta" id="revenue-currency"><?php echo htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?></span>
                            <span class="ad-kpi__meta ad-kpi__trend" id="revenue-trend"></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading">
                            <span class="ad-kpi__label"><?php echo __t('sales_today', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="sales-today-val">—</strong>
                            <span class="ad-kpi__meta ad-kpi__trend" id="sales-trend"></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading">
                            <span class="ad-kpi__label"><?php echo __t('month_revenue', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="revenue-month-val">—</strong>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading">
                            <span class="ad-kpi__label"><?php echo __t('low_stock', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="low-stock-val">—</strong>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_main', 'admin'); ?>">
                        <a href="sales.php" class="ad-quick-btn"><span class="material-icons-round">point_of_sale</span><?php echo __t('nav_sales', 'admin'); ?></a>
                        <a href="inventory.php" class="ad-quick-btn"><span class="material-icons-round">inventory_2</span><?php echo __t('nav_inventory', 'admin'); ?></a>
                        <a href="analytics.php" class="ad-quick-btn"><span class="material-icons-round">insights</span><?php echo __t('nav_analytics', 'admin'); ?></a>
                        <a href="../cashier/pos.php" class="ad-quick-btn ad-quick-btn--accent"><span class="material-icons-round">shopping_cart</span><?php echo __t('nav_pos', 'admin'); ?></a>
                    </nav>
                </section>

                <a href="inventory.php" class="ad-alert-strip ad-alert-strip--warn ad-dash-alert" id="adLowStockAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">warning_amber</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('low_stock', 'admin'); ?></strong>
                        <span class="ad-alert-strip__msg" id="adLowStockAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <section class="ad-dash-section" aria-labelledby="adDashCustomersTitle">
                    <h3 class="ad-dash-section__title" id="adDashCustomersTitle"><?php echo __t('active_customers', 'admin'); ?></h3>
                    <div class="ad-kpi-grid ad-kpi-grid--single">
                        <article class="ad-kpi ad-kpi--primary ad-kpi--wide is-loading">
                            <span class="ad-kpi__label"><?php echo __t('active_customers', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="active-customers-val">—</strong>
                            <p class="ad-kpi__hint" id="customers-meta"><?php echo __t('customer_base_hint', 'admin'); ?></p>
                        </article>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="adDashChartsTitle">
                    <h3 class="ad-dash-section__title" id="adDashChartsTitle"><?php echo __t('dash_section_charts', 'admin'); ?></h3>
                    <div class="charts-section ad-charts ad-charts--panels">
                        <div class="ad-panel chart-container main-chart">
                            <div class="ad-panel__head">
                                <h3><?php echo __t('chart_revenue_7mo', 'admin'); ?></h3>
                            </div>
                            <div class="ad-panel__body chart-wrapper">
                                <canvas id="revenueChart"></canvas>
                            </div>
                        </div>
                        <div class="ad-panel chart-container secondary-chart">
                            <div class="ad-panel__head">
                                <h3><?php echo __t('chart_category_month', 'admin'); ?></h3>
                            </div>
                            <div class="ad-panel__body chart-wrapper">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="ad-dash-section" aria-labelledby="adDashActivityTitle">
                    <h3 class="ad-dash-section__title" id="adDashActivityTitle"><?php echo __t('dash_section_activity', 'admin'); ?></h3>
                    <div class="bottom-widgets ad-bottom">
                    <div class="card table-widget ad-tx-widget">
                        <div class="card-header">
                            <h3><?php echo __t('recent_transactions', 'admin'); ?></h3>
                            <a href="sales.php" class="btn-text"><?php echo __t('view_all', 'admin'); ?></a>
                        </div>
                        <div class="table-responsive">
                            <table class="modern-table ad-tx-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_receipt', 'admin'); ?></th>
                                        <th><?php echo __t('col_customer', 'admin'); ?></th>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_amount', 'admin'); ?></th>
                                        <th><?php echo __t('col_status', 'admin'); ?></th>
                                        <th><?php echo __t('col_payment', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="recent-transactions-list">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="side-widgets">
                        <?php
                        $alertsWidgetTitle = 'Alertes et notifications';
                        $alertsWidgetViewAll = 'Voir tout';
                        include __DIR__ . '/includes/notification-alerts-widget.php';
                        ?>
                        <div class="card list-widget">
                            <div class="card-header">
                                <h3><?php echo __t('top_products_30d', 'admin'); ?></h3>
                            </div>
                            <ul class="item-list" id="top-products-list">
                                <li class="item">
                                    <div class="item-details">
                                        <p><?php echo __t('loading', 'admin'); ?></p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
    window.ADMIN_PAGE = window.ADMIN_PAGE || {};
    window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
    window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
    window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?>, api: { base: '../../api/v1/index.php' } };
    window.ADMIN_I18N = <?php echo json_encode($adminI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.NOTIF_API = { base: '../../api/v1/index.php' };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=12"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/notifications/notification-offline.js?v=1"></script>
    <script src="../../assets/js/notifications/notification-bell.js?v=9"></script>
    <script src="../../assets/js/admin/dashboard.js?v=9"></script>
    <script>
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

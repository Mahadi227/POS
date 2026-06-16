<?php
require_once '../../includes/Config/session.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$storeName = '';
$storeCurrency = 'FCFA';
try {
    require_once '../../includes/Database/Database.php';
    $db = Database::getInstance()->getConnection();
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
    'load_dashboard_error', 'load_error_hint', 'vs_yesterday', 'trend_neutral',
    'pay_cash', 'pay_card', 'pay_mobile_money', 'items', 'last_updated',
    'col_receipt', 'col_customer', 'col_date', 'col_amount', 'col_status', 'col_payment',
    'nav_sales', 'nav_inventory', 'nav_pos', 'nav_analytics',
    'cr_notif_title', 'cr_notif_empty', 'cr_notif_mark_read', 'cr_alerts_widget', 'cr_nav_reconciliation',
];
$adminI18n = [];
foreach ($adminI18nKeys as $key) {
    $adminI18n[$key] = __t($key, 'admin');
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
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=5">
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
                    <div class="ad-notif-wrap">
                        <button type="button" class="icon-btn ad-notif-btn" id="adminNotifBtn" aria-label="<?php echo __t('cr_notif_title', 'admin'); ?>" aria-expanded="false" aria-haspopup="true">
                            <span class="material-icons-round">notifications</span>
                            <span class="ad-notif-badge" id="adminNotifBadge" hidden>0</span>
                        </button>
                        <div class="ad-notif-panel" id="adminNotifPanel" role="menu">
                            <div class="ad-notif-panel__head">
                                <strong><?php echo __t('cr_notif_title', 'admin'); ?></strong>
                                <button type="button" class="ad-notif-mark" id="adminNotifMarkRead"><?php echo __t('cr_notif_mark_read', 'admin'); ?></button>
                            </div>
                            <ul class="ad-notif-list" id="adminNotifList"></ul>
                            <a href="cash_registers/dashboard.php" class="ad-notif-footer"><?php echo __t('nav_cash_registers', 'admin'); ?> →</a>
                        </div>
                    </div>
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

                <nav class="ad-quick-nav" aria-label="<?php echo __t('nav_main', 'admin'); ?>">
                    <a href="sales.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_sales', 'admin'); ?></span>
                    </a>
                    <a href="inventory.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_inventory', 'admin'); ?></span>
                    </a>
                    <a href="analytics.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">insights</span>
                        <span><?php echo __t('nav_analytics', 'admin'); ?></span>
                    </a>
                    <a href="../cashier/pos.php" class="ad-quick-nav__item ad-quick-nav__item--accent">
                        <span class="material-icons-round">shopping_cart</span>
                        <span><?php echo __t('nav_pos', 'admin'); ?></span>
                    </a>
                </nav>

                <div class="ad-month-banner">
                    <div class="ad-month-banner__content">
                        <span class="ad-month-banner__label"><?php echo __t('month_revenue', 'admin'); ?></span>
                        <strong id="revenue-month-val">—</strong>
                    </div>
                    <a href="sales.php" class="ad-month-banner__cta">
                        <?php echo __t('view_all_sales', 'admin'); ?>
                        <span class="material-icons-round">arrow_forward</span>
                    </a>
                </div>

                <div class="stat-cards ad-stat-cards">
                    <div class="card stat-card is-loading">
                        <div class="card-icon primary">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('revenue_today', 'admin'); ?></h3>
                            <h2 id="revenue-today-val">—</h2>
                            <p class="trend" id="revenue-trend"></p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon success">
                            <span class="material-icons-round">shopping_bag</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('sales_today', 'admin'); ?></h3>
                            <h2 id="sales-today-val">—</h2>
                            <p class="trend" id="sales-trend"></p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon warning">
                            <span class="material-icons-round">warning_amber</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('low_stock', 'admin'); ?></h3>
                            <h2 id="low-stock-val">—</h2>
                            <p class="trend negative">
                                <span class="material-icons-round">inventory_2</span>
                                <a href="inventory.php" class="ad-inline-link"><?php echo __t('view_inventory', 'admin'); ?></a>
                            </p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon info">
                            <span class="material-icons-round">groups</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('active_customers', 'admin'); ?></h3>
                            <h2 id="active-customers-val">—</h2>
                            <p class="trend ad-trend--neutral"><?php echo __t('customer_base_hint', 'admin'); ?></p>
                        </div>
                    </div>
                </div>

                <div class="charts-section ad-charts">
                    <div class="card chart-container main-chart">
                        <div class="card-header">
                            <h3><?php echo __t('chart_revenue_7mo', 'admin'); ?></h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    <div class="card chart-container secondary-chart">
                        <div class="card-header">
                            <h3><?php echo __t('chart_category_month', 'admin'); ?></h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

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
                        <div class="card list-widget ad-cr-alerts-card">
                            <div class="card-header">
                                <h3><?php echo __t('cr_alerts_widget', 'admin'); ?></h3>
                                <a href="cash_registers/dashboard.php" class="btn-text"><?php echo __t('view_all', 'admin'); ?></a>
                            </div>
                            <div id="crAlertsWidget" class="ad-cr-alerts-body">
                                <p class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></p>
                            </div>
                        </div>
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
            </div>
        </main>
    </div>

    <script>
    window.ADMIN_PAGE = window.ADMIN_PAGE || {};
    window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
    window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
    window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    window.ADMIN_I18N = <?php echo json_encode($adminI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=11"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/admin-notifications.js?v=1"></script>
    <script src="../../assets/js/admin/dashboard.js?v=5"></script>
    <script>
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

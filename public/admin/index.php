<?php
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../../includes/Helpers/StoreScope.php';
RbacGuard::workspace('admin', '../login.php');
require_once __DIR__ . '/../../includes/Helpers/OnboardingGuard.php';
OnboardingGuard::enforceForAdmin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';
require_once __DIR__ . '/../../includes/Helpers/EntitlementGuard.php';
require_once __DIR__ . '/../../includes/Platform/TenantScope.php';
require_once __DIR__ . '/../../includes/Platform/TenantResolver.php';

$storeId = 1;
$storeName = '';
$storeCurrency = 'FCFA';
$db = null;
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

$saasModules = EntitlementGuard::modulesForCurrentTenant();
$adminRoleSlug = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
$hasEcommerce = !empty($saasModules['ecommerce'])
    && in_array($adminRoleSlug, ['super_admin', 'admin', 'manager'], true);

require __DIR__ . '/includes/admin-branding.php';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');

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
    'nav_ecommerce', 'ecom_open_storefront', 'ecom_nav_orders', 'ecom_nav_products', 'ecom_nav_settings',
    'ecom_no_orders', 'col_receipt', 'col_date', 'col_amount', 'col_status',
    'dash_ecom_section', 'dash_ecom_subtitle', 'dash_ecom_manage', 'dash_ecom_online_products',
    'dash_ecom_orders_today', 'dash_ecom_revenue_today', 'dash_ecom_accounts', 'dash_ecom_recent_orders',
    'dash_ecom_view_all', 'dash_ecom_quick_catalog', 'dash_ecom_quick_orders', 'dash_ecom_quick_settings',
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

$todayDateLabel = '';
if (class_exists('IntlDateFormatter')) {
    $dateFormatter = new IntlDateFormatter(
        $locale,
        IntlDateFormatter::FULL,
        IntlDateFormatter::NONE
    );
    if ($dateFormatter !== false) {
        $todayDateLabel = (string) $dateFormatter->format(new DateTime());
    }
}
if ($todayDateLabel === '') {
    $todayDateLabel = date('l, F j, Y');
}
$todayDisplay = __t('today_prefix', 'admin', [$todayDateLabel]);

$ecomStats = [
    'online_products' => 0,
    'web_orders_today' => 0,
    'web_revenue_today' => 0.0,
    'storefront_accounts' => 0,
];
$ecomRecentOrders = [];
$ecomStoreId = $storeId;

if ($hasEcommerce && $db) {
    try {
        require_once __DIR__ . '/../../includes/Platform/SaaSPhase15Migrator.php';
        require_once __DIR__ . '/../../includes/Platform/SaaSPhase16Migrator.php';
        require_once __DIR__ . '/../../includes/Ecommerce/Repositories/EcommerceAdminRepository.php';
        SaaSPhase15Migrator::ensure($db);
        SaaSPhase16Migrator::ensure($db);
        $tenantId = TenantScope::id();
        $ecomRepo = new EcommerceAdminRepository($db);
        $ecomStoreId = $ecomRepo->resolveStoreId($tenantId);
        if ($ecomStoreId <= 0) {
            $ecomStoreId = $storeId;
        }
        $ecomStats = $ecomRepo->dashboardStats($tenantId, $ecomStoreId);
        $ecomOrderResult = $ecomRepo->listWebOrders($tenantId, $ecomStoreId, 5, 0);
        $ecomRecentOrders = $ecomOrderResult['items'] ?? [];
    } catch (Throwable $e) {
        error_log('admin index ecom stats: ' . $e->getMessage());
    }
}

$formatEcomMoney = static function (float $amount) use ($storeCurrency): string {
    return number_format($amount, 2, '.', ' ') . ' ' . $storeCurrency;
};

$formatEcomDate = static function (string $dateTime) use ($locale): string {
    if ($dateTime === '') {
        return '—';
    }
    try {
        $dt = new DateTime($dateTime);
        if (class_exists('IntlDateFormatter')) {
            $fmt = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT);
            $formatted = $fmt->format($dt);
            if ($formatted !== false) {
                return $formatted;
            }
        }
    } catch (Throwable) {
        // fall through
    }

    return $dateTime;
};
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="admin" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/admin-head-theme.php'; ?>
    <title><?php echo __t('title', 'admin'); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css?v=5">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=7">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=2">
    <?php require __DIR__ . '/includes/admin-tail-theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body class="ad-page">
<?php
require_once __DIR__ . '/../../includes/Helpers/ImpersonationBanner.php';
ImpersonationBanner::render('../platform/exit-impersonation.php');
?>
    <div class="admin-layout">
        <aside class="sidebar">
            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>
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
                            <span class="date-display" id="current-date"><?php echo htmlspecialchars($todayDisplay, ENT_QUOTES, 'UTF-8'); ?></span>
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
                        <p class="ad-dash-hero__period" id="adDashPeriod" aria-live="polite"><?php echo htmlspecialchars($todayDateLabel, ENT_QUOTES, 'UTF-8'); ?></p>
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
                        <?php if ($hasEcommerce): ?>
                        <a href="ecommerce/dashboard.php" class="ad-quick-btn ad-quick-btn--ecom"><span class="material-icons-round">storefront</span><?php echo __t('nav_ecommerce', 'admin'); ?></a>
                        <?php endif; ?>
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

                <?php if ($hasEcommerce): ?>
                <section class="ad-dash-section ad-dash-section--ecom" id="adDashEcomSection" aria-labelledby="adDashEcomTitle">
                    <div class="ad-dash-section__head ad-dash-section__head--split">
                        <div>
                            <h3 class="ad-dash-section__title" id="adDashEcomTitle">
                                <span class="material-icons-round" aria-hidden="true">storefront</span>
                                <?php echo __t('dash_ecom_section', 'admin'); ?>
                            </h3>
                            <p class="ad-dash-section__desc"><?php echo __t('dash_ecom_subtitle', 'admin'); ?></p>
                        </div>
                        <div class="ad-dash-section__actions">
                            <a href="ecommerce/dashboard.php" class="ad-text-btn"><?php echo __t('dash_ecom_manage', 'admin'); ?></a>
                            <?php if ($ecomStorefrontUrl !== ''): ?>
                            <a href="<?php echo htmlspecialchars($ecomStorefrontUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ad-text-btn" target="_blank" rel="noopener"><?php echo __t('ecom_open_storefront', 'admin'); ?></a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--ecom" role="group" aria-label="<?php echo __t('dash_ecom_section', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--ecom">
                            <span class="ad-kpi__label"><?php echo __t('dash_ecom_online_products', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="dash-ecom-online"><?php echo (int) $ecomStats['online_products']; ?></strong>
                        </article>
                        <article class="ad-kpi ad-kpi--ecom">
                            <span class="ad-kpi__label"><?php echo __t('dash_ecom_orders_today', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="dash-ecom-orders-today"><?php echo (int) $ecomStats['web_orders_today']; ?></strong>
                        </article>
                        <article class="ad-kpi ad-kpi--ecom">
                            <span class="ad-kpi__label"><?php echo __t('dash_ecom_revenue_today', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="dash-ecom-revenue-today"><?php echo htmlspecialchars($formatEcomMoney((float) $ecomStats['web_revenue_today']), ENT_QUOTES, 'UTF-8'); ?></strong>
                        </article>
                        <article class="ad-kpi ad-kpi--ecom">
                            <span class="ad-kpi__label"><?php echo __t('dash_ecom_accounts', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="dash-ecom-accounts"><?php echo (int) $ecomStats['storefront_accounts']; ?></strong>
                        </article>
                    </div>
                    <div class="ad-ecom-row">
                        <div class="card table-widget ad-tx-widget ad-ecom-orders">
                            <div class="card-header">
                                <h3><?php echo __t('dash_ecom_recent_orders', 'admin'); ?></h3>
                                <a href="ecommerce/orders.php" class="btn-text"><?php echo __t('dash_ecom_view_all', 'admin'); ?></a>
                            </div>
                            <div class="table-responsive">
                                <table class="modern-table ad-tx-table">
                                    <thead>
                                        <tr>
                                            <th><?php echo __t('col_receipt', 'admin'); ?></th>
                                            <th><?php echo __t('col_date', 'admin'); ?></th>
                                            <th><?php echo __t('col_amount', 'admin'); ?></th>
                                            <th><?php echo __t('col_status', 'admin'); ?></th>
                                        </tr>
                                    </thead>
                                    <tbody id="dash-ecom-orders-list">
                                        <?php if ($ecomRecentOrders === []): ?>
                                        <tr><td colspan="4" class="ad-empty-row"><?php echo __t('ecom_no_orders', 'admin'); ?></td></tr>
                                        <?php else: ?>
                                        <?php foreach ($ecomRecentOrders as $order): ?>
                                        <?php
                                            $receipt = (string) ($order['receipt_no'] ?? ('#' . ($order['id'] ?? '')));
                                            $orderStatus = (string) ($order['status'] ?? '');
                                            $orderTotal = (float) ($order['total'] ?? 0);
                                            $orderDate = $formatEcomDate((string) ($order['created_at'] ?? ''));
                                        ?>
                                        <tr>
                                            <td data-label="<?php echo __t('col_receipt', 'admin'); ?>"><?php echo htmlspecialchars($receipt, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="<?php echo __t('col_date', 'admin'); ?>" style="color:var(--text-secondary)"><?php echo htmlspecialchars($orderDate, ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="<?php echo __t('col_amount', 'admin'); ?>" style="font-weight:600"><?php echo htmlspecialchars($formatEcomMoney($orderTotal), ENT_QUOTES, 'UTF-8'); ?></td>
                                            <td data-label="<?php echo __t('col_status', 'admin'); ?>">
                                                <?php if ($orderStatus === 'completed'): ?>
                                                <span class="status-badge success"><?php echo __t('status_completed', 'admin'); ?></span>
                                                <?php else: ?>
                                                <span class="status-badge warning"><?php echo htmlspecialchars($orderStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="ad-ecom-quick card list-widget">
                            <div class="card-header"><h3><?php echo __t('nav_ecommerce', 'admin'); ?></h3></div>
                            <nav class="ad-ecom-quick__links">
                                <a href="ecommerce/products.php" class="ad-ecom-quick__link"><span class="material-icons-round">inventory_2</span><?php echo __t('dash_ecom_quick_catalog', 'admin'); ?></a>
                                <a href="ecommerce/orders.php" class="ad-ecom-quick__link"><span class="material-icons-round">shopping_bag</span><?php echo __t('dash_ecom_quick_orders', 'admin'); ?></a>
                                <?php if (in_array($adminRoleSlug, ['super_admin', 'admin'], true)): ?>
                                <a href="ecommerce/settings.php" class="ad-ecom-quick__link"><span class="material-icons-round">settings</span><?php echo __t('dash_ecom_quick_settings', 'admin'); ?></a>
                                <?php endif; ?>
                                <?php if ($ecomStorefrontUrl !== ''): ?>
                                <a href="<?php echo htmlspecialchars($ecomStorefrontUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ad-ecom-quick__link" target="_blank" rel="noopener"><span class="material-icons-round">open_in_new</span><?php echo __t('ecom_open_storefront', 'admin'); ?></a>
                                <?php endif; ?>
                            </nav>
                        </div>
                    </div>
                </section>
                <?php endif; ?>

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
    window.ADMIN_PAGE.hasEcommerce = <?php echo $hasEcommerce ? 'true' : 'false'; ?>;
    window.ADMIN_PAGE.ecomStorefrontUrl = <?php echo json_encode($ecomStorefrontUrl, JSON_UNESCAPED_UNICODE); ?>;
    window.ADMIN_PAGE.ecomStoreId = <?php echo json_encode($ecomStoreId); ?>;
    window.ADMIN_CONFIG = {
        lang: <?php echo json_encode($activeLang); ?>,
        locale: <?php echo json_encode($locale); ?>,
        accent: <?php echo json_encode($adminAccent); ?>,
        api: { base: '../../api/v1/index.php' },
    };
    window.ADMIN_I18N = <?php echo json_encode($adminI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.NOTIF_API = { base: '../../api/v1/index.php' };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=12"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/notifications/notification-offline.js?v=1"></script>
    <script src="../../assets/js/notifications/notification-bell.js?v=9"></script>
    <script src="../../assets/js/admin/dashboard.js?v=13"></script>
    <script>
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

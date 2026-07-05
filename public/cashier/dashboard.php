<?php

/**
 * Cashier dashboard — today's stats, recent sales, quick access.
 */
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Helpers/RbacGuard.php';
RbacGuard::workspace('cashier', '../login.php');

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

require_once __DIR__ . '/includes/pos-config.php';
require_once __DIR__ . '/includes/cashier-branding.php';

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$displayName = htmlspecialchars($_SESSION['name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($posConfig['store']['name'] ?? 'RetailPOS', ENT_QUOTES, 'UTF-8');
$brandName = htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8');
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');

$dashboardI18nKeys = [
    'loading', 'greeting_morning', 'greeting_afternoon', 'greeting_evening', 'greeting',
    'today_summary', 'connected_as_role', 'no_sales_today', 'open_pos_hint', 'no_payments',
    'last_sale', 'no_sales_yet', 'load_error', 'connection_error', 'error_short', 'last_updated', 'vs_yesterday',
    'pay_cash', 'pay_card', 'pay_mobile_money', 'pay_split', 'active', 'theme', 'open_pos',
    'shift_section', 'shift_closed_title', 'shift_closed_desc', 'shift_open_title', 'shift_opened_at',
    'shift_status_open', 'shift_open_btn', 'shift_close_btn', 'shift_float', 'shift_sales', 'shift_tx',
    'shift_expected', 'shift_open_prompt', 'shift_close_prompt', 'shift_close_hint', 'shift_invalid_float',
    'shift_invalid_count', 'shift_none_open', 'shift_closed_ok', 'shift_migration_hint',
];
$dashboardI18n = [];
foreach ($dashboardI18nKeys as $key) {
    $dashboardI18n[$key] = __t($key, 'dashboard');
}

$changeUrl = '../change_language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="cashier" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/cashier-head-theme.php'; ?>
    <title><?php echo __t('title', 'dashboard'); ?> — <?php echo $brandName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-dashboard.css?v=7">
    <?php echo cashier_theme_css_block($adminAccent); ?>
</head>

<body class="cd-page cd-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header cd-page-header">
                <div class="header-left cd-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn cd-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'dashboard'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('dashboard', 'dashboard'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="dashHeaderDate">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="cd-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools cd-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="cd-header-user user-profile">
                        <div class="user-info">
                            <span class="name" id="headerUserName"><?php echo $displayName; ?></span>
                            <span class="role" id="headerUserRole"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions cd-header-actions">
                    <a href="pos.php" class="cd-header-pos" title="<?php echo __t('open_pos', 'dashboard'); ?>">
                        <span class="material-icons-round">point_of_sale</span>
                        <span class="cd-header-pos__label"><?php echo __t('open_pos', 'dashboard'); ?></span>
                    </a>
                    <button type="button" class="cd-refresh-btn cd-header-refresh" id="dashRefreshBtn" title="<?php echo __t('refresh', 'dashboard'); ?>" aria-label="<?php echo __t('refresh', 'dashboard'); ?>">
                        <span class="material-icons-round" aria-hidden="true">refresh</span>
                        <span class="cd-refresh-btn__label"><?php echo __t('refresh', 'dashboard'); ?></span>
                    </button>
                    <?php $themeToggleClass = 'cd-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="cd-error-banner" id="dashboardError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="cd-error-text"></span>
                </div>

                <nav class="cd-quick-nav" aria-label="<?php echo __t('quick_access', 'dashboard'); ?>">
                    <a href="dashboard.php" class="cd-quick-nav__item cd-quick-nav__item--accent">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('dashboard', 'dashboard'); ?></span>
                    </a>
                    <a href="pos.php" class="cd-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="cd-quick-nav__item">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="cd-quick-nav__item">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="cd-quick-nav__item">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                </nav>
                <section class="cd-hero" aria-label="<?php echo __t('overview', 'dashboard'); ?>">
                    <div class="cd-hero__content">
                        <p class="cd-hero__eyebrow">
                            <span class="material-icons-round" aria-hidden="true">storefront</span>
                            <span id="dashStoreName"><?php echo $storeName; ?></span>
                        </p>
                        <h2 id="heroGreeting"><?php echo sprintf(__t('greeting', 'dashboard'), $displayName); ?></h2>
                        <p class="cd-hero__sub" id="heroSub"><?php echo __t('today_summary', 'dashboard'); ?></p>
                        <div class="cd-hero__meta">
                            <span>
                                <span class="material-icons-round" aria-hidden="true">calendar_today</span>
                                <span id="dashHeroDate">—</span>
                            </span>
                            <span>
                                <span class="material-icons-round" aria-hidden="true">badge</span>
                                <span id="dashRoleBadge"><?php echo sprintf(__t('connected_as_role', 'dashboard'), $displayRole); ?></span>
                            </span>
                        </div>
                    </div>
                    <div class="cd-hero__actions">
                        <span class="cd-hero__clock" id="dashLiveClock" aria-live="polite">--:--:--</span>
                        <span class="cd-hero__date" id="dashLiveDate">—</span>
                        <a href="pos.php" class="cd-btn-pos">
                            <span class="material-icons-round" aria-hidden="true">point_of_sale</span>
                            <?php echo __t('open_pos', 'dashboard'); ?>
                        </a>
                    </div>
                </section>

                <h3 class="cd-section-title"><?php echo __t('stats_today', 'dashboard'); ?></h3>
                <div class="cd-stats cd-summary-cards" id="dashStats">
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--blue">
                            <span class="material-icons-round">receipt_long</span>
                        </div>
                        <div>
                            <div class="cd-stat__label"><?php echo __t('sales_made', 'dashboard'); ?></div>
                            <div class="cd-stat__value is-loading" id="todaySalesCount"><?php echo __t('loading', 'dashboard'); ?></div>
                            <div class="cd-stat__hint" id="lastSaleHint">—</div>
                            <div class="cd-stat__trend" id="salesTrend" hidden></div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--green">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div>
                            <div class="cd-stat__label"><?php echo __t('revenue', 'dashboard'); ?></div>
                            <div class="cd-stat__value is-loading" id="todayRevenue"><?php echo __t('loading', 'dashboard'); ?></div>
                            <div class="cd-stat__hint"><?php echo __t('revenue_hint', 'dashboard'); ?></div>
                            <div class="cd-stat__trend" id="revenueTrend" hidden></div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--purple">
                            <span class="material-icons-round">trending_up</span>
                        </div>
                        <div>
                            <div class="cd-stat__label"><?php echo __t('avg_ticket', 'dashboard'); ?></div>
                            <div class="cd-stat__value is-loading" id="avgTicket"><?php echo __t('loading', 'dashboard'); ?></div>
                            <div class="cd-stat__hint"><?php echo __t('avg_ticket_hint', 'dashboard'); ?></div>
                        </div>
                    </article>
                    <article class="cd-stat">
                        <div class="cd-stat__icon cd-stat__icon--amber">
                            <span class="material-icons-round">schedule</span>
                        </div>
                        <div>
                            <div class="cd-stat__label"><?php echo __t('session', 'dashboard'); ?></div>
                            <div class="cd-stat__value cd-stat__value--session" id="sessionStatus"><?php echo __t('active', 'dashboard'); ?></div>
                            <div class="cd-stat__hint" id="sessionHint"><?php echo sprintf(__t('connected_as_role', 'dashboard'), $displayRole); ?></div>
                        </div>
                    </article>
                </div>

                <h3 class="cd-section-title"><?php echo __t('shift_section', 'dashboard'); ?></h3>
                <section class="cd-shift-panel" id="shiftPanel" aria-label="<?php echo __t('shift_section', 'dashboard'); ?>">
                    <div class="cd-shift cd-shift--loading">
                        <span class="material-icons-round">hourglass_empty</span>
                        <p><?php echo __t('loading', 'dashboard'); ?></p>
                    </div>
                </section>

                <div class="cd-grid">
                    <section class="cd-panel" aria-labelledby="recentSalesHeading">
                        <div class="cd-panel__head">
                            <h3 id="recentSalesHeading"><?php echo __t('recent_sales', 'dashboard'); ?></h3>
                            <a href="sales_history.php"><?php echo __t('view_all', 'dashboard'); ?></a>
                        </div>
                        <ul class="cd-sales-list" id="recentSalesList">
                            <li class="cd-empty cd-skeleton">
                                <span class="material-icons-round">hourglass_empty</span>
                                <p><?php echo __t('loading', 'dashboard'); ?></p>
                            </li>
                        </ul>
                    </section>

                    <section class="cd-panel" aria-labelledby="paymentBreakdownHeading">
                        <div class="cd-panel__head">
                            <h3 id="paymentBreakdownHeading"><?php echo __t('payment_breakdown', 'dashboard'); ?></h3>
                        </div>
                        <div class="cd-pay-bars" id="paymentBars">
                            <div class="cd-empty cd-skeleton">
                                <span class="material-icons-round">hourglass_empty</span>
                                <p><?php echo __t('loading', 'dashboard'); ?></p>
                            </div>
                        </div>
                    </section>
                </div>

                <h3 class="cd-section-title"><?php echo __t('quick_access', 'dashboard'); ?></h3>
                <div class="cd-actions">
                    <a href="pos.php" class="cd-action cd-action--primary">
                        <span class="cd-action__icon"><span class="material-icons-round">point_of_sale</span></span>
                        <h4><?php echo __t('pos_terminal', 'dashboard'); ?></h4>
                        <p><?php echo __t('pos_description', 'dashboard'); ?></p>
                    </a>
                    <a href="sales_history.php" class="cd-action">
                        <span class="cd-action__icon"><span class="material-icons-round">history</span></span>
                        <h4><?php echo __t('history', 'dashboard'); ?></h4>
                        <p><?php echo __t('history_desc', 'dashboard'); ?></p>
                    </a>
                    <a href="returns.php" class="cd-action">
                        <span class="cd-action__icon"><span class="material-icons-round">assignment_return</span></span>
                        <h4><?php echo __t('returns', 'dashboard'); ?></h4>
                        <p><?php echo __t('returns_desc', 'dashboard'); ?></p>
                    </a>
                    <a href="customers.php" class="cd-action">
                        <span class="cd-action__icon"><span class="material-icons-round">people</span></span>
                        <h4><?php echo __t('customers', 'dashboard'); ?></h4>
                        <p><?php echo __t('customers_desc', 'dashboard'); ?></p>
                    </a>
                    <a href="profile.php" class="cd-action">
                        <span class="cd-action__icon"><span class="material-icons-round">person</span></span>
                        <h4><?php echo __t('profile', 'dashboard'); ?></h4>
                        <p><?php echo __t('profile_desc', 'dashboard'); ?></p>
                    </a>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        window.DASHBOARD_CONFIG = <?php echo json_encode([
            'lang' => $activeLang,
            'locale' => $locale,
            'userName' => $_SESSION['name'] ?? 'Cashier',
            'userRole' => $_SESSION['role'] ?? 'Cashier',
            'autoRefreshMs' => 60000,
        ], JSON_UNESCAPED_UNICODE); ?>;
        window.DASHBOARD_I18N = <?php echo json_encode($dashboardI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/dashboard.js?v=5"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>

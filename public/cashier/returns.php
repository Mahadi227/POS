<?php

/**
 * Returns & refunds — receipt lookup, item selection, restock.
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

$returnsI18nKeys = [
    'searching', 'ticket_not_found', 'connection_error', 'already_cancelled', 'cashier_label',
    'items_to_return', 'sold_qty', 'no_items', 'select_items_hint', 'return_details',
    'reason', 'reason_customer', 'reason_defective', 'reason_wrong_item', 'reason_other',
    'refund_method', 'pay_cash', 'pay_card', 'pay_mobile_money', 'notes', 'notes_placeholder',
    'refund_estimated', 'submit_return', 'view_ticket', 'another_ticket',
    'select_at_least_one', 'confirm_return', 'success_new_return', 'history',
    'error_return', 'system_error', 'estimated_refund', 'mark_damaged', 'last_updated',
];
$returnsI18n = [];
foreach ($returnsI18nKeys as $key) {
    $returnsI18n[$key] = __t($key, 'returns');
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
    <title><?php echo __t('title', 'returns'); ?> — <?php echo $brandName; ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-returns.css?v=5">
    <?php echo cashier_theme_css_block($adminAccent); ?>
</head>

<body class="rt-page rt-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header rt-page-header">
                <div class="header-left rt-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn rt-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'returns'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('heading', 'returns'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="rtHeaderDate">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="rt-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools rt-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="rt-header-user user-profile">
                        <div class="user-info">
                            <span class="name"><?php echo $displayName; ?></span>
                            <span class="role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions rt-header-actions">
                    <a href="sales_history.php" class="rt-header-history" title="<?php echo __t('history_link', 'returns'); ?>">
                        <span class="material-icons-round">history</span>
                        <span class="rt-header-history__label"><?php echo __t('history_link', 'returns'); ?></span>
                    </a>
                    <a href="pos.php" class="rt-header-pos" title="<?php echo __t('nav_pos', 'cashier'); ?>">
                        <span class="material-icons-round">point_of_sale</span>
                        <span class="rt-header-pos__label"><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <?php $themeToggleClass = 'rt-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="rt-error-banner" id="returnsError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="rt-error-text"></span>
                </div>

                <nav class="rt-quick-nav" aria-label="<?php echo __t('menu', 'returns'); ?>">
                    <a href="dashboard.php" class="rt-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'cashier'); ?></span>
                    </a>
                    <a href="pos.php" class="rt-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="rt-quick-nav__item">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="rt-quick-nav__item rt-quick-nav__item--accent">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="rt-quick-nav__item">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                </nav>
                <section class="rt-search-hero">
                    <div class="rt-search-hero__icon">
                        <span class="material-icons-round">assignment_return</span>
                    </div>
                    <h2><?php echo __t('hero_title', 'returns'); ?></h2>
                    <p><?php echo __t('hero_sub', 'returns'); ?></p>
                    <div class="rt-search-form">
                        <div class="rt-search-input-wrap">
                            <span class="material-icons-round">confirmation_number</span>
                            <input type="text" id="receiptNumber" placeholder="<?php echo __t('receipt_placeholder', 'returns'); ?>" autocomplete="off"
                                autofocus>
                        </div>
                        <button type="button" class="rt-search-btn" id="searchBtn">
                            <span class="material-icons-round">search</span>
                            <?php echo __t('search_btn', 'returns'); ?>
                        </button>
                    </div>
                    <p class="rt-hint"><?php echo __t('search_hint', 'returns'); ?></p>
                </section>

                <div id="resultArea" aria-live="polite"></div>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
        window.RETURNS_CONFIG = <?php echo json_encode([
            'lang' => $activeLang,
            'locale' => $locale,
        ], JSON_UNESCAPED_UNICODE); ?>;
        window.RETURNS_I18N = <?php echo json_encode($returnsI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/returns.js?v=5"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>

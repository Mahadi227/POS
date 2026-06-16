<?php

/**
 * Sale ticket detail — cashier.
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

$saleId = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($saleId <= 0) {
    header('Location: sales_history.php');
    exit;
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$displayName = htmlspecialchars($_SESSION['name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($posConfig['store']['name'] ?? 'RetailPOS', ENT_QUOTES, 'UTF-8');

$vsI18nKeys = [
    'loading_ticket', 'loading_ticket_message', 'sale_not_found', 'sale_not_found_msg',
    'view_load_error', 'view_load_error_msg', 'back_history', 'total_paid',
    'status_completed', 'status_pending', 'status_cancelled', 'cashier_label', 'customer',
    'store_label', 'payment_ref', 'items_section', 'col_product', 'col_qty', 'col_unit_price',
    'col_line_total', 'no_items', 'summary_section', 'subtotal', 'discount', 'tax',
    'grand_total', 'print_receipt', 'sales_history_link', 'sku_label', 'walk_in',
    'pay_cash', 'pay_card', 'pay_mobile_money', 'pay_split', 'popup_blocked', 'last_updated',
];
$vsI18n = [];
foreach ($vsI18nKeys as $key) {
    $vsI18n[$key] = __t($key, 'sales');
}

$posConfig['lang'] = $activeLang;
$posConfig['locale'] = $locale;

$changeUrl = '../change_language.php';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <?php include __DIR__ . '/../includes/theme-head.php'; ?>
    <title><?php echo __t('view_title', 'sales'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-view-sale.css?v=2">
</head>

<body class="vs-page vs-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header vs-page-header">
                <div class="header-left vs-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn vs-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'sales'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('view_heading', 'sales'); ?></h1>
                        <div class="header-subline">
                            <span class="vs-receipt-label" id="pageReceiptLabel">#<?php echo $saleId; ?></span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="date-display" id="vsHeaderDate">—</span>
                            <span class="header-dot vs-last-updated-dot" aria-hidden="true">·</span>
                            <span class="vs-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools vs-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="vs-header-user user-profile">
                        <div class="user-info">
                            <span class="name"><?php echo $displayName; ?></span>
                            <span class="role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions vs-header-actions">
                    <a href="sales_history.php" class="vs-header-back" title="<?php echo __t('back_history', 'sales'); ?>">
                        <span class="material-icons-round">arrow_back</span>
                        <span class="vs-header-back__label"><?php echo __t('back_history', 'sales'); ?></span>
                    </a>
                    <a href="pos.php" class="vs-header-pos" title="<?php echo __t('open_pos', 'sales'); ?>">
                        <span class="material-icons-round">point_of_sale</span>
                        <span class="vs-header-pos__label"><?php echo __t('open_pos', 'sales'); ?></span>
                    </a>
                    <?php $themeToggleClass = 'vs-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="vs-error-banner" id="viewSaleError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="vs-error-text"></span>
                </div>

                <nav class="vs-quick-nav" aria-label="<?php echo __t('menu', 'sales'); ?>">
                    <a href="dashboard.php" class="vs-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'cashier'); ?></span>
                    </a>
                    <a href="pos.php" class="vs-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="vs-quick-nav__item vs-quick-nav__item--accent">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="vs-quick-nav__item">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="vs-quick-nav__item">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                </nav>

                <p class="vs-store-line"><?php echo $storeName; ?></p>

                <div id="saleDetailRoot">
                    <div class="vs-state">
                        <span class="material-icons-round">hourglass_empty</span>
                        <h3><?php echo __t('loading_ticket', 'sales'); ?></h3>
                        <p><?php echo __t('loading_ticket_message', 'sales'); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.VS_CONFIG = <?php echo json_encode([
        'lang' => $activeLang,
        'locale' => $locale,
        'saleId' => $saleId,
    ], JSON_UNESCAPED_UNICODE); ?>;
    window.VS_I18N = <?php echo json_encode($vsI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    window.VS_SALE_ID = <?php echo json_encode($saleId); ?>;
    </script>
    <script src="../../assets/js/cashier/view-sale.js?v=2"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>

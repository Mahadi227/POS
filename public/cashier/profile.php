<?php

/**
 * Cashier profile — personal info and password.
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

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$displayName = htmlspecialchars($_SESSION['name'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Cashier', ENT_QUOTES, 'UTF-8');

$profileI18nKeys = [
    'account_active', 'account_inactive', 'sales_today', 'revenue_today', 'last_login',
    'personal_info', 'full_name', 'email_address', 'email_hint', 'member_since', 'store_label',
    'security', 'password_section_hint', 'current_password', 'new_password', 'confirm_password',
    'current_password_ph', 'new_password_ph', 'confirm_password_ph', 'show_password',
    'save_changes', 'dashboard_link', 'logout', 'load_error', 'name_min_length',
    'password_min_length', 'password_mismatch', 'current_password_required',
    'connection_error', 'updated_success', 'error',
];
$profileI18n = [];
foreach ($profileI18nKeys as $key) {
    $profileI18n[$key] = __t($key, 'settings');
}
$profileI18n['last_updated'] = __t('last_updated', 'dashboard');

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
    <title><?php echo __t('profile_title', 'settings'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-profile.css?v=3">
</head>

<body class="cp-page cp-pro-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay" aria-hidden="true"></div>

        <main class="main-content">
            <header class="top-header cp-page-header">
                <div class="header-left cp-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn cp-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'settings'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('my_profile', 'settings'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="cpHeaderDate">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="cp-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools cp-header-tools">
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                    <div class="cp-header-user user-profile">
                        <div class="user-info">
                            <span class="name"><?php echo $displayName; ?></span>
                            <span class="role"><?php echo $displayRole; ?></span>
                        </div>
                    </div>
                </div>

                <div class="header-actions cp-header-actions">
                    <a href="dashboard.php" class="cp-header-dash" title="<?php echo __t('dashboard_link', 'settings'); ?>">
                        <span class="material-icons-round">dashboard</span>
                        <span class="cp-header-dash__label"><?php echo __t('dashboard_link', 'settings'); ?></span>
                    </a>
                    <?php $themeToggleClass = 'cp-header-icon'; include __DIR__ . '/includes/theme-toggle.php'; ?>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="cp-error-banner" id="profileError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="cp-error-text"></span>
                </div>

                <nav class="cp-quick-nav" aria-label="<?php echo __t('menu', 'settings'); ?>">
                    <a href="dashboard.php" class="cp-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'cashier'); ?></span>
                    </a>
                    <a href="pos.php" class="cp-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_pos', 'cashier'); ?></span>
                    </a>
                    <a href="sales_history.php" class="cp-quick-nav__item">
                        <span class="material-icons-round">receipt_long</span>
                        <span><?php echo __t('nav_sales_history', 'cashier'); ?></span>
                    </a>
                    <a href="returns.php" class="cp-quick-nav__item">
                        <span class="material-icons-round">assignment_return</span>
                        <span><?php echo __t('nav_returns', 'cashier'); ?></span>
                    </a>
                    <a href="customers.php" class="cp-quick-nav__item">
                        <span class="material-icons-round">people</span>
                        <span><?php echo __t('nav_customers', 'cashier'); ?></span>
                    </a>
                    <a href="profile.php" class="cp-quick-nav__item cp-quick-nav__item--accent">
                        <span class="material-icons-round">account_circle</span>
                        <span><?php echo __t('nav_profile', 'cashier'); ?></span>
                    </a>
                </nav>

                <div id="profileRoot">
                    <div class="cp-loading">
                        <span class="material-icons-round">hourglass_empty</span>
                        <p><?php echo __t('loading_profile', 'settings'); ?></p>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="cp-toast" id="profileToast" role="status" aria-live="polite"></div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.PROFILE_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    window.PROFILE_I18N = <?php echo json_encode($profileI18n, JSON_UNESCAPED_UNICODE); ?>;
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/profile.js?v=3"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>
</body>

</html>

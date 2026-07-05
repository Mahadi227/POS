<?php
require __DIR__ . '/includes/bootstrap.php';

if (PlatformSessionAuth::isLoggedIn()) {
    header('Location: dashboard.php');
    exit;
}

$lang = htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8');
$platAccent = '#7c3aed';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="platform" data-theme-accent="<?php echo $platAccent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $platAccent; ?>">
    <meta name="theme-accent" content="<?php echo $platAccent; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('plat_login_title', 'platform'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../../assets/css/platform-login.css?v=1">
</head>
<body class="plat-login-page">
<div class="plat-login-shell">
    <aside class="plat-login-hero" aria-hidden="false">
        <div class="plat-login-hero__inner">
            <div class="plat-login-hero__brand">
                <span class="material-icons-round" aria-hidden="true">cloud</span>
                <span>RetailPOS Cloud</span>
            </div>
            <h2><?php echo __t('plat_login_hero_title', 'platform'); ?></h2>
            <p><?php echo __t('plat_login_hero_desc', 'platform'); ?></p>
            <ul class="plat-login-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">business</span>
                    <?php echo __t('plat_login_feat_tenants', 'platform'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">credit_card</span>
                    <?php echo __t('plat_login_feat_billing', 'platform'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">monitor_heart</span>
                    <?php echo __t('plat_login_feat_status', 'platform'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <?php echo __t('plat_login_feat_support', 'platform'); ?>
                </li>
            </ul>
            <div class="plat-login-hero__links">
                <a href="../status.php"><?php echo __t('plat_login_status_link', 'platform'); ?></a>
            </div>
        </div>
    </aside>

    <main class="plat-login-panel">
        <div class="plat-login-toolbar">
            <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
            <button type="button" class="plat-theme-toggle" id="platThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'platform'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container">
            <div class="plat-login-badge">
                <span class="material-icons-round" aria-hidden="true">admin_panel_settings</span>
                <?php echo __t('plat_login_badge', 'platform'); ?>
            </div>

            <div class="auth-header">
                <h1><?php echo __t('plat_title', 'platform'); ?></h1>
                <p><?php echo __t('plat_login_subtitle', 'platform'); ?></p>
            </div>

            <div id="alertBox" class="alert" aria-live="polite" style="display:none;"></div>

            <form id="platLoginForm" method="post" action="#" novalidate>
                <div class="form-group">
                    <label for="email"><?php echo __t('email', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">email</span>
                        <input type="email" id="email" name="email" required autofocus
                               autocomplete="username"
                               placeholder="<?php echo htmlspecialchars(__t('plat_login_email_placeholder', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><?php echo __t('password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">lock</span>
                        <input type="password" id="password" name="password" required
                               autocomplete="current-password"
                               placeholder="••••••••">
                        <button type="button" class="material-icons-round toggle-password" id="togglePassword"
                                aria-label="<?php echo htmlspecialchars(__t('plat_login_show_password', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="rememberEmail" name="remember">
                        <?php echo __t('plat_login_remember_email', 'platform'); ?>
                    </label>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <span id="btnText"><?php echo __t('submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>
            </form>

            <nav class="plat-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('plat_login_nav', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                <a href="../login.php"><?php echo __t('plat_tenant_login', 'platform'); ?></a>
                <a href="../signup-organization.php"><?php echo __t('signup_submit', 'saas'); ?></a>
                <a href="../developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
            </nav>
        </div>
    </main>
</div>

<script>
window.PLATFORM_LOGIN_CONFIG = {
    apiBase: <?php echo json_encode($apiBase . '?request=platform/login', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'plat_login_error_generic' => __t('plat_login_error_generic', 'platform'),
        'plat_login_server_error' => __t('plat_login_server_error', 'platform'),
        'plat_login_show_password' => __t('plat_login_show_password', 'platform'),
        'plat_login_hide_password' => __t('plat_login_hide_password', 'platform'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../../assets/js/app-theme.js?v=3"></script>
<script src="../../assets/js/platform/platform-login.js?v=1"></script>
</body>
</html>

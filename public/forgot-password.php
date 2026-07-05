<?php
require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Auth/RoleRedirect.php';
require_once __DIR__ . '/../includes/Helpers/TenantBootstrap.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$resolvedTenant = TenantBootstrap::resolveTenant();
$tenantParam = defined('SAAS_TENANT_PARAM') ? SAAS_TENANT_PARAM : 'tenant';
$tenantSlug = $resolvedTenant['slug'] ?? trim((string) ($_GET[$tenantParam] ?? ''));
$tenantName = $resolvedTenant['name'] ?? '';
$hasTenant = $tenantSlug !== '';

$branding = TenantBootstrap::branding();
$themeAccent = $branding['accent'] ?? ($hasTenant ? '#2563eb' : '#7c3aed');
$brandName = $hasTenant
    ? ($branding['brand_name'] ?? ($tenantName !== '' ? $tenantName : 'RetailPOS'))
    : 'RetailPOS Cloud';
$logoUrl = $hasTenant ? ($branding['logo_url'] ?? null) : null;

$loginHref = 'login.php';
if ($tenantSlug !== '') {
    $loginHref .= '?' . urlencode($tenantParam) . '=' . urlencode($tenantSlug);
}

if (isset($_SESSION['user_id'])) {
    header('Location: ' . RoleRedirect::publicPath($_SESSION['role'] ?? ''));
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$accentEsc = htmlspecialchars($themeAccent, ENT_QUOTES, 'UTF-8');
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('forgot_title', 'auth'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=3">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=3">
    <link rel="stylesheet" href="../assets/css/tenant-login.css?v=5">
    <style>:root { --primary: <?php echo $accentEsc; ?>; --signup-accent: <?php echo $accentEsc; ?>; }</style>
    <?php if (!empty($branding['favicon_url'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($branding['favicon_url'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body class="signup-org-page tenant-login-page tenant-forgot-page<?php echo $hasTenant ? ' tenant-login-page--tenant' : ' tenant-login-page--cloud'; ?>">
<div class="signup-org-shell tenant-login-shell">
    <aside class="signup-org-hero tenant-login-hero" aria-label="<?php echo htmlspecialchars(__t('forgot_hero_aria', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__grid" aria-hidden="true"></div>
        <div class="signup-org-hero__inner tenant-login-hero__inner">
            <div class="signup-org-hero__brand tenant-login-hero__brand">
                <?php if ($logoUrl): ?>
                <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                     alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                     class="tenant-login-hero__logo">
                <?php else: ?>
                <span class="material-icons-round" aria-hidden="true">lock_reset</span>
                <span><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <p class="signup-org-hero__eyebrow">
                <span class="material-icons-round" aria-hidden="true">verified_user</span>
                <?php echo __t('forgot_hero_eyebrow', 'auth'); ?>
            </p>

            <h2><?php echo __t('forgot_hero_title', 'auth'); ?></h2>
            <p class="signup-org-hero__lead"><?php echo __t('forgot_hero_desc', 'auth'); ?></p>

            <ul class="signup-org-features tenant-login-features">
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">mail</span></span>
                    <span><?php echo __t('forgot_feat_email', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">schedule</span></span>
                    <span><?php echo __t('forgot_feat_expire', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">security</span></span>
                    <span><?php echo __t('forgot_feat_secure', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">privacy_tip</span></span>
                    <span><?php echo __t('forgot_feat_privacy', 'auth'); ?></span>
                </li>
            </ul>

            <div class="signup-org-trust" aria-label="<?php echo htmlspecialchars(__t('signup_trust_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">lock</span>
                    <span><?php echo __t('signup_trust_secure', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">mail_lock</span>
                    <span><?php echo __t('forgot_trust_email', 'auth'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <span><?php echo __t('signup_trust_support', 'saas'); ?></span>
                </div>
            </div>
        </div>
    </aside>

    <main class="signup-org-panel tenant-login-panel">
        <div class="signup-org-toolbar tenant-login-toolbar">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="signup-theme-toggle tenant-theme-toggle" id="tenantThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container tenant-login-card signup-org-card">
            <div class="signup-org-badge tenant-login-badge">
                <span class="material-icons-round" aria-hidden="true">vpn_key</span>
                <?php echo __t('forgot_badge', 'auth'); ?>
            </div>

            <div class="auth-header tenant-login-header signup-org-header">
                <h1><?php echo __t('forgot_heading', 'auth'); ?></h1>
                <p><?php echo __t('forgot_subtitle', 'auth'); ?></p>
                <?php if ($hasTenant): ?>
                <p class="tenant-login-org">
                    <span class="material-icons-round" aria-hidden="true">apartment</span>
                    <?php echo __t('login_org_label', 'auth'); ?>
                    <code><?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?></code>
                </p>
                <?php endif; ?>
            </div>

            <div id="alertBox" class="alert tenant-login-alert" aria-live="polite" hidden></div>

            <form id="forgotPasswordForm" method="post" action="#" novalidate>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="email"><?php echo __t('email', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">email</span>
                        <input type="email" id="email" name="email" required autofocus
                               autocomplete="username"
                               placeholder="<?php echo htmlspecialchars(__t('email_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <small class="field-hint"><?php echo __t('forgot_email_hint', 'auth'); ?></small>
                </div>

                <button type="submit" class="btn-primary tenant-login-submit signup-org-submit" id="submitBtn">
                    <span id="btnText"><?php echo __t('forgot_submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>

                <p class="signup-org-terms tenant-forgot-note"><?php echo __t('forgot_help_note', 'auth'); ?></p>
            </form>

            <p class="signup-org-signin tenant-forgot-back">
                <a href="<?php echo htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8'); ?>" class="tenant-forgot-back__link">
                    <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                    <?php echo __t('forgot_back_login', 'auth'); ?>
                </a>
            </p>

            <nav class="signup-org-footer-links tenant-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('forgot_nav', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!$hasTenant): ?>
                <a href="signup-organization.php"><?php echo __t('login_create_org', 'auth'); ?></a>
                <?php endif; ?>
                <a href="marketing/contact.php"><?php echo __t('signup_footer_contact', 'saas'); ?></a>
                <a href="marketing/faq.php"><?php echo __t('signup_footer_faq', 'saas'); ?></a>
            </nav>

            <p class="signup-org-copy">© <?php echo $year; ?> <?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </main>
</div>

<script>
window.TENANT_FORGOT_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php?request=auth/forgot-password', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'error_generic' => __t('error_generic', 'auth'),
        'server_error' => __t('server_error', 'auth'),
        'forgot_success' => __t('forgot_success', 'auth'),
        'invalid_email' => __t('invalid_email', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/tenant-forgot-password.js?v=2"></script>
</body>
</html>

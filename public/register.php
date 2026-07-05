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

if (isset($_SESSION['user_id'])) {
    header('Location: ' . RoleRedirect::publicPath($_SESSION['role'] ?? ''));
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$accentEsc = htmlspecialchars($themeAccent, ENT_QUOTES, 'UTF-8');
$subtitleKey = $hasTenant ? 'register_subtitle_tenant' : 'register_subtitle_cloud';
$subtitleText = $hasTenant
    ? __t('register_subtitle_tenant', 'auth', ['store' => $brandName])
    : __t('register_subtitle_cloud', 'auth');
$tenantQuery = $hasTenant ? '?' . urlencode($tenantParam) . '=' . urlencode($tenantSlug) : '';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('register_title', 'auth'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=3">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=2">
    <link rel="stylesheet" href="../assets/css/tenant-login.css?v=3">
    <style>:root { --primary: <?php echo $accentEsc; ?>; --signup-accent: <?php echo $accentEsc; ?>; }</style>
    <?php if (!empty($branding['favicon_url'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($branding['favicon_url'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body class="signup-org-page tenant-login-page tenant-register-page<?php echo $hasTenant ? ' tenant-login-page--tenant' : ' tenant-login-page--cloud'; ?>">
<div class="signup-org-shell tenant-login-shell">
    <aside class="signup-org-hero tenant-login-hero" aria-label="<?php echo htmlspecialchars(__t('register_hero_aria', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__inner tenant-login-hero__inner">
            <div class="signup-org-hero__brand tenant-login-hero__brand">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                         class="tenant-login-hero__logo">
                <?php else: ?>
                    <span class="material-icons-round" aria-hidden="true"><?php echo $hasTenant ? 'person_add' : 'group_add'; ?></span>
                    <span><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <h2><?php echo __t('register_hero_title', 'auth'); ?></h2>
            <p><?php echo __t('register_hero_desc', 'auth'); ?></p>
            <ul class="signup-org-features tenant-login-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">verified_user</span>
                    <?php echo __t('register_feat_activation', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">badge</span>
                    <?php echo __t('register_feat_role', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">apartment</span>
                    <?php echo __t('register_feat_team', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">lock</span>
                    <?php echo __t('register_feat_secure', 'auth'); ?>
                </li>
            </ul>
            <div class="signup-org-hero__links tenant-login-hero__links">
                <a href="signup-organization.php"><?php echo __t('register_new_org', 'auth'); ?> →</a>
                <a href="marketing/"><?php echo __t('register_marketing_link', 'auth'); ?></a>
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

        <div class="auth-container tenant-login-card tenant-register-card">
            <div class="signup-org-badge tenant-login-badge">
                <span class="material-icons-round" aria-hidden="true">how_to_reg</span>
                <?php echo __t('register_badge', 'auth'); ?>
            </div>

            <div class="auth-header tenant-login-header">
                <h1><?php echo __t('register_heading', 'auth'); ?></h1>
                <p><?php echo htmlspecialchars($subtitleText, ENT_QUOTES, 'UTF-8'); ?></p>
                <?php if ($hasTenant): ?>
                <p class="tenant-login-org" id="tenantOrgBadge">
                    <span class="material-icons-round" aria-hidden="true">apartment</span>
                    <?php echo __t('login_org_label', 'auth'); ?>
                    <code><?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?></code>
                </p>
                <?php endif; ?>
            </div>

            <div id="alertBox" class="alert tenant-login-alert" aria-live="polite" hidden></div>

            <form id="registerForm" method="post" action="#" novalidate>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <?php if (!$hasTenant): ?>
                <div class="form-group" id="workspaceGroup">
                    <label for="tenant_slug"><?php echo __t('login_workspace', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">apartment</span>
                        <input type="text" id="tenant_slug" name="tenant_slug"
                               pattern="[a-z0-9-]+" required autocomplete="organization"
                               placeholder="<?php echo htmlspecialchars(__t('login_workspace_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                               value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <small class="field-hint"><?php echo __t('register_workspace_hint', 'auth'); ?></small>
                </div>
                <?php else: ?>
                <input type="hidden" id="tenant_slug" value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="name"><?php echo __t('full_name', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">person</span>
                        <input type="text" id="name" name="name" required autofocus autocomplete="name"
                               placeholder="<?php echo htmlspecialchars(__t('name_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="email"><?php echo __t('email', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">email</span>
                        <input type="email" id="email" name="email" required autocomplete="username"
                               placeholder="<?php echo htmlspecialchars(__t('email_placeholder_register', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password"><?php echo __t('password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">lock</span>
                        <input type="password" id="password" name="password" required minlength="8"
                               autocomplete="new-password" aria-describedby="passwordHint passwordStrength"
                               placeholder="••••••••">
                        <button type="button" class="material-icons-round toggle-password" id="togglePassword"
                                aria-label="<?php echo htmlspecialchars(__t('show_password', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                    <div class="password-strength" id="passwordStrength" aria-hidden="true">
                        <span class="password-strength__bar"></span>
                        <span class="password-strength__bar"></span>
                        <span class="password-strength__bar"></span>
                        <span class="password-strength__bar"></span>
                    </div>
                    <small class="field-hint" id="passwordHint"><?php echo __t('register_password_hint', 'auth'); ?></small>
                </div>

                <div class="form-group">
                    <label for="password_confirmation"><?php echo __t('confirm_password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">lock</span>
                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                               autocomplete="new-password" aria-describedby="matchHint"
                               placeholder="••••••••">
                        <button type="button" class="material-icons-round toggle-password" id="togglePasswordConfirm"
                                aria-label="<?php echo htmlspecialchars(__t('show_password_confirm', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                    <small class="field-hint" id="matchHint" hidden></small>
                </div>

                <button type="submit" class="btn-primary tenant-login-submit" id="submitBtn">
                    <span id="btnText"><?php echo __t('register_submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>
            </form>

            <p class="tenant-register-signin">
                <?php echo __t('has_account', 'auth'); ?>
                <a href="login.php<?php echo $tenantQuery; ?>"><?php echo __t('login_link', 'auth'); ?></a>
            </p>

            <nav class="signup-org-footer-links tenant-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('register_nav', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <a href="signup-organization.php"><?php echo __t('register_new_org', 'auth'); ?></a>
                <a href="login.php<?php echo $tenantQuery; ?>"><?php echo __t('login_link', 'auth'); ?></a>
                <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
                <a href="marketing/"><?php echo __t('register_marketing_link', 'auth'); ?></a>
            </nav>
        </div>
    </main>
</div>

<script>
window.TENANT_REGISTER_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php?request=auth/register', JSON_THROW_ON_ERROR); ?>,
    hasTenant: <?php echo $hasTenant ? 'true' : 'false'; ?>,
    tenantParam: <?php echo json_encode($tenantParam, JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'error_generic' => __t('error_generic', 'auth'),
        'server_error' => __t('server_error', 'auth'),
        'password_mismatch' => __t('password_mismatch', 'auth'),
        'password_min_length' => __t('password_min_length', 'auth'),
        'register_error' => __t('register_error', 'auth'),
        'register_success' => __t('register_success', 'auth'),
        'show_password' => __t('show_password', 'auth'),
        'hide_password' => __t('hide_password', 'auth'),
        'show_password_confirm' => __t('show_password_confirm', 'auth'),
        'hide_password_confirm' => __t('hide_password_confirm', 'auth'),
        'workspace_required' => __t('login_workspace_required', 'auth'),
        'password_match' => __t('register_password_match', 'auth'),
        'password_no_match' => __t('password_mismatch', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/tenant-register.js?v=2"></script>
</body>
</html>

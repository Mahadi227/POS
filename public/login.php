<?php
require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Auth/RoleRedirect.php';
require_once __DIR__ . '/../includes/Helpers/TenantBootstrap.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/TenantRepository.php';

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

$orgOptions = [];
if (!$hasTenant) {
    $db = Database::getInstance()->getConnection();
    TenantSchemaMigrator::ensure($db);
    if (TenantSchemaMigrator::isReady($db)) {
        $orgOptions = (new TenantRepository($db))->listLoginOptions();
    }
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? '';
    header('Location: ' . RoleRedirect::publicPath($role));
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$accentEsc = htmlspecialchars($themeAccent, ENT_QUOTES, 'UTF-8');
$subtitleKey = $hasTenant ? 'login_subtitle_tenant' : 'login_subtitle_cloud';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('title', 'auth'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=3">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=3">
    <link rel="stylesheet" href="../assets/css/tenant-login.css?v=4">
    <style>:root { --primary: <?php echo $accentEsc; ?>; --signup-accent: <?php echo $accentEsc; ?>; }</style>
    <?php if (!empty($branding['favicon_url'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($branding['favicon_url'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body class="signup-org-page tenant-login-page<?php echo $hasTenant ? ' tenant-login-page--tenant' : ' tenant-login-page--cloud'; ?>">
<div class="signup-org-shell tenant-login-shell">
    <aside class="signup-org-hero tenant-login-hero" aria-label="<?php echo htmlspecialchars(__t('login_hero_aria', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__grid" aria-hidden="true"></div>
        <div class="signup-org-hero__inner tenant-login-hero__inner">
            <div class="signup-org-hero__brand tenant-login-hero__brand">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                         class="tenant-login-hero__logo">
                <?php else: ?>
                    <span class="material-icons-round" aria-hidden="true"><?php echo $hasTenant ? 'storefront' : 'cloud'; ?></span>
                    <span><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <h2><?php echo __t('login_hero_title', 'auth'); ?></h2>
            <p class="signup-org-hero__lead"><?php echo __t('login_hero_desc', 'auth'); ?></p>
            <ul class="signup-org-features tenant-login-features">
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">point_of_sale</span></span>
                    <span><?php echo __t('login_feat_pos', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></span>
                    <span><?php echo __t('login_feat_inventory', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">insights</span></span>
                    <span><?php echo __t('login_feat_reports', 'auth'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">shield</span></span>
                    <span><?php echo __t('login_feat_security', 'auth'); ?></span>
                </li>
            </ul>
            <div class="signup-org-trust" aria-label="<?php echo htmlspecialchars(__t('signup_trust_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">lock</span>
                    <span><?php echo __t('signup_trust_secure', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">cloud_done</span>
                    <span><?php echo __t('signup_trust_cloud', 'saas'); ?></span>
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

        <div class="auth-container tenant-login-card">
            <div class="signup-org-badge tenant-login-badge">
                <span class="material-icons-round" aria-hidden="true">badge</span>
                <?php echo __t('login_badge', 'auth'); ?>
            </div>

            <div class="auth-header tenant-login-header">
                <h1><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p><?php echo __t($subtitleKey, 'auth'); ?></p>
                <?php if ($hasTenant): ?>
                <p class="tenant-login-org" id="tenantOrgBadge">
                    <span class="material-icons-round" aria-hidden="true">apartment</span>
                    <?php echo __t('login_org_label', 'auth'); ?>
                    <code><?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?></code>
                </p>
                <?php endif; ?>
            </div>

            <div id="alertBox" class="alert tenant-login-alert" aria-live="polite" hidden></div>

            <form id="loginForm" method="post" action="#" novalidate>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">

                <?php if (!$hasTenant): ?>
                <div class="form-group" id="workspaceGroup">
                    <label for="tenant_slug_select"><?php echo __t('login_workspace', 'auth'); ?></label>

                    <?php if ($orgOptions !== []): ?>
                    <div class="workspace-picker" id="workspacePicker">
                        <?php if (count($orgOptions) > 8): ?>
                        <div class="workspace-picker__search">
                            <span class="material-icons-round" aria-hidden="true">search</span>
                            <input type="search" id="workspaceSearch" class="workspace-picker__filter"
                                   placeholder="<?php echo htmlspecialchars(__t('login_workspace_filter', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                                   autocomplete="off" aria-controls="tenant_slug_select">
                        </div>
                        <?php endif; ?>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">apartment</span>
                            <select id="tenant_slug_select" required aria-describedby="workspaceHint">
                                <option value=""><?php echo __t('login_workspace_select', 'auth'); ?></option>
                                <?php foreach ($orgOptions as $org):
                                    $slug = (string) $org['slug'];
                                    $selected = ($tenantSlug !== '' && $tenantSlug === $slug) ? ' selected' : '';
                                ?>
                                <option value="<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selected; ?>>
                                    <?php echo htmlspecialchars($org['name'], ENT_QUOTES, 'UTF-8'); ?>
                                    (<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="button" class="workspace-manual-link" id="workspaceManualToggle">
                            <?php echo __t('login_workspace_manual', 'auth'); ?>
                        </button>
                    </div>

                    <div class="workspace-manual" id="workspaceManual" hidden>
                        <div class="input-icon-wrapper">
                            <span class="material-icons-round" aria-hidden="true">apartment</span>
                            <input type="text" id="tenant_slug_manual"
                                   pattern="[a-z0-9-]+" autocomplete="organization"
                                   placeholder="<?php echo htmlspecialchars(__t('login_workspace_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                                   value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                        </div>
                        <button type="button" class="workspace-manual-link" id="workspacePickerToggle">
                            <?php echo __t('login_workspace_choose_list', 'auth'); ?>
                        </button>
                    </div>

                    <input type="hidden" id="tenant_slug" name="tenant_slug"
                           value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    <?php else: ?>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">apartment</span>
                        <input type="text" id="tenant_slug" name="tenant_slug"
                               pattern="[a-z0-9-]+" required autocomplete="organization"
                               placeholder="<?php echo htmlspecialchars(__t('login_workspace_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                               value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <?php endif; ?>

                    <small class="field-hint" id="workspaceHint"><?php echo __t('login_workspace_hint', 'auth'); ?></small>
                </div>
                <?php else: ?>
                <input type="hidden" id="tenant_slug" value="<?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>

                <div class="form-group">
                    <label for="email"><?php echo __t('email', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">email</span>
                        <input type="email" id="email" name="email" required
                               autocomplete="username"
                               placeholder="<?php echo htmlspecialchars(__t('email_placeholder', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
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
                                aria-label="<?php echo htmlspecialchars(__t('show_password', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                </div>

                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" id="remember" name="remember">
                        <?php echo __t('remember_me', 'auth'); ?>
                    </label>
                    <a href="forgot-password.php<?php echo $hasTenant ? '?' . urlencode($tenantParam) . '=' . urlencode($tenantSlug) : ''; ?>"
                       class="forgot-link"><?php echo __t('forgot_password', 'auth'); ?></a>
                </div>

                <button type="submit" class="btn-primary tenant-login-submit" id="submitBtn">
                    <span id="btnText"><?php echo __t('submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>
            </form>

            <nav class="signup-org-footer-links tenant-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('login_nav', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <?php if (!$hasTenant): ?>
                <a href="signup-organization.php"><?php echo __t('login_create_org', 'auth'); ?></a>
                <?php endif; ?>
                <a href="marketing/pricing.php"><?php echo __t('signup_footer_pricing', 'saas'); ?></a>
                <a href="marketing/contact.php"><?php echo __t('signup_footer_contact', 'saas'); ?></a>
                <a href="marketing/faq.php"><?php echo __t('signup_footer_faq', 'saas'); ?></a>
            </nav>
        </div>
    </main>
</div>

<script>
window.TENANT_LOGIN_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php?request=auth/login', JSON_THROW_ON_ERROR); ?>,
    hasTenant: <?php echo $hasTenant ? 'true' : 'false'; ?>,
    hasOrgList: <?php echo $orgOptions !== [] ? 'true' : 'false'; ?>,
    tenantParam: <?php echo json_encode($tenantParam, JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'error_generic' => __t('error_generic', 'auth'),
        'server_error' => __t('server_error', 'auth'),
        'show_password' => __t('show_password', 'auth'),
        'hide_password' => __t('hide_password', 'auth'),
        'workspace_required' => __t('login_workspace_required', 'auth'),
        'workspace_select_required' => __t('login_workspace_select_required', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/tenant-login.js?v=3"></script>
</body>
</html>

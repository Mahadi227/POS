<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Helpers/RbacGuard.php';
require_once __DIR__ . '/../includes/Helpers/TenantBootstrap.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

RbacGuard::requireRoles(['super_admin', 'admin'], 'login.php');

$tenantBranding = TenantBootstrap::branding();
$pageAccent = $tenantBranding['accent'] ?? '#7c3aed';
$accentEsc = htmlspecialchars($pageAccent, ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$themeAccent = $pageAccent;

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('apikeys_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-api-keys.css?v=2">
    <style>:root { --billing-accent: <?php echo $accentEsc; ?>; --apikeys-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="apikeys-page billing-page">
<div class="apikeys-app billing-app">
    <div class="billing-topbar">
        <div class="billing-topbar__left">
            <a href="admin/index.php" class="billing-back">
                <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                <?php echo __t('billing_back_admin', 'saas'); ?>
            </a>
        </div>
        <div class="billing-topbar__actions">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="billing-theme-toggle" id="apikeysThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>
    </div>

    <header class="apikeys-hero apikeys-header">
        <div class="billing-badge">
            <span class="material-icons-round" aria-hidden="true">key</span>
            <?php echo __t('apikeys_badge', 'saas'); ?>
        </div>
        <h1><?php echo __t('apikeys_title', 'saas'); ?></h1>
        <p><?php echo __t('apikeys_subtitle', 'saas'); ?></p>
        <a href="developers/index.php" class="apikeys-dev-cta">
            <span class="material-icons-round" aria-hidden="true" style="font-size:18px;">code</span>
            <?php echo __t('apikeys_dev_portal', 'saas'); ?> →
        </a>
    </header>

    <div id="apikeysAlert" class="apikeys-alert" aria-live="polite"></div>

    <div id="apikeySecretBox" class="apikeys-secret-box" hidden>
        <p><?php echo __t('apikeys_copy', 'saas'); ?></p>
        <code id="apikeySecretValue"></code>
        <div class="apikeys-secret-actions">
            <button type="button" class="apikeys-btn-secondary" id="apikeySecretCopy">
                <?php echo __t('apikeys_secret_copy', 'saas'); ?>
            </button>
            <button type="button" class="apikeys-btn-secondary" id="apikeySecretDismiss">
                <?php echo __t('apikeys_secret_dismiss', 'saas'); ?>
            </button>
        </div>
    </div>

    <section class="apikeys-locked" id="apikeysLocked" hidden>
        <span class="apikeys-locked__icon" aria-hidden="true">
            <span class="material-icons-round">lock</span>
        </span>
        <div>
            <h2><?php echo __t('apikeys_locked_title', 'saas'); ?></h2>
            <p><?php echo __t('apikeys_locked', 'saas'); ?></p>
            <a href="billing.php" class="apikeys-upgrade-btn">
                <span class="material-icons-round" aria-hidden="true" style="font-size:18px;">upgrade</span>
                <?php echo __t('billing_upgrade', 'saas'); ?>
            </a>
        </div>
    </section>

    <section class="apikeys-grid" id="apikeysMain" hidden>
        <article class="plat-panel apikeys-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">add_circle</span>
                <?php echo __t('apikeys_create', 'saas'); ?>
            </h2>
            <form id="apikeyForm" class="apikey-form" novalidate>
                <div class="apikey-field">
                    <label for="apikeyName"><?php echo __t('apikeys_name', 'saas'); ?></label>
                    <input type="text" id="apikeyName" name="name" maxlength="128" required
                           placeholder="<?php echo htmlspecialchars(__t('apikeys_name_placeholder', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <fieldset class="apikey-scopes-field">
                    <legend><?php echo __t('apikeys_scopes', 'saas'); ?></legend>
                    <div id="apikeyScopes" class="apikey-scopes" aria-live="polite"></div>
                </fieldset>
                <button type="submit" class="apikeys-submit-btn" id="apikeySubmitBtn">
                    <span class="btn-label"><?php echo __t('apikeys_create', 'saas'); ?></span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>
        </article>

        <article class="plat-panel apikeys-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">vpn_key</span>
                <?php echo __t('apikeys_list_title', 'saas'); ?>
            </h2>
            <div id="apikeyList" class="apikey-list" aria-live="polite">
                <div class="apikeys-loading">
                    <span class="spinner" aria-hidden="true"></span>
                    <?php echo __t('loading', 'saas'); ?>…
                </div>
            </div>
        </article>
    </section>

    <nav class="billing-footer-links" aria-label="<?php echo htmlspecialchars(__t('apikeys_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <a href="billing.php"><?php echo __t('billing_title', 'saas'); ?></a>
        <a href="webhooks.php"><?php echo __t('webhooks_title', 'saas'); ?></a>
        <a href="branding.php"><?php echo __t('billing_link_branding', 'saas'); ?></a>
        <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
    </nav>
</div>

<script>
window.APIKEYS_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'loading' => __t('loading', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'apikeys_created' => __t('apikeys_created', 'saas'),
        'apikeys_copy' => __t('apikeys_copy', 'saas'),
        'apikeys_secret_copy' => __t('apikeys_secret_copy', 'saas'),
        'apikeys_secret_copied' => __t('apikeys_secret_copied', 'saas'),
        'apikeys_secret_dismiss' => __t('apikeys_secret_dismiss', 'saas'),
        'apikeys_revoke' => __t('apikeys_revoke', 'saas'),
        'apikeys_revoked_ok' => __t('apikeys_revoked_ok', 'saas'),
        'apikeys_confirm_revoke' => __t('apikeys_confirm_revoke', 'saas'),
        'apikeys_no_keys' => __t('apikeys_no_keys', 'saas'),
        'apikeys_last_used' => __t('apikeys_last_used', 'saas'),
        'apikeys_active' => __t('apikeys_active', 'saas'),
        'apikeys_revoked' => __t('apikeys_revoked', 'saas'),
        'apikeys_create_error' => __t('apikeys_create_error', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/api-keys.js?v=2"></script>
</body>
</html>

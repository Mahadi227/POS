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
    <title><?php echo __t('webhooks_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-webhooks.css?v=2">
    <style>:root { --billing-accent: <?php echo $accentEsc; ?>; --webhooks-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="webhooks-page billing-page">
<div class="webhooks-app billing-app">
    <div class="billing-topbar">
        <div class="billing-topbar__left">
            <a href="admin/index.php" class="billing-back">
                <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                <?php echo __t('billing_back_admin', 'saas'); ?>
            </a>
        </div>
        <div class="billing-topbar__actions">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="billing-theme-toggle" id="webhooksThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>
    </div>

    <header class="webhooks-hero webhooks-header">
        <div class="billing-badge">
            <span class="material-icons-round" aria-hidden="true">webhook</span>
            <?php echo __t('webhooks_badge', 'saas'); ?>
        </div>
        <h1><?php echo __t('webhooks_title', 'saas'); ?></h1>
        <p><?php echo __t('webhooks_subtitle', 'saas'); ?></p>
    </header>

    <div id="webhooksAlert" class="webhooks-alert" aria-live="polite"></div>

    <div id="webhookSecretBox" class="webhooks-secret-box" hidden>
        <p><?php echo __t('webhooks_secret', 'saas'); ?></p>
        <code id="webhookSecretValue"></code>
        <div class="webhooks-secret-actions">
            <button type="button" class="webhooks-btn-secondary" id="webhookSecretCopy">
                <?php echo __t('webhooks_secret_copy', 'saas'); ?>
            </button>
            <button type="button" class="webhooks-btn-secondary" id="webhookSecretDismiss">
                <?php echo __t('webhooks_secret_dismiss', 'saas'); ?>
            </button>
        </div>
    </div>

    <section class="webhooks-locked" id="webhooksLocked" hidden>
        <span class="webhooks-locked__icon" aria-hidden="true">
            <span class="material-icons-round">lock</span>
        </span>
        <div>
            <h2><?php echo __t('webhooks_locked_title', 'saas'); ?></h2>
            <p><?php echo __t('webhooks_locked', 'saas'); ?></p>
            <a href="billing.php" class="webhooks-upgrade-btn">
                <span class="material-icons-round" aria-hidden="true" style="font-size:18px;">upgrade</span>
                <?php echo __t('billing_upgrade', 'saas'); ?>
            </a>
        </div>
    </section>

    <section class="webhooks-grid" id="webhooksMain" hidden>
        <article class="plat-panel webhooks-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">link</span>
                <?php echo __t('webhooks_endpoints', 'saas'); ?>
            </h2>
            <form id="webhookForm" class="webhook-form" novalidate>
                <div class="webhook-field">
                    <label for="webhookUrl"><?php echo __t('webhooks_url', 'saas'); ?></label>
                    <input type="url" id="webhookUrl" name="url" required placeholder="https://example.com/hooks/retailpos">
                </div>
                <div class="webhook-field">
                    <label for="webhookDesc"><?php echo __t('webhooks_description', 'saas'); ?></label>
                    <input type="text" id="webhookDesc" name="description" maxlength="255" placeholder="<?php echo htmlspecialchars(__t('webhooks_desc_placeholder', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                </div>
                <fieldset class="webhook-events-field">
                    <legend><?php echo __t('webhooks_events', 'saas'); ?></legend>
                    <div id="webhookEvents" class="webhook-events" aria-live="polite"></div>
                </fieldset>
                <button type="submit" class="webhooks-submit-btn" id="webhookSubmitBtn">
                    <span class="btn-label"><?php echo __t('webhooks_add', 'saas'); ?></span>
                    <span class="spinner" aria-hidden="true"></span>
                </button>
            </form>
            <div id="webhookEndpointList" class="webhook-endpoint-list" aria-live="polite"></div>
        </article>

        <article class="plat-panel webhooks-panel">
            <div class="webhooks-toolbar">
                <h2>
                    <span class="material-icons-round" aria-hidden="true">history</span>
                    <?php echo __t('webhooks_deliveries', 'saas'); ?>
                </h2>
                <button type="button" class="webhooks-test-btn" id="webhookTestBtn">
                    <?php echo __t('webhooks_test', 'saas'); ?>
                </button>
            </div>
            <div id="webhookDeliveryList" class="webhook-delivery-list" aria-live="polite"></div>
        </article>
    </section>

    <nav class="billing-footer-links" aria-label="<?php echo htmlspecialchars(__t('webhooks_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <a href="billing.php"><?php echo __t('billing_title', 'saas'); ?></a>
        <a href="api-keys.php"><?php echo __t('apikeys_title', 'saas'); ?></a>
        <a href="branding.php"><?php echo __t('billing_link_branding', 'saas'); ?></a>
        <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
    </nav>
</div>

<script>
window.WEBHOOKS_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'loading' => __t('loading', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'webhooks_secret' => __t('webhooks_secret', 'saas'),
        'webhooks_secret_copy' => __t('webhooks_secret_copy', 'saas'),
        'webhooks_secret_copied' => __t('webhooks_secret_copied', 'saas'),
        'webhooks_active' => __t('webhooks_active', 'saas'),
        'webhooks_inactive' => __t('webhooks_inactive', 'saas'),
        'webhooks_delete' => __t('webhooks_delete', 'saas'),
        'webhooks_deleted' => __t('webhooks_deleted', 'saas'),
        'webhooks_test_ok' => __t('webhooks_test_ok', 'saas'),
        'webhooks_created' => __t('webhooks_created', 'saas'),
        'webhooks_create_error' => __t('webhooks_create_error', 'saas'),
        'webhooks_confirm_delete' => __t('webhooks_confirm_delete', 'saas'),
        'webhooks_no_deliveries' => __t('webhooks_no_deliveries', 'saas'),
        'webhooks_no_endpoints' => __t('webhooks_no_endpoints', 'saas'),
        'webhooks_col_event' => __t('webhooks_col_event', 'saas'),
        'webhooks_col_url' => __t('webhooks_col_url', 'saas'),
        'webhooks_col_status' => __t('webhooks_col_status', 'saas'),
        'webhooks_col_attempts' => __t('webhooks_col_attempts', 'saas'),
        'webhooks_col_date' => __t('webhooks_col_date', 'saas'),
        'webhooks_status_ok' => __t('webhooks_status_ok', 'saas'),
        'webhooks_status_failed' => __t('webhooks_status_failed', 'saas'),
        'webhooks_status_pending' => __t('webhooks_status_pending', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/webhooks.js?v=2"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Helpers/RbacGuard.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

RbacGuard::requireRoles(['super_admin', 'admin'], 'login.php');

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('webhooks_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-webhooks.css?v=1">
</head>
<body class="webhooks-page">
<div class="webhooks-shell">
    <header class="webhooks-header">
        <a href="admin/index.php" class="webhooks-back"><span class="material-icons-round">arrow_back</span> Admin</a>
        <h1><?php echo __t('webhooks_title', 'saas'); ?></h1>
        <p><?php echo __t('webhooks_subtitle', 'saas'); ?></p>
    </header>

    <section class="plat-panel" id="webhooksLocked" hidden>
        <p><?php echo __t('webhooks_locked', 'saas'); ?></p>
        <a href="billing.php" class="btn-primary"><?php echo __t('billing_upgrade', 'saas'); ?></a>
    </section>

    <section class="webhooks-grid" id="webhooksMain" hidden>
        <article class="plat-panel">
            <h2><?php echo __t('webhooks_endpoints', 'saas'); ?></h2>
            <form id="webhookForm" class="webhook-form">
                <label><?php echo __t('webhooks_url', 'saas'); ?>
                    <input type="url" id="webhookUrl" required placeholder="https://example.com/hooks/retailpos">
                </label>
                <label><?php echo __t('webhooks_description', 'saas'); ?>
                    <input type="text" id="webhookDesc" maxlength="255">
                </label>
                <fieldset>
                    <legend><?php echo __t('webhooks_events', 'saas'); ?></legend>
                    <div id="webhookEvents" class="webhook-events"></div>
                </fieldset>
                <button type="submit" class="btn-primary"><?php echo __t('webhooks_add', 'saas'); ?></button>
            </form>
            <div id="webhookEndpointList" class="webhook-endpoint-list"></div>
        </article>

        <article class="plat-panel">
            <div class="webhooks-toolbar">
                <h2><?php echo __t('webhooks_deliveries', 'saas'); ?></h2>
                <button type="button" class="btn-secondary" id="webhookTestBtn"><?php echo __t('webhooks_test', 'saas'); ?></button>
            </div>
            <div id="webhookDeliveryList" class="webhook-delivery-list"></div>
        </article>
    </section>
</div>
<script>
window.WEBHOOKS_CONFIG = {
    apiBase: '../api/v1/index.php',
    i18n: <?php echo json_encode([
        'loading' => __t('loading', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'webhooks_secret' => __t('webhooks_secret', 'saas'),
        'webhooks_active' => __t('webhooks_active', 'saas'),
        'webhooks_inactive' => __t('webhooks_inactive', 'saas'),
        'webhooks_delete' => __t('webhooks_delete', 'saas'),
        'webhooks_test_ok' => __t('webhooks_test_ok', 'saas'),
        'webhooks_created' => __t('webhooks_created', 'saas'),
        'webhooks_confirm_delete' => __t('webhooks_confirm_delete', 'saas'),
        'webhooks_no_deliveries' => __t('webhooks_no_deliveries', 'saas'),
        'webhooks_no_endpoints' => __t('webhooks_no_endpoints', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/saas/webhooks.js?v=1"></script>
</body>
</html>

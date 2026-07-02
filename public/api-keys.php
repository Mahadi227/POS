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
    <title><?php echo __t('apikeys_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-api-keys.css?v=1">
</head>
<body class="apikeys-page">
<div class="apikeys-shell">
    <header class="apikeys-header">
        <a href="admin/index.php" class="apikeys-back"><span class="material-icons-round">arrow_back</span> Admin</a>
        <h1><?php echo __t('apikeys_title', 'saas'); ?></h1>
        <p><?php echo __t('apikeys_subtitle', 'saas'); ?></p>
        <a href="developers/index.php" class="apikeys-dev-link"><?php echo __t('apikeys_dev_portal', 'saas'); ?></a>
    </header>

    <section class="plat-panel" id="apikeysLocked" hidden>
        <p><?php echo __t('apikeys_locked', 'saas'); ?></p>
        <a href="billing.php" class="btn-primary"><?php echo __t('billing_upgrade', 'saas'); ?></a>
    </section>

    <section class="plat-panel" id="apikeysMain" hidden>
        <form id="apikeyForm" class="apikey-form">
            <label><?php echo __t('apikeys_name', 'saas'); ?>
                <input type="text" id="apikeyName" maxlength="128" required placeholder="Production integration">
            </label>
            <fieldset>
                <legend><?php echo __t('apikeys_scopes', 'saas'); ?></legend>
                <div id="apikeyScopes" class="apikey-scopes"></div>
            </fieldset>
            <button type="submit" class="btn-primary"><?php echo __t('apikeys_create', 'saas'); ?></button>
        </form>
        <div id="apikeyList" class="apikey-list"></div>
    </section>
</div>
<script>
window.APIKEYS_CONFIG = {
    apiBase: '../api/v1/index.php',
    i18n: <?php echo json_encode([
        'loading' => __t('loading', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'apikeys_created' => __t('apikeys_created', 'saas'),
        'apikeys_copy' => __t('apikeys_copy', 'saas'),
        'apikeys_revoke' => __t('apikeys_revoke', 'saas'),
        'apikeys_confirm_revoke' => __t('apikeys_confirm_revoke', 'saas'),
        'apikeys_no_keys' => __t('apikeys_no_keys', 'saas'),
        'apikeys_last_used' => __t('apikeys_last_used', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/saas/api-keys.js?v=1"></script>
</body>
</html>

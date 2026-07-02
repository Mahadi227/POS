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
    <title><?php echo __t('branding_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-branding.css?v=1">
</head>
<body class="branding-page">
<div class="branding-shell">
    <header class="branding-header">
        <a href="admin/index.php" class="branding-back"><span class="material-icons-round">arrow_back</span> Admin</a>
        <h1><?php echo __t('branding_title', 'saas'); ?></h1>
        <p><?php echo __t('branding_subtitle', 'saas'); ?></p>
    </header>

    <div id="brandingAlert" class="branding-alert" hidden></div>

    <section class="branding-panel" id="brandingLocked" hidden>
        <p><?php echo __t('branding_locked', 'saas'); ?> <a href="billing.php"><?php echo __t('billing_upgrade', 'saas'); ?></a></p>
    </section>

    <form class="branding-form" id="brandingForm" hidden>
        <section class="branding-panel">
            <h2><?php echo __t('branding_identity', 'saas'); ?></h2>
            <label><?php echo __t('branding_name', 'saas'); ?></label>
            <input type="text" id="brandName" maxlength="64">
            <label><?php echo __t('branding_accent', 'saas'); ?></label>
            <input type="color" id="brandAccent" value="#2563eb">
        </section>
        <section class="branding-panel">
            <h2><?php echo __t('branding_logo', 'saas'); ?></h2>
            <img id="logoPreview" alt="" class="branding-preview" hidden>
            <input type="file" id="logoFile" accept="image/*">
        </section>
        <section class="branding-panel">
            <h2><?php echo __t('branding_domain', 'saas'); ?></h2>
            <label><?php echo __t('branding_custom_domain', 'saas'); ?></label>
            <input type="text" id="customDomain" placeholder="erp.example.com">
            <p class="branding-hint"><?php echo __t('branding_domain_hint', 'saas'); ?></p>
        </section>
        <button type="submit" class="btn-primary"><?php echo __t('branding_save', 'saas'); ?></button>
    </form>

    <section class="branding-panel" id="brandingUsage">
        <h2><?php echo __t('branding_usage_title', 'saas'); ?></h2>
        <div id="usageGrid" class="branding-usage-grid"><p><?php echo __t('loading', 'saas'); ?></p></div>
    </section>
</div>
<script>
window.BRANDING_CONFIG = {
    apiBase: '../api/v1/index.php',
    i18n: <?php echo json_encode([
        'save_ok' => __t('branding_save_ok', 'saas'),
        'save_error' => __t('branding_save_error', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'usage_unlimited' => __t('branding_usage_unlimited', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>,
};
</script>
<script src="../assets/js/saas/branding.js?v=1"></script>
</body>
</html>

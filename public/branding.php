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
    <title><?php echo __t('branding_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-branding.css?v=2">
    <style>:root { --billing-accent: <?php echo $accentEsc; ?>; --branding-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="branding-page billing-page">
<div class="branding-app billing-app">
    <div class="billing-topbar">
        <div class="billing-topbar__left">
            <a href="admin/index.php" class="billing-back">
                <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                <?php echo __t('billing_back_admin', 'saas'); ?>
            </a>
        </div>
        <div class="billing-topbar__actions">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="billing-theme-toggle" id="brandingThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>
    </div>

    <header class="branding-hero branding-header">
        <div class="billing-badge">
            <span class="material-icons-round" aria-hidden="true">palette</span>
            <?php echo __t('branding_badge', 'saas'); ?>
        </div>
        <h1><?php echo __t('branding_title', 'saas'); ?></h1>
        <p><?php echo __t('branding_subtitle', 'saas'); ?></p>
    </header>

    <div id="brandingAlert" class="branding-alert" aria-live="polite"></div>

    <section class="branding-locked" id="brandingLocked" hidden>
        <span class="branding-locked__icon" aria-hidden="true">
            <span class="material-icons-round">lock</span>
        </span>
        <div>
            <h2><?php echo __t('branding_locked_title', 'saas'); ?></h2>
            <p>
                <?php echo __t('branding_locked', 'saas'); ?>
                <a href="billing.php"><?php echo __t('billing_upgrade', 'saas'); ?></a>
            </p>
        </div>
    </section>

    <form class="branding-form" id="brandingForm" hidden novalidate>
        <div class="branding-form-grid">
            <section class="branding-panel">
                <h2>
                    <span class="material-icons-round" aria-hidden="true">badge</span>
                    <?php echo __t('branding_identity', 'saas'); ?>
                </h2>
                <div class="branding-field">
                    <label for="brandName"><?php echo __t('branding_name', 'saas'); ?></label>
                    <input type="text" id="brandName" name="brand_name" maxlength="64" autocomplete="organization">
                </div>
                <div class="branding-field">
                    <label for="brandAccent"><?php echo __t('branding_accent', 'saas'); ?></label>
                    <input type="color" id="brandAccent" name="accent" value="#2563eb">
                </div>
            </section>

            <section class="branding-panel">
                <h2>
                    <span class="material-icons-round" aria-hidden="true">image</span>
                    <?php echo __t('branding_logo', 'saas'); ?>
                </h2>
                <div class="branding-logo-zone">
                    <div class="branding-preview-wrap">
                        <img id="logoPreview" alt="" class="branding-preview" hidden>
                        <span id="logoPlaceholder" class="branding-preview-placeholder">
                            <span class="material-icons-round" aria-hidden="true">hide_image</span>
                            <?php echo __t('branding_logo_empty', 'saas'); ?>
                        </span>
                    </div>
                    <input type="file" id="logoFile" class="branding-file-input" accept="image/*">
                    <div class="branding-logo-actions">
                        <button type="button" class="branding-logo-delete-btn" id="logoDeleteBtn" hidden>
                            <span class="material-icons-round" aria-hidden="true">delete</span>
                            <?php echo __t('branding_logo_delete', 'saas'); ?>
                        </button>
                    </div>
                    <p class="branding-hint"><?php echo __t('branding_logo_hint', 'saas'); ?></p>
                </div>
            </section>

            <section class="branding-panel branding-panel--wide">
                <h2>
                    <span class="material-icons-round" aria-hidden="true">language</span>
                    <?php echo __t('branding_domain', 'saas'); ?>
                </h2>
                <div class="branding-field">
                    <label for="customDomain"><?php echo __t('branding_custom_domain', 'saas'); ?></label>
                    <input type="text" id="customDomain" name="custom_domain" placeholder="erp.example.com" autocomplete="off">
                </div>
                <p class="branding-hint"><?php echo __t('branding_domain_hint', 'saas'); ?></p>
            </section>
        </div>

        <div class="branding-form-actions">
            <button type="submit" class="branding-save-btn" id="brandingSaveBtn">
                <span class="btn-label"><?php echo __t('branding_save', 'saas'); ?></span>
                <span class="spinner" aria-hidden="true"></span>
            </button>
        </div>
    </form>

    <section class="branding-panel" id="brandingUsage">
        <h2>
            <span class="material-icons-round" aria-hidden="true">insights</span>
            <?php echo __t('branding_usage_title', 'saas'); ?>
        </h2>
        <div id="usageGrid" class="branding-usage-grid" aria-live="polite">
            <div class="branding-loading">
                <span class="spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'saas'); ?>…
            </div>
        </div>
    </section>

    <nav class="billing-footer-links" aria-label="<?php echo htmlspecialchars(__t('branding_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <a href="billing.php"><?php echo __t('billing_title', 'saas'); ?></a>
        <a href="api-keys.php"><?php echo __t('apikeys_title', 'saas'); ?></a>
        <a href="webhooks.php"><?php echo __t('webhooks_title', 'saas'); ?></a>
        <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
    </nav>
</div>

<script>
window.BRANDING_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'save_ok' => __t('branding_save_ok', 'saas'),
        'save_error' => __t('branding_save_error', 'saas'),
        'logo_delete_ok' => __t('branding_logo_delete_ok', 'saas'),
        'logo_delete_error' => __t('branding_logo_delete_error', 'saas'),
        'logo_delete_confirm' => __t('branding_logo_delete_confirm', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'usage_unlimited' => __t('branding_usage_unlimited', 'saas'),
        'loading' => __t('loading', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/branding.js?v=3"></script>
</body>
</html>

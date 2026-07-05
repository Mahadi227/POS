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

RbacGuard::requireRoles(['super_admin', 'admin', 'manager'], 'login.php');

$branding = TenantBootstrap::branding();
$billingAccent = $branding['accent'] ?? '#7c3aed';
$accentEsc = htmlspecialchars($billingAccent, ENT_QUOTES, 'UTF-8');
$upgrade = htmlspecialchars($_GET['upgrade'] ?? '', ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$themeAccent = $billingAccent;

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
    <title><?php echo __t('billing_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=2">
    <style>:root { --billing-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="billing-page">
<div class="billing-app">
    <div class="billing-topbar">
        <div class="billing-topbar__left">
            <a href="admin/index.php" class="billing-back">
                <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                <?php echo __t('billing_back_admin', 'saas'); ?>
            </a>
        </div>
        <div class="billing-topbar__actions">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="billing-theme-toggle" id="billingThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>
    </div>

    <header class="billing-hero billing-header">
        <div class="billing-badge">
            <span class="material-icons-round" aria-hidden="true">credit_card</span>
            <?php echo __t('billing_badge', 'saas'); ?>
        </div>
        <h1><?php echo __t('billing_title', 'saas'); ?></h1>
        <p><?php echo __t('billing_subtitle', 'saas'); ?></p>
        <?php if ($upgrade !== ''): ?>
        <div class="billing-upgrade-hint" role="status">
            <?php echo __t('billing_upgrade_hint', 'saas'); ?>:
            <strong><?php echo $upgrade; ?></strong>
        </div>
        <?php endif; ?>
    </header>

    <div id="billingAlert" class="billing-alert" aria-live="polite"></div>

    <section class="billing-current plat-panel" id="billingCurrent" aria-live="polite">
        <div class="billing-loading">
            <span class="spinner" aria-hidden="true"></span>
            <?php echo __t('loading', 'saas'); ?>…
        </div>
    </section>

    <section class="billing-plans plat-panel">
        <h2><?php echo __t('billing_plans_title', 'saas'); ?></h2>

        <div class="billing-providers" id="billingProviders" role="tablist" aria-label="<?php echo htmlspecialchars(__t('billing_providers_label', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="billing-provider-btn is-active" data-provider="stripe" role="tab" aria-selected="true">
                <span class="material-icons-round" aria-hidden="true">payments</span>
                <?php echo __t('billing_provider_stripe', 'saas'); ?>
            </button>
            <button type="button" class="billing-provider-btn" data-provider="paystack" role="tab" aria-selected="false">
                <span class="material-icons-round" aria-hidden="true">account_balance</span>
                <?php echo __t('billing_provider_paystack', 'saas'); ?>
            </button>
            <button type="button" class="billing-provider-btn" data-provider="mobile_money" role="tab" aria-selected="false">
                <span class="material-icons-round" aria-hidden="true">smartphone</span>
                <?php echo __t('billing_provider_mm', 'saas'); ?>
            </button>
        </div>

        <div class="billing-mm-fields" id="billingMmFields" hidden>
            <label for="billingMmPhone"><?php echo __t('billing_mm_phone', 'saas'); ?>
                <input type="tel" id="billingMmPhone" placeholder="+221...">
            </label>
            <label for="billingMmProvider"><?php echo __t('billing_mm_provider', 'saas'); ?>
                <select id="billingMmProvider">
                    <option value="wave">Wave</option>
                    <option value="orange">Orange Money</option>
                    <option value="mtn">MTN</option>
                    <option value="moov">Moov</option>
                </select>
            </label>
        </div>

        <div class="billing-plan-grid" id="billingPlans"></div>
    </section>

    <nav class="billing-footer-links" aria-label="<?php echo htmlspecialchars(__t('billing_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <a href="branding.php"><?php echo __t('billing_link_branding', 'saas'); ?></a>
        <a href="api-keys.php"><?php echo __t('apikeys_title', 'saas'); ?></a>
        <a href="webhooks.php"><?php echo __t('webhooks_title', 'saas'); ?></a>
        <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
    </nav>
</div>

<script>
window.BILLING_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'current_plan' => __t('billing_current_plan', 'saas'),
        'trial_ends' => __t('billing_trial_ends', 'saas'),
        'usage_stores' => __t('billing_usage_stores', 'saas'),
        'usage_users' => __t('billing_usage_users', 'saas'),
        'upgrade' => __t('billing_upgrade', 'saas'),
        'plan_current' => __t('billing_plan_current', 'saas'),
        'billing_status' => __t('billing_status', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'checkout_error' => __t('billing_checkout_error', 'saas'),
        'billing_success_msg' => __t('billing_success_msg', 'saas'),
        'mm_pending' => __t('billing_mm_pending', 'saas'),
        'loading' => __t('loading', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>,
    success: <?php echo json_encode(isset($_GET['success'])); ?>,
    sessionId: <?php echo json_encode($_GET['session_id'] ?? ''); ?>,
    reference: <?php echo json_encode($_GET['reference'] ?? ''); ?>,
    provider: <?php echo json_encode($_GET['provider'] ?? ''); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/billing.js?v=2"></script>
</body>
</html>

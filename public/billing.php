<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Helpers/RbacGuard.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

RbacGuard::requireRoles(['super_admin', 'admin', 'manager'], 'login.php');

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
$upgrade = htmlspecialchars($_GET['upgrade'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('billing_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/admin.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=1">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=1">
    <link rel="stylesheet" href="../assets/css/saas-onboarding.css?v=1">
</head>
<body class="billing-page">
<div class="billing-shell">
    <header class="billing-header">
        <a href="admin/index.php" class="billing-back"><span class="material-icons-round">arrow_back</span> Admin</a>
        <h1><?php echo __t('billing_title', 'saas'); ?></h1>
        <p><?php echo __t('billing_subtitle', 'saas'); ?></p>
        <?php if ($upgrade): ?>
        <div class="billing-upgrade-hint"><?php echo __t('billing_upgrade_hint', 'saas'); ?>: <strong><?php echo $upgrade; ?></strong></div>
        <?php endif; ?>
    </header>

    <section class="billing-current plat-panel" id="billingCurrent">
        <p><?php echo __t('loading', 'saas'); ?></p>
    </section>

    <section class="billing-plans">
        <h2><?php echo __t('billing_plans_title', 'saas'); ?></h2>
        <div class="billing-providers" id="billingProviders">
            <button type="button" class="billing-provider-btn is-active" data-provider="stripe">Stripe</button>
            <button type="button" class="billing-provider-btn" data-provider="paystack">Paystack</button>
            <button type="button" class="billing-provider-btn" data-provider="mobile_money">Mobile Money</button>
        </div>
        <div class="billing-mm-fields" id="billingMmFields" hidden>
            <label><?php echo __t('billing_mm_phone', 'saas'); ?>
                <input type="tel" id="billingMmPhone" placeholder="+221...">
            </label>
            <label><?php echo __t('billing_mm_provider', 'saas'); ?>
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
</div>
<script>
window.BILLING_CONFIG = {
    apiBase: '../api/v1/index.php',
    i18n: <?php echo json_encode([
        'current_plan' => __t('billing_current_plan', 'saas'),
        'trial_ends' => __t('billing_trial_ends', 'saas'),
        'usage_stores' => __t('billing_usage_stores', 'saas'),
        'usage_users' => __t('billing_usage_users', 'saas'),
        'upgrade' => __t('billing_upgrade', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'billing_mm_phone' => __t('billing_mm_phone', 'saas'),
        'billing_mm_provider' => __t('billing_mm_provider', 'saas'),
        'billing_mm_pending' => __t('billing_mm_pending', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>,
    success: <?php echo json_encode(isset($_GET['success'])); ?>,
    sessionId: <?php echo json_encode($_GET['session_id'] ?? ''); ?>,
    reference: <?php echo json_encode($_GET['reference'] ?? ''); ?>,
    provider: <?php echo json_encode($_GET['provider'] ?? ''); ?>
};
</script>
<script src="../assets/js/saas/billing.js?v=1"></script>
</body>
</html>

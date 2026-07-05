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

require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../includes/Platform/Services/EmailVerificationService.php';
$db = Database::getInstance()->getConnection();
SaaSPhase5Migrator::ensure($db);
if (!(new EmailVerificationService($db))->isVerified((int) $_SESSION['user_id'])) {
    header('Location: verify-email.php');
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$onboardingAccent = '#7c3aed';
$themePortal = 'auth';
$themeAccent = $onboardingAccent;
$userName = htmlspecialchars($_SESSION['name'] ?? $_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $onboardingAccent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $onboardingAccent; ?>">
    <meta name="theme-accent" content="<?php echo $onboardingAccent; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('onboarding_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=1">
    <link rel="stylesheet" href="../assets/css/saas-onboarding.css?v=2">
</head>
<body class="signup-org-page onboarding-page">
<div class="signup-org-shell onboarding-shell">
    <aside class="signup-org-hero onboarding-hero" aria-label="<?php echo htmlspecialchars(__t('onboarding_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__inner">
            <div class="signup-org-hero__brand">
                <span class="material-icons-round" aria-hidden="true">cloud</span>
                <span>RetailPOS Cloud</span>
            </div>
            <h2 id="onboardingHeroTitle"><?php echo __t('onboarding_hero_title', 'saas'); ?></h2>
            <p id="onboardingHeroDesc"><?php echo __t('onboarding_hero_desc', 'saas'); ?></p>
            <ul class="signup-org-features onboarding-features" id="onboardingHeroFeatures">
                <li>
                    <span class="material-icons-round" aria-hidden="true">business</span>
                    <?php echo __t('onboarding_feat_profile', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">store</span>
                    <?php echo __t('onboarding_feat_store', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">groups</span>
                    <?php echo __t('onboarding_feat_team', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">inventory_2</span>
                    <?php echo __t('onboarding_feat_catalog', 'saas'); ?>
                </li>
            </ul>
            <div class="onboarding-hero-progress" aria-hidden="true">
                <div class="onboarding-hero-progress__bar" id="onboardingHeroProgressBar"></div>
            </div>
        </div>
    </aside>

    <main class="signup-org-panel onboarding-panel">
        <div class="signup-org-toolbar">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="signup-theme-toggle" id="onboardingThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container onboarding-container">
            <div class="signup-org-badge onboarding-badge">
                <span class="material-icons-round" aria-hidden="true">rocket_launch</span>
                <?php echo __t('onboarding_badge', 'saas'); ?>
            </div>

            <div class="auth-header onboarding-header">
                <h1><?php echo __t('onboarding_title', 'saas'); ?></h1>
                <p><?php echo __t('onboarding_subtitle', 'saas'); ?></p>
                <?php if ($userName !== ''): ?>
                <p class="onboarding-welcome"><?php echo __t('onboarding_welcome', 'saas'); ?>, <strong><?php echo $userName; ?></strong></p>
                <?php endif; ?>
            </div>

            <nav class="onboarding-stepper" id="onboardingStepper" aria-label="<?php echo htmlspecialchars(__t('onboarding_progress_label', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"></nav>

            <div class="onboarding-progress-wrap" aria-hidden="true">
                <div class="onboarding-progress" id="onboardingProgress">
                    <div class="onboarding-progress__bar" id="onboardingProgressBar"></div>
                </div>
                <span class="onboarding-progress__label" id="onboardingProgressLabel"></span>
            </div>

            <div id="onboardingAlert" class="onboarding-alert" hidden role="alert"></div>

            <div class="onboarding-loading" id="onboardingLoading">
                <span class="onboarding-spinner" aria-hidden="true"></span>
                <span><?php echo __t('onboarding_loading', 'saas'); ?></span>
            </div>

            <form id="onboardingForm" class="onboarding-form" hidden novalidate></form>

            <div class="onboarding-complete" id="onboardingComplete" hidden></div>

            <div class="onboarding-actions" id="onboardingActions" hidden>
                <button type="button" class="btn-secondary onboarding-back-btn" id="backBtn" hidden>
                    <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                    <?php echo __t('onboarding_back', 'saas'); ?>
                </button>
                <div class="onboarding-actions__right">
                    <button type="button" class="btn-secondary onboarding-skip-btn" id="skipBtn">
                        <?php echo __t('onboarding_skip', 'saas'); ?>
                    </button>
                    <button type="submit" form="onboardingForm" class="btn-primary" id="nextBtn">
                        <span id="nextBtnText"><?php echo __t('onboarding_next', 'saas'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
                    </button>
                </div>
            </div>
        </div>
    </main>
</div>

<script>
window.ONBOARDING_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php?request=onboarding/', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'step1_title' => __t('onboarding_step1', 'saas'),
        'step1_desc' => __t('onboarding_step1_desc', 'saas'),
        'step2_title' => __t('onboarding_step2', 'saas'),
        'step2_desc' => __t('onboarding_step2_desc', 'saas'),
        'step3_title' => __t('onboarding_step3', 'saas'),
        'step3_desc' => __t('onboarding_step3_desc', 'saas'),
        'step4_title' => __t('onboarding_step4', 'saas'),
        'step4_desc' => __t('onboarding_step4_desc', 'saas'),
        'step5_title' => __t('onboarding_step5', 'saas'),
        'step5_desc' => __t('onboarding_step5_desc', 'saas'),
        'step6_title' => __t('onboarding_step6', 'saas'),
        'step6_desc' => __t('onboarding_step6_desc', 'saas'),
        'next' => __t('onboarding_next', 'saas'),
        'finish' => __t('onboarding_finish', 'saas'),
        'back' => __t('onboarding_back', 'saas'),
        'skip' => __t('onboarding_skip', 'saas'),
        'error' => __t('load_error', 'saas'),
        'loading' => __t('onboarding_loading', 'saas'),
        'progress' => __t('onboarding_progress_of', 'saas'),
        'org_name' => __t('onboarding_org_name', 'saas'),
        'address' => __t('onboarding_address', 'saas'),
        'country_code' => __t('onboarding_country', 'saas'),
        'currency' => __t('onboarding_currency', 'saas'),
        'store_name' => __t('onboarding_store_name', 'saas'),
        'location' => __t('onboarding_location', 'saas'),
        'emails' => __t('onboarding_team_emails', 'saas'),
        'emails_hint' => __t('onboarding_team_hint', 'saas'),
        'tax_rate' => __t('onboarding_tax_rate', 'saas'),
        'tax_hint' => __t('onboarding_tax_hint', 'saas'),
        'product_name' => __t('onboarding_product_name', 'saas'),
        'price' => __t('onboarding_price', 'saas'),
        'stock' => __t('onboarding_stock', 'saas'),
        'product_optional' => __t('onboarding_product_optional', 'saas'),
        'complete_title' => __t('onboarding_complete_title', 'saas'),
        'complete_desc' => __t('onboarding_complete_desc', 'saas'),
        'open_admin' => __t('onboarding_open_admin', 'saas'),
        'launch_pos' => __t('onboarding_launch_pos', 'saas'),
        'hero_title' => __t('onboarding_hero_title', 'saas'),
        'hero_desc' => __t('onboarding_hero_desc', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/onboarding.js?v=2"></script>
</body>
</html>

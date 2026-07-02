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

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('onboarding_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/saas-onboarding.css?v=1">
</head>
<body class="onboarding-page">
<div class="onboarding-shell">
    <header class="onboarding-header">
        <h1><?php echo __t('onboarding_title', 'saas'); ?></h1>
        <p><?php echo __t('onboarding_subtitle', 'saas'); ?></p>
        <div class="onboarding-progress" id="onboardingProgress"></div>
    </header>
    <div id="onboardingAlert" class="onboarding-alert" hidden></div>
    <form id="onboardingForm" class="onboarding-form"></form>
    <div class="onboarding-actions">
        <button type="button" class="btn-secondary" id="skipBtn"><?php echo __t('onboarding_skip', 'saas'); ?></button>
        <button type="submit" form="onboardingForm" class="btn-primary" id="nextBtn"><?php echo __t('onboarding_next', 'saas'); ?></button>
    </div>
</div>
<script>
window.ONBOARDING_I18N = <?php echo json_encode([
    'step1_title' => __t('onboarding_step1', 'saas'),
    'step2_title' => __t('onboarding_step2', 'saas'),
    'step3_title' => __t('onboarding_step3', 'saas'),
    'step4_title' => __t('onboarding_step4', 'saas'),
    'step5_title' => __t('onboarding_step5', 'saas'),
    'step6_title' => __t('onboarding_step6', 'saas'),
    'next' => __t('onboarding_next', 'saas'),
    'finish' => __t('onboarding_finish', 'saas'),
    'error' => __t('load_error', 'saas'),
], JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="../assets/js/saas/onboarding.js?v=1"></script>
</body>
</html>

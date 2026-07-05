<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../includes/Platform/Services/EmailVerificationService.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$db = Database::getInstance()->getConnection();
TenantSchemaMigrator::ensure($db);
SaaSPhase5Migrator::ensure($db);

$emailSvc = new EmailVerificationService($db);
$isLoggedIn = !empty($_SESSION['user_id']);
$token = trim($_GET['token'] ?? '');
$verified = false;
$invalidToken = false;
$pending = false;
$message = '';

if ($token !== '') {
    $result = $emailSvc->verify($token);
    if ($result) {
        $verified = true;
        $message = __t('verify_success', 'saas');
    } else {
        $invalidToken = true;
        $message = __t('verify_invalid', 'saas');
    }
} elseif ($isLoggedIn) {
    $verified = $emailSvc->isVerified((int) $_SESSION['user_id']);
    if ($verified) {
        header('Location: onboarding.php');
        exit;
    }
    $pending = true;
    $message = __t('verify_pending', 'saas');
} else {
    header('Location: login.php');
    exit;
}

$canResend = $isLoggedIn && !$verified;
$devVerifyUrl = null;
if ($pending && $isLoggedIn && defined('APP_DEBUG') && APP_DEBUG) {
    $devVerifyUrl = $emailSvc->getPendingVerifyUrl((int) $_SESSION['user_id']);
}

$statusType = $verified ? 'success' : ($invalidToken ? 'error' : 'pending');
$statusIcon = $verified ? 'mark_email_read' : ($invalidToken ? 'error_outline' : 'mark_email_unread');
$subtitleKey = $verified ? 'verify_subtitle_success' : ($invalidToken ? 'verify_subtitle_invalid' : 'verify_subtitle_pending');
$userEmail = trim((string) ($_SESSION['email'] ?? ''));

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$verifyAccent = '#7c3aed';
$themePortal = 'auth';
$themeAccent = $verifyAccent;
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $verifyAccent; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $verifyAccent; ?>">
    <meta name="theme-accent" content="<?php echo $verifyAccent; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('verify_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=3">
    <link rel="stylesheet" href="../assets/css/signup-organization.css?v=3">
    <link rel="stylesheet" href="../assets/css/tenant-login.css?v=5">
    <link rel="stylesheet" href="../assets/css/verify-email.css?v=2">
</head>
<body class="signup-org-page tenant-login-page tenant-login-page--cloud tenant-verify-page">
<div class="signup-org-shell tenant-login-shell">
    <aside class="signup-org-hero tenant-login-hero" aria-label="<?php echo htmlspecialchars(__t('verify_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="signup-org-hero__grid" aria-hidden="true"></div>
        <div class="signup-org-hero__inner tenant-login-hero__inner">
            <div class="signup-org-hero__brand tenant-login-hero__brand">
                <span class="material-icons-round" aria-hidden="true">cloud</span>
                <span>RetailPOS Cloud</span>
            </div>

            <p class="signup-org-hero__eyebrow">
                <span class="material-icons-round" aria-hidden="true">mark_email_unread</span>
                <?php echo __t('verify_hero_eyebrow', 'saas'); ?>
            </p>

            <h2><?php echo __t('verify_hero_title', 'saas'); ?></h2>
            <p class="signup-org-hero__lead"><?php echo __t('verify_hero_desc', 'saas'); ?></p>

            <ul class="signup-org-features tenant-login-features">
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">inbox</span></span>
                    <span><?php echo __t('verify_feat_inbox', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">folder_special</span></span>
                    <span><?php echo __t('verify_feat_spam', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">link</span></span>
                    <span><?php echo __t('verify_feat_link', 'saas'); ?></span>
                </li>
                <li>
                    <span class="signup-org-features__icon" aria-hidden="true"><span class="material-icons-round">rocket_launch</span></span>
                    <span><?php echo __t('verify_feat_onboarding', 'saas'); ?></span>
                </li>
            </ul>

            <div class="signup-org-trust" aria-label="<?php echo htmlspecialchars(__t('signup_trust_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">lock</span>
                    <span><?php echo __t('signup_trust_secure', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">mail_lock</span>
                    <span><?php echo __t('verify_trust_email', 'saas'); ?></span>
                </div>
                <div class="signup-org-trust__item">
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <span><?php echo __t('signup_trust_support', 'saas'); ?></span>
                </div>
            </div>
        </div>
    </aside>

    <main class="signup-org-panel tenant-login-panel">
        <div class="signup-org-toolbar tenant-login-toolbar">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="signup-theme-toggle tenant-theme-toggle" id="verifyThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container tenant-login-card signup-org-card">
            <div class="signup-org-badge tenant-login-badge">
                <span class="material-icons-round" aria-hidden="true">mail</span>
                <?php echo __t('verify_badge', 'saas'); ?>
            </div>

            <div class="auth-header tenant-login-header signup-org-header">
                <h1><?php echo __t('verify_title', 'saas'); ?></h1>
                <p><?php echo __t($subtitleKey, 'saas'); ?></p>
            </div>

            <div class="verify-status verify-status--<?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>" role="status" aria-live="polite">
                <span class="verify-status__icon verify-status__icon--<?php echo htmlspecialchars($statusType, ENT_QUOTES, 'UTF-8'); ?>"
                      aria-hidden="true">
                    <span class="material-icons-round"><?php echo htmlspecialchars($statusIcon, ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <?php if ($pending && $userEmail !== ''): ?>
                <p class="verify-email-target">
                    <?php echo htmlspecialchars(__t('verify_sent_to', 'saas', ['email' => $userEmail]), ENT_QUOTES, 'UTF-8'); ?>
                </p>
                <?php endif; ?>
                <p id="verifyMessage" class="verify-status__message"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></p>
            </div>

            <?php if ($devVerifyUrl): ?>
            <div class="verify-dev-panel" id="verifyDevPanel" role="region" aria-labelledby="verifyDevTitle">
                <div class="verify-dev-panel__head">
                    <span class="material-icons-round" aria-hidden="true">developer_mode</span>
                    <strong id="verifyDevTitle"><?php echo __t('verify_dev_title', 'saas'); ?></strong>
                </div>
                <p class="verify-dev-panel__hint"><?php echo __t('verify_dev_hint', 'saas'); ?></p>
                <a href="<?php echo htmlspecialchars($devVerifyUrl, ENT_QUOTES, 'UTF-8'); ?>" class="verify-dev-panel__link" id="verifyDevLink">
                    <?php echo htmlspecialchars($devVerifyUrl, ENT_QUOTES, 'UTF-8'); ?>
                </a>
                <button type="button" class="verify-dev-panel__copy" id="verifyDevCopy" data-url="<?php echo htmlspecialchars($devVerifyUrl, ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round" aria-hidden="true">content_copy</span>
                    <?php echo __t('verify_dev_copy', 'saas'); ?>
                </button>
            </div>
            <?php endif; ?>

            <div class="verify-actions">
                <?php if ($verified): ?>
                <a href="onboarding.php" class="btn-primary signup-org-submit tenant-login-submit verify-actions__primary">
                    <?php echo __t('verify_continue', 'saas'); ?>
                    <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
                </a>
                <?php elseif ($canResend): ?>
                <button type="button" class="btn-secondary verify-actions__secondary" id="resendBtn">
                    <span class="material-icons-round" aria-hidden="true">refresh</span>
                    <span id="resendBtnText"><?php echo __t('verify_resend', 'saas'); ?></span>
                </button>
                <?php endif; ?>

                <?php if ($invalidToken && !$isLoggedIn): ?>
                <a href="login.php" class="btn-primary signup-org-submit tenant-login-submit verify-actions__primary">
                    <?php echo __t('verify_back_login', 'saas'); ?>
                </a>
                <?php endif; ?>
            </div>

            <p class="signup-org-signin tenant-verify-back">
                <a href="login.php" class="tenant-forgot-back__link">
                    <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                    <?php echo __t('verify_back_login', 'saas'); ?>
                </a>
            </p>

            <nav class="signup-org-footer-links tenant-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('verify_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <a href="marketing/contact.php"><?php echo __t('signup_footer_contact', 'saas'); ?></a>
                <a href="marketing/faq.php"><?php echo __t('signup_footer_faq', 'saas'); ?></a>
            </nav>

            <p class="signup-org-copy">© <?php echo $year; ?> RetailPOS Cloud</p>
        </div>
    </main>
</div>

<script>
window.VERIFY_EMAIL_CONFIG = {
    resendUrl: <?php echo json_encode('../api/v1/index.php?request=onboarding/resend-verification', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'verify_resend' => __t('verify_resend', 'saas'),
        'verify_resent' => __t('verify_resent', 'saas'),
        'verify_resend_error' => __t('verify_resend_error', 'saas'),
        'loading' => __t('loading', 'saas'),
        'verify_dev_copy' => __t('verify_dev_copy', 'saas'),
        'verify_dev_copied' => __t('verify_dev_copied', 'saas'),
        'verify_dev_title' => __t('verify_dev_title', 'saas'),
        'verify_dev_hint' => __t('verify_dev_hint', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/verify-email.js?v=2"></script>
</body>
</html>

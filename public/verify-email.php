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

$token = trim($_GET['token'] ?? '');
$verified = false;
$message = '';

if ($token !== '') {
    $result = (new EmailVerificationService($db))->verify($token);
    if ($result) {
        $verified = true;
        $message = __t('verify_success', 'saas');
    } else {
        $message = __t('verify_invalid', 'saas');
    }
} elseif (!empty($_SESSION['user_id'])) {
    $verified = (new EmailVerificationService($db))->isVerified((int) $_SESSION['user_id']);
    if ($verified) {
        header('Location: onboarding.php');
        exit;
    }
} else {
    header('Location: login.php');
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('verify_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/saas-onboarding.css?v=1">
</head>
<body class="verify-email-page">
<div class="auth-container onboarding-container">
    <div class="auth-header">
        <h1><?php echo __t('verify_title', 'saas'); ?></h1>
        <p id="verifyMessage"><?php echo htmlspecialchars($message ?: __t('verify_pending', 'saas')); ?></p>
    </div>
    <?php if ($verified): ?>
        <a href="onboarding.php" class="btn-primary onboarding-btn"><?php echo __t('verify_continue', 'saas'); ?></a>
    <?php else: ?>
        <button type="button" class="btn-primary" id="resendBtn"><?php echo __t('verify_resend', 'saas'); ?></button>
    <?php endif; ?>
</div>
<script>
document.getElementById('resendBtn')?.addEventListener('click', async () => {
    const res = await fetch('../api/v1/index.php?request=onboarding/resend-verification', { method: 'POST', credentials: 'same-origin' });
    const data = await res.json();
    document.getElementById('verifyMessage').textContent = data.status === 'success'
        ? <?php echo json_encode(__t('verify_resent', 'saas')); ?>
        : <?php echo json_encode(__t('verify_resend_error', 'saas')); ?>;
});
</script>
</body>
</html>

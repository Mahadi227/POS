<?php
require_once __DIR__ . '/../includes/Config/session.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

if (isset($_SESSION['user_id'])) {
    header('Location: admin/index.php');
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('signup_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
    <link rel="stylesheet" href="../assets/css/saas-billing.css?v=1">
</head>
<body class="signup-org-page">
    <div class="auth-lang-switcher">
        <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
    </div>

    <div class="auth-container signup-org-container">
        <div class="auth-header">
            <h1>RetailPOS<span style="color:var(--primary)"> Cloud</span></h1>
            <p><?php echo __t('signup_subtitle', 'saas'); ?></p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="signupOrgForm">
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label><?php echo __t('signup_org_name', 'saas'); ?></label>
                <input type="text" id="org_name" required>
            </div>

            <div class="form-group">
                <label><?php echo __t('signup_slug', 'saas'); ?></label>
                <input type="text" id="slug" pattern="[a-z0-9-]+" placeholder="my-company">
                <small id="slugHint" class="field-hint"></small>
            </div>

            <div class="form-group">
                <label><?php echo __t('signup_plan', 'saas'); ?></label>
                <select id="plan_code" required></select>
            </div>

            <div class="form-group">
                <label><?php echo __t('signup_store', 'saas'); ?></label>
                <input type="text" id="store_name">
            </div>

            <div class="form-group">
                <label><?php echo __t('full_name', 'auth'); ?></label>
                <input type="text" id="admin_name" required>
            </div>

            <div class="form-group">
                <label><?php echo __t('email', 'auth'); ?></label>
                <input type="email" id="admin_email" required>
            </div>

            <div class="form-group">
                <label><?php echo __t('password', 'auth'); ?></label>
                <input type="password" id="password" required minlength="8">
            </div>

            <button type="submit" class="btn-primary" id="submitBtn"><?php echo __t('signup_submit', 'saas'); ?></button>
        </form>

        <div class="auth-footer">
            <?php echo __t('has_account', 'auth'); ?> <a href="login.php"><?php echo __t('login_link', 'auth'); ?></a>
        </div>
    </div>

    <script>
    window.SIGNUP_I18N = <?php echo json_encode([
        'slug_available' => __t('signup_slug_ok', 'saas'),
        'slug_taken' => __t('signup_slug_taken', 'saas'),
        'error_generic' => __t('register_error', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../assets/js/saas/signup-organization.js?v=1"></script>
</body>
</html>

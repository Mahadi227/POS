<?php
require_once __DIR__ . '/../includes/Config/session.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

if (isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('forgot_title', 'auth'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css">
</head>
<body>
    <div class="auth-lang-switcher">
        <?php
        $changeUrl = 'change_language.php';
        include __DIR__ . '/includes/language_switcher.php';
        ?>
    </div>

    <div class="auth-container">
        <div class="auth-header">
            <h1><?php echo __t('forgot_heading', 'auth'); ?></h1>
            <p><?php echo __t('forgot_subtitle', 'auth'); ?></p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="forgotPasswordForm">
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">

            <div class="form-group">
                <label><?php echo __t('email', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" placeholder="<?php echo __t('email_placeholder', 'auth'); ?>" required autofocus>
                </div>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                <span id="btnText"><?php echo __t('forgot_submit', 'auth'); ?></span>
                <div class="spinner" id="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            <a href="login.php" class="auth-back-link">
                <span class="material-icons-round">arrow_back</span>
                <?php echo __t('forgot_back_login', 'auth'); ?>
            </a>
        </div>
    </div>

    <script>
        window.AUTH_I18N = <?php echo json_encode([
            'error_generic' => __t('error_generic', 'auth'),
            'server_error' => __t('server_error', 'auth'),
            'forgot_success' => __t('forgot_success', 'auth'),
            'invalid_email' => __t('invalid_email', 'auth'),
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../assets/js/auth.js"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../includes/Config/session.php';

// Auth pages: English by default; only session/cookie override (not browser locale)
define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

if (isset($_SESSION['user_id'])) {
    header("Location: admin/index.html");
    exit;
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('register_title', 'auth'); ?></title>
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
            <h1><?php echo __t('register_heading', 'auth'); ?></h1>
            <p><?php echo __t('register_subtitle', 'auth'); ?></p>
        </div>

        <div id="alertBox" class="alert"></div>

        <form id="registerForm">
            <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
            
            <div class="form-group">
                <label><?php echo __t('full_name', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">person</span>
                    <input type="text" id="name" placeholder="<?php echo __t('name_placeholder', 'auth'); ?>" required autofocus>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo __t('email', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">email</span>
                    <input type="email" id="email" placeholder="<?php echo __t('email_placeholder_register', 'auth'); ?>" required>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo __t('password', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password" placeholder="••••••••" required minlength="8">
                    <button type="button" class="material-icons-round toggle-password" onclick="togglePwd('password')">visibility</button>
                </div>
            </div>

            <div class="form-group">
                <label><?php echo __t('confirm_password', 'auth'); ?></label>
                <div class="input-icon-wrapper">
                    <span class="material-icons-round">lock</span>
                    <input type="password" id="password_confirmation" placeholder="••••••••" required minlength="8">
                </div>
            </div>

            <button type="submit" class="btn-primary" id="submitBtn">
                <span id="btnText"><?php echo __t('register_submit', 'auth'); ?></span>
                <div class="spinner" id="spinner"></div>
            </button>
        </form>

        <div class="auth-footer">
            <?php echo __t('has_account', 'auth'); ?> <a href="login.php"><?php echo __t('login_link', 'auth'); ?></a>
        </div>
    </div>

    <script>
        window.AUTH_I18N = <?php echo json_encode([
            'error_generic' => __t('error_generic', 'auth'),
            'server_error' => __t('server_error', 'auth'),
            'password_mismatch' => __t('password_mismatch', 'auth'),
            'register_error' => __t('register_error', 'auth'),
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../assets/js/auth.js"></script>
</body>
</html>

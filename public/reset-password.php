<?php
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Database/Database.php';

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

$rawToken = trim($_GET['token'] ?? '');
$tokenValid = false;

if ($rawToken !== '') {
    try {
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'SELECT id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
        );
        $stmt->execute([hash('sha256', $rawToken)]);
        $tokenValid = (bool) $stmt->fetch();
    } catch (PDOException $e) {
        $tokenValid = false;
    }
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('reset_title', 'auth'); ?></title>
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
            <h1><?php echo __t('reset_heading', 'auth'); ?></h1>
            <p><?php echo __t('reset_subtitle', 'auth'); ?></p>
        </div>

        <div id="alertBox" class="alert"></div>

        <?php if (!$tokenValid): ?>
            <p class="auth-message auth-message--error"><?php echo __t($rawToken === '' ? 'reset_token_missing' : 'reset_invalid_token', 'auth'); ?></p>
            <div class="auth-footer">
                <a href="forgot-password.php" class="auth-back-link">
                    <span class="material-icons-round">arrow_back</span>
                    <?php echo __t('forgot_heading', 'auth'); ?>
                </a>
            </div>
        <?php else: ?>
            <form id="resetPasswordForm">
                <input type="hidden" id="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label><?php echo __t('password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round">lock</span>
                        <input type="password" id="password" placeholder="••••••••" required minlength="8" autofocus>
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
                    <span id="btnText"><?php echo __t('reset_submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner"></div>
                </button>
            </form>

            <div class="auth-footer">
                <a href="login.php" class="auth-back-link">
                    <span class="material-icons-round">arrow_back</span>
                    <?php echo __t('forgot_back_login', 'auth'); ?>
                </a>
            </div>
        <?php endif; ?>
    </div>

    <?php if ($tokenValid): ?>
    <script>
        window.AUTH_I18N = <?php echo json_encode([
            'error_generic' => __t('error_generic', 'auth'),
            'server_error' => __t('server_error', 'auth'),
            'password_mismatch' => __t('password_mismatch', 'auth'),
            'reset_success' => __t('reset_success', 'auth'),
            'reset_invalid_token' => __t('reset_invalid_token', 'auth'),
        ], JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../assets/js/auth.js"></script>
    <?php endif; ?>
</body>
</html>

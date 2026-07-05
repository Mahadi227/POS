<?php
require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Auth/RoleRedirect.php';
require_once __DIR__ . '/../includes/Helpers/TenantBootstrap.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$resolvedTenant = TenantBootstrap::resolveTenant();
$branding = TenantBootstrap::branding();
$themeAccent = $branding['accent'] ?? '#2563eb';
$brandName = $branding['brand_name'] ?? 'RetailPOS';
$logoUrl = $branding['logo_url'] ?? null;
$tenantSlug = $resolvedTenant['slug'] ?? '';

if (isset($_SESSION['user_id'])) {
    header('Location: ' . RoleRedirect::publicPath($_SESSION['role'] ?? ''));
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
$themePortal = 'auth';
$accentEsc = htmlspecialchars($themeAccent, ENT_QUOTES, 'UTF-8');
$tokenErrorKey = $rawToken === '' ? 'reset_token_missing' : 'reset_invalid_token';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo __t('reset_title', 'auth'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/tenant-login.css?v=1">
    <style>:root { --primary: <?php echo $accentEsc; ?>; }</style>
    <?php if (!empty($branding['favicon_url'])): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($branding['favicon_url'], ENT_QUOTES, 'UTF-8'); ?>">
    <?php endif; ?>
</head>
<body class="tenant-login-page">
<div class="tenant-login-shell">
    <aside class="tenant-login-hero" aria-label="<?php echo htmlspecialchars(__t('reset_hero_aria', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="tenant-login-hero__inner">
            <div class="tenant-login-hero__brand">
                <?php if ($logoUrl): ?>
                    <img src="<?php echo htmlspecialchars($logoUrl, ENT_QUOTES, 'UTF-8'); ?>"
                         alt="<?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?>"
                         class="tenant-login-hero__logo">
                <?php else: ?>
                    <span class="material-icons-round" aria-hidden="true">password</span>
                    <span><?php echo htmlspecialchars($brandName, ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>
            <h2><?php echo __t('reset_hero_title', 'auth'); ?></h2>
            <p><?php echo __t('reset_hero_desc', 'auth'); ?></p>
            <ul class="tenant-login-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">enhanced_encryption</span>
                    <?php echo __t('reset_feat_strong', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">link_off</span>
                    <?php echo __t('reset_feat_once', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">login</span>
                    <?php echo __t('reset_feat_signin', 'auth'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">shield</span>
                    <?php echo __t('reset_feat_secure', 'auth'); ?>
                </li>
            </ul>
            <div class="tenant-login-hero__links">
                <a href="forgot-password.php"><?php echo __t('reset_request_new', 'auth'); ?> →</a>
            </div>
        </div>
    </aside>

    <main class="tenant-login-panel">
        <div class="tenant-login-toolbar">
            <?php
            $changeUrl = 'change_language.php';
            include __DIR__ . '/includes/language_switcher.php';
            ?>
            <button type="button" class="tenant-theme-toggle" id="tenantThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'auth'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <div class="auth-container">
            <div class="tenant-login-badge">
                <span class="material-icons-round" aria-hidden="true">lock_reset</span>
                <?php echo __t('reset_badge', 'auth'); ?>
            </div>

            <div class="auth-header">
                <h1><?php echo __t('reset_heading', 'auth'); ?></h1>
                <p><?php echo __t('reset_subtitle', 'auth'); ?></p>
                <?php if ($tenantSlug !== ''): ?>
                    <p class="tenant-login-org">
                        <span class="material-icons-round" aria-hidden="true" style="font-size:16px;">apartment</span>
                        <?php echo __t('login_org_label', 'auth'); ?>
                        <code><?php echo htmlspecialchars($tenantSlug, ENT_QUOTES, 'UTF-8'); ?></code>
                    </p>
                <?php endif; ?>
            </div>

            <div id="alertBox" class="alert<?php echo $tokenValid ? '' : ' alert-error'; ?>" aria-live="polite"
                 style="display:<?php echo $tokenValid ? 'none' : 'block'; ?>;"
                 <?php if (!$tokenValid): ?>role="alert"<?php endif; ?>>
                <?php if (!$tokenValid): ?>
                    <?php echo __t($tokenErrorKey, 'auth'); ?>
                <?php endif; ?>
            </div>

            <?php if ($tokenValid): ?>
            <form id="resetPasswordForm" method="post" action="#" novalidate>
                <input type="hidden" id="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" id="token" value="<?php echo htmlspecialchars($rawToken, ENT_QUOTES, 'UTF-8'); ?>">

                <div class="form-group">
                    <label for="password"><?php echo __t('password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">lock</span>
                        <input type="password" id="password" name="password" required minlength="8" autofocus
                               autocomplete="new-password"
                               placeholder="••••••••">
                        <button type="button" class="material-icons-round toggle-password" id="togglePassword"
                                aria-label="<?php echo htmlspecialchars(__t('show_password', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password_confirmation"><?php echo __t('confirm_password', 'auth'); ?></label>
                    <div class="input-icon-wrapper">
                        <span class="material-icons-round" aria-hidden="true">lock</span>
                        <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8"
                               autocomplete="new-password"
                               placeholder="••••••••">
                        <button type="button" class="material-icons-round toggle-password" id="togglePasswordConfirm"
                                aria-label="<?php echo htmlspecialchars(__t('show_password_confirm', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">visibility</button>
                    </div>
                </div>

                <button type="submit" class="btn-primary" id="submitBtn">
                    <span id="btnText"><?php echo __t('reset_submit', 'auth'); ?></span>
                    <div class="spinner" id="spinner" aria-hidden="true"></div>
                </button>
            </form>
            <?php endif; ?>

            <nav class="tenant-login-footer-links" aria-label="<?php echo htmlspecialchars(__t('reset_nav', 'auth'), ENT_QUOTES, 'UTF-8'); ?>">
                <a href="login.php"><?php echo __t('login_link', 'auth'); ?></a>
                <?php if ($tokenValid): ?>
                <a href="forgot-password.php"><?php echo __t('forgot_heading', 'auth'); ?></a>
                <?php else: ?>
                <a href="forgot-password.php"><?php echo __t('reset_request_new', 'auth'); ?></a>
                <?php endif; ?>
            </nav>
        </div>
    </main>
</div>

<script>
window.TENANT_RESET_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php?request=auth/reset-password', JSON_THROW_ON_ERROR); ?>,
    loginUrl: <?php echo json_encode('login.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'error_generic' => __t('error_generic', 'auth'),
        'server_error' => __t('server_error', 'auth'),
        'password_mismatch' => __t('password_mismatch', 'auth'),
        'password_min_length' => __t('password_min_length', 'auth'),
        'reset_success' => __t('reset_success', 'auth'),
        'reset_invalid_token' => __t('reset_invalid_token', 'auth'),
        'show_password' => __t('show_password', 'auth'),
        'hide_password' => __t('hide_password', 'auth'),
        'show_password_confirm' => __t('show_password_confirm', 'auth'),
        'hide_password_confirm' => __t('hide_password_confirm', 'auth'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/tenant-reset-password.js?v=1"></script>
</body>
</html>

<?php
require_once __DIR__ . '/../../includes/Config/config.php';
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Auth/RoleRedirect.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$devAccent = '#7c3aed';
$accentEsc = htmlspecialchars($devAccent, ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$themeAccent = $devAccent;

$isLoggedIn = !empty($_SESSION['user_id']);
$adminBackUrl = $isLoggedIn ? '../' . RoleRedirect::publicPath($_SESSION['role'] ?? '') : '../login.php';

$scriptDir = dirname($_SERVER['SCRIPT_NAME'] ?? '/public/developers/index.php');
$publicRoot = rtrim(dirname($scriptDir), '/\\');
$apiV2TenantUrl = htmlspecialchars($publicRoot . '/api/v2/index.php?request=tenant', ENT_QUOTES, 'UTF-8');

$curlSnippet = 'curl -H "X-API-Key: rp_live_…" \\' . "\n"
    . '  "' . $publicRoot . '/api/v2/index.php?request=tenant"';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <title><?php echo __t('dev_title', 'saas'); ?></title>
    <?php include __DIR__ . '/../includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../../assets/css/saas-developers.css?v=2">
    <style>:root { --dev-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="dev-page">
<div class="dev-shell">
    <aside class="dev-hero-panel" aria-label="<?php echo htmlspecialchars(__t('dev_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="dev-hero-panel__inner">
            <div class="dev-hero-brand">
                <span class="material-icons-round" aria-hidden="true">code</span>
                <span>RetailPOS Developers</span>
            </div>
            <h2><?php echo __t('dev_hero_title', 'saas'); ?></h2>
            <p><?php echo __t('dev_hero_desc', 'saas'); ?></p>
            <ul class="dev-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">api</span>
                    <?php echo __t('dev_feat_rest', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">vpn_key</span>
                    <?php echo __t('dev_feat_auth', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">webhook</span>
                    <?php echo __t('dev_feat_webhooks', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">description</span>
                    <?php echo __t('dev_feat_openapi', 'saas'); ?>
                </li>
            </ul>
            <div class="dev-hero-links">
                <a href="../status.php"><?php echo __t('signup_status_link', 'saas'); ?></a>
            </div>
        </div>
    </aside>

    <main class="dev-main-panel">
        <div class="dev-topbar">
            <?php
            $changeUrl = '../change_language.php';
            include __DIR__ . '/../includes/language_switcher.php';
            ?>
            <button type="button" class="dev-theme-toggle" id="devThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <header class="dev-content-header">
            <div class="dev-badge">
                <span class="material-icons-round" aria-hidden="true">integration_instructions</span>
                <?php echo __t('dev_badge', 'saas'); ?>
            </div>
            <h1><?php echo __t('dev_title', 'saas'); ?></h1>
            <p><?php echo __t('dev_subtitle', 'saas'); ?></p>
        </header>

        <section class="dev-panel dev-quickstart">
            <h2>
                <span class="material-icons-round" aria-hidden="true">rocket_launch</span>
                <?php echo __t('dev_quickstart', 'saas'); ?>
            </h2>
            <ol>
                <li><?php echo __t('dev_step1', 'saas'); ?> — <a href="../api-keys.php"><?php echo __t('apikeys_title', 'saas'); ?></a></li>
                <li><?php echo __t('dev_step2', 'saas'); ?></li>
                <li><?php echo __t('dev_step3', 'saas'); ?> — <a href="../webhooks.php"><?php echo __t('webhooks_title', 'saas'); ?></a></li>
            </ol>
            <div class="dev-code-wrap">
                <button type="button" class="dev-copy-btn" id="devCopyCurl">
                    <span class="material-icons-round" aria-hidden="true" style="font-size:14px">content_copy</span>
                    <?php echo __t('dev_copy_curl', 'saas'); ?>
                </button>
                <pre class="dev-code" id="devCurlSnippet"><?php echo htmlspecialchars($curlSnippet, ENT_QUOTES, 'UTF-8'); ?></pre>
            </div>
        </section>

        <section class="dev-grid" aria-label="<?php echo htmlspecialchars(__t('dev_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
            <a class="dev-card" href="../api-keys.php">
                <h3><span class="material-icons-round" aria-hidden="true">key</span><?php echo __t('apikeys_title', 'saas'); ?></h3>
                <p><?php echo __t('dev_card_keys', 'saas'); ?></p>
            </a>
            <a class="dev-card" href="../webhooks.php">
                <h3><span class="material-icons-round" aria-hidden="true">webhook</span><?php echo __t('webhooks_title', 'saas'); ?></h3>
                <p><?php echo __t('dev_card_webhooks', 'saas'); ?></p>
            </a>
            <a class="dev-card" href="../status.php">
                <h3><span class="material-icons-round" aria-hidden="true">monitor_heart</span><?php echo __t('status_title', 'saas'); ?></h3>
                <p><?php echo __t('dev_card_status', 'saas'); ?></p>
            </a>
            <a class="dev-card" href="openapi.php">
                <h3><span class="material-icons-round" aria-hidden="true">menu_book</span><?php echo __t('dev_openapi_title', 'saas'); ?></h3>
                <p><?php echo __t('dev_card_openapi', 'saas'); ?></p>
            </a>
        </section>

        <section class="dev-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">lock</span>
                <?php echo __t('dev_auth', 'saas'); ?>
            </h2>
            <ul class="dev-auth-list">
                <li>
                    <strong>JWT</strong> — <?php echo __t('dev_jwt_desc', 'saas'); ?>
                    <br><code>POST <?php echo htmlspecialchars($publicRoot . '/api/v2/index.php?request=auth/token', ENT_QUOTES, 'UTF-8'); ?></code>
                </li>
                <li>
                    <strong>API Key</strong> — <?php echo __t('dev_apikey_desc', 'saas'); ?>
                    <br><code>X-API-Key: rp_live_…</code>
                </li>
            </ul>
        </section>

        <nav class="dev-footer-links">
            <?php if ($isLoggedIn): ?>
            <a href="<?php echo htmlspecialchars($adminBackUrl, ENT_QUOTES, 'UTF-8'); ?>"><?php echo __t('dev_back_admin', 'saas'); ?></a>
            <?php endif; ?>
            <a href="../login.php"><?php echo __t('dev_back_login', 'saas'); ?></a>
            <a href="../signup-organization.php"><?php echo __t('signup_submit', 'saas'); ?></a>
            <a href="../billing.php"><?php echo __t('billing_title', 'saas'); ?></a>
        </nav>
    </main>
</div>

<script>
window.DEV_PORTAL_CONFIG = {
    apiV2Tenant: <?php echo json_encode($publicRoot . '/api/v2/index.php?request=tenant', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'dev_copy_curl' => __t('dev_copy_curl', 'saas'),
        'dev_copied' => __t('dev_copied', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../../assets/js/app-theme.js?v=3"></script>
<script src="../../assets/js/saas/developers-portal.js?v=1"></script>
</body>
</html>

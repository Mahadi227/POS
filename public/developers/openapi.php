<?php
require_once __DIR__ . '/../../includes/Config/session.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$openapiAccent = '#7c3aed';
$accentEsc = htmlspecialchars($openapiAccent, ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$themeAccent = $openapiAccent;

$specUrl = '../../docs/api/openapi-v2.yaml';
$yamlDownloadUrl = '../../docs/api/openapi-v2.yaml';
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <title><?php echo __t('openapi_page_title', 'saas'); ?></title>
    <?php include __DIR__ . '/../includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../../assets/css/saas-openapi.css?v=2">
    <style>:root { --openapi-accent: <?php echo $accentEsc; ?>; --dev-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="openapi-page">
<div class="openapi-shell">
    <aside class="openapi-hero-panel" aria-label="<?php echo htmlspecialchars(__t('openapi_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="openapi-hero-panel__inner">
            <div class="openapi-hero-brand">
                <span class="material-icons-round" aria-hidden="true">menu_book</span>
                <span>RetailPOS API</span>
            </div>
            <h2><?php echo __t('openapi_hero_title', 'saas'); ?></h2>
            <p><?php echo __t('openapi_hero_desc', 'saas'); ?></p>
            <ul class="openapi-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">play_circle</span>
                    <?php echo __t('openapi_feat_try', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">search</span>
                    <?php echo __t('openapi_feat_filter', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">vpn_key</span>
                    <?php echo __t('openapi_feat_auth', 'saas'); ?>
                </li>
            </ul>
            <div class="openapi-hero-links">
                <a href="index.php"><?php echo __t('openapi_back', 'saas'); ?></a>
                <span aria-hidden="true">·</span>
                <a href="../status.php"><?php echo __t('signup_status_link', 'saas'); ?></a>
            </div>
        </div>
    </aside>

    <main class="openapi-main-panel">
        <div class="openapi-topbar">
            <?php
            $changeUrl = '../change_language.php';
            include __DIR__ . '/../includes/language_switcher.php';
            ?>
            <a href="<?php echo htmlspecialchars($yamlDownloadUrl, ENT_QUOTES, 'UTF-8'); ?>"
               class="openapi-yaml-btn" download="openapi-v2.yaml"
               title="<?php echo htmlspecialchars(__t('openapi_download_yaml', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">download</span>
                YAML
            </a>
            <button type="button" class="openapi-theme-toggle" id="openapiThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <header class="openapi-content-header">
            <div class="openapi-badge">
                <span class="material-icons-round" aria-hidden="true">api</span>
                <?php echo __t('openapi_badge', 'saas'); ?>
            </div>
            <h1><?php echo __t('dev_openapi_title', 'saas'); ?></h1>
            <p><?php echo __t('openapi_subtitle', 'saas'); ?></p>
        </header>

        <section class="openapi-swagger-panel" aria-label="<?php echo htmlspecialchars(__t('openapi_spec_label', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
            <div id="swagger-ui"></div>
        </section>
    </main>
</div>

<script>
window.OPENAPI_CONFIG = {
    specUrl: <?php echo json_encode($specUrl, JSON_THROW_ON_ERROR); ?>
};
</script>
<script src="../../assets/js/app-theme.js?v=3"></script>
<script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
<script src="../../assets/js/saas/openapi.js?v=2"></script>
</body>
</html>

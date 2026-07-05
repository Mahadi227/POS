<?php
require_once __DIR__ . '/../includes/Config/session.php';

define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$lang = htmlspecialchars($_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en'), ENT_QUOTES, 'UTF-8');
$statusAccent = '#7c3aed';
$accentEsc = htmlspecialchars($statusAccent, ENT_QUOTES, 'UTF-8');
$themePortal = 'auth';
$themeAccent = $statusAccent;
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light" data-portal="auth" data-theme-accent="<?php echo $accentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="<?php echo $accentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $accentEsc; ?>">
    <title><?php echo __t('status_title', 'saas'); ?></title>
    <?php include __DIR__ . '/includes/theme-head.php'; ?>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/auth.css?v=2">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-status.css?v=2">
    <style>:root { --status-accent: <?php echo $accentEsc; ?>; --primary: <?php echo $accentEsc; ?>; }</style>
</head>
<body class="status-page">
<div class="status-shell">
    <aside class="status-hero-panel" aria-label="<?php echo htmlspecialchars(__t('status_hero_aria', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="status-hero-panel__inner">
            <div class="status-hero-brand">
                <span class="material-icons-round" aria-hidden="true">monitor_heart</span>
                <span>RetailPOS Cloud</span>
            </div>
            <h2><?php echo __t('status_hero_title', 'saas'); ?></h2>
            <p><?php echo __t('status_hero_desc', 'saas'); ?></p>
            <ul class="status-features">
                <li>
                    <span class="material-icons-round" aria-hidden="true">sync</span>
                    <?php echo __t('status_feat_live', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">dns</span>
                    <?php echo __t('status_feat_components', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">warning</span>
                    <?php echo __t('status_feat_incidents', 'saas'); ?>
                </li>
                <li>
                    <span class="material-icons-round" aria-hidden="true">api</span>
                    <?php echo __t('status_feat_api', 'saas'); ?>
                </li>
            </ul>
            <div class="status-hero-links">
                <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?> →</a>
            </div>
        </div>
    </aside>

    <main class="status-main-panel">
        <div class="status-topbar">
            <?php $changeUrl = 'change_language.php'; include __DIR__ . '/includes/language_switcher.php'; ?>
            <button type="button" class="status-refresh-btn" id="statusRefreshBtn"
                    title="<?php echo htmlspecialchars(__t('status_refresh', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true" style="font-size:18px">refresh</span>
                <?php echo __t('status_refresh', 'saas'); ?>
            </button>
            <button type="button" class="status-theme-toggle" id="statusThemeToggle" data-theme-toggle
                    title="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>"
                    aria-label="<?php echo htmlspecialchars(__t('theme', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round" aria-hidden="true">dark_mode</span>
            </button>
        </div>

        <header class="status-content-header">
            <div class="status-badge">
                <span class="material-icons-round" aria-hidden="true">public</span>
                <?php echo __t('status_badge', 'saas'); ?>
            </div>
            <h1><?php echo __t('status_title', 'saas'); ?></h1>
            <p><?php echo __t('status_subtitle', 'saas'); ?></p>
            <div class="status-overall" id="statusOverall" aria-live="polite">
                <span class="status-dot" aria-hidden="true"></span>
                <strong><?php echo __t('loading', 'saas'); ?>…</strong>
            </div>
        </header>

        <section class="status-panel status-components" id="statusComponents">
            <h2>
                <span class="material-icons-round" aria-hidden="true">hub</span>
                <?php echo __t('status_components', 'saas'); ?>
            </h2>
            <ul class="status-component-list" id="statusComponentList" aria-live="polite">
                <li class="status-loading">
                    <span class="spinner" aria-hidden="true"></span>
                    <?php echo __t('loading', 'saas'); ?>…
                </li>
            </ul>
        </section>

        <section class="status-panel status-incidents" id="statusIncidents" hidden>
            <h2>
                <span class="material-icons-round" aria-hidden="true">report</span>
                <?php echo __t('status_incidents', 'saas'); ?>
            </h2>
            <div id="statusIncidentList" aria-live="polite"></div>
        </section>

        <footer class="status-footer">
            <a href="health.php" target="_blank" rel="noopener noreferrer">
                <span class="material-icons-round" aria-hidden="true" style="font-size:16px;vertical-align:middle">link</span>
                <?php echo __t('status_health_link', 'saas'); ?>
            </a>
            <span id="statusUpdated"></span>
        </footer>

        <nav class="status-footer-links" aria-label="<?php echo htmlspecialchars(__t('status_nav', 'saas'), ENT_QUOTES, 'UTF-8'); ?>">
            <a href="login.php"><?php echo __t('status_back_login', 'saas'); ?></a>
            <a href="developers/index.php"><?php echo __t('dev_title', 'saas'); ?></a>
            <a href="signup-organization.php"><?php echo __t('signup_submit', 'saas'); ?></a>
        </nav>
    </main>
</div>

<script>
window.STATUS_CONFIG = {
    apiBase: <?php echo json_encode('../api/v1/index.php', JSON_THROW_ON_ERROR); ?>,
    i18n: <?php echo json_encode([
        'loading' => __t('loading', 'saas'),
        'operational' => __t('status_operational', 'saas'),
        'degraded' => __t('status_degraded', 'saas'),
        'partial_outage' => __t('status_partial', 'saas'),
        'major_outage' => __t('status_major', 'saas'),
        'maintenance' => __t('status_maintenance', 'saas'),
        'no_incidents' => __t('status_no_incidents', 'saas'),
        'no_components' => __t('status_no_components', 'saas'),
        'load_error' => __t('load_error', 'saas'),
        'last_updated' => __t('status_last_updated', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/app-theme.js?v=3"></script>
<script src="../assets/js/saas/status-page.js?v=2"></script>
</body>
</html>

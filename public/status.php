<?php
require_once __DIR__ . '/../includes/Config/session.php';
define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('status_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../assets/css/saas-status.css?v=1">
</head>
<body class="status-page">
<div class="status-shell">
    <header class="status-header">
        <h1><?php echo __t('status_title', 'saas'); ?></h1>
        <p><?php echo __t('status_subtitle', 'saas'); ?></p>
        <div class="status-overall" id="statusOverall" aria-live="polite">
            <span class="status-dot"></span>
            <strong><?php echo __t('loading', 'saas'); ?></strong>
        </div>
    </header>

    <section class="plat-panel status-components" id="statusComponents">
        <h2><?php echo __t('status_components', 'saas'); ?></h2>
        <ul class="status-component-list" id="statusComponentList"></ul>
    </section>

    <section class="plat-panel status-incidents" id="statusIncidents" hidden>
        <h2><?php echo __t('status_incidents', 'saas'); ?></h2>
        <div id="statusIncidentList"></div>
    </section>

    <footer class="status-footer">
        <a href="health.php" target="_blank" rel="noopener"><?php echo __t('status_health_link', 'saas'); ?></a>
        <span id="statusUpdated"></span>
    </footer>
</div>
<script>
window.STATUS_CONFIG = {
    apiBase: '../api/v1/index.php',
    i18n: <?php echo json_encode([
        'operational' => __t('status_operational', 'saas'),
        'degraded' => __t('status_degraded', 'saas'),
        'partial_outage' => __t('status_partial', 'saas'),
        'major_outage' => __t('status_major', 'saas'),
        'maintenance' => __t('status_maintenance', 'saas'),
        'no_incidents' => __t('status_no_incidents', 'saas'),
        'load_error' => __t('load_error', 'saas'),
    ], JSON_UNESCAPED_UNICODE); ?>
};
</script>
<script src="../assets/js/saas/status-page.js?v=1"></script>
</body>
</html>

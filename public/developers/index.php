<?php
define('I18N_SKIP_BROWSER_LANG', true);
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

if (empty($_COOKIE['lang'])) {
    LanguageManager::apply(ACTIVE_LANG);
}

$lang = htmlspecialchars($_SESSION['lang'] ?? 'en', ENT_QUOTES, 'UTF-8');
$base = dirname($_SERVER['SCRIPT_NAME'] ?? '');
$root = rtrim(dirname($base), '/');
?>
<!DOCTYPE html>
<html lang="<?php echo $lang; ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo __t('dev_title', 'saas'); ?></title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/platform-portal.css?v=2">
    <link rel="stylesheet" href="../../assets/css/saas-developers.css?v=1">
</head>
<body class="dev-page">
<div class="dev-shell">
    <header class="dev-hero">
        <h1><?php echo __t('dev_title', 'saas'); ?></h1>
        <p><?php echo __t('dev_subtitle', 'saas'); ?></p>
    </header>

    <section class="plat-panel dev-quickstart">
        <h2><?php echo __t('dev_quickstart', 'saas'); ?></h2>
        <ol>
            <li><?php echo __t('dev_step1', 'saas'); ?> — <a href="../api-keys.php"><?php echo __t('apikeys_title', 'saas'); ?></a></li>
            <li><?php echo __t('dev_step2', 'saas'); ?></li>
            <li><?php echo __t('dev_step3', 'saas'); ?></li>
        </ol>
        <pre class="dev-code">curl -H "X-API-Key: rp_live_…" \
  "<?php echo htmlspecialchars($root); ?>/api/v2/index.php?request=tenant"</pre>
    </section>

    <section class="dev-grid">
        <a class="plat-panel dev-card" href="../api-keys.php">
            <h3><?php echo __t('apikeys_title', 'saas'); ?></h3>
            <p><?php echo __t('dev_card_keys', 'saas'); ?></p>
        </a>
        <a class="plat-panel dev-card" href="../webhooks.php">
            <h3><?php echo __t('webhooks_title', 'saas'); ?></h3>
            <p><?php echo __t('dev_card_webhooks', 'saas'); ?></p>
        </a>
        <a class="plat-panel dev-card" href="../status.php">
            <h3><?php echo __t('status_title', 'saas'); ?></h3>
            <p><?php echo __t('dev_card_status', 'saas'); ?></p>
        </a>
        <a class="plat-panel dev-card" href="openapi.php">
            <h3>OpenAPI v2</h3>
            <p><?php echo __t('dev_card_openapi', 'saas'); ?></p>
        </a>
    </section>

    <section class="plat-panel">
        <h2><?php echo __t('dev_auth', 'saas'); ?></h2>
        <ul class="dev-auth-list">
            <li><strong>JWT</strong> — POST <code>api/v2/index.php?request=auth/token</code></li>
            <li><strong>API Key</strong> — header <code>X-API-Key: rp_live_…</code></li>
        </ul>
    </section>
</div>
</body>
</html>

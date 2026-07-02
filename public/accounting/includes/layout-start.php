<?php
/** @var string $pageTitle @var string $activeAccPage @var array $pageI18n @var array $extraScripts @var bool $loadChart */
if (!isset($pageTitle)) {
    $pageTitle = __t('module_title', 'accounting');
}
$extraScripts = $extraScripts ?? ['accounting-common.js'];
$loadChart = $loadChart ?? false;
$pageI18n = $pageI18n ?? [];
$accI18n = array_merge(acc_i18n($accCommonI18nKeys), $pageI18n);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="accounting" data-theme-accent="#059669">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#059669">
    <meta name="theme-accent" content="#059669">
    <?php
    $themeAccent = '#059669';
    $themePortal = 'accounting';
    include __DIR__ . '/../../includes/theme-head.php';
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Finance</title>
    <link rel="manifest" href="../manifest.json">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=2">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-accounting.css?v=24">
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="acc-page ad-page">
<div class="admin-layout">
    <aside class="sidebar acc-sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">account_balance</span>
                <h2>Finance<span class="dot">.</span></h2>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('section_finance', 'accounting'); ?></li>
            <?php foreach ($accNav as $item): ?>
            <li>
                <a href="<?php echo htmlspecialchars($item['href']); ?>" class="nav-link<?php echo ($activeAccPage ?? '') === $item['id'] ? ' active' : ''; ?>">
                    <span class="material-icons-round"><?php echo htmlspecialchars($item['icon']); ?></span>
                    <span><?php echo __t($item['label'], 'accounting'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <li class="nav-section"><?php echo __t('section_system', 'accounting'); ?></li>
            <li><a href="../admin/index.php" class="nav-link"><span class="material-icons-round">arrow_back</span><span><?php echo __t('back_admin', 'accounting'); ?></span></a></li>
            <li><a href="../logout.php" class="nav-link" style="color:var(--danger);"><span class="material-icons-round">logout</span><span><?php echo __t('logout', 'accounting'); ?></span></a></li>
        </ul>
        <div class="user-profile-widget">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'User'); ?></p>
                <p class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="main-content">
        <header class="top-header admin-page-header ad-page-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu"><span class="material-icons-round">menu</span></button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="date-display" id="accHeaderDate">—</span>
                        <span class="header-dot">·</span>
                        <span class="ih-last-updated" id="lastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-tools ad-header-tools">
                <div id="headerStoreSlot" class="header-store-slot"></div>
                <?php include __DIR__ . '/../../includes/language_switcher.php'; ?>
            </div>
            <div class="header-actions ad-header-actions">
                <button type="button" class="ad-refresh-btn" id="accRefreshBtn" title="<?php echo __t('refresh', 'accounting'); ?>"><span class="material-icons-round">refresh</span></button>
                <?php
                $themeLabel = __t('theme', 'accounting');
                include __DIR__ . '/../../includes/theme-toggle.php';
                ?>
            </div>
        </header>
        <div class="dashboard-scroll-area">
            <div class="ad-error-banner" id="accError"><span class="material-icons-round">error_outline</span><span class="ad-error-text"></span></div>
            <p class="acc-migration-hint" id="accMigrationHint"<?php echo !empty($accModuleReady) ? ' hidden' : ''; ?>><span class="material-icons-round">info</span> <?php echo __t('migration_hint', 'accounting'); ?></p>

<?php
/** @var string $pageTitle @var string $activePlatPage @var array $pageI18n @var array $extraScripts */
if (!isset($pageTitle)) {
    $pageTitle = __t('plat_title', 'platform');
}
$extraScripts = $extraScripts ?? ['platform-common.js'];
$pageI18n = $pageI18n ?? [];
$platI18n = array_merge(plat_i18n($platCommonI18nKeys), $pageI18n);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="platform" data-theme-accent="#7c3aed">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#7c3aed">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Cloud</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=2">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/platform-portal.css?v=2">
</head>
<body class="plat-page ad-page">
<div class="admin-layout">
    <aside class="sidebar plat-sidebar" id="platSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">cloud</span>
                <h2>Cloud<span class="dot">.</span></h2>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('plat_section', 'platform'); ?></li>
            <li>
                <a href="index.php" class="nav-link<?php echo ($activePlatPage ?? '') === 'dashboard' ? ' active' : ''; ?>">
                    <span class="material-icons-round">dashboard</span>
                    <span><?php echo __t('plat_nav_dashboard', 'platform'); ?></span>
                </a>
            </li>
            <li>
                <a href="tenants.php" class="nav-link<?php echo ($activePlatPage ?? '') === 'tenants' ? ' active' : ''; ?>">
                    <span class="material-icons-round">business</span>
                    <span><?php echo __t('plat_nav_tenants', 'platform'); ?></span>
                </a>
            </li>
            <li>
                <a href="status.php" class="nav-link<?php echo ($activePlatPage ?? '') === 'status' ? ' active' : ''; ?>">
                    <span class="material-icons-round">monitor_heart</span>
                    <span><?php echo __t('plat_nav_status', 'platform'); ?></span>
                </a>
            </li>
            <li class="nav-section"><?php echo __t('plat_section_system', 'platform'); ?></li>
            <li>
                <a href="../admin/index.php" class="nav-link">
                    <span class="material-icons-round">storefront</span>
                    <span><?php echo __t('plat_open_tenant_app', 'platform'); ?></span>
                </a>
            </li>
            <li>
                <a href="logout.php" class="nav-link plat-nav-logout">
                    <span class="material-icons-round">logout</span>
                    <span><?php echo __t('plat_logout', 'platform'); ?></span>
                </a>
            </li>
        </ul>
        <div class="user-profile-widget">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <p class="name"><?php echo htmlspecialchars($_SESSION['platform_name'] ?? ''); ?></p>
                <p class="role"><?php echo htmlspecialchars($_SESSION['platform_role'] ?? ''); ?></p>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-header ad-page-header plat-page-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn" id="platMenuBtn" aria-label="Menu">
                    <span class="material-icons-round">menu</span>
                </button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="ih-last-updated" id="platLastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-actions ad-header-actions">
                <button type="button" class="ad-refresh-btn ad-header-icon" id="platRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'platform'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">refresh</span>
                </button>
            </div>
        </header>
        <div class="dashboard-scroll-area">

<?php
/** @var string $pageTitle @var string $activeWhPage @var array $pageI18n @var array $extraScripts @var array $extraAdminScripts @var bool $loadChart @var bool $loadScanner @var bool $useWmsModules @var string $whBreadcrumb */
if (!isset($pageTitle)) {
    $pageTitle = __t('wh_title', 'warehouse');
}
$extraScripts = $extraScripts ?? ['warehouse-common.js'];
$extraCss = $extraCss ?? [];
$extraAdminScripts = $extraAdminScripts ?? [];
$loadChart = $loadChart ?? false;
$loadScanner = $loadScanner ?? false;
$useWmsModules = $useWmsModules ?? false;
$pageI18n = $pageI18n ?? [];
$whBreadcrumb = $whBreadcrumb ?? '';
$whI18n = array_merge(wh_i18n($whCommonI18nKeys), $pageI18n);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="warehouse" data-theme-accent="#0d9488">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <meta name="theme-accent" content="#0d9488">
    <?php
    $themeAccent = '#0d9488';
    $themePortal = 'warehouse';
    include __DIR__ . '/../../includes/theme-head.php';
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Warehouse</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($whManifest, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=2">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/warehouse-portal.css?v=76">
    <?php if ($useWmsModules): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-wms.css?v=24">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/wh-form-modals.css?v=1">
    <?php endif; ?>
    <?php foreach ($extraCss as $cssFile): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($cssFile, ENT_QUOTES, 'UTF-8'); ?>?v=26">
    <?php endforeach; ?>
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
    <?php if ($loadScanner): ?>
    <script src="https://unpkg.com/html5-qrcode@2.3.8/html5-qrcode.min.js" type="text/javascript"></script>
    <?php endif; ?>
</head>
<body class="wh-page ad-page">
<div class="admin-layout">
    <div class="sidebar-overlay" id="whSidebarOverlay"></div>
    <aside class="sidebar wh-sidebar" id="whSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">warehouse</span>
                <h2>Warehouse<span class="dot">.</span></h2>
            </div>
        </div>
        <ul class="nav-menu">
            <?php foreach ($whNav as $section): ?>
            <li class="nav-section"><?php echo __t($section['label'], 'warehouse'); ?></li>
            <?php foreach ($section['items'] as $item):
                $href = $item['href'];
                $isActive = ($activeWhPage ?? '') === $item['id'];
                if (str_contains($href, '/')) {
                    $isActive = $isActive || str_ends_with($_SERVER['PHP_SELF'] ?? '', str_replace('/', DIRECTORY_SEPARATOR, $href));
                }
            ?>
            <li>
                <a href="<?php echo htmlspecialchars($whRootPrefix . $href, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActive ? ' active' : ''; ?>">
                    <span class="material-icons-round"><?php echo htmlspecialchars($item['icon']); ?></span>
                    <span><?php echo __t($item['label'], 'warehouse'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php endforeach; ?>
            <li class="nav-section"><?php echo __t('wh_section_system', 'warehouse'); ?></li>
            <?php if ($whCanAccessAdmin): ?>
            <li>
                <a href="<?php echo htmlspecialchars($whAdminUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">
                    <span class="material-icons-round">arrow_back</span>
                    <span><?php echo __t('wh_back_admin', 'warehouse'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li><a href="<?php echo htmlspecialchars($whLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link wh-nav-logout"><span class="material-icons-round">logout</span><span><?php echo __t('logout', 'warehouse'); ?></span></a></li>
        </ul>
        <div class="user-profile-widget">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></p>
                <p class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
            </div>
        </div>
    </aside>
    <main class="main-content">
        <header class="top-header ad-page-header wh-page-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="whMenuBtn" aria-label="<?php echo htmlspecialchars(__t('menu', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">menu</span>
                </button>
                <div class="header-title-group wh-header-title">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <?php if ($whBreadcrumb): ?>
                    <nav class="wh-breadcrumb header-subline" aria-label="Breadcrumb"><?php echo $whBreadcrumb; ?></nav>
                    <?php endif; ?>
                </div>
            </div>
            <div class="header-tools ad-header-tools wh-header-tools">
                <form class="wh-global-search" id="whGlobalSearchForm" role="search">
                    <span class="material-icons-round" aria-hidden="true">search</span>
                    <input type="search" id="whGlobalSearch" placeholder="<?php echo htmlspecialchars(__t('wh_search_placeholder', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" aria-label="<?php echo htmlspecialchars(__t('wh_search_placeholder', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                </form>
            </div>
            <div class="header-actions ad-header-actions wh-header-actions">
                <?php if ($whCanSwitchStore): ?>
                <div id="headerStoreSlot" class="wh-header-store"></div>
                <?php endif; ?>
                <a href="<?php echo htmlspecialchars($whRootPrefix . 'notifications.php', ENT_QUOTES, 'UTF-8'); ?>" class="wh-notif-btn ad-header-icon" id="whNotifBtn" aria-label="<?php echo htmlspecialchars(__t('wh_nav_notifications', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">notifications</span>
                    <span class="wh-notif-badge" id="whNotifBadge" hidden>0</span>
                </a>
                <?php
                $themeLabel = __t('theme', 'warehouse');
                include __DIR__ . '/../../includes/theme-toggle.php';
                ?>
                <button type="button" class="ad-refresh-btn ad-header-icon" id="whRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('refresh', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">refresh</span>
                </button>
            </div>
        </header>
        <div class="dashboard-scroll-area">
            <div id="whErrorBanner" class="ad-error-banner" hidden role="alert"></div>
            <div id="whMigrationHint" class="wh-migration-hint" hidden></div>
            <div id="whSearchResults" class="wh-search-dropdown" hidden></div>

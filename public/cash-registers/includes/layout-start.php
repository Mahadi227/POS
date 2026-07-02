<?php
/** @var string $pageTitle @var string $activeCrPage @var array $pageI18n @var array $extraCss @var array $extraScripts @var bool $loadChart @var string $crPageData */
if (!isset($pageTitle)) {
    $pageTitle = __t('cr_title', 'admin');
}
$extraCss = $extraCss ?? [];
$extraScripts = $extraScripts ?? ['cash-registers-common.js'];
$loadChart = $loadChart ?? false;
$pageI18n = $pageI18n ?? [];
$crPageData = $crPageData ?? '';
$crI18n = array_merge(cr_i18n($crCommonI18nKeys), $pageI18n);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="cash-registers" data-theme-accent="#2563eb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <meta name="theme-accent" content="#2563eb">
    <?php
    $themeAccent = '#2563eb';
    $themePortal = 'cash-registers';
    include __DIR__ . '/../../includes/theme-head.php';
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS Caisses</title>
    <link rel="manifest" href="<?php echo htmlspecialchars($crManifest, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=2">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-cash-registers.css?v=19">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/cash-registers-portal.css?v=1">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="cr-portal-page cr-admin-page ad-page"<?php echo $crPageData !== '' ? ' data-cr-page="' . htmlspecialchars($crPageData, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
<div class="admin-layout">
    <div class="sidebar-overlay" id="crSidebarOverlay"></div>
    <aside class="sidebar cr-portal-sidebar" id="crSidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">point_of_sale</span>
                <h2>Caisses<span class="dot">.</span></h2>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('cr_section', 'admin'); ?></li>
            <?php foreach ($crNav as $item):
                $href = $crRootPrefix . $item['href'];
                $isActive = ($activeCrPage ?? '') === $item['id'];
            ?>
            <li>
                <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActive ? ' active' : ''; ?>">
                    <span class="material-icons-round"><?php echo htmlspecialchars($item['icon']); ?></span>
                    <span><?php echo __t($item['label'], 'admin'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if ($canManageRegisters): ?>
            <li>
                <a href="<?php echo htmlspecialchars($crRootPrefix . 'settings.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo ($activeCrPage ?? '') === 'settings' ? ' active' : ''; ?>">
                    <span class="material-icons-round">settings</span>
                    <span><?php echo __t('cr_nav_settings', 'admin'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
            <li>
                <a href="<?php echo htmlspecialchars($crPosUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">
                    <span class="material-icons-round">shopping_cart</span>
                    <span><?php echo __t('cr_open_pos', 'admin'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($crAdminUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">
                    <span class="material-icons-round">arrow_back</span>
                    <span><?php echo __t('cr_back_admin', 'admin'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($crLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link cr-nav-logout">
                    <span class="material-icons-round">logout</span>
                    <span><?php echo __t('logout', 'admin'); ?></span>
                </a>
            </li>
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
        <header class="top-header admin-page-header ad-page-header cr-portal-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="crMenuBtn" aria-label="<?php echo htmlspecialchars(__t('menu', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">menu</span>
                </button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="date-display" id="crHeaderDate">—</span>
                        <span class="header-dot">·</span>
                        <span class="ih-last-updated" id="lastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-tools ad-header-tools">
                <?php if ($crCanSwitchStore): ?>
                <div id="headerStoreSlot" class="header-store-slot"></div>
                <?php endif; ?>
                <?php include __DIR__ . '/../../includes/language_switcher.php'; ?>
            </div>
            <div class="header-actions ad-header-actions">
                <button type="button" class="ad-refresh-btn ad-header-icon" id="crRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('refresh', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">refresh</span>
                </button>
                <?php
                $themeLabel = __t('theme', 'admin');
                include __DIR__ . '/../../includes/theme-toggle.php';
                ?>
            </div>
        </header>
        <div class="dashboard-scroll-area">
            <div class="ad-error-banner" id="crError"><span class="material-icons-round">error_outline</span><span class="ad-error-text"></span></div>
            <p class="cr-migration-hint" id="crMigrationHint"<?php echo !empty($crModuleReady) ? ' hidden' : ''; ?>><span class="material-icons-round">info</span> <?php echo __t('cr_migration_hint', 'admin'); ?></p>

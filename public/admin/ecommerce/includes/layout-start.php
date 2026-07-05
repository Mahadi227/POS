<?php
/** @var string $pageTitle @var string $activeEcomPage @var array $pageI18n @var array $extraCss @var array $extraScripts @var bool $loadChart */
$pageTitle = $pageTitle ?? __t('ecom_title', 'admin');
$extraCss = $extraCss ?? [];
$extraScripts = $extraScripts ?? ['ecommerce-common.js'];
$loadChart = $loadChart ?? false;
$pageI18n = $pageI18n ?? [];
$ecomI18n = array_merge(ecom_i18n($ecomCommonI18nKeys), $pageI18n);
$portalAccent = $adminAccent ?? $ecomAccent ?? '#2563eb';
$portalBrand = $adminBrandName ?? $ecomBrandName ?? 'E-Shop';
$portalAccentEsc = htmlspecialchars($portalAccent, ENT_QUOTES, 'UTF-8');
$accentSoft = function_exists('admin_hex_rgba') ? admin_hex_rgba($portalAccent, 0.12) : 'rgba(37, 99, 235, 0.12)';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="ecommerce" data-theme-accent="<?php echo $portalAccentEsc; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="<?php echo $portalAccentEsc; ?>">
    <meta name="theme-accent" content="<?php echo $portalAccentEsc; ?>">
    <?php if (($adminFaviconUrl ?? '') !== ''): ?>
    <link rel="icon" href="<?php echo htmlspecialchars($adminFaviconUrl, ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
    <?php endif; ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — <?php echo htmlspecialchars($portalBrand, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-ecommerce.css?v=3">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>">
    <?php endforeach; ?>
    <?php echo admin_theme_css_block($portalAccent); ?>
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="ecom-portal-page ad-page">
<?php
require_once __DIR__ . '/../../../../includes/Helpers/ImpersonationBanner.php';
ImpersonationBanner::render($ecomPublicPrefix . 'platform/exit-impersonation.php');
?>
<div class="admin-layout">
    <div class="sidebar-overlay" id="ecomSidebarOverlay"></div>
    <aside class="sidebar ecom-portal-sidebar" id="ecomSidebar">
        <?php
        $brandLabel = $portalBrand;
        include __DIR__ . '/../../includes/sidebar-header.php';
        ?>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('ecom_section', 'admin'); ?></li>
            <?php foreach ($ecomNav as $item):
                $href = $ecomRootPrefix . $item['href'];
                $isActive = ($activeEcomPage ?? '') === $item['id'];
            ?>
            <li>
                <a href="<?php echo htmlspecialchars($href, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo $isActive ? ' active' : ''; ?>">
                    <span class="material-icons-round"><?php echo htmlspecialchars($item['icon']); ?></span>
                    <span><?php echo __t($item['label'], 'admin'); ?></span>
                </a>
            </li>
            <?php endforeach; ?>
            <?php if ($canManageEcom): ?>
            <li>
                <a href="<?php echo htmlspecialchars($ecomRootPrefix . 'settings.php', ENT_QUOTES, 'UTF-8'); ?>" class="nav-link<?php echo ($activeEcomPage ?? '') === 'settings' ? ' active' : ''; ?>">
                    <span class="material-icons-round">settings</span>
                    <span><?php echo __t('ecom_nav_settings', 'admin'); ?></span>
                </a>
            </li>
            <?php endif; ?>
            <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
            <li>
                <a href="<?php echo htmlspecialchars($ecomStorefrontUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link" target="_blank" rel="noopener">
                    <span class="material-icons-round">open_in_new</span>
                    <span><?php echo __t('ecom_open_storefront', 'admin'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($ecomAdminUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link">
                    <span class="material-icons-round">arrow_back</span>
                    <span><?php echo __t('ecom_back_admin', 'admin'); ?></span>
                </a>
            </li>
            <li>
                <a href="<?php echo htmlspecialchars($ecomLogoutUrl, ENT_QUOTES, 'UTF-8'); ?>" class="nav-link ecom-nav-logout">
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
        <header class="top-header admin-page-header ad-page-header ecom-portal-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="ecomMenuBtn" aria-label="<?php echo htmlspecialchars(__t('menu', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">menu</span>
                </button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="date-display" id="ecomHeaderDate">—</span>
                        <span class="header-dot">·</span>
                        <span class="ih-last-updated" id="lastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-tools ad-header-tools">
                <?php if ($ecomCanSwitchStore): ?>
                <div id="headerStoreSlot" class="header-store-slot"></div>
                <?php endif; ?>
                <?php include __DIR__ . '/../../../includes/language_switcher.php'; ?>
            </div>
            <div class="header-actions ad-header-actions">
                <a href="<?php echo htmlspecialchars($ecomStorefrontUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ad-refresh-btn ecom-header-store-link" target="_blank" rel="noopener" title="<?php echo htmlspecialchars(__t('ecom_open_storefront', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">storefront</span>
                    <span class="btn-label"><?php echo __t('ecom_open_storefront', 'admin'); ?></span>
                </a>
                <button type="button" class="ad-refresh-btn" id="ecomRefreshBtn" title="<?php echo htmlspecialchars(__t('refresh', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">refresh</span>
                    <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                </button>
                <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo htmlspecialchars(__t('theme', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                    <span class="material-icons-round">dark_mode</span>
                </button>
            </div>
        </header>
        <div class="dashboard-scroll-area ecom-scroll-area">

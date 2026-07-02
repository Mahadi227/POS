<?php
/** @var string $pageTitle @var string $activeCrPage @var array $pageI18n @var array $extraCss @var array $extraScripts @var bool $loadChart */
if (!isset($pageTitle)) {
    $pageTitle = __t('cr_title', 'admin');
}
$extraCss = $extraCss ?? [];
$extraScripts = $extraScripts ?? [];
$loadChart = $loadChart ?? false;
$crI18n = array_merge(cr_i18n($crCommonI18nKeys), $pageI18n ?? []);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="registers" data-theme-accent="#2563eb">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <meta name="theme-accent" content="#2563eb">
    <?php
    $themeAccent = '#2563eb';
    $themePortal = 'registers';
    include __DIR__ . '/../../../includes/theme-head.php';
    ?>
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-cash-registers.css?v=17">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css); ?>">
    <?php endforeach; ?>
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="cr-admin-page ad-page"<?php echo !empty($crPageData) ? ' data-cr-page="' . htmlspecialchars($crPageData, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo">
                <span class="material-icons-round">point_of_sale</span>
                <h2>RetailPOS<span class="dot">.</span></h2>
            </div>
        </div>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('nav_main', 'admin'); ?></li>
            <li><a href="../index.php" class="nav-link"><span class="material-icons-round">dashboard</span><span><?php echo __t('nav_dashboard', 'admin'); ?></span></a></li>
            <li><a href="../sales.php" class="nav-link"><span class="material-icons-round">point_of_sale</span><span><?php echo __t('nav_sales', 'admin'); ?></span></a></li>
            <li><a href="../inventory.php" class="nav-link"><span class="material-icons-round">inventory_2</span><span><?php echo __t('nav_inventory', 'admin'); ?></span></a></li>
            <?php $activePage = 'cash_registers'; include __DIR__ . '/../../includes/sidebar-extra.php'; ?>
            <li class="nav-section"><?php echo __t('cr_section', 'admin'); ?></li>
            <li><a href="dashboard.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'dashboard' ? ' active' : ''; ?>"><span class="material-icons-round">dashboard</span><span><?php echo __t('cr_nav_dashboard', 'admin'); ?></span></a></li>
            <li><a href="registers.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'registers' ? ' active' : ''; ?>"><span class="material-icons-round">storefront</span><span><?php echo __t('cr_nav_registers', 'admin'); ?></span></a></li>
            <li><a href="reconciliation.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'reconciliation' ? ' active' : ''; ?>"><span class="material-icons-round">account_balance_wallet</span><span><?php echo __t('cr_nav_reconciliation', 'admin'); ?></span></a></li>
            <li><a href="cash_movements.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'movements' ? ' active' : ''; ?>"><span class="material-icons-round">swap_horiz</span><span><?php echo __t('cr_nav_movements', 'admin'); ?></span></a></li>
            <li><a href="cash_transfers.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'transfers' ? ' active' : ''; ?>"><span class="material-icons-round">sync_alt</span><span><?php echo __t('cr_nav_transfers', 'admin'); ?></span></a></li>
            <li><a href="shift_management.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'shifts' ? ' active' : ''; ?>"><span class="material-icons-round">schedule</span><span><?php echo __t('cr_nav_shifts', 'admin'); ?></span></a></li>
            <li><a href="reports.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'reports' ? ' active' : ''; ?>"><span class="material-icons-round">summarize</span><span><?php echo __t('cr_nav_reports', 'admin'); ?></span></a></li>
            <li><a href="analytics.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'analytics' ? ' active' : ''; ?>"><span class="material-icons-round">analytics</span><span><?php echo __t('cr_nav_analytics', 'admin'); ?></span></a></li>
            <li><a href="logs.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'logs' ? ' active' : ''; ?>"><span class="material-icons-round">history</span><span><?php echo __t('cr_nav_logs', 'admin'); ?></span></a></li>
            <?php if ($canManageRegisters): ?>
            <li><a href="settings.php" class="nav-link<?php echo ($activeCrPage ?? '') === 'settings' ? ' active' : ''; ?>"><span class="material-icons-round">settings</span><span><?php echo __t('cr_nav_settings', 'admin'); ?></span></a></li>
            <?php endif; ?>
            <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
            <li><a href="../../cashier/pos.php" class="nav-link"><span class="material-icons-round">shopping_cart</span><span><?php echo __t('nav_pos', 'admin'); ?></span></a></li>
            <li><a href="../../logout.php" class="nav-link" style="color:var(--danger);margin-top:12px;"><span class="material-icons-round">logout</span><span><?php echo __t('logout', 'admin'); ?></span></a></li>
        </ul>
        <div class="user-profile-widget">
            <div class="avatar"><?php echo htmlspecialchars($initial); ?></div>
            <div class="user-info">
                <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></p>
                <p class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
            </div>
        </div>
    </aside>
    <div class="sidebar-overlay" id="sidebarOverlay"></div>
    <main class="main-content">
        <header class="top-header admin-page-header ad-page-header">
            <div class="header-left ad-header-left">
                <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'admin'); ?>"><span class="material-icons-round">menu</span></button>
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
                <div id="headerStoreSlot" class="header-store-slot"></div>
                <?php include __DIR__ . '/../../../includes/language_switcher.php'; ?>
            </div>
            <div class="header-actions ad-header-actions">
                <button type="button" class="ad-refresh-btn" id="crRefreshBtn" title="<?php echo __t('refresh', 'admin'); ?>"><span class="material-icons-round">refresh</span><span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span></button>
                <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>"><span class="material-icons-round">dark_mode</span></button>
            </div>
        </header>
        <div class="dashboard-scroll-area">
            <div class="ad-error-banner" id="crError"><span class="material-icons-round">error_outline</span><span class="ad-error-text"></span></div>
            <p class="cr-migration-hint" id="crMigrationHint" hidden><span class="material-icons-round">info</span> <?php echo __t('cr_migration_hint', 'admin'); ?></p>

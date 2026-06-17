<?php
if (!isset($pageTitle)) {
    $pageTitle = __t('wms_title', 'wms');
}
$extraCss = $extraCss ?? [];
$extraScripts = $extraScripts ?? [];
$loadChart = $loadChart ?? false;
$wmsI18n = array_merge(wms_i18n($wmsCommonI18nKeys), $pageI18n ?? []);
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#0d9488">
    <title><?php echo htmlspecialchars($pageTitle); ?> — RetailPOS WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin.css">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-cash-registers.css?v=1">
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/admin-wms.css?v=23">
    <?php foreach ($extraCss as $css): ?>
    <link rel="stylesheet" href="<?php echo $assetsBase; ?>/css/<?php echo htmlspecialchars($css, ENT_QUOTES, 'UTF-8'); ?>?v=1">
    <?php endforeach; ?>
    <?php if ($loadChart): ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <?php endif; ?>
</head>
<body class="wms-admin-page ad-page cr-admin-page"<?php echo !empty($wmsPageData) ? ' data-wms-page="' . htmlspecialchars($wmsPageData, ENT_QUOTES, 'UTF-8') . '"' : ''; ?>>
<div class="admin-layout">
    <aside class="sidebar">
        <div class="sidebar-header">
            <div class="logo"><span class="material-icons-round">warehouse</span><h2>RetailPOS<span class="dot">.</span></h2></div>
        </div>
        <ul class="nav-menu">
            <li class="nav-section"><?php echo __t('nav_main', 'admin'); ?></li>
            <li><a href="../index.php" class="nav-link"><span class="material-icons-round">dashboard</span><span><?php echo __t('nav_dashboard', 'admin'); ?></span></a></li>
            <li><a href="../inventory.php" class="nav-link"><span class="material-icons-round">inventory_2</span><span><?php echo __t('nav_inventory', 'admin'); ?></span></a></li>
            <?php $activePage = 'warehouse'; include __DIR__ . '/../../includes/sidebar-extra.php'; ?>
            <li class="nav-section"><?php echo __t('wms_section', 'wms'); ?></li>
            <?php
            $nav = [
                ['dashboard', 'wms_nav_dashboard', 'dashboard'],
                ['warehouses', 'wms_nav_warehouses', 'warehouse'],
                ['warehouse_inventory', 'wms_nav_inventory', 'inventory_2'],
                ['warehouse_locations', 'wms_nav_locations', 'place'],
                ['goods_receipts', 'wms_nav_receipts', 'move_to_inbox'],
                ['stock_dispatch', 'wms_nav_dispatch', 'local_shipping'],
                ['stock_requests', 'wms_nav_requests', 'assignment'],
                ['stock_transfers', 'wms_nav_transfers', 'sync_alt'],
                ['batch_management', 'wms_nav_batches', 'qr_code_2'],
                ['expiry_management', 'wms_nav_expiry', 'event_busy'],
                ['inventory_audit', 'wms_nav_audit', 'fact_check'],
                ['reports', 'wms_nav_reports', 'summarize'],
                ['analytics', 'wms_nav_analytics', 'analytics'],
                ['logs', 'wms_nav_logs', 'history'],
                ['sync-monitor', 'wms_nav_sync', 'cloud_sync'],
            ];
            foreach ($nav as [$file, $label, $icon]): ?>
            <li><a href="<?php echo $file; ?>.php" class="nav-link<?php echo ($activeWmsPage ?? '') === $file ? ' active' : ''; ?>"><span class="material-icons-round"><?php echo $icon; ?></span><span><?php echo __t($label, 'wms'); ?></span></a></li>
            <?php endforeach; ?>
            <?php if (($roleSlug ?? '') === 'super_admin'): ?>
            <li><a href="stores.php" class="nav-link<?php echo ($activeWmsPage ?? '') === 'stores' ? ' active' : ''; ?>"><span class="material-icons-round">storefront</span><span><?php echo __t('nav_stores', 'admin'); ?></span></a></li>
            <li><a href="users.php" class="nav-link<?php echo ($activeWmsPage ?? '') === 'users' ? ' active' : ''; ?>"><span class="material-icons-round">group</span><span><?php echo __t('nav_users', 'admin'); ?></span></a></li>
            <?php endif; ?>
            <?php if ($canManageWms): ?>
            <li><a href="settings.php" class="nav-link<?php echo ($activeWmsPage ?? '') === 'settings' ? ' active' : ''; ?>"><span class="material-icons-round">settings</span><span><?php echo __t('wms_nav_settings', 'wms'); ?></span></a></li>
            <?php endif; ?>
            <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
            <li><a href="../../logout.php" class="nav-link" style="color:var(--danger)"><span class="material-icons-round">logout</span><span><?php echo __t('logout', 'admin'); ?></span></a></li>
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
                <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn"><span class="material-icons-round">menu</span></button>
                <div class="header-title-group">
                    <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
                    <div class="header-subline">
                        <span class="date-display" id="wmsHeaderDate">—</span>
                        <span class="header-dot">·</span>
                        <span id="lastUpdated"></span>
                    </div>
                </div>
            </div>
            <div class="header-tools ad-header-tools">
                <div id="headerStoreSlot" class="header-store-slot"></div>
                <?php include __DIR__ . '/../../../includes/language_switcher.php'; ?>
            </div>
            <div class="header-actions ad-header-actions">
                <button type="button" class="ad-refresh-btn" id="wmsRefreshBtn"><span class="material-icons-round">refresh</span><span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span></button>
                <button type="button" class="icon-btn theme-toggle" id="theme-toggle"><span class="material-icons-round">dark_mode</span></button>
            </div>
        </header>
        <div class="dashboard-scroll-area">
            <div class="ad-error-banner" id="wmsError"><span class="material-icons-round">error_outline</span><span class="ad-error-text"></span></div>
            <p class="cr-migration-hint" id="wmsMigrationHint" hidden><span class="material-icons-round">info</span> <?php echo __t('wms_migration_hint', 'wms'); ?></p>

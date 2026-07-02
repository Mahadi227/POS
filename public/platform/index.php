<?php
require __DIR__ . '/includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'dashboard';
$pageTitle = __t('plat_nav_dashboard', 'platform');
$extraScripts = ['platform-common.js', 'platform-dashboard.js'];
$pageI18n = plat_i18n([
    'plat_kpi_tenants', 'plat_kpi_active', 'plat_kpi_stores', 'plat_kpi_users',
    'plat_tenants_by_status', 'plat_schema_version',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="plat-kpi-grid" id="platKpiGrid" aria-live="polite">
    <article class="plat-kpi-card">
        <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_tenants', 'platform'); ?></span>
        <strong class="plat-kpi-card__value" id="platKpiTenants">—</strong>
    </article>
    <article class="plat-kpi-card plat-kpi-card--success">
        <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_active', 'platform'); ?></span>
        <strong class="plat-kpi-card__value" id="platKpiActive">—</strong>
    </article>
    <article class="plat-kpi-card">
        <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_stores', 'platform'); ?></span>
        <strong class="plat-kpi-card__value" id="platKpiStores">—</strong>
    </article>
    <article class="plat-kpi-card">
        <span class="plat-kpi-card__label"><?php echo __t('plat_kpi_users', 'platform'); ?></span>
        <strong class="plat-kpi-card__value" id="platKpiUsers">—</strong>
    </article>
</section>

<section class="plat-panel">
    <h2><?php echo __t('plat_tenants_by_status', 'platform'); ?></h2>
    <ul class="plat-status-list" id="platStatusList">
        <li><span>Trial</span><strong id="platStatTrial">—</strong></li>
        <li><span>Active</span><strong id="platStatActive">—</strong></li>
        <li><span>Suspended</span><strong id="platStatSuspended">—</strong></li>
        <li><span>Cancelled</span><strong id="platStatCancelled">—</strong></li>
    </ul>
    <p class="plat-meta"><span><?php echo __t('plat_schema_version', 'platform'); ?>:</span> <code id="platSchemaVersion">—</code></p>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

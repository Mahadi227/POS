<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWmsPage = 'warehouses';
$pageTitle = __t('wms_warehouses_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-warehouses.js'];
$pageI18n = wms_i18n([
    'wms_warehouses_subtitle', 'wms_new_warehouse', 'wms_wh_code', 'wms_wh_name', 'wms_wh_type',
    'wms_wh_manager', 'wms_stat_inv_value', 'wms_col_units', 'wms_col_store', 'col_status',
    'wms_no_warehouses', 'wms_search_warehouse', 'wms_filter_all_status', 'wms_status_active',
    'wms_status_inactive', 'wms_stat_total_wh', 'wms_stat_active_wh', 'wms_stat_inv_units',
    'view_all', 'refresh', 'loading', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_warehouses_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <?php if ($canManageWms): ?>
        <a href="create_warehouse.php" class="cr-btn"><span class="material-icons-round">add</span><?php echo __t('wms_new_warehouse', 'wms'); ?></a>
        <?php endif; ?>
        <input id="wmsWhSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_warehouse', 'wms')); ?>" style="max-width:320px;">
        <select id="wmsWhStatus" class="form-input" style="max-width:200px;">
            <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
            <option value="inactive"><?php echo __t('wms_status_inactive', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsWhRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">warehouse</span></div><div class="card-info"><h3><?php echo __t('wms_stat_total_wh', 'wms'); ?></h3><h2 id="wmsWhTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_active_wh', 'wms'); ?></h3><h2 id="wmsWhActive">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">inventory</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_units', 'wms'); ?></h3><h2 id="wmsWhUnits">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_value', 'wms'); ?></h3><h2 id="wmsWhValue">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">warehouse</span><?php echo __t('wms_warehouses_title', 'wms'); ?></h3>
    <div id="wmsWarehousesRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

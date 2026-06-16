<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'warehouse_inventory';
$pageTitle = __t('wms_inventory_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-inventory.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_select_warehouse',
    'wms_inventory_title', 'wms_inventory_subtitle', 'wms_stat_inv_skus', 'wms_stat_inv_units',
    'wms_stat_inv_value', 'wms_stat_inv_low', 'wms_search_inventory', 'wms_filter_all_stock',
    'wms_stock_low', 'wms_stock_out', 'wms_stock_damaged_filter', 'wms_col_product', 'wms_col_qty',
    'wms_col_available', 'wms_col_reserved', 'wms_col_value', 'wms_col_reorder', 'wms_col_location',
    'wms_col_unit_cost', 'wms_col_batch', 'wms_col_last_movement', 'wms_inventory_details',
    'wms_recent_movements', 'wms_view_details', 'wms_export_csv', 'wms_nav_warehouses', 'close',
    'col_date', 'wms_col_damaged', 'wms_col_expired', 'wms_stock_ok', 'wms_stock_alert',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_inventory_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <select id="wmsInvWarehouse" class="form-input" style="max-width:240px;" required>
            <option value=""><?php echo __t('wms_select_warehouse', 'wms'); ?></option>
        </select>
        <input id="wmsInvSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_inventory', 'wms')); ?>" style="max-width:280px;">
        <select id="wmsInvFilter" class="form-input" style="max-width:200px;">
            <option value="all"><?php echo __t('wms_filter_all_stock', 'wms'); ?></option>
            <option value="low"><?php echo __t('wms_stock_low', 'wms'); ?></option>
            <option value="out"><?php echo __t('wms_stock_out', 'wms'); ?></option>
            <option value="damaged"><?php echo __t('wms_stock_damaged_filter', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsInvRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsInvExport"><span class="material-icons-round">download</span><?php echo __t('wms_export_csv', 'wms'); ?></button>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">inventory_2</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_skus', 'wms'); ?></h3><h2 id="wmsInvSkus">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">layers</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_units', 'wms'); ?></h3><h2 id="wmsInvUnits">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_value', 'wms'); ?></h3><h2 id="wmsInvValue">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">warning_amber</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_low', 'wms'); ?></h3><h2 id="wmsInvLow">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">inventory_2</span><?php echo __t('wms_inventory_title', 'wms'); ?></h3>
    <div id="wmsInvRoot"><div class="cr-empty"><?php echo __t('wms_select_warehouse', 'wms'); ?></div></div>
</section>

<div class="wms-modal-overlay" id="wmsInvDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsInvDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory</span></div>
                <div>
                    <h3 id="wmsInvDetailTitle"><?php echo __t('wms_inventory_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsInvDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsInvDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsInvDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

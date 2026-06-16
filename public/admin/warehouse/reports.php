<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'reports';
$pageTitle = __t('wms_reports_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-report-export.js', 'wms-reports.js'];
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));
$dateToDefault = date('Y-m-d');
$pageI18n = wms_i18n([
    'loading', 'refresh', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses', 'wms_select_warehouse',
    'wms_reports_subtitle', 'wms_stat_mov_total', 'wms_stat_mov_in', 'wms_stat_mov_out', 'wms_stat_mov_value',
    'wms_search_movement', 'wms_filter_all_types', 'wms_col_movement_type', 'wms_col_product', 'wms_col_qty',
    'wms_col_balance', 'wms_col_value', 'wms_col_reference', 'wms_col_user', 'wms_breakdown_title',
    'wms_movements_title', 'wms_date_from', 'wms_date_to', 'wms_export_csv', 'wms_export_pdf', 'wms_export_print',
    'wms_mov_purchase', 'wms_mov_sale', 'wms_mov_transfer_in', 'wms_mov_transfer_out', 'wms_mov_return_in',
    'wms_mov_return_out', 'wms_mov_adjustment', 'wms_mov_damaged', 'wms_mov_expired', 'wms_mov_lost',
    'wms_mov_manual', 'wms_mov_dispatch_out', 'wms_mov_receipt_in', 'close', 'col_date', 'wms_nav_warehouses',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_reports_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsRepSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_movement', 'wms')); ?>" style="max-width:280px;">
        <select id="wmsRepWarehouse" class="form-input" style="max-width:180px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsRepType" class="form-input" style="max-width:180px;">
            <option value=""><?php echo __t('wms_filter_all_types', 'wms'); ?></option>
            <option value="receipt_in"><?php echo __t('wms_mov_receipt_in', 'wms'); ?></option>
            <option value="purchase"><?php echo __t('wms_mov_purchase', 'wms'); ?></option>
            <option value="dispatch_out"><?php echo __t('wms_mov_dispatch_out', 'wms'); ?></option>
            <option value="transfer_in"><?php echo __t('wms_mov_transfer_in', 'wms'); ?></option>
            <option value="transfer_out"><?php echo __t('wms_mov_transfer_out', 'wms'); ?></option>
            <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
            <option value="sale"><?php echo __t('wms_mov_sale', 'wms'); ?></option>
            <option value="return_in"><?php echo __t('wms_mov_return_in', 'wms'); ?></option>
            <option value="return_out"><?php echo __t('wms_mov_return_out', 'wms'); ?></option>
            <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
            <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
            <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
            <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
        </select>
        <label class="wms-rep-date">
            <span><?php echo __t('wms_date_from', 'wms'); ?></span>
            <input type="date" id="wmsRepDateFrom" class="form-input" value="<?php echo htmlspecialchars($dateFromDefault); ?>">
        </label>
        <label class="wms-rep-date">
            <span><?php echo __t('wms_date_to', 'wms'); ?></span>
            <input type="date" id="wmsRepDateTo" class="form-input" value="<?php echo htmlspecialchars($dateToDefault); ?>">
        </label>
        <button type="button" class="cr-btn" id="wmsRepRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsRepExportCsv"><span class="material-icons-round">download</span><?php echo __t('wms_export_csv', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsRepExportPdf"><span class="material-icons-round">picture_as_pdf</span><?php echo __t('wms_export_pdf', 'wms'); ?></button>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">swap_horiz</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_total', 'wms'); ?></h3><h2 id="wmsRepTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">arrow_downward</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_in', 'wms'); ?></h3><h2 id="wmsRepIn">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">arrow_upward</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_out', 'wms'); ?></h3><h2 id="wmsRepOut">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_value', 'wms'); ?></h3><h2 id="wmsRepValue">—</h2></div></div>
</div>

<section class="cr-panel wms-rep-breakdown">
    <h3><span class="material-icons-round">pie_chart</span><?php echo __t('wms_breakdown_title', 'wms'); ?></h3>
    <div id="wmsRepBreakdown"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<section class="cr-panel">
    <h3><span class="material-icons-round">summarize</span><?php echo __t('wms_movements_title', 'wms'); ?></h3>
    <div id="wmsRepRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'logs';
$pageTitle = __t('wms_logs_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-report-export.js', 'wms-logs.js'];
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));
$dateToDefault = date('Y-m-d');
$pageI18n = wms_i18n([
    'loading', 'refresh', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses', 'wms_select_warehouse',
    'wms_logs_subtitle', 'wms_stat_log_total', 'wms_stat_log_today', 'wms_stat_log_users', 'wms_stat_log_entities',
    'wms_search_log', 'wms_filter_all_actions', 'wms_filter_all_entities', 'wms_col_action', 'wms_col_entity',
    'wms_col_details', 'wms_col_ip', 'wms_log_details', 'wms_view_details', 'wms_breakdown_title',
    'wms_date_from', 'wms_date_to', 'wms_export_csv', 'wms_export_pdf', 'close', 'col_date', 'wms_nav_warehouses',
    'wms_col_user', 'wms_entity_warehouse', 'wms_entity_location', 'wms_entity_transfer', 'wms_entity_dispatch',
    'wms_entity_request', 'wms_entity_batch', 'wms_entity_audit', 'wms_entity_notification',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_logs_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsLogSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_log', 'wms')); ?>" style="max-width:280px;">
        <select id="wmsLogWarehouse" class="form-input" style="max-width:180px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsLogAction" class="form-input" style="max-width:180px;">
            <option value=""><?php echo __t('wms_filter_all_actions', 'wms'); ?></option>
        </select>
        <select id="wmsLogEntity" class="form-input" style="max-width:180px;">
            <option value=""><?php echo __t('wms_filter_all_entities', 'wms'); ?></option>
            <option value="warehouse"><?php echo __t('wms_entity_warehouse', 'wms'); ?></option>
            <option value="warehouse_location"><?php echo __t('wms_entity_location', 'wms'); ?></option>
            <option value="warehouse_transfer"><?php echo __t('wms_entity_transfer', 'wms'); ?></option>
            <option value="warehouse_dispatch"><?php echo __t('wms_entity_dispatch', 'wms'); ?></option>
            <option value="warehouse_request"><?php echo __t('wms_entity_request', 'wms'); ?></option>
            <option value="batch_tracking"><?php echo __t('wms_entity_batch', 'wms'); ?></option>
            <option value="warehouse_audit"><?php echo __t('wms_entity_audit', 'wms'); ?></option>
            <option value="notification"><?php echo __t('wms_entity_notification', 'wms'); ?></option>
        </select>
        <label class="wms-rep-date">
            <span><?php echo __t('wms_date_from', 'wms'); ?></span>
            <input type="date" id="wmsLogDateFrom" class="form-input" value="<?php echo htmlspecialchars($dateFromDefault); ?>">
        </label>
        <label class="wms-rep-date">
            <span><?php echo __t('wms_date_to', 'wms'); ?></span>
            <input type="date" id="wmsLogDateTo" class="form-input" value="<?php echo htmlspecialchars($dateToDefault); ?>">
        </label>
        <button type="button" class="cr-btn" id="wmsLogRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsLogExportCsv"><span class="material-icons-round">download</span><?php echo __t('wms_export_csv', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsLogExportPdf"><span class="material-icons-round">picture_as_pdf</span><?php echo __t('wms_export_pdf', 'wms'); ?></button>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">history</span></div><div class="card-info"><h3><?php echo __t('wms_stat_log_total', 'wms'); ?></h3><h2 id="wmsLogTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">today</span></div><div class="card-info"><h3><?php echo __t('wms_stat_log_today', 'wms'); ?></h3><h2 id="wmsLogToday">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">group</span></div><div class="card-info"><h3><?php echo __t('wms_stat_log_users', 'wms'); ?></h3><h2 id="wmsLogUsers">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">category</span></div><div class="card-info"><h3><?php echo __t('wms_stat_log_entities', 'wms'); ?></h3><h2 id="wmsLogEntities">—</h2></div></div>
</div>

<section class="cr-panel wms-rep-breakdown">
    <h3><span class="material-icons-round">pie_chart</span><?php echo __t('wms_breakdown_title', 'wms'); ?></h3>
    <div id="wmsLogBreakdown"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<section class="cr-panel">
    <h3><span class="material-icons-round">history</span><?php echo __t('wms_logs_title', 'wms'); ?></h3>
    <div id="wmsLogRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<div class="wms-modal-overlay" id="wmsLogDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsLogDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsLogDetailTitle"><?php echo __t('wms_log_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsLogDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsLogDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsLogDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

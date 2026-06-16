<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'expiry_management';
$pageTitle = __t('wms_expiry_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-expiry.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_expiry_subtitle', 'wms_stat_exp_soon', 'wms_stat_exp_past', 'wms_stat_exp_units', 'wms_stat_exp_value',
    'wms_search_expiry', 'wms_expiry_period', 'wms_period_7d', 'wms_period_14d', 'wms_period_30d',
    'wms_period_60d', 'wms_period_90d', 'wms_filter_at_risk', 'wms_filter_expiring_only', 'wms_filter_expired_only',
    'wms_col_batch', 'wms_col_product', 'wms_col_expiry', 'wms_col_qty', 'wms_col_value', 'wms_days_to_expiry',
    'wms_days_short', 'wms_expiry_details', 'wms_view_details', 'wms_batch_details', 'wms_nav_warehouses',
    'wms_unit_cost', 'wms_col_mfg', 'wms_col_barcode', 'wms_col_serial', 'wms_urgency_critical', 'wms_urgency_warning',
    'wms_urgency_expired', 'wms_mark_expired', 'wms_mark_recalled', 'wms_mark_depleted', 'wms_confirm_mark_expired',
    'wms_confirm_recall', 'wms_confirm_deplete', 'wms_export_csv', 'wms_status_active', 'wms_status_expired',
    'wms_status_recalled', 'wms_status_depleted', 'close', 'col_status', 'col_date',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_expiry_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsExpSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_expiry', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsExpWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsExpPeriod" class="form-input" style="max-width:160px;" title="<?php echo htmlspecialchars(__t('wms_expiry_period', 'wms')); ?>">
            <option value="7"><?php echo __t('wms_period_7d', 'wms'); ?></option>
            <option value="14"><?php echo __t('wms_period_14d', 'wms'); ?></option>
            <option value="30" selected><?php echo __t('wms_period_30d', 'wms'); ?></option>
            <option value="60"><?php echo __t('wms_period_60d', 'wms'); ?></option>
            <option value="90"><?php echo __t('wms_period_90d', 'wms'); ?></option>
        </select>
        <select id="wmsExpFilter" class="form-input" style="max-width:200px;">
            <option value="at_risk"><?php echo __t('wms_filter_at_risk', 'wms'); ?></option>
            <option value="expiring_soon"><?php echo __t('wms_filter_expiring_only', 'wms'); ?></option>
            <option value="expired"><?php echo __t('wms_filter_expired_only', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsExpRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <button type="button" class="cr-btn cr-btn--ghost" id="wmsExpExport"><span class="material-icons-round">download</span><?php echo __t('wms_export_csv', 'wms'); ?></button>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">schedule</span></div><div class="card-info"><h3><?php echo __t('wms_stat_exp_soon', 'wms'); ?></h3><h2 id="wmsExpSoon">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">event_busy</span></div><div class="card-info"><h3><?php echo __t('wms_stat_exp_past', 'wms'); ?></h3><h2 id="wmsExpPast">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">inventory</span></div><div class="card-info"><h3><?php echo __t('wms_stat_exp_units', 'wms'); ?></h3><h2 id="wmsExpUnits">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_exp_value', 'wms'); ?></h3><h2 id="wmsExpValue">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">event_busy</span><?php echo __t('wms_expiry_title', 'wms'); ?></h3>
    <div id="wmsExpRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<div class="wms-modal-overlay" id="wmsExpDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsExpDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">event_busy</span></div>
                <div>
                    <h3 id="wmsExpDetailTitle"><?php echo __t('wms_expiry_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsExpDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsExpDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsExpDetailBody" class="wms-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="wmsExpDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="cr-btn" id="wmsExpExpiredBtn"><?php echo __t('wms_mark_expired', 'wms'); ?></button>
                <button type="button" class="cr-btn cr-btn--warn" id="wmsExpRecallBtn"><?php echo __t('wms_mark_recalled', 'wms'); ?></button>
                <button type="button" class="cr-btn cr-btn--ghost" id="wmsExpDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

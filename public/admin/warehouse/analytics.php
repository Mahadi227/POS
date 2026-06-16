<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWmsPage = 'analytics';
$wmsPageData = 'analytics';
$pageTitle = __t('wms_analytics_title', 'wms');
$loadChart = true;
$extraScripts = ['wms-common.js', 'wms-analytics.js'];
$pageI18n = wms_i18n([
    'wms_analytics_subtitle', 'wms_period_week', 'wms_period_month', 'wms_period_year',
    'wms_chart_trends', 'wms_chart_comparison', 'wms_top_moving', 'wms_expiry_trends',
    'wms_stat_mov_total', 'wms_stat_mov_in', 'wms_stat_mov_out', 'wms_stat_mov_value',
    'wms_stat_inv_value', 'wms_stat_total_wh', 'wms_stat_exp_soon',
    'wms_mov_purchase', 'wms_mov_sale', 'wms_mov_transfer_in', 'wms_mov_transfer_out',
    'wms_mov_return_in', 'wms_mov_return_out', 'wms_mov_adjustment', 'wms_mov_damaged',
    'wms_mov_expired', 'wms_mov_lost', 'wms_mov_manual', 'wms_mov_dispatch_out', 'wms_mov_receipt_in',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_analytics_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <select id="wmsPeriod" class="form-input" style="max-width:220px;">
        <option value="week"><?php echo __t('wms_period_week', 'wms'); ?></option>
        <option value="month" selected><?php echo __t('wms_period_month', 'wms'); ?></option>
        <option value="year"><?php echo __t('wms_period_year', 'wms'); ?></option>
    </select>
    <button type="button" class="cr-btn" id="wmsAnalyticsRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">swap_vert</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_total', 'wms'); ?></h3><h2 id="wmsAnMovTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">south</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_in', 'wms'); ?></h3><h2 id="wmsAnMovIn">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">north</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_out', 'wms'); ?></h3><h2 id="wmsAnMovOut">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_mov_value', 'wms'); ?></h3><h2 id="wmsAnMovValue">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">inventory_2</span></div><div class="card-info"><h3><?php echo __t('wms_stat_inv_value', 'wms'); ?></h3><h2 id="wmsAnInvValue">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">event_busy</span></div><div class="card-info"><h3><?php echo __t('wms_stat_exp_soon', 'wms'); ?></h3><h2 id="wmsAnExpiring">—</h2></div></div>
</div>

<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">bar_chart</span><?php echo __t('wms_chart_trends', 'wms'); ?></h3><canvas id="wmsTrendChart" height="200"></canvas></section>
    <section class="cr-panel"><h3><span class="material-icons-round">compare</span><?php echo __t('wms_chart_comparison', 'wms'); ?></h3><canvas id="wmsCompareChart" height="200"></canvas></section>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">donut_large</span><?php echo __t('wms_top_moving', 'wms'); ?></h3><canvas id="wmsTopChart" height="200"></canvas></section>
    <section class="cr-panel"><h3><span class="material-icons-round">timeline</span><?php echo __t('wms_expiry_trends', 'wms'); ?></h3><canvas id="wmsExpiryChart" height="200"></canvas></section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

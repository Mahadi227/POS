<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWmsPage = 'dashboard';
$pageTitle = __t('wms_dashboard_title', 'wms');
$loadChart = true;
$extraScripts = ['wms-common.js', 'wms-dashboard.js'];
$pageI18n = wms_i18n([
    'wms_dashboard_subtitle', 'wms_stat_total_wh', 'wms_stat_active_wh', 'wms_stat_inv_value', 'wms_stat_products',
    'wms_stat_incoming', 'wms_stat_outgoing', 'wms_stat_pending_transfers', 'wms_stat_low_stock', 'wms_stat_damaged',
    'wms_stat_expired', 'wms_stat_capacity', 'wms_chart_movements', 'wms_chart_capacity', 'wms_wh_status', 'wms_recent_activity',
    'wms_quick_actions', 'wms_no_data',
    'wms_chart_incoming', 'wms_chart_outgoing', 'wms_chart_capacity_pct',
    'wms_log_warehouse_created', 'wms_log_warehouse_updated', 'wms_log_warehouse_deleted',
    'wms_log_location_created', 'wms_log_transfer_requested', 'wms_log_transfer_approved',
    'wms_log_transfer_rejected', 'wms_log_transfer_received', 'wms_log_dispatch_created',
    'wms_log_dispatch_out', 'wms_log_request_created', 'wms_log_request_approved',
    'wms_log_request_rejected', 'wms_log_batch_created', 'wms_log_batch_status_updated',
    'wms_log_audit_created', 'wms_log_audit_submitted', 'wms_log_audit_approved',
    'wms_log_audit_rejected', 'wms_log_low_stock', 'wms_log_damaged_stock',
    'wms_log_expired_product', 'wms_log_incoming_delivery', 'wms_log_purchase_received',
    'wms_log_warehouse_full',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<p class="cr-intro"><?php echo __t('wms_dashboard_subtitle', 'wms'); ?></p>
<div class="cr-kpi-grid">
<?php
$kpis = [
    ['wmsTotalWh', 'wms_stat_total_wh', 'warehouse'],
    ['wmsActiveWh', 'wms_stat_active_wh', 'check_circle'],
    ['wmsInvValue', 'wms_stat_inv_value', 'payments'],
    ['wmsProducts', 'wms_stat_products', 'inventory_2'],
    ['wmsIncoming', 'wms_stat_incoming', 'move_to_inbox'],
    ['wmsOutgoing', 'wms_stat_outgoing', 'local_shipping'],
    ['wmsPendingTransfers', 'wms_stat_pending_transfers', 'sync_alt'],
    ['wmsLowStock', 'wms_stat_low_stock', 'warning'],
    ['wmsDamaged', 'wms_stat_damaged', 'broken_image'],
    ['wmsExpired', 'wms_stat_expired', 'event_busy'],
];
foreach ($kpis as [$id, $label, $icon]): ?>
<div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round"><?php echo $icon; ?></span></div><div class="card-info"><h3><?php echo __t($label, 'wms'); ?></h3><h2 id="<?php echo $id; ?>">—</h2></div></div>
<?php endforeach; ?>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">show_chart</span><?php echo __t('wms_chart_movements', 'wms'); ?></h3><canvas id="wmsMovementChart" height="200"></canvas></section>
    <section class="cr-panel"><h3><span class="material-icons-round">pie_chart</span><?php echo __t('wms_chart_capacity', 'wms'); ?></h3><canvas id="wmsCapacityChart" height="200"></canvas></section>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">sensors</span><?php echo __t('wms_wh_status', 'wms'); ?></h3><div id="wmsStatusList"></div></section>
    <section class="cr-panel"><h3><span class="material-icons-round">history</span><?php echo __t('wms_recent_activity', 'wms'); ?></h3><div id="wmsActivityList"></div></section>
</div>
<div class="cr-toolbar wms-quick-actions">
    <a href="goods_receipts.php" class="cr-btn"><?php echo __t('wms_nav_receipts', 'wms'); ?></a>
    <a href="stock_dispatch.php" class="cr-btn"><?php echo __t('wms_nav_dispatch', 'wms'); ?></a>
    <a href="stock_transfers.php" class="cr-btn"><?php echo __t('wms_nav_transfers', 'wms'); ?></a>
    <a href="stock_requests.php" class="cr-btn cr-btn--ghost"><?php echo __t('wms_nav_requests', 'wms'); ?></a>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

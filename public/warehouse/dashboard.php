<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWhPage = 'dashboard';
$pageTitle = __t('wh_nav_dashboard', 'warehouse');
$loadChart = true;
$today = date('Y-m-d');
$weekStart = date('Y-m-d', strtotime('-6 days'));
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-dashboard.js'];
$pageI18n = array_merge(wh_i18n([
    'wh_dashboard_subtitle', 'wh_dash_period_week', 'wh_dash_period_month', 'wh_dash_period_year', 'wh_dash_period_all',
    'wh_dash_date_from', 'wh_dash_date_to',
    'wh_dash_alert_stock', 'wh_prof_notif_low_stock', 'wh_dash_section_ops', 'wh_dash_section_inventory', 'wh_dash_section_network',
    'wh_wh_status', 'wh_kpi_receiving', 'wh_kpi_dispatch', 'wh_kpi_transfers',
    'wh_kpi_pending_requests', 'wh_kpi_pending_deliveries', 'wh_kpi_pending_approvals',
    'wh_kpi_low_stock', 'wh_kpi_out_stock', 'wh_kpi_damaged', 'wh_kpi_expired',
    'wh_kpi_capacity', 'wh_kpi_inventory_value', 'wh_kpi_total_wh', 'wh_kpi_active_wh',
    'wh_kpi_products', 'wh_kpi_pending_transfers', 'wh_kpi_incoming', 'wh_kpi_outgoing', 'wh_kpi_expiring_soon',
    'wh_chart_movements', 'wh_chart_capacity', 'wh_chart_incoming', 'wh_chart_outgoing',
    'wh_recent_activity', 'wh_recent_notifications', 'wh_quick_actions', 'wh_upcoming_tasks',
    'wh_task_due_today', 'wh_view_all',
    'wh_action_receive', 'wh_action_dispatch', 'wh_action_transfer', 'wh_action_scan', 'wh_action_count',
    'wh_dash_currency_title', 'wh_dash_currency_multi', 'wh_dash_currency_store', 'wh_dash_currency_country',
    'dash_all_stores', 'loading', 'no_data', 'load_error', 'refresh', 'export_csv', 'last_updated',
]), wms_i18n([
    'wms_log_warehouse_created', 'wms_log_warehouse_updated', 'wms_log_warehouse_deleted',
    'wms_log_location_created', 'wms_log_transfer_requested', 'wms_log_transfer_approved',
    'wms_log_transfer_rejected', 'wms_log_transfer_received', 'wms_log_dispatch_created',
    'wms_log_dispatch_out', 'wms_log_request_created', 'wms_log_request_approved',
    'wms_log_request_rejected', 'wms_log_batch_created', 'wms_log_batch_status_updated',
    'wms_log_audit_created', 'wms_log_audit_submitted', 'wms_log_audit_approved',
    'wms_log_audit_rejected', 'wms_log_low_stock', 'wms_log_damaged_stock',
    'wms_log_expired_product', 'wms_log_incoming_delivery', 'wms_log_purchase_received',
    'wms_log_warehouse_full',
]));
require __DIR__ . '/includes/layout-start.php';
?>

<section class="wh-dash-hero" aria-labelledby="whDashHeroTitle">
    <div class="wh-dash-hero__intro">
        <h2 class="wh-dash-hero__title" id="whDashHeroTitle"><?php echo __t('wh_dashboard_subtitle', 'warehouse'); ?></h2>
        <p class="wh-dash-hero__period" id="whDashPeriodLabel" aria-live="polite">—</p>
        <p class="wh-dash-hero__scope" id="whHeroScope" aria-live="polite">—</p>
    </div>
    <div class="wh-dash-hero__stats" id="whDashHeroStats" role="group" aria-label="<?php echo htmlspecialchars(__t('wh_nav_dashboard', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="wh-dash-stat wh-dash-stat--primary">
            <span class="wh-dash-stat__label"><?php echo __t('wh_kpi_inventory_value', 'warehouse'); ?></span>
            <strong class="wh-dash-stat__value is-loading" id="whHeroValue">—</strong>
            <span class="wh-dash-stat__currency" id="whHeroCurrency" hidden></span>
        </div>
        <div class="wh-dash-stat wh-dash-stat--success">
            <span class="wh-dash-stat__label"><?php echo __t('wh_kpi_receiving', 'warehouse'); ?></span>
            <strong class="wh-dash-stat__value is-loading" id="whHeroReceiving">—</strong>
        </div>
        <div class="wh-dash-stat">
            <span class="wh-dash-stat__label"><?php echo __t('wh_kpi_dispatch', 'warehouse'); ?></span>
            <strong class="wh-dash-stat__value is-loading" id="whHeroDispatch">—</strong>
        </div>
        <div class="wh-dash-stat wh-dash-stat--warn">
            <span class="wh-dash-stat__label"><?php echo __t('wh_kpi_pending_approvals', 'warehouse'); ?></span>
            <strong class="wh-dash-stat__value is-loading" id="whHeroApprovals">—</strong>
        </div>
    </div>
    <div class="wh-dash-hero__actions wh-quick-actions">
        <?php if ($whCanReceive): ?>
        <a href="receiving/goods_receipts.php" class="wh-quick-action"><span class="material-icons-round">move_to_inbox</span><span><?php echo __t('wh_action_receive', 'warehouse'); ?></span></a>
        <?php endif; ?>
        <?php if ($whCanDispatch): ?>
        <a href="dispatch/dispatch_orders.php" class="wh-quick-action"><span class="material-icons-round">local_shipping</span><span><?php echo __t('wh_action_dispatch', 'warehouse'); ?></span></a>
        <?php endif; ?>
        <?php if ($whCanTransfer): ?>
        <a href="transfers/transfer_requests.php" class="wh-quick-action"><span class="material-icons-round">sync_alt</span><span><?php echo __t('wh_action_transfer', 'warehouse'); ?></span></a>
        <?php endif; ?>
        <?php if ($whCanInventory): ?>
        <a href="inventory/barcode_scanner.php" class="wh-quick-action"><span class="material-icons-round">qr_code_scanner</span><span><?php echo __t('wh_action_scan', 'warehouse'); ?></span></a>
        <a href="inventory/stock_count.php" class="wh-quick-action"><span class="material-icons-round">fact_check</span><span><?php echo __t('wh_action_count', 'warehouse'); ?></span></a>
        <?php endif; ?>
    </div>
</section>

<section class="wh-currency-panel" id="whCurrencyPanel" aria-labelledby="whCurrencyTitle" hidden>
    <header class="wh-currency-panel__head">
        <h3 id="whCurrencyTitle"><?php echo __t('wh_dash_currency_title', 'warehouse'); ?></h3>
        <span class="wh-currency-panel__hint" id="whCurrencyHint"></span>
    </header>
    <ul class="wh-currency-list" id="whCurrencyList"></ul>
</section>

<div class="wh-dash-toolbar">
    <div class="wh-dash-toolbar__top">
        <div class="wh-dash-period" id="whDashPeriod" role="tablist" aria-label="<?php echo htmlspecialchars(__t('wh_chart_movements', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="wh-dash-chip is-active" data-period="week" role="tab" aria-selected="true"><?php echo __t('wh_dash_period_week', 'warehouse'); ?></button>
            <button type="button" class="wh-dash-chip" data-period="month" role="tab"><?php echo __t('wh_dash_period_month', 'warehouse'); ?></button>
            <button type="button" class="wh-dash-chip" data-period="year" role="tab"><?php echo __t('wh_dash_period_year', 'warehouse'); ?></button>
            <button type="button" class="wh-dash-chip" data-period="all" role="tab"><?php echo __t('wh_dash_period_all', 'warehouse'); ?></button>
        </div>
        <div class="wh-dash-toolbar__dates">
            <label class="wh-dash-date">
                <span class="material-icons-round" aria-hidden="true">calendar_today</span>
                <input type="date" id="whDashDateFrom" value="<?php echo htmlspecialchars($weekStart, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('wh_dash_date_from', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-dash-date">
                <span class="material-icons-round" aria-hidden="true">calendar_today</span>
                <input type="date" id="whDashDateTo" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" max="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('wh_dash_date_to', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-dash-toolbar__actions">
            <span class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></span>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDashExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDashRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<a href="inventory/warehouse_inventory.php" class="ad-alert-strip ad-alert-strip--warn wh-dash-alert" id="whDashStockAlert" hidden>
    <span class="ad-alert-strip__icon" aria-hidden="true">
        <span class="material-icons-round">warning_amber</span>
    </span>
    <span class="ad-alert-strip__body">
        <strong class="ad-alert-strip__title"><?php echo __t('wh_prof_notif_low_stock', 'warehouse'); ?></strong>
        <span class="ad-alert-strip__msg" id="whDashStockAlertText"></span>
    </span>
    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
</a>

<section class="wh-dash-section" aria-labelledby="whDashOpsTitle">
    <h3 class="wh-dash-section__title" id="whDashOpsTitle"><?php echo __t('wh_dash_section_ops', 'warehouse'); ?></h3>
    <div class="wh-kpi-grid wh-kpi-grid--compact">
        <?php foreach ([
            ['whKpiReceiving', 'wh_kpi_receiving', 'move_to_inbox', 'success'],
            ['whKpiDispatch', 'wh_kpi_dispatch', 'local_shipping', 'primary'],
            ['whKpiTransfers', 'wh_kpi_transfers', 'sync_alt', 'info'],
            ['whKpiRequests', 'wh_kpi_pending_requests', 'assignment', 'warn'],
            ['whKpiDeliveries', 'wh_kpi_pending_deliveries', 'inventory', ''],
            ['whKpiApprovals', 'wh_kpi_pending_approvals', 'thumb_up', 'warn'],
        ] as [$id, $label, $icon, $mod]): ?>
        <article class="wh-kpi wh-kpi--<?php echo $mod ?: 'default'; ?>">
            <span class="material-icons-round wh-kpi__icon"><?php echo $icon; ?></span>
            <span class="wh-kpi__label"><?php echo __t($label, 'warehouse'); ?></span>
            <strong class="wh-kpi__value is-loading" id="<?php echo $id; ?>">—</strong>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="wh-dash-section" aria-labelledby="whDashInvTitle">
    <h3 class="wh-dash-section__title" id="whDashInvTitle"><?php echo __t('wh_dash_section_inventory', 'warehouse'); ?></h3>
    <div class="wh-kpi-grid wh-kpi-grid--compact">
        <?php foreach ([
            ['whKpiLow', 'wh_kpi_low_stock', 'warning', 'warn'],
            ['whKpiOut', 'wh_kpi_out_stock', 'remove_shopping_cart', 'danger'],
            ['whKpiDamaged', 'wh_kpi_damaged', 'broken_image', 'danger'],
            ['whKpiExpired', 'wh_kpi_expired', 'event_busy', 'danger'],
            ['whKpiExpiring', 'wh_kpi_expiring_soon', 'schedule', 'warn'],
            ['whKpiCapacity', 'wh_kpi_capacity', 'storage', ''],
        ] as [$id, $label, $icon, $mod]): ?>
        <article class="wh-kpi wh-kpi--<?php echo $mod ?: 'default'; ?>">
            <span class="material-icons-round wh-kpi__icon"><?php echo $icon; ?></span>
            <span class="wh-kpi__label"><?php echo __t($label, 'warehouse'); ?></span>
            <strong class="wh-kpi__value is-loading" id="<?php echo $id; ?>">—</strong>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="wh-dash-section" aria-labelledby="whDashNetTitle">
    <h3 class="wh-dash-section__title" id="whDashNetTitle"><?php echo __t('wh_dash_section_network', 'warehouse'); ?></h3>
    <div class="wh-kpi-grid wh-kpi-grid--compact">
        <?php foreach ([
            ['whKpiTotalWh', 'wh_kpi_total_wh', 'warehouse', ''],
            ['whKpiActiveWh', 'wh_kpi_active_wh', 'check_circle', 'success'],
            ['whKpiProducts', 'wh_kpi_products', 'inventory_2', 'primary'],
            ['whKpiPendingXfer', 'wh_kpi_pending_transfers', 'sync_alt', 'info'],
            ['whKpiIncoming', 'wh_kpi_incoming', 'move_to_inbox', ''],
            ['whKpiOutgoing', 'wh_kpi_outgoing', 'local_shipping', ''],
        ] as [$id, $label, $icon, $mod]): ?>
        <article class="wh-kpi wh-kpi--<?php echo $mod ?: 'default'; ?>">
            <span class="material-icons-round wh-kpi__icon"><?php echo $icon; ?></span>
            <span class="wh-kpi__label"><?php echo __t($label, 'warehouse'); ?></span>
            <strong class="wh-kpi__value is-loading" id="<?php echo $id; ?>">—</strong>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="wh-dash-grid">
    <section class="wh-panel wh-panel--wide">
        <header class="wh-panel__head"><h3><?php echo __t('wh_chart_movements', 'warehouse'); ?></h3></header>
        <div class="wh-panel__body">
            <div class="wh-chart-wrap wh-chart-wrap--tall">
                <canvas id="whMovementChart"></canvas>
                <p class="wh-chart-empty" id="whMovementEmpty" hidden><?php echo __t('no_data', 'warehouse'); ?></p>
            </div>
        </div>
    </section>
    <section class="wh-panel">
        <header class="wh-panel__head"><h3><?php echo __t('wh_chart_capacity', 'warehouse'); ?></h3></header>
        <div class="wh-panel__body">
            <div class="wh-chart-wrap">
                <canvas id="whCapacityChart"></canvas>
                <p class="wh-chart-empty" id="whCapacityEmpty" hidden><?php echo __t('no_data', 'warehouse'); ?></p>
            </div>
        </div>
    </section>
    <section class="wh-panel wh-panel--wide">
        <header class="wh-panel__head">
            <h3><?php echo __t('wh_wh_status', 'warehouse'); ?></h3>
            <span class="wh-panel__meta" id="whStatusMeta">—</span>
        </header>
        <div class="wh-panel__body" id="whStatusList">
            <div class="wh-loading"><?php echo __t('loading', 'warehouse'); ?></div>
        </div>
    </section>
    <section class="wh-panel">
        <header class="wh-panel__head">
            <h3><?php echo __t('wh_upcoming_tasks', 'warehouse'); ?></h3>
            <span class="wh-panel__meta" id="whTaskSummary">—</span>
        </header>
        <div class="wh-panel__body" id="whTasksList">
            <div class="wh-loading"><?php echo __t('loading', 'warehouse'); ?></div>
        </div>
    </section>
    <section class="wh-panel">
        <header class="wh-panel__head">
            <h3><?php echo __t('wh_recent_activity', 'warehouse'); ?></h3>
            <a href="management/logs.php" class="wh-link"><?php echo __t('wh_view_all', 'warehouse'); ?></a>
        </header>
        <div class="wh-panel__body" id="whActivityList">
            <div class="wh-loading"><?php echo __t('loading', 'warehouse'); ?></div>
        </div>
    </section>
    <section class="wh-panel">
        <header class="wh-panel__head">
            <h3><?php echo __t('wh_recent_notifications', 'warehouse'); ?></h3>
            <a href="notifications.php" class="wh-link"><?php echo __t('wh_view_all', 'warehouse'); ?></a>
        </header>
        <div class="wh-panel__body" id="whNotifList">
            <div class="wh-loading"><?php echo __t('loading', 'warehouse'); ?></div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

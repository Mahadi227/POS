<?php

require __DIR__ . '/includes/bootstrap.php';



$activeWmsPage = 'sync-monitor';

$pageTitle = __t('wms_sync_title', 'wms');

$loadChart = true;

$extraCss = ['admin-sync-monitor.css'];

$extraScripts = ['wms-common.js', 'wms-sync-monitor.js'];

$pageI18n = wms_i18n([

    'wms_sync_subtitle', 'wms_sync_auto_hint', 'wms_sync_summary',

    'wms_stat_sync_pending', 'wms_stat_sync_conflicts', 'wms_stat_synced_today',

    'wms_stat_wh_with_issues', 'wms_stat_total_wh',

    'wms_chart_sync_activity', 'wms_chart_synced', 'wms_chart_conflicts',

    'wms_tab_warehouses', 'wms_tab_pending', 'wms_tab_conflicts',

    'wms_sync_search_wh', 'wms_filter_all_wh', 'wms_filter_has_issues', 'wms_filter_clean',

    'wms_sync_entity_receipt', 'wms_sync_entity_transfer', 'wms_sync_entity_movement',

    'wms_col_reference', 'wms_col_entity', 'wms_col_uuid', 'wms_col_actions',

    'wms_sync_no_wh', 'wms_sync_queue_empty', 'wms_sync_no_conflicts',

    'wms_sync_retry', 'wms_sync_dismiss', 'wms_sync_retry_ok', 'wms_sync_resolve_ok', 'wms_sync_action_failed',

    'wms_sync_wh_pending', 'wms_sync_wh_conflicts', 'wms_sync_wh_offline',

    'connection_error', 'col_status',

]);



require __DIR__ . '/includes/layout-start.php';

?>



<p class="cr-intro sm-subtitle"><?php echo __t('wms_sync_subtitle', 'wms'); ?></p>

<p class="sm-meta-line" id="wmsSyncSummary"></p>



<div class="cr-kpi-grid sm-stats">

    <div class="card stat-card cr-kpi-card sm-stat is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_sync_pending', 'wms'); ?></h3><h2 id="wmsStPending">—</h2></div></div>

    <div class="card stat-card cr-kpi-card sm-stat is-loading"><div class="card-icon danger"><span class="material-icons-round">gavel</span></div><div class="card-info"><h3><?php echo __t('wms_stat_sync_conflicts', 'wms'); ?></h3><h2 id="wmsStConflicts">—</h2></div></div>

    <div class="card stat-card cr-kpi-card sm-stat is-loading"><div class="card-icon success"><span class="material-icons-round">cloud_done</span></div><div class="card-info"><h3><?php echo __t('wms_stat_synced_today', 'wms'); ?></h3><h2 id="wmsStSyncedToday">—</h2></div></div>

    <div class="card stat-card cr-kpi-card sm-stat is-loading"><div class="card-icon primary"><span class="material-icons-round">warning</span></div><div class="card-info"><h3><?php echo __t('wms_stat_wh_with_issues', 'wms'); ?></h3><h2 id="wmsStWhIssues">—</h2></div></div>

    <div class="card stat-card cr-kpi-card sm-stat is-loading"><div class="card-icon primary"><span class="material-icons-round">warehouse</span></div><div class="card-info"><h3><?php echo __t('wms_stat_total_wh', 'wms'); ?></h3><h2 id="wmsStTotalWh">—</h2></div></div>

</div>



<section class="cr-panel sm-chart-card">

    <div class="sm-chart-head">

        <h3><span class="material-icons-round">bar_chart</span><?php echo __t('wms_chart_sync_activity', 'wms'); ?></h3>

        <span class="sm-auto-hint"><?php echo __t('wms_sync_auto_hint', 'wms'); ?></span>

    </div>

    <div class="sm-chart-wrap"><canvas id="wmsSyncChart"></canvas></div>

</section>



<div class="cr-toolbar sm-toolbar">

    <div class="inv-filters sm-filters" style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">

        <div class="inv-search sm-search">

            <span class="material-icons-round">search</span>

            <input type="search" id="wmsSyncWhSearch" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_sync_search_wh', 'wms')); ?>" autocomplete="off">

        </div>

        <select id="wmsSyncWhFilter" class="form-input sm-select" style="max-width:200px;">

            <option value="all"><?php echo __t('wms_filter_all_wh', 'wms'); ?></option>

            <option value="issues"><?php echo __t('wms_filter_has_issues', 'wms'); ?></option>

            <option value="clean"><?php echo __t('wms_filter_clean', 'wms'); ?></option>

        </select>

        <select id="wmsSyncWarehouse" class="form-input" style="max-width:220px;">

            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>

        </select>

    </div>

</div>



<div class="sm-tabs" role="tablist">

    <button type="button" class="sm-tab active" data-panel="warehouses">

        <?php echo __t('wms_tab_warehouses', 'wms'); ?>

        <span class="sm-tab-badge" id="wmsBadgeWh">0</span>

    </button>

    <button type="button" class="sm-tab" data-panel="pending">

        <?php echo __t('wms_tab_pending', 'wms'); ?>

        <span class="sm-tab-badge" id="wmsBadgePending">0</span>

    </button>

    <button type="button" class="sm-tab" data-panel="conflicts">

        <?php echo __t('wms_tab_conflicts', 'wms'); ?>

        <span class="sm-tab-badge sm-tab-badge--warn" id="wmsBadgeConflicts">0</span>

    </button>

</div>



<section id="panel-warehouses" class="sm-panel">

    <div class="sm-grid" id="wmsSyncWhGrid"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>

</section>



<section id="panel-pending" class="sm-panel hidden">

    <div class="cr-panel sm-table-wrap">

        <div class="table-responsive">

            <table class="modern-table sm-sync-table">

                <thead>

                    <tr>

                        <th><?php echo __t('col_date', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_entity', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_reference', 'wms'); ?></th>

                        <th><?php echo __t('wms_nav_warehouses', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_uuid', 'wms'); ?></th>

                        <th><?php echo __t('col_status', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_actions', 'wms'); ?></th>

                    </tr>

                </thead>

                <tbody id="wmsPendingBody"><tr><td colspan="7" class="ad-empty-row"><?php echo __t('loading', 'wms'); ?></td></tr></tbody>

            </table>

        </div>

    </div>

</section>



<section id="panel-conflicts" class="sm-panel hidden">

    <div class="cr-panel sm-table-wrap">

        <div class="table-responsive">

            <table class="modern-table sm-sync-table">

                <thead>

                    <tr>

                        <th><?php echo __t('col_date', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_entity', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_reference', 'wms'); ?></th>

                        <th><?php echo __t('wms_nav_warehouses', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_uuid', 'wms'); ?></th>

                        <th><?php echo __t('col_status', 'wms'); ?></th>

                        <th><?php echo __t('wms_col_actions', 'wms'); ?></th>

                    </tr>

                </thead>

                <tbody id="wmsConflictsBody"><tr><td colspan="7" class="ad-empty-row"><?php echo __t('loading', 'wms'); ?></td></tr></tbody>

            </table>

        </div>

    </div>

</section>



<div class="inv-toast sm-toast" id="wmsSyncToast" role="status" aria-live="polite"></div>



<?php require __DIR__ . '/includes/layout-end.php'; ?>


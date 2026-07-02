<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('settings');

$useWmsModules = true;
$activeWhPage = 'sync-monitor';
$pageTitle = __t('wms_sync_title', 'wms');
$loadChart = true;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-sync-monitor.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_sync_subtitle', 'wh_sync_auto_hint', 'wh_sync_link_logs', 'wh_sync_link_warehouses',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'connection_error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_sync_title', 'wms_sync_subtitle', 'wms_sync_auto_hint', 'wms_sync_summary',
        'wms_stat_sync_pending', 'wms_stat_sync_conflicts', 'wms_stat_synced_today',
        'wms_stat_wh_with_issues', 'wms_stat_total_wh', 'wms_chart_sync_activity',
        'wms_chart_synced', 'wms_chart_conflicts', 'wms_tab_warehouses', 'wms_tab_pending',
        'wms_tab_conflicts', 'wms_sync_search_wh', 'wms_filter_all_wh', 'wms_filter_has_issues',
        'wms_filter_clean', 'wms_sync_entity_receipt', 'wms_sync_entity_transfer',
        'wms_sync_entity_movement', 'wms_col_reference', 'wms_col_entity', 'wms_col_uuid',
        'wms_col_actions', 'wms_sync_no_wh', 'wms_sync_queue_empty', 'wms_sync_no_conflicts',
        'wms_sync_retry', 'wms_sync_dismiss', 'wms_sync_retry_ok', 'wms_sync_resolve_ok',
        'wms_sync_action_failed', 'wms_sync_wh_pending', 'wms_sync_wh_conflicts',
        'wms_sync_wh_offline', 'wms_nav_warehouses', 'wms_col_user',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-sync-hero" aria-labelledby="whSyncHeroTitle">
    <div class="wh-sync-hero__intro">
        <h2 class="wh-sync-hero__title" id="whSyncHeroTitle"><?php echo __t('wh_sync_subtitle', 'warehouse'); ?></h2>
        <p class="wh-sync-hero__meta" id="whSyncHeroMeta" aria-live="polite">—</p>
        <div class="wh-sync-hero__links">
            <a class="wh-sync-hero__link" href="logs.php"><?php echo __t('wh_sync_link_logs', 'warehouse'); ?></a>
            <a class="wh-sync-hero__link" href="warehouses.php"><?php echo __t('wh_sync_link_warehouses', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-sync-hero__stats" role="group">
        <article class="wh-sync-stat wh-sync-stat--warn">
            <span class="wh-sync-stat__label"><?php echo __t('wms_stat_sync_pending', 'wms'); ?></span>
            <strong class="wh-sync-stat__value is-loading" id="whSyncStatPending">—</strong>
        </article>
        <article class="wh-sync-stat wh-sync-stat--danger">
            <span class="wh-sync-stat__label"><?php echo __t('wms_stat_sync_conflicts', 'wms'); ?></span>
            <strong class="wh-sync-stat__value is-loading" id="whSyncStatConflicts">—</strong>
        </article>
        <article class="wh-sync-stat wh-sync-stat--success">
            <span class="wh-sync-stat__label"><?php echo __t('wms_stat_synced_today', 'wms'); ?></span>
            <strong class="wh-sync-stat__value is-loading" id="whSyncStatSyncedToday">—</strong>
        </article>
        <article class="wh-sync-stat">
            <span class="wh-sync-stat__label"><?php echo __t('wms_stat_wh_with_issues', 'wms'); ?></span>
            <strong class="wh-sync-stat__value is-loading" id="whSyncStatWhIssues">—</strong>
        </article>
        <article class="wh-sync-stat wh-sync-stat--primary">
            <span class="wh-sync-stat__label"><?php echo __t('wms_stat_total_wh', 'wms'); ?></span>
            <strong class="wh-sync-stat__value is-loading" id="whSyncStatTotalWh">—</strong>
        </article>
    </div>
</section>

<section class="wh-sync-chart-panel" aria-labelledby="whSyncChartTitle">
    <header class="wh-sync-chart-panel__head">
        <h3 id="whSyncChartTitle">
            <span class="material-icons-round" aria-hidden="true">bar_chart</span>
            <?php echo __t('wms_chart_sync_activity', 'wms'); ?>
        </h3>
        <span class="wh-sync-chart-panel__hint"><?php echo __t('wh_sync_auto_hint', 'warehouse'); ?></span>
    </header>
    <div class="wh-sync-chart-wrap"><canvas id="whSyncChart"></canvas></div>
</section>

<div class="wh-sync-toolbar">
    <div class="wh-sync-toolbar__row">
        <div class="wh-sync-toolbar__filters">
            <label class="wh-sync-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whSyncWhSearch" class="wh-sync-search" placeholder="<?php echo htmlspecialchars(__t('wms_sync_search_wh', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whSyncWhFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_filter_all_wh', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_wh', 'wms'); ?></option>
                <option value="issues"><?php echo __t('wms_filter_has_issues', 'wms'); ?></option>
                <option value="clean"><?php echo __t('wms_filter_clean', 'wms'); ?></option>
            </select>
            <select id="whSyncWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
        </div>
        <div class="wh-sync-toolbar__actions">
            <button type="button" class="wh-btn" id="whSyncRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="wh-sync-tabs" role="tablist" aria-label="<?php echo htmlspecialchars(__t('wms_sync_title', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="wh-sync-tab is-active" role="tab" data-panel="warehouses" aria-selected="true">
        <?php echo __t('wms_tab_warehouses', 'wms'); ?>
        <span class="wh-sync-tab__badge" id="whSyncBadgeWh">0</span>
    </button>
    <button type="button" class="wh-sync-tab" role="tab" data-panel="pending" aria-selected="false">
        <?php echo __t('wms_tab_pending', 'wms'); ?>
        <span class="wh-sync-tab__badge" id="whSyncBadgePending">0</span>
    </button>
    <button type="button" class="wh-sync-tab" role="tab" data-panel="conflicts" aria-selected="false">
        <?php echo __t('wms_tab_conflicts', 'wms'); ?>
        <span class="wh-sync-tab__badge wh-sync-tab__badge--warn" id="whSyncBadgeConflicts">0</span>
    </button>
</div>

<section id="whSyncPanelWarehouses" class="wh-sync-panel" role="tabpanel">
    <div class="wh-sync-wh-grid" id="whSyncWhGrid">
        <div class="wh-loading"><?php echo __t('loading', 'warehouse'); ?></div>
    </div>
</section>

<section id="whSyncPanelPending" class="wh-sync-panel" role="tabpanel" hidden>
    <div class="wh-sync-table-wrap">
        <table class="modern-table wh-table wh-sync-table">
            <thead>
                <tr>
                    <th><?php echo __t('col_date', 'warehouse'); ?></th>
                    <th><?php echo __t('wms_col_entity', 'wms'); ?></th>
                    <th><?php echo __t('wms_col_reference', 'wms'); ?></th>
                    <th><?php echo __t('wms_nav_warehouses', 'wms'); ?></th>
                    <th><?php echo __t('wms_col_uuid', 'wms'); ?></th>
                    <th><?php echo __t('col_status', 'warehouse'); ?></th>
                    <th><?php echo __t('wms_col_actions', 'wms'); ?></th>
                </tr>
            </thead>
            <tbody id="whSyncPendingBody">
                <tr><td colspan="7" class="wh-sync-empty-row"><?php echo __t('loading', 'warehouse'); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<section id="whSyncPanelConflicts" class="wh-sync-panel" role="tabpanel" hidden>
    <div class="wh-sync-table-wrap">
        <table class="modern-table wh-table wh-sync-table">
            <thead>
                <tr>
                    <th><?php echo __t('col_date', 'warehouse'); ?></th>
                    <th><?php echo __t('wms_col_entity', 'wms'); ?></th>
                    <th><?php echo __t('wms_col_reference', 'wms'); ?></th>
                    <th><?php echo __t('wms_nav_warehouses', 'wms'); ?></th>
                    <th><?php echo __t('wms_col_uuid', 'wms'); ?></th>
                    <th><?php echo __t('col_status', 'warehouse'); ?></th>
                    <th><?php echo __t('wms_col_actions', 'wms'); ?></th>
                </tr>
            </thead>
            <tbody id="whSyncConflictsBody">
                <tr><td colspan="7" class="wh-sync-empty-row"><?php echo __t('loading', 'warehouse'); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<div class="wh-sync-toast" id="whSyncToast" role="status" aria-live="polite"></div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

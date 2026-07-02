<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('settings');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'logs';
$pageTitle = __t('wms_logs_title', 'wms');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-logs.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_log_subtitle', 'wh_log_stat_total', 'wh_log_stat_today', 'wh_log_stat_users',
        'wh_log_stat_entities', 'wh_log_search', 'wh_log_breakdown', 'wh_log_empty',
        'wh_log_link_warehouses', 'wh_log_link_sync', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'export_pdf',
        'prev_page', 'next_page', 'records', 'close', 'col_date',
    ]),
    wms_i18n([
        'wms_logs_title', 'wms_logs_subtitle', 'wms_stat_log_total', 'wms_stat_log_today',
        'wms_stat_log_users', 'wms_stat_log_entities', 'wms_search_log', 'wms_filter_all_actions',
        'wms_filter_all_entities', 'wms_col_action', 'wms_col_entity', 'wms_col_details',
        'wms_col_ip', 'wms_log_details', 'wms_view_details', 'wms_breakdown_title',
        'wms_date_from', 'wms_date_to', 'wms_export_csv', 'wms_export_pdf', 'wms_nav_warehouses',
        'wms_col_user', 'wms_entity_warehouse', 'wms_entity_location', 'wms_entity_transfer',
        'wms_entity_dispatch', 'wms_entity_request', 'wms_entity_batch', 'wms_entity_audit',
        'wms_entity_notification', 'wms_entity_movement', 'wms_log_warehouse_created',
        'wms_log_warehouse_updated', 'wms_log_warehouse_deleted', 'wms_log_location_created',
        'wms_log_stock_adjusted', 'wms_log_transfer_requested', 'wms_log_transfer_approved',
        'wms_log_transfer_rejected', 'wms_log_transfer_received', 'wms_log_dispatch_created',
        'wms_log_dispatch_out', 'wms_log_request_created', 'wms_log_request_approved',
        'wms_log_request_rejected', 'wms_log_batch_created', 'wms_log_batch_status_updated',
        'wms_log_audit_created', 'wms_log_audit_submitted', 'wms_log_audit_approved',
        'wms_log_audit_rejected', 'wms_log_low_stock', 'wms_log_damaged_stock',
        'wms_log_expired_product', 'wms_log_incoming_delivery', 'wms_log_purchase_received',
        'wms_log_warehouse_full',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-log-hero" aria-labelledby="whLogHeroTitle">
    <div class="wh-log-hero__intro">
        <h2 class="wh-log-hero__title" id="whLogHeroTitle"><?php echo __t('wh_log_subtitle', 'warehouse'); ?></h2>
        <p class="wh-log-hero__meta" id="whLogHeroMeta" aria-live="polite">—</p>
        <div class="wh-log-hero__links">
            <a class="wh-log-hero__link" href="warehouses.php"><?php echo __t('wh_log_link_warehouses', 'warehouse'); ?></a>
            <a class="wh-log-hero__link" href="sync-monitor.php"><?php echo __t('wh_log_link_sync', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-log-hero__stats" role="group">
        <article class="wh-log-stat wh-log-stat--primary">
            <span class="wh-log-stat__label"><?php echo __t('wh_log_stat_total', 'warehouse'); ?></span>
            <strong class="wh-log-stat__value is-loading" id="whLogStatTotal">—</strong>
        </article>
        <article class="wh-log-stat wh-log-stat--success">
            <span class="wh-log-stat__label"><?php echo __t('wh_log_stat_today', 'warehouse'); ?></span>
            <strong class="wh-log-stat__value is-loading" id="whLogStatToday">—</strong>
        </article>
        <article class="wh-log-stat">
            <span class="wh-log-stat__label"><?php echo __t('wh_log_stat_users', 'warehouse'); ?></span>
            <strong class="wh-log-stat__value is-loading" id="whLogStatUsers">—</strong>
        </article>
        <article class="wh-log-stat wh-log-stat--muted">
            <span class="wh-log-stat__label"><?php echo __t('wh_log_stat_entities', 'warehouse'); ?></span>
            <strong class="wh-log-stat__value is-loading" id="whLogStatEntities">—</strong>
        </article>
    </div>
</section>

<section class="wh-log-breakdown" id="whLogBreakdownPanel" hidden aria-labelledby="whLogBreakdownTitle">
    <div class="wh-log-breakdown__head">
        <h3 id="whLogBreakdownTitle"><?php echo __t('wh_log_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-log-action-chips" id="whLogBreakdownChips"></div>
</section>

<div class="wh-log-toolbar">
    <div class="wh-log-toolbar__row">
        <div class="wh-log-toolbar__filters">
            <label class="wh-log-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whLogSearch" class="wh-log-search" placeholder="<?php echo htmlspecialchars(__t('wh_log_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whLogWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whLogAction" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_filter_all_actions', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_filter_all_actions', 'wms'); ?></option>
            </select>
            <select id="whLogEntity" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_filter_all_entities', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_filter_all_entities', 'wms'); ?></option>
                <option value="warehouse"><?php echo __t('wms_entity_warehouse', 'wms'); ?></option>
                <option value="warehouse_location"><?php echo __t('wms_entity_location', 'wms'); ?></option>
                <option value="warehouse_transfer"><?php echo __t('wms_entity_transfer', 'wms'); ?></option>
                <option value="warehouse_dispatch"><?php echo __t('wms_entity_dispatch', 'wms'); ?></option>
                <option value="warehouse_request"><?php echo __t('wms_entity_request', 'wms'); ?></option>
                <option value="warehouse_movement"><?php echo __t('wms_entity_movement', 'wms'); ?></option>
                <option value="batch_tracking"><?php echo __t('wms_entity_batch', 'wms'); ?></option>
                <option value="warehouse_audit"><?php echo __t('wms_entity_audit', 'wms'); ?></option>
                <option value="notification"><?php echo __t('wms_entity_notification', 'wms'); ?></option>
            </select>
            <label class="wh-log-date-wrap">
                <span><?php echo __t('wms_date_from', 'wms'); ?></span>
                <input type="date" id="whLogDateFrom" class="wh-input wh-log-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-log-date-wrap">
                <span><?php echo __t('wms_date_to', 'wms'); ?></span>
                <input type="date" id="whLogDateTo" class="wh-input wh-log-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-log-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whLogExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whLogExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whLogRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-log-panel" aria-live="polite">
    <div class="wh-log-table-wrap" id="whLogTableWrap"></div>
    <div class="wh-log-empty" id="whLogEmpty" hidden>
        <span class="material-icons-round">history</span>
        <p><?php echo __t('wh_log_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whLogLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-log-pagination" id="whLogPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLogPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-log-pagination__meta" id="whLogPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLogNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wms-modal-overlay" id="whLogDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whLogDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whLogDetailTitle"><?php echo __t('wms_log_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whLogDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whLogDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whLogDetailBody" class="wms-detail-body wh-log-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

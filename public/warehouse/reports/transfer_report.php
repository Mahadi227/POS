<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'transfer_report';
$pageTitle = __t('wh_nav_rpt_transfer', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-transfer-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trpt_subtitle', 'wh_trpt_hint', 'wh_trpt_stat_total', 'wh_trpt_stat_requested', 'wh_trpt_stat_progress',
        'wh_trpt_stat_completed', 'wh_trpt_stat_rejected', 'wh_trpt_stat_value', 'wh_trpt_stat_items',
        'wh_trpt_search', 'wh_trpt_empty', 'wh_trpt_status_breakdown', 'wh_trpt_chart_trend', 'wh_trpt_chart_status',
        'wh_trpt_export_excel', 'wh_trpt_export_pdf', 'wh_trpt_print', 'wh_trpt_offline_cached',
        'wh_trpt_link_transfers', 'wh_trpt_link_history', 'wh_trpt_col_requested_by',
        'wh_trpt_filter_incoming', 'wh_trpt_filter_outgoing',
        'wh_trf_filter_active', 'wh_trf_filter_pending',
        'wh_ledger_date_from', 'wh_ledger_date_to', 'wh_ledger_filter_all',
        'wh_nav_rpt_transfer', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_filter_all_status', 'wms_view_details', 'wms_col_product', 'wms_col_qty',
        'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh', 'wms_type_branch',
        'wms_status_requested', 'wms_status_approved', 'wms_status_picking', 'wms_status_in_transit',
        'wms_status_received', 'wms_status_completed', 'wms_status_rejected', 'wms_status_cancelled',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whTrptOfflineBadge" class="wh-trpt-offline-badge" hidden><?php echo __t('wh_trpt_offline_cached', 'warehouse'); ?></div>

<section class="wh-trpt-hero" aria-labelledby="whTrptHeroTitle">
    <div class="wh-trpt-hero__intro">
        <h2 class="wh-trpt-hero__title" id="whTrptHeroTitle"><?php echo __t('wh_trpt_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trpt-hero__meta" id="whTrptHeroMeta" aria-live="polite">—</p>
        <p class="wh-trpt-hero__hint"><?php echo __t('wh_trpt_hint', 'warehouse'); ?></p>
        <div class="wh-trpt-hero__links">
            <a class="wh-trpt-hero__link" href="../transfers/transfer_requests.php"><?php echo __t('wh_trpt_link_transfers', 'warehouse'); ?></a>
            <a class="wh-trpt-hero__link" href="../transfers/transfer_history.php"><?php echo __t('wh_trpt_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trpt-hero__stats" role="group">
        <article class="wh-trpt-stat wh-trpt-stat--primary">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_total', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatTotal">—</strong>
        </article>
        <article class="wh-trpt-stat wh-trpt-stat--warn">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_requested', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatRequested">—</strong>
        </article>
        <article class="wh-trpt-stat">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_progress', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatProgress">—</strong>
        </article>
        <article class="wh-trpt-stat wh-trpt-stat--success">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatCompleted">—</strong>
        </article>
        <article class="wh-trpt-stat wh-trpt-stat--danger">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_rejected', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatRejected">—</strong>
        </article>
        <article class="wh-trpt-stat">
            <span class="wh-trpt-stat__label"><?php echo __t('wh_trpt_stat_value', 'warehouse'); ?></span>
            <strong class="wh-trpt-stat__value is-loading" id="whTrptStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-trpt-charts" aria-label="Charts">
    <div class="wh-trpt-charts__grid">
        <article class="wh-trpt-chart-card">
            <h4><?php echo __t('wh_trpt_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-trpt-chart-wrap"><canvas id="whTrptChartTrend"></canvas></div>
        </article>
        <article class="wh-trpt-chart-card">
            <h4><?php echo __t('wh_trpt_chart_status', 'warehouse'); ?></h4>
            <div class="wh-trpt-chart-wrap"><canvas id="whTrptChartStatus"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-trpt-breakdown" id="whTrptBreakdownPanel" hidden aria-labelledby="whTrptBreakdownTitle">
    <div class="wh-trpt-breakdown__head">
        <h3 id="whTrptBreakdownTitle"><?php echo __t('wh_trpt_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trpt-status-chips" id="whTrptStatusChips"></div>
</section>

<div class="wh-trpt-toolbar">
    <div class="wh-trpt-toolbar__row">
        <div class="wh-trpt-toolbar__filters">
            <select id="whTrptWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-trpt-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTrptSearch" class="wh-trpt-search" placeholder="<?php echo htmlspecialchars(__t('wh_trpt_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTrptDirection" class="wh-select" aria-label="Direction">
                <option value=""><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="incoming"><?php echo __t('wh_trpt_filter_incoming', 'warehouse'); ?></option>
                <option value="outgoing"><?php echo __t('wh_trpt_filter_outgoing', 'warehouse'); ?></option>
            </select>
            <select id="whTrptType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_col_type', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="warehouse_to_warehouse"><?php echo __t('wms_type_wh_wh', 'wms'); ?></option>
                <option value="warehouse_to_store"><?php echo __t('wms_type_wh_store', 'wms'); ?></option>
                <option value="store_to_warehouse"><?php echo __t('wms_type_store_wh', 'wms'); ?></option>
                <option value="branch_to_branch"><?php echo __t('wms_type_branch', 'wms'); ?></option>
            </select>
            <select id="whTrptStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="trf_active"><?php echo __t('wh_trf_filter_active', 'warehouse'); ?></option>
                <option value="trf_pending"><?php echo __t('wh_trf_filter_pending', 'warehouse'); ?></option>
                <option value="requested"><?php echo __t('wms_status_requested', 'wms'); ?></option>
                <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="received"><?php echo __t('wms_status_received', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
            <label class="wh-trpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whTrptDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-trpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whTrptDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-trpt-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrptExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrptExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_trpt_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrptExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_trpt_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrptPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_trpt_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTrptRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trpt-panel" aria-live="polite">
    <div class="wh-trpt-table-wrap" id="whTrptTableWrap"></div>
    <div class="wh-trpt-empty" id="whTrptEmpty" hidden>
        <span class="material-icons-round">swap_horiz</span>
        <p><?php echo __t('wh_trpt_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTrptLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trpt-pagination" id="whTrptPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrptPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trpt-pagination__meta" id="whTrptPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrptNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wh-modal" id="whTrptDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whTrptDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-trpt-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whTrptDetailTitle"><?php echo __t('wms_view_details', 'wms'); ?></h3>
                <p class="wh-modal__sub" id="whTrptDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whTrptDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whTrptDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'dispatch_report';
$pageTitle = __t('wh_nav_rpt_dispatch', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-dispatch-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_dsrpt_subtitle', 'wh_dsrpt_hint', 'wh_dsrpt_stat_total', 'wh_dsrpt_stat_draft', 'wh_dsrpt_stat_in_transit',
        'wh_dsrpt_stat_delivered', 'wh_dsrpt_stat_cancelled', 'wh_dsrpt_stat_value', 'wh_dsrpt_stat_items',
        'wh_dsrpt_search', 'wh_dsrpt_empty', 'wh_dsrpt_status_breakdown', 'wh_dsrpt_chart_trend', 'wh_dsrpt_chart_status',
        'wh_dsrpt_export_excel', 'wh_dsrpt_export_pdf', 'wh_dsrpt_print', 'wh_dsrpt_offline_cached',
        'wh_dsrpt_link_orders', 'wh_dsrpt_link_history', 'wh_dsrpt_col_created_by',
        'wh_dsp_filter_open', 'wh_dsp_filter_in_flight', 'wh_ledger_date_from', 'wh_ledger_date_to', 'wh_ledger_filter_all',
        'wh_nav_rpt_dispatch', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_dispatch', 'wms_col_destination', 'wms_col_items', 'wms_col_value', 'wms_col_driver',
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_qty',
        'wms_status_draft', 'wms_status_picking', 'wms_status_packed', 'wms_status_dispatched',
        'wms_status_in_transit', 'wms_status_delivered', 'wms_status_cancelled', 'wms_view_details',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whDsrptOfflineBadge" class="wh-dsrpt-offline-badge" hidden><?php echo __t('wh_dsrpt_offline_cached', 'warehouse'); ?></div>

<section class="wh-dsrpt-hero" aria-labelledby="whDsrptHeroTitle">
    <div class="wh-dsrpt-hero__intro">
        <h2 class="wh-dsrpt-hero__title" id="whDsrptHeroTitle"><?php echo __t('wh_dsrpt_subtitle', 'warehouse'); ?></h2>
        <p class="wh-dsrpt-hero__meta" id="whDsrptHeroMeta" aria-live="polite">—</p>
        <p class="wh-dsrpt-hero__hint"><?php echo __t('wh_dsrpt_hint', 'warehouse'); ?></p>
        <div class="wh-dsrpt-hero__links">
            <a class="wh-dsrpt-hero__link" href="../dispatch/dispatch_orders.php"><?php echo __t('wh_dsrpt_link_orders', 'warehouse'); ?></a>
            <a class="wh-dsrpt-hero__link" href="../dispatch/dispatch_history.php"><?php echo __t('wh_dsrpt_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-dsrpt-hero__stats" role="group">
        <article class="wh-dsrpt-stat wh-dsrpt-stat--primary">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_total', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatTotal">—</strong>
        </article>
        <article class="wh-dsrpt-stat wh-dsrpt-stat--warn">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_draft', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatDraft">—</strong>
        </article>
        <article class="wh-dsrpt-stat">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_in_transit', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatTransit">—</strong>
        </article>
        <article class="wh-dsrpt-stat wh-dsrpt-stat--success">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_delivered', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatDelivered">—</strong>
        </article>
        <article class="wh-dsrpt-stat wh-dsrpt-stat--danger">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_cancelled', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatCancelled">—</strong>
        </article>
        <article class="wh-dsrpt-stat">
            <span class="wh-dsrpt-stat__label"><?php echo __t('wh_dsrpt_stat_value', 'warehouse'); ?></span>
            <strong class="wh-dsrpt-stat__value is-loading" id="whDsrptStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-dsrpt-charts" aria-label="Charts">
    <div class="wh-dsrpt-charts__grid">
        <article class="wh-dsrpt-chart-card">
            <h4><?php echo __t('wh_dsrpt_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-dsrpt-chart-wrap"><canvas id="whDsrptChartTrend"></canvas></div>
        </article>
        <article class="wh-dsrpt-chart-card">
            <h4><?php echo __t('wh_dsrpt_chart_status', 'warehouse'); ?></h4>
            <div class="wh-dsrpt-chart-wrap"><canvas id="whDsrptChartStatus"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-dsrpt-breakdown" id="whDsrptBreakdownPanel" hidden aria-labelledby="whDsrptBreakdownTitle">
    <div class="wh-dsrpt-breakdown__head">
        <h3 id="whDsrptBreakdownTitle"><?php echo __t('wh_dsrpt_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-dsrpt-status-chips" id="whDsrptStatusChips"></div>
</section>

<div class="wh-dsrpt-toolbar">
    <div class="wh-dsrpt-toolbar__row">
        <div class="wh-dsrpt-toolbar__filters">
            <select id="whDsrptWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-dsrpt-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whDsrptSearch" class="wh-dsrpt-search" placeholder="<?php echo htmlspecialchars(__t('wh_dsrpt_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whDsrptStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="open"><?php echo __t('wh_dsp_filter_open', 'warehouse'); ?></option>
                <option value="in_flight"><?php echo __t('wh_dsp_filter_in_flight', 'warehouse'); ?></option>
                <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="packed"><?php echo __t('wms_status_packed', 'wms'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
            <label class="wh-dsrpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whDsrptDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-dsrpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whDsrptDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-dsrpt-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_dsrpt_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_dsrpt_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_dsrpt_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDsrptRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-dsrpt-panel" aria-live="polite">
    <div class="wh-dsrpt-table-wrap" id="whDsrptTableWrap"></div>
    <div class="wh-dsrpt-empty" id="whDsrptEmpty" hidden>
        <span class="material-icons-round">local_shipping</span>
        <p><?php echo __t('wh_dsrpt_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whDsrptLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-dsrpt-pagination" id="whDsrptPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-dsrpt-pagination__meta" id="whDsrptPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDsrptNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wh-modal" id="whDsrptDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whDsrptDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-dsrpt-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whDsrptDetailTitle"><?php echo __t('wms_view_details', 'wms'); ?></h3>
                <p class="wh-modal__sub" id="whDsrptDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whDsrptDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whDsrptDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'receiving_report';
$pageTitle = __t('wh_nav_rpt_receiving', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-receiving-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_rcpt_subtitle', 'wh_rcpt_hint', 'wh_rcpt_stat_total', 'wh_rcpt_stat_pipeline', 'wh_rcpt_stat_completed',
        'wh_rcpt_stat_rejected', 'wh_rcpt_stat_value', 'wh_rcpt_stat_items', 'wh_rcpt_search', 'wh_rcpt_empty',
        'wh_rcpt_status_breakdown', 'wh_rcpt_chart_trend', 'wh_rcpt_chart_status', 'wh_rcpt_export_excel',
        'wh_rcpt_export_pdf', 'wh_rcpt_print', 'wh_rcpt_offline_cached', 'wh_rcpt_link_grn', 'wh_rcpt_link_history',
        'wh_rcpt_col_received_by', 'wh_ledger_date_from', 'wh_ledger_date_to', 'wh_ledger_filter_all',
        'wh_nav_rpt_receiving',
        'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_grn', 'wms_col_supplier', 'wms_col_items', 'wms_col_value', 'wms_nav_warehouses',
        'wms_col_product', 'wms_col_qty',
        'wms_status_pending', 'wms_status_inspecting', 'wms_status_accepted', 'wms_status_completed', 'wms_status_rejected',
        'wms_view_details', 'wms_date_from', 'wms_date_to',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whRcptOfflineBadge" class="wh-rcpt-offline-badge" hidden><?php echo __t('wh_rcpt_offline_cached', 'warehouse'); ?></div>

<section class="wh-rcpt-hero" aria-labelledby="whRcptHeroTitle">
    <div class="wh-rcpt-hero__intro">
        <h2 class="wh-rcpt-hero__title" id="whRcptHeroTitle"><?php echo __t('wh_rcpt_subtitle', 'warehouse'); ?></h2>
        <p class="wh-rcpt-hero__meta" id="whRcptHeroMeta" aria-live="polite">—</p>
        <p class="wh-rcpt-hero__hint"><?php echo __t('wh_rcpt_hint', 'warehouse'); ?></p>
        <div class="wh-rcpt-hero__links">
            <a class="wh-rcpt-hero__link" href="../receiving/goods_receipts.php"><?php echo __t('wh_rcpt_link_grn', 'warehouse'); ?></a>
            <a class="wh-rcpt-hero__link" href="../receiving/receiving_history.php"><?php echo __t('wh_rcpt_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-rcpt-hero__stats" role="group">
        <article class="wh-rcpt-stat wh-rcpt-stat--primary">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_total', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatTotal">—</strong>
        </article>
        <article class="wh-rcpt-stat wh-rcpt-stat--warn">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_pipeline', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatPipeline">—</strong>
        </article>
        <article class="wh-rcpt-stat wh-rcpt-stat--success">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatCompleted">—</strong>
        </article>
        <article class="wh-rcpt-stat wh-rcpt-stat--danger">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_rejected', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatRejected">—</strong>
        </article>
        <article class="wh-rcpt-stat">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_value', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatValue">—</strong>
        </article>
        <article class="wh-rcpt-stat">
            <span class="wh-rcpt-stat__label"><?php echo __t('wh_rcpt_stat_items', 'warehouse'); ?></span>
            <strong class="wh-rcpt-stat__value is-loading" id="whRcptStatItems">—</strong>
        </article>
    </div>
</section>

<section class="wh-rcpt-charts" aria-label="Charts">
    <div class="wh-rcpt-charts__grid">
        <article class="wh-rcpt-chart-card">
            <h4><?php echo __t('wh_rcpt_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-rcpt-chart-wrap"><canvas id="whRcptChartTrend"></canvas></div>
        </article>
        <article class="wh-rcpt-chart-card">
            <h4><?php echo __t('wh_rcpt_chart_status', 'warehouse'); ?></h4>
            <div class="wh-rcpt-chart-wrap"><canvas id="whRcptChartStatus"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-rcpt-breakdown" id="whRcptBreakdownPanel" hidden aria-labelledby="whRcptBreakdownTitle">
    <div class="wh-rcpt-breakdown__head">
        <h3 id="whRcptBreakdownTitle"><?php echo __t('wh_rcpt_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-rcpt-status-chips" id="whRcptStatusChips"></div>
</section>

<div class="wh-rcpt-toolbar">
    <div class="wh-rcpt-toolbar__row">
        <div class="wh-rcpt-toolbar__filters">
            <select id="whRcptWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-rcpt-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whRcptSearch" class="wh-rcpt-search" placeholder="<?php echo htmlspecialchars(__t('wh_rcpt_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whRcptStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
                <option value="inspecting"><?php echo __t('wms_status_inspecting', 'wms'); ?></option>
                <option value="accepted"><?php echo __t('wms_status_accepted', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            </select>
            <label class="wh-rcpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whRcptDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-rcpt-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whRcptDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-rcpt-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whRcptExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whRcptExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_rcpt_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whRcptExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_rcpt_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whRcptPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_rcpt_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whRcptRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-rcpt-panel" aria-live="polite">
    <div class="wh-rcpt-table-wrap" id="whRcptTableWrap"></div>
    <div class="wh-rcpt-empty" id="whRcptEmpty" hidden>
        <span class="material-icons-round">move_to_inbox</span>
        <p><?php echo __t('wh_rcpt_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whRcptLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-rcpt-pagination" id="whRcptPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whRcptPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-rcpt-pagination__meta" id="whRcptPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whRcptNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wh-modal" id="whRcptDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whRcptDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-rcpt-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whRcptDetailTitle"><?php echo __t('wms_view_details', 'wms'); ?></h3>
                <p class="wh-modal__sub" id="whRcptDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whRcptDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whRcptDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

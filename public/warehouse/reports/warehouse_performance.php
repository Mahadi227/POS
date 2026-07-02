<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'warehouse_performance';
$pageTitle = __t('wh_nav_rpt_performance', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-performance-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_wperf_subtitle', 'wh_wperf_hint', 'wh_wperf_stat_movements', 'wh_wperf_stat_stock_in', 'wh_wperf_stat_stock_out',
        'wh_wperf_stat_mov_value', 'wh_wperf_stat_inv_value', 'wh_wperf_stat_utilization', 'wh_wperf_stat_expiring',
        'wh_wperf_stat_turnover', 'wh_wperf_stat_receiving', 'wh_wperf_stat_dispatch',
        'wh_wperf_search', 'wh_wperf_empty', 'wh_wperf_chart_throughput', 'wh_wperf_chart_comparison',
        'wh_wperf_chart_top_moving', 'wh_wperf_chart_stock_status', 'wh_wperf_chart_aging',
        'wh_wperf_export_excel', 'wh_wperf_export_pdf', 'wh_wperf_print', 'wh_wperf_offline_cached',
        'wh_wperf_link_dashboard', 'wh_wperf_link_inventory', 'wh_wperf_col_code', 'wh_wperf_col_products',
        'wh_wperf_col_capacity', 'wh_wperf_col_turnover', 'wh_wperf_period_week', 'wh_wperf_period_month',
        'wh_wperf_period_year', 'wh_wperf_period_custom', 'wh_wperf_score_cards', 'wh_wperf_table_title',
        'wh_wperf_stock_in_stock', 'wh_wperf_stock_low', 'wh_wperf_stock_out',
        'wh_ledger_date_from', 'wh_ledger_date_to', 'wh_ledger_filter_all',
        'wh_nav_rpt_performance', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
    ]),
    wms_i18n([
        'wms_nav_warehouses', 'wms_col_value', 'wms_col_items',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whWperfOfflineBadge" class="wh-wperf-offline-badge" hidden><?php echo __t('wh_wperf_offline_cached', 'warehouse'); ?></div>

<section class="wh-wperf-hero" aria-labelledby="whWperfHeroTitle">
    <div class="wh-wperf-hero__intro">
        <h2 class="wh-wperf-hero__title" id="whWperfHeroTitle"><?php echo __t('wh_wperf_subtitle', 'warehouse'); ?></h2>
        <p class="wh-wperf-hero__meta" id="whWperfHeroMeta" aria-live="polite">—</p>
        <p class="wh-wperf-hero__hint"><?php echo __t('wh_wperf_hint', 'warehouse'); ?></p>
        <div class="wh-wperf-hero__links">
            <a class="wh-wperf-hero__link" href="../dashboard.php"><?php echo __t('wh_wperf_link_dashboard', 'warehouse'); ?></a>
            <a class="wh-wperf-hero__link" href="inventory_report.php"><?php echo __t('wh_wperf_link_inventory', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-wperf-hero__stats" role="group">
        <article class="wh-wperf-stat wh-wperf-stat--primary">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_movements', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatMovements">—</strong>
        </article>
        <article class="wh-wperf-stat wh-wperf-stat--success">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_stock_in', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatIn">—</strong>
        </article>
        <article class="wh-wperf-stat wh-wperf-stat--warn">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_stock_out', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatOut">—</strong>
        </article>
        <article class="wh-wperf-stat">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_mov_value', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatMovValue">—</strong>
        </article>
        <article class="wh-wperf-stat">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_inv_value', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatInvValue">—</strong>
        </article>
        <article class="wh-wperf-stat wh-wperf-stat--danger">
            <span class="wh-wperf-stat__label"><?php echo __t('wh_wperf_stat_expiring', 'warehouse'); ?></span>
            <strong class="wh-wperf-stat__value is-loading" id="whWperfStatExpiring">—</strong>
        </article>
    </div>
</section>

<section class="wh-wperf-scores" id="whWperfScores" aria-labelledby="whWperfScoresTitle">
    <h3 id="whWperfScoresTitle"><?php echo __t('wh_wperf_score_cards', 'warehouse'); ?></h3>
    <div class="wh-wperf-scores__grid">
        <article class="wh-wperf-score">
            <span class="wh-wperf-score__label"><?php echo __t('wh_wperf_stat_utilization', 'warehouse'); ?></span>
            <strong class="wh-wperf-score__value is-loading" id="whWperfScoreUtil">—</strong>
        </article>
        <article class="wh-wperf-score">
            <span class="wh-wperf-score__label"><?php echo __t('wh_wperf_stat_turnover', 'warehouse'); ?></span>
            <strong class="wh-wperf-score__value is-loading" id="whWperfScoreTurnover">—</strong>
        </article>
        <article class="wh-wperf-score">
            <span class="wh-wperf-score__label"><?php echo __t('wh_wperf_stat_receiving', 'warehouse'); ?></span>
            <strong class="wh-wperf-score__value is-loading" id="whWperfScoreReceiving">—</strong>
        </article>
        <article class="wh-wperf-score">
            <span class="wh-wperf-score__label"><?php echo __t('wh_wperf_stat_dispatch', 'warehouse'); ?></span>
            <strong class="wh-wperf-score__value is-loading" id="whWperfScoreDispatch">—</strong>
        </article>
    </div>
</section>

<section class="wh-wperf-charts" aria-label="Charts">
    <div class="wh-wperf-charts__grid">
        <article class="wh-wperf-chart-card">
            <h4><?php echo __t('wh_wperf_chart_throughput', 'warehouse'); ?></h4>
            <div class="wh-wperf-chart-wrap"><canvas id="whWperfChartThroughput"></canvas></div>
        </article>
        <article class="wh-wperf-chart-card">
            <h4><?php echo __t('wh_wperf_chart_comparison', 'warehouse'); ?></h4>
            <div class="wh-wperf-chart-wrap"><canvas id="whWperfChartComparison"></canvas></div>
        </article>
        <article class="wh-wperf-chart-card">
            <h4><?php echo __t('wh_wperf_chart_top_moving', 'warehouse'); ?></h4>
            <div class="wh-wperf-chart-wrap"><canvas id="whWperfChartTop"></canvas></div>
        </article>
        <article class="wh-wperf-chart-card">
            <h4><?php echo __t('wh_wperf_chart_stock_status', 'warehouse'); ?></h4>
            <div class="wh-wperf-chart-wrap"><canvas id="whWperfChartStatus"></canvas></div>
        </article>
    </div>
</section>

<div class="wh-wperf-toolbar">
    <div class="wh-wperf-toolbar__row">
        <div class="wh-wperf-toolbar__filters">
            <select id="whWperfPeriod" class="wh-select" aria-label="Period">
                <option value="week"><?php echo __t('wh_wperf_period_week', 'warehouse'); ?></option>
                <option value="month" selected><?php echo __t('wh_wperf_period_month', 'warehouse'); ?></option>
                <option value="year"><?php echo __t('wh_wperf_period_year', 'warehouse'); ?></option>
                <option value="custom"><?php echo __t('wh_wperf_period_custom', 'warehouse'); ?></option>
            </select>
            <select id="whWperfWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-wperf-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whWperfSearch" class="wh-wperf-search" placeholder="<?php echo htmlspecialchars(__t('wh_wperf_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <label class="wh-wperf-date-wrap" id="whWperfDateFromWrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whWperfDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </label>
            <label class="wh-wperf-date-wrap" id="whWperfDateToWrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whWperfDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>" disabled>
            </label>
        </div>
        <div class="wh-wperf-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whWperfExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whWperfExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_wperf_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whWperfExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_wperf_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whWperfPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_wperf_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whWperfRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-wperf-panel" aria-live="polite">
    <header class="wh-wperf-panel__head">
        <h3><?php echo __t('wh_wperf_table_title', 'warehouse'); ?></h3>
    </header>
    <div class="wh-wperf-table-wrap" id="whWperfTableWrap"></div>
    <div class="wh-wperf-empty" id="whWperfEmpty" hidden>
        <span class="material-icons-round">analytics</span>
        <p><?php echo __t('wh_wperf_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whWperfLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-wperf-pagination" id="whWperfPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whWperfPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-wperf-pagination__meta" id="whWperfPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whWperfNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/../includes/layout-end.php';

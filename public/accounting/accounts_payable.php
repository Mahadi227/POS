<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'payables';
$pageTitle = __t('nav_payables', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-payables.js'];
$pageI18n = acc_i18n([
    'ap_subtitle', 'ap_stat_outstanding', 'ap_stat_open', 'ap_stat_overdue', 'ap_stat_paid',
    'ap_insight_suppliers', 'ap_insight_avg_balance', 'ap_insight_overdue_ratio', 'ap_insight_paid_ratio',
    'ap_chart_status', 'ap_chart_suppliers', 'ap_chart_aging', 'ap_search_placeholder',
    'ap_filter_status', 'ap_filter_all', 'ap_status_open', 'ap_status_partial', 'ap_status_overdue', 'ap_status_paid',
    'ap_table_summary', 'ap_col_invoice', 'ap_col_supplier', 'ap_col_due', 'ap_col_amount', 'ap_col_paid',
    'ap_col_balance', 'ap_col_status', 'ap_view_details', 'ap_detail_title', 'ap_aging_current', 'ap_aging_30',
    'ap_aging_60', 'ap_aging_90', 'ap_notes', 'dash_all_stores', 'cr_export_csv', 'rpt_export_print',
    'refresh', 'loading', 'no_data', 'load_error', 'start_date', 'end_date', 'clear_search',
    'prev_page', 'next_page', 'records', 'close',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-ap-hero" aria-labelledby="accApHeroTitle">
    <div class="acc-ap-hero__intro">
        <h2 class="acc-ap-hero__title" id="accApHeroTitle"><?php echo __t('ap_subtitle', 'accounting'); ?></h2>
        <p class="acc-ap-hero__count" id="accApCount" aria-live="polite">—</p>
    </div>
    <div class="acc-ap-hero__stats" id="accApStats" role="group">
        <button type="button" class="acc-ap-stat acc-ap-stat--primary acc-ap-stat--click" data-stat-filter="all">
            <span class="acc-ap-stat__label"><?php echo __t('ap_stat_outstanding', 'accounting'); ?></span>
            <strong class="acc-ap-stat__value is-loading" id="accApStatOutstanding">—</strong>
        </button>
        <button type="button" class="acc-ap-stat acc-ap-stat--click" data-stat-filter="open">
            <span class="acc-ap-stat__label"><?php echo __t('ap_status_open', 'accounting'); ?></span>
            <strong class="acc-ap-stat__value is-loading" id="accApStatOpen">—</strong>
        </button>
        <button type="button" class="acc-ap-stat acc-ap-stat--warn acc-ap-stat--click" data-stat-filter="overdue">
            <span class="acc-ap-stat__label"><?php echo __t('ap_stat_overdue', 'accounting'); ?></span>
            <strong class="acc-ap-stat__value is-loading" id="accApStatOverdue">—</strong>
        </button>
        <button type="button" class="acc-ap-stat acc-ap-stat--success acc-ap-stat--click" data-stat-filter="paid">
            <span class="acc-ap-stat__label"><?php echo __t('ap_stat_paid', 'accounting'); ?></span>
            <strong class="acc-ap-stat__value is-loading" id="accApStatPaid">—</strong>
        </button>
    </div>
</section>

<div class="acc-ap-insights" id="accApInsights">
    <article class="acc-ap-insight">
        <span class="acc-ap-insight__label"><?php echo __t('ap_insight_suppliers', 'accounting'); ?></span>
        <strong class="acc-ap-insight__value is-loading" id="accApSuppliers">—</strong>
    </article>
    <article class="acc-ap-insight">
        <span class="acc-ap-insight__label"><?php echo __t('ap_insight_avg_balance', 'accounting'); ?></span>
        <strong class="acc-ap-insight__value is-loading" id="accApAvgBalance">—</strong>
    </article>
    <article class="acc-ap-insight">
        <span class="acc-ap-insight__label"><?php echo __t('ap_insight_overdue_ratio', 'accounting'); ?></span>
        <strong class="acc-ap-insight__value is-loading" id="accApOverdueRatio">—</strong>
    </article>
    <article class="acc-ap-insight">
        <span class="acc-ap-insight__label"><?php echo __t('ap_insight_paid_ratio', 'accounting'); ?></span>
        <strong class="acc-ap-insight__value is-loading" id="accApPaidRatio">—</strong>
    </article>
</div>

<div class="acc-ap-charts" id="accApCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('ap_chart_status', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accApStatus"></canvas>
                <p class="acc-chart-empty" id="accApStatusEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accApStatusLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('ap_chart_aging', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accApAging"></canvas>
                <p class="acc-chart-empty" id="accApAgingEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('ap_chart_suppliers', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accApSuppliers"></canvas>
                <p class="acc-chart-empty" id="accApSuppliersEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-ap-toolbar">
    <div class="acc-ap-toolbar__top">
        <div class="acc-ap-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accApSearch" placeholder="<?php echo htmlspecialchars(__t('ap_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-ap-search-clear" id="accApSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-ap-toolbar__dates">
            <label class="acc-ap-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accApDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-ap-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accApDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-ap-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accApExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-ap-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accApPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-ap-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accApRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-ap-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-ap-toolbar__filters" id="accApStatusFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('ap_filter_status', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-ap-chip is-active" data-status="all" role="tab" aria-selected="true"><?php echo __t('ap_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-ap-chip" data-status="open" role="tab"><?php echo __t('ap_status_open', 'accounting'); ?></button>
        <button type="button" class="acc-ap-chip" data-status="partial" role="tab"><?php echo __t('ap_status_partial', 'accounting'); ?></button>
        <button type="button" class="acc-ap-chip" data-status="overdue" role="tab"><?php echo __t('ap_status_overdue', 'accounting'); ?></button>
        <button type="button" class="acc-ap-chip" data-status="paid" role="tab"><?php echo __t('ap_status_paid', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-ap-panel" id="accApPrintArea">
    <div class="acc-ap-panel__head">
        <span class="acc-ap-panel__meta" id="accApMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-ap-pagination">
            <button type="button" class="acc-ap-page-btn" id="accApPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accApPageInfo">1 / 1</span>
            <button type="button" class="acc-ap-page-btn" id="accApNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accApRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-ap-modal-overlay" id="accApDetailModal" hidden>
    <div class="acc-ap-modal" role="dialog" aria-labelledby="accApDetailTitle">
        <header class="acc-ap-modal__head">
            <h2 id="accApDetailTitle"><?php echo __t('ap_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-ap-modal__close" id="accApDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-ap-modal__body" id="accApDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

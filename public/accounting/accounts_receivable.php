<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'receivables';
$pageTitle = __t('nav_receivables', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-receivables.js'];
$pageI18n = acc_i18n([
    'ar_subtitle', 'ar_stat_outstanding', 'ar_stat_open', 'ar_stat_overdue', 'ar_stat_collected',
    'ar_insight_customers', 'ar_insight_avg_balance', 'ar_insight_overdue_ratio', 'ar_insight_collected_ratio',
    'ar_chart_status', 'ar_chart_customers', 'ar_chart_aging', 'ar_search_placeholder',
    'ar_filter_status', 'ar_filter_all', 'ar_status_open', 'ar_status_partial', 'ar_status_overdue',
    'ar_status_paid', 'ar_status_written_off', 'ar_col_invoice', 'ar_col_customer', 'ar_col_due',
    'ar_col_amount', 'ar_col_paid', 'ar_col_balance', 'ar_col_status', 'ar_view_details',
    'ar_detail_title', 'ar_aging_current', 'ar_aging_30', 'ar_aging_60', 'ar_aging_90', 'ar_notes',
    'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data',
    'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records', 'close',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-ar-hero" aria-labelledby="accArHeroTitle">
    <div class="acc-ar-hero__intro">
        <h2 class="acc-ar-hero__title" id="accArHeroTitle"><?php echo __t('ar_subtitle', 'accounting'); ?></h2>
        <p class="acc-ar-hero__count" id="accArCount" aria-live="polite">—</p>
    </div>
    <div class="acc-ar-hero__stats" id="accArStats" role="group">
        <button type="button" class="acc-ar-stat acc-ar-stat--primary acc-ar-stat--click" data-stat-filter="all">
            <span class="acc-ar-stat__label"><?php echo __t('ar_stat_outstanding', 'accounting'); ?></span>
            <strong class="acc-ar-stat__value is-loading" id="accArStatOutstanding">—</strong>
        </button>
        <button type="button" class="acc-ar-stat acc-ar-stat--click" data-stat-filter="open">
            <span class="acc-ar-stat__label"><?php echo __t('ar_status_open', 'accounting'); ?></span>
            <strong class="acc-ar-stat__value is-loading" id="accArStatOpen">—</strong>
        </button>
        <button type="button" class="acc-ar-stat acc-ar-stat--warn acc-ar-stat--click" data-stat-filter="overdue">
            <span class="acc-ar-stat__label"><?php echo __t('ar_stat_overdue', 'accounting'); ?></span>
            <strong class="acc-ar-stat__value is-loading" id="accArStatOverdue">—</strong>
        </button>
        <button type="button" class="acc-ar-stat acc-ar-stat--success acc-ar-stat--click" data-stat-filter="paid">
            <span class="acc-ar-stat__label"><?php echo __t('ar_stat_collected', 'accounting'); ?></span>
            <strong class="acc-ar-stat__value is-loading" id="accArStatCollected">—</strong>
        </button>
    </div>
</section>

<div class="acc-ar-insights" id="accArInsights">
    <article class="acc-ar-insight">
        <span class="acc-ar-insight__label"><?php echo __t('ar_insight_customers', 'accounting'); ?></span>
        <strong class="acc-ar-insight__value is-loading" id="accArCustomers">—</strong>
    </article>
    <article class="acc-ar-insight">
        <span class="acc-ar-insight__label"><?php echo __t('ar_insight_avg_balance', 'accounting'); ?></span>
        <strong class="acc-ar-insight__value is-loading" id="accArAvgBalance">—</strong>
    </article>
    <article class="acc-ar-insight">
        <span class="acc-ar-insight__label"><?php echo __t('ar_insight_overdue_ratio', 'accounting'); ?></span>
        <strong class="acc-ar-insight__value is-loading" id="accArOverdueRatio">—</strong>
    </article>
    <article class="acc-ar-insight">
        <span class="acc-ar-insight__label"><?php echo __t('ar_insight_collected_ratio', 'accounting'); ?></span>
        <strong class="acc-ar-insight__value is-loading" id="accArCollectedRatio">—</strong>
    </article>
</div>

<div class="acc-ar-charts" id="accArCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('ar_chart_status', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accArStatus"></canvas>
                <p class="acc-chart-empty" id="accArStatusEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accArStatusLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('ar_chart_aging', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accArAging"></canvas>
                <p class="acc-chart-empty" id="accArAgingEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('ar_chart_customers', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accArCustomers"></canvas>
                <p class="acc-chart-empty" id="accArCustomersEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-ar-toolbar">
    <div class="acc-ar-toolbar__top">
        <div class="acc-ar-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accArSearch" placeholder="<?php echo htmlspecialchars(__t('ar_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-ar-search-clear" id="accArSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-ar-toolbar__dates">
            <label class="acc-ar-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accArDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-ar-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accArDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-ar-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accArExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-ar-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accArPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-ar-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accArRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-ar-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-ar-toolbar__filters" id="accArStatusFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('ar_filter_status', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-ar-chip is-active" data-status="all" role="tab" aria-selected="true"><?php echo __t('ar_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-ar-chip" data-status="open" role="tab"><?php echo __t('ar_status_open', 'accounting'); ?></button>
        <button type="button" class="acc-ar-chip" data-status="partial" role="tab"><?php echo __t('ar_status_partial', 'accounting'); ?></button>
        <button type="button" class="acc-ar-chip" data-status="overdue" role="tab"><?php echo __t('ar_status_overdue', 'accounting'); ?></button>
        <button type="button" class="acc-ar-chip" data-status="paid" role="tab"><?php echo __t('ar_status_paid', 'accounting'); ?></button>
        <button type="button" class="acc-ar-chip" data-status="written_off" role="tab"><?php echo __t('ar_status_written_off', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-ar-panel" id="accArPrintArea">
    <div class="acc-ar-panel__head">
        <span class="acc-ar-panel__meta" id="accArMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-ar-pagination">
            <button type="button" class="acc-ar-page-btn" id="accArPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accArPageInfo">1 / 1</span>
            <button type="button" class="acc-ar-page-btn" id="accArNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accArRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-ar-modal-overlay" id="accArDetailModal" hidden>
    <div class="acc-ar-modal" role="dialog" aria-labelledby="accArDetailTitle">
        <header class="acc-ar-modal__head">
            <h2 id="accArDetailTitle"><?php echo __t('ar_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-ar-modal__close" id="accArDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-ar-modal__body" id="accArDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

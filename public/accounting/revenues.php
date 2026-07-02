<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'revenues';
$pageTitle = __t('nav_revenues', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-revenues.js'];
$pageI18n = acc_i18n([
    'rev_subtitle', 'rev_stat_period', 'rev_stat_today', 'rev_stat_sales', 'rev_stat_manual',
    'rev_insight_lines', 'rev_insight_avg_daily', 'rev_insight_auto_pct', 'rev_insight_top_account',
    'rev_chart_trend', 'rev_chart_accounts', 'rev_chart_source', 'rev_search_placeholder',
    'rev_filter_source', 'rev_filter_account', 'rev_filter_all',
    'rev_col_date', 'rev_col_entry', 'rev_col_account', 'rev_col_amount', 'rev_col_source',
    'rev_col_description', 'rev_col_by', 'rev_view_details', 'rev_detail_title', 'rev_memo',
    'je_ref_manual', 'je_ref_sale', 'je_ref_expense', 'je_ref_payment', 'je_ref_purchase', 'je_ref_inventory',
    'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh',
    'loading', 'no_data', 'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page',
    'next_page', 'records', 'close',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-rev-hero" aria-labelledby="accRevHeroTitle">
    <div class="acc-rev-hero__intro">
        <h2 class="acc-rev-hero__title" id="accRevHeroTitle"><?php echo __t('rev_subtitle', 'accounting'); ?></h2>
        <p class="acc-rev-hero__count" id="accRevCount" aria-live="polite">—</p>
    </div>
    <div class="acc-rev-hero__stats" id="accRevStats" role="group">
        <button type="button" class="acc-rev-stat acc-rev-stat--primary acc-rev-stat--click" data-stat-filter="all">
            <span class="acc-rev-stat__label"><?php echo __t('rev_stat_period', 'accounting'); ?></span>
            <strong class="acc-rev-stat__value is-loading" id="accRevStatPeriod">—</strong>
        </button>
        <button type="button" class="acc-rev-stat acc-rev-stat--click" data-stat-filter="today">
            <span class="acc-rev-stat__label"><?php echo __t('rev_stat_today', 'accounting'); ?></span>
            <strong class="acc-rev-stat__value is-loading" id="accRevStatToday">—</strong>
        </button>
        <button type="button" class="acc-rev-stat acc-rev-stat--success acc-rev-stat--click" data-stat-filter="sale">
            <span class="acc-rev-stat__label"><?php echo __t('rev_stat_sales', 'accounting'); ?></span>
            <strong class="acc-rev-stat__value is-loading" id="accRevStatSales">—</strong>
        </button>
        <button type="button" class="acc-rev-stat acc-rev-stat--warn acc-rev-stat--click" data-stat-filter="manual">
            <span class="acc-rev-stat__label"><?php echo __t('rev_stat_manual', 'accounting'); ?></span>
            <strong class="acc-rev-stat__value is-loading" id="accRevStatManual">—</strong>
        </button>
    </div>
</section>

<div class="acc-rev-insights" id="accRevInsights">
    <article class="acc-rev-insight">
        <span class="acc-rev-insight__label"><?php echo __t('rev_insight_lines', 'accounting'); ?></span>
        <strong class="acc-rev-insight__value is-loading" id="accRevInsightLines">—</strong>
    </article>
    <article class="acc-rev-insight">
        <span class="acc-rev-insight__label"><?php echo __t('rev_insight_avg_daily', 'accounting'); ?></span>
        <strong class="acc-rev-insight__value is-loading" id="accRevInsightAvg">—</strong>
    </article>
    <article class="acc-rev-insight">
        <span class="acc-rev-insight__label"><?php echo __t('rev_insight_auto_pct', 'accounting'); ?></span>
        <strong class="acc-rev-insight__value is-loading" id="accRevInsightAuto">—</strong>
    </article>
    <article class="acc-rev-insight">
        <span class="acc-rev-insight__label"><?php echo __t('rev_insight_top_account', 'accounting'); ?></span>
        <strong class="acc-rev-insight__value is-loading acc-rev-insight__value--truncate" id="accRevInsightTop">—</strong>
    </article>
</div>

<div class="acc-rev-charts" id="accRevCharts">
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('rev_chart_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accRevTrend"></canvas>
                <p class="acc-chart-empty" id="accRevTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('rev_chart_source', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accRevSource"></canvas>
                <p class="acc-chart-empty" id="accRevSourceEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accRevSourceLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('rev_chart_accounts', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accRevAccounts"></canvas>
                <p class="acc-chart-empty" id="accRevAccountsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-rev-toolbar">
    <div class="acc-rev-toolbar__top">
        <div class="acc-rev-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accRevSearch" placeholder="<?php echo htmlspecialchars(__t('rev_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-rev-search-clear" id="accRevSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-rev-toolbar__dates">
            <label class="acc-rev-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accRevDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-rev-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accRevDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-rev-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accRevExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-rev-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accRevPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-rev-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accRevRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-rev-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-rev-toolbar__filters">
        <div class="acc-rev-toolbar__filters-group" id="accRevSourceFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('rev_filter_source', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-rev-chip is-active" data-source="all" role="tab" aria-selected="true"><?php echo __t('rev_filter_all', 'accounting'); ?></button>
            <button type="button" class="acc-rev-chip" data-source="sale" role="tab"><?php echo __t('je_ref_sale', 'accounting'); ?></button>
            <button type="button" class="acc-rev-chip" data-source="manual" role="tab"><?php echo __t('je_ref_manual', 'accounting'); ?></button>
        </div>
        <div class="acc-rev-toolbar__filters-group acc-rev-toolbar__filters-group--accounts" id="accRevAccountFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('rev_filter_account', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-rev-chip is-active" data-account="all" role="tab" aria-selected="true"><?php echo __t('rev_filter_all', 'accounting'); ?></button>
        </div>
    </div>
</div>

<div class="acc-rev-panel" id="accRevPrintArea">
    <div class="acc-rev-panel__head">
        <span class="acc-rev-panel__meta" id="accRevMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-rev-pagination">
            <button type="button" class="acc-rev-page-btn" id="accRevPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accRevPageInfo">1 / 1</span>
            <button type="button" class="acc-rev-page-btn" id="accRevNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accRevRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-rev-modal-overlay" id="accRevDetailModal" hidden>
    <div class="acc-rev-modal" role="dialog" aria-labelledby="accRevDetailTitle">
        <header class="acc-rev-modal__head">
            <h2 id="accRevDetailTitle"><?php echo __t('rev_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-rev-modal__close" id="accRevDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-rev-modal__body" id="accRevDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'profit_loss';
$pageTitle = __t('nav_profit_loss', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-profit-loss.js'];
$pageI18n = acc_i18n([
    'pl_subtitle', 'pl_stat_revenue', 'pl_stat_gross', 'pl_stat_expenses', 'pl_stat_net',
    'pl_insight_gross_margin', 'pl_insight_net_margin', 'pl_insight_expense_ratio', 'pl_insight_avg_profit',
    'pl_chart_combined', 'pl_chart_categories', 'pl_waterfall_title', 'pl_cogs', 'pl_opex',
    'dash_period_week', 'dash_period_month', 'rpt_period_quarter', 'dash_period_year',
    'kpi_revenue', 'kpi_gross_profit', 'kpi_expenses', 'kpi_net_profit', 'dash_all_stores',
    'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data', 'load_error',
    'start_date', 'end_date',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-pl-hero" aria-labelledby="accPlHeroTitle">
    <div class="acc-pl-hero__intro">
        <h2 class="acc-pl-hero__title" id="accPlHeroTitle"><?php echo __t('pl_subtitle', 'accounting'); ?></h2>
        <p class="acc-pl-hero__period" id="accPlPeriodLabel" aria-live="polite">—</p>
        <p class="acc-pl-hero__scope" id="accPlStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-pl-hero__stats" id="accPlHeroStats" role="group">
        <div class="acc-pl-stat acc-pl-stat--primary">
            <span class="acc-pl-stat__label"><?php echo __t('pl_stat_revenue', 'accounting'); ?></span>
            <strong class="acc-pl-stat__value is-loading" id="accPlStatRevenue">—</strong>
        </div>
        <div class="acc-pl-stat acc-pl-stat--success">
            <span class="acc-pl-stat__label"><?php echo __t('pl_stat_gross', 'accounting'); ?></span>
            <strong class="acc-pl-stat__value is-loading" id="accPlStatGross">—</strong>
        </div>
        <div class="acc-pl-stat acc-pl-stat--warn">
            <span class="acc-pl-stat__label"><?php echo __t('pl_stat_expenses', 'accounting'); ?></span>
            <strong class="acc-pl-stat__value is-loading" id="accPlStatExpenses">—</strong>
        </div>
        <div class="acc-pl-stat">
            <span class="acc-pl-stat__label"><?php echo __t('pl_stat_net', 'accounting'); ?></span>
            <strong class="acc-pl-stat__value is-loading" id="accPlStatNet">—</strong>
        </div>
    </div>
</section>

<div class="acc-pl-insights" id="accPlInsights">
    <article class="acc-pl-insight">
        <span class="acc-pl-insight__label"><?php echo __t('pl_insight_gross_margin', 'accounting'); ?></span>
        <strong class="acc-pl-insight__value is-loading" id="accPlGrossMargin">—</strong>
    </article>
    <article class="acc-pl-insight">
        <span class="acc-pl-insight__label"><?php echo __t('pl_insight_net_margin', 'accounting'); ?></span>
        <strong class="acc-pl-insight__value is-loading" id="accPlNetMargin">—</strong>
    </article>
    <article class="acc-pl-insight">
        <span class="acc-pl-insight__label"><?php echo __t('pl_insight_expense_ratio', 'accounting'); ?></span>
        <strong class="acc-pl-insight__value is-loading" id="accPlExpenseRatio">—</strong>
    </article>
    <article class="acc-pl-insight">
        <span class="acc-pl-insight__label"><?php echo __t('pl_insight_avg_profit', 'accounting'); ?></span>
        <strong class="acc-pl-insight__value is-loading" id="accPlAvgProfit">—</strong>
    </article>
</div>

<div class="acc-pl-toolbar">
    <div class="acc-pl-toolbar__top">
        <div class="acc-pl-period" id="accPlPeriod" role="tablist">
            <button type="button" class="acc-pl-chip" data-period="week" role="tab"><?php echo __t('dash_period_week', 'accounting'); ?></button>
            <button type="button" class="acc-pl-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('dash_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-pl-chip" data-period="quarter" role="tab"><?php echo __t('rpt_period_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-pl-chip" data-period="year" role="tab"><?php echo __t('dash_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-pl-toolbar__dates">
            <label class="acc-pl-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accPlDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-pl-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accPlDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-pl-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accPlExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-pl-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accPlPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-pl-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accPlRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-pl-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-pl-charts" id="accPlCharts">
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('pl_chart_combined', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accPlCombined"></canvas>
                <p class="acc-chart-empty" id="accPlCombinedEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('pl_chart_categories', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accPlCategories"></canvas>
                <p class="acc-chart-empty" id="accPlCategoriesEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accPlCategoriesLegend"></ul>
        </div>
    </section>
</div>

<div class="acc-pl-panel" id="accPlPrintArea">
    <header class="acc-pl-panel__head">
        <h3><?php echo __t('pl_waterfall_title', 'accounting'); ?></h3>
    </header>
    <div class="acc-pl-panel__body" id="accPlDetailRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'analytics';
$pageTitle = __t('nav_analytics', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-analytics.js'];
$pageI18n = acc_i18n([
    'an_subtitle', 'an_stat_margin', 'an_insight_avg_revenue', 'an_insight_avg_expense',
    'an_insight_expense_ratio', 'an_insight_gross_margin', 'an_chart_combined', 'an_chart_branches',
    'dash_period_week', 'dash_period_month', 'dash_period_year', 'rpt_period_quarter',
    'dash_hero_treasury', 'dash_treasury_mix', 'dash_branch_meta', 'dash_all_stores',
    'kpi_revenue', 'kpi_expenses', 'kpi_gross_profit', 'kpi_net_profit',
    'kpi_cash', 'kpi_bank', 'kpi_mobile', 'chart_revenue', 'chart_expenses',
    'chart_expense_breakdown', 'branch_comparison', 'cr_export_csv', 'refresh', 'loading',
    'no_data', 'branch', 'load_error', 'start_date', 'end_date', 'records',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-an-hero" aria-labelledby="accAnHeroTitle">
    <div class="acc-an-hero__intro">
        <h2 class="acc-an-hero__title" id="accAnHeroTitle"><?php echo __t('an_subtitle', 'accounting'); ?></h2>
        <p class="acc-an-hero__period" id="accAnPeriodLabel" aria-live="polite">—</p>
        <p class="acc-an-hero__scope" id="accAnStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-an-hero__stats" id="accAnHeroStats" role="group">
        <div class="acc-an-stat acc-an-stat--primary">
            <span class="acc-an-stat__label"><?php echo __t('kpi_revenue', 'accounting'); ?></span>
            <strong class="acc-an-stat__value is-loading" id="accAnStatRevenue">—</strong>
        </div>
        <div class="acc-an-stat acc-an-stat--warn">
            <span class="acc-an-stat__label"><?php echo __t('kpi_expenses', 'accounting'); ?></span>
            <strong class="acc-an-stat__value is-loading" id="accAnStatExpenses">—</strong>
        </div>
        <div class="acc-an-stat acc-an-stat--success">
            <span class="acc-an-stat__label"><?php echo __t('kpi_net_profit', 'accounting'); ?></span>
            <strong class="acc-an-stat__value is-loading" id="accAnStatNet">—</strong>
        </div>
        <div class="acc-an-stat">
            <span class="acc-an-stat__label"><?php echo __t('an_stat_margin', 'accounting'); ?></span>
            <strong class="acc-an-stat__value is-loading" id="accAnStatMargin">—</strong>
        </div>
    </div>
</section>

<div class="acc-an-insights" id="accAnInsights">
    <article class="acc-an-insight">
        <span class="acc-an-insight__label"><?php echo __t('an_insight_avg_revenue', 'accounting'); ?></span>
        <strong class="acc-an-insight__value is-loading" id="accAnAvgRevenue">—</strong>
    </article>
    <article class="acc-an-insight">
        <span class="acc-an-insight__label"><?php echo __t('an_insight_avg_expense', 'accounting'); ?></span>
        <strong class="acc-an-insight__value is-loading" id="accAnAvgExpense">—</strong>
    </article>
    <article class="acc-an-insight">
        <span class="acc-an-insight__label"><?php echo __t('an_insight_gross_margin', 'accounting'); ?></span>
        <strong class="acc-an-insight__value is-loading" id="accAnGrossMargin">—</strong>
    </article>
    <article class="acc-an-insight">
        <span class="acc-an-insight__label"><?php echo __t('an_insight_expense_ratio', 'accounting'); ?></span>
        <strong class="acc-an-insight__value is-loading" id="accAnExpenseRatio">—</strong>
    </article>
</div>

<div class="acc-an-toolbar">
    <div class="acc-an-toolbar__top">
        <div class="acc-an-period" id="accAnPeriod" role="tablist">
            <button type="button" class="acc-an-chip" data-period="week" role="tab"><?php echo __t('dash_period_week', 'accounting'); ?></button>
            <button type="button" class="acc-an-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('dash_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-an-chip" data-period="quarter" role="tab"><?php echo __t('rpt_period_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-an-chip" data-period="year" role="tab"><?php echo __t('dash_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-an-toolbar__dates">
            <label class="acc-an-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accAnDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-an-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accAnDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-an-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accAnExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-an-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accAnRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-an-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-an-charts" id="accAnCharts">
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('an_chart_combined', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accAnCombined"></canvas>
                <p class="acc-chart-empty" id="accAnCombinedEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_revenue', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accAnRevenue"></canvas>
                <p class="acc-chart-empty" id="accAnRevenueEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_expenses', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accAnExpense"></canvas>
                <p class="acc-chart-empty" id="accAnExpenseEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_expense_breakdown', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accAnBreakdown"></canvas>
                <p class="acc-chart-empty" id="accAnBreakdownEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accAnBreakdownLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('dash_treasury_mix', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accAnTreasury"></canvas>
                <p class="acc-chart-empty" id="accAnTreasuryEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accAnTreasuryLegend"></ul>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('an_chart_branches', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--medium">
                <canvas id="accAnBranches"></canvas>
                <p class="acc-chart-empty" id="accAnBranchesEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="acc-an-branch-panel">
    <header class="acc-an-branch-panel__head">
        <h3><?php echo __t('branch_comparison', 'accounting'); ?></h3>
        <span class="acc-panel__meta" id="accAnBranchMeta">—</span>
    </header>
    <div id="accAnBranchRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

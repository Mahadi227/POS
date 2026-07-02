<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'cashflow';
$pageTitle = __t('nav_cashflow', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-cashflow.js'];
$pageI18n = acc_i18n([
    'cf_subtitle', 'cf_stat_cash_in', 'cf_stat_cash_out', 'cf_stat_net', 'cf_stat_treasury',
    'cf_insight_avg_in', 'cf_insight_avg_out', 'cf_insight_ratio', 'cf_insight_runway',
    'cf_insight_receivable', 'cf_insight_payable', 'cf_chart_combined', 'cf_chart_net',
    'dash_period_week', 'dash_period_month', 'rpt_period_quarter', 'dash_period_year',
    'rpt_cash_in_section', 'rpt_cash_out_section', 'rpt_sales_in', 'rpt_ar_collected',
    'rpt_expenses_out', 'rpt_ap_paid', 'rpt_treasury_balances', 'dash_treasury_mix',
    'dash_all_stores', 'cash_in', 'cash_out', 'net_cash_flow', 'kpi_cash', 'kpi_bank',
    'kpi_mobile', 'kpi_receivable', 'kpi_payable', 'cr_export_csv', 'rpt_export_print',
    'refresh', 'loading', 'no_data', 'load_error', 'start_date', 'end_date',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-cf-hero" aria-labelledby="accCfHeroTitle">
    <div class="acc-cf-hero__intro">
        <h2 class="acc-cf-hero__title" id="accCfHeroTitle"><?php echo __t('cf_subtitle', 'accounting'); ?></h2>
        <p class="acc-cf-hero__period" id="accCfPeriodLabel" aria-live="polite">—</p>
        <p class="acc-cf-hero__scope" id="accCfStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-cf-hero__stats" id="accCfHeroStats" role="group">
        <div class="acc-cf-stat acc-cf-stat--success">
            <span class="acc-cf-stat__label"><?php echo __t('cf_stat_cash_in', 'accounting'); ?></span>
            <strong class="acc-cf-stat__value is-loading" id="accCfStatIn">—</strong>
        </div>
        <div class="acc-cf-stat acc-cf-stat--warn">
            <span class="acc-cf-stat__label"><?php echo __t('cf_stat_cash_out', 'accounting'); ?></span>
            <strong class="acc-cf-stat__value is-loading" id="accCfStatOut">—</strong>
        </div>
        <div class="acc-cf-stat acc-cf-stat--primary">
            <span class="acc-cf-stat__label"><?php echo __t('cf_stat_net', 'accounting'); ?></span>
            <strong class="acc-cf-stat__value is-loading" id="accCfStatNet">—</strong>
        </div>
        <div class="acc-cf-stat">
            <span class="acc-cf-stat__label"><?php echo __t('cf_stat_treasury', 'accounting'); ?></span>
            <strong class="acc-cf-stat__value is-loading" id="accCfStatTreasury">—</strong>
        </div>
    </div>
</section>

<div class="acc-cf-insights" id="accCfInsights">
    <article class="acc-cf-insight">
        <span class="acc-cf-insight__label"><?php echo __t('cf_insight_avg_in', 'accounting'); ?></span>
        <strong class="acc-cf-insight__value is-loading" id="accCfAvgIn">—</strong>
    </article>
    <article class="acc-cf-insight">
        <span class="acc-cf-insight__label"><?php echo __t('cf_insight_avg_out', 'accounting'); ?></span>
        <strong class="acc-cf-insight__value is-loading" id="accCfAvgOut">—</strong>
    </article>
    <article class="acc-cf-insight">
        <span class="acc-cf-insight__label"><?php echo __t('cf_insight_ratio', 'accounting'); ?></span>
        <strong class="acc-cf-insight__value is-loading" id="accCfRatio">—</strong>
    </article>
    <article class="acc-cf-insight">
        <span class="acc-cf-insight__label"><?php echo __t('cf_insight_runway', 'accounting'); ?></span>
        <strong class="acc-cf-insight__value is-loading" id="accCfRunway">—</strong>
    </article>
</div>

<div class="acc-cf-toolbar">
    <div class="acc-cf-toolbar__top">
        <div class="acc-cf-period" id="accCfPeriod" role="tablist">
            <button type="button" class="acc-cf-chip" data-period="week" role="tab"><?php echo __t('dash_period_week', 'accounting'); ?></button>
            <button type="button" class="acc-cf-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('dash_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-cf-chip" data-period="quarter" role="tab"><?php echo __t('rpt_period_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-cf-chip" data-period="year" role="tab"><?php echo __t('dash_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-cf-toolbar__dates">
            <label class="acc-cf-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCfDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-cf-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCfDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-cf-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accCfExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-cf-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCfPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-cf-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accCfRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-cf-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-cf-charts" id="accCfCharts">
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('cf_chart_combined', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accCfCombined"></canvas>
                <p class="acc-chart-empty" id="accCfCombinedEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('cf_chart_net', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accCfNet"></canvas>
                <p class="acc-chart-empty" id="accCfNetEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('dash_treasury_mix', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accCfTreasury"></canvas>
                <p class="acc-chart-empty" id="accCfTreasuryEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accCfTreasuryLegend"></ul>
        </div>
    </section>
</div>

<div class="acc-cf-panel" id="accCfPrintArea">
    <div class="acc-cf-panel__grid" id="accCfDetailRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

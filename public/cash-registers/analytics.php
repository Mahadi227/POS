<?php
/**
 * Cash register analytics — trends, comparisons, cashier performance.
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'analytics';
$pageTitle = __t('cr_analytics_title', 'admin');
$loadChart = true;
$extraScripts = ['cash-registers-common.js', 'cash-registers-analytics.js'];
$pageI18n = cr_i18n([
    'cr_analytics_subtitle', 'cr_analytics_period_week', 'cr_analytics_period_month', 'cr_analytics_period_year',
    'cr_analytics_stat_revenue', 'cr_analytics_stat_sessions', 'cr_analytics_stat_refunds', 'cr_analytics_stat_avg_session',
    'cr_analytics_chart_daily', 'cr_analytics_chart_registers', 'cr_analytics_chart_branches', 'cr_analytics_chart_refunds',
    'cr_analytics_chart_payments', 'cr_analytics_cashier_title', 'cr_analytics_cashier_sessions', 'cr_analytics_rank',
    'cr_stat_cash', 'cr_stat_cash_balance', 'cr_stat_mobile', 'cr_stat_card', 'cr_stat_sales_today',
    'cr_no_data', 'cr_export_csv', 'loading', 'refresh', 'col_date', 'cr_col_register', 'cr_col_cashier',
    'cr_branch', 'load_error', 'error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-analytics-hero" aria-labelledby="crAnalyticsHeroTitle">
    <div class="cr-analytics-hero__intro">
        <h2 class="cr-analytics-hero__title" id="crAnalyticsHeroTitle"><?php echo __t('cr_analytics_subtitle', 'admin'); ?></h2>
        <p class="cr-analytics-hero__period" id="crAnalyticsPeriodLabel" aria-live="polite">—</p>
    </div>
    <div class="cr-analytics-hero__stats" id="crAnalyticsStats">
        <div class="cr-analytics-stat cr-analytics-stat--primary">
            <span class="cr-analytics-stat__label"><?php echo __t('cr_analytics_stat_revenue', 'admin'); ?></span>
            <strong class="cr-analytics-stat__value is-loading" id="crAnalyticsStatRevenue">—</strong>
        </div>
        <div class="cr-analytics-stat">
            <span class="cr-analytics-stat__label"><?php echo __t('cr_analytics_stat_sessions', 'admin'); ?></span>
            <strong class="cr-analytics-stat__value is-loading" id="crAnalyticsStatSessions">—</strong>
        </div>
        <div class="cr-analytics-stat cr-analytics-stat--warn">
            <span class="cr-analytics-stat__label"><?php echo __t('cr_analytics_stat_refunds', 'admin'); ?></span>
            <strong class="cr-analytics-stat__value is-loading" id="crAnalyticsStatRefunds">—</strong>
        </div>
        <div class="cr-analytics-stat">
            <span class="cr-analytics-stat__label"><?php echo __t('cr_analytics_stat_avg_session', 'admin'); ?></span>
            <strong class="cr-analytics-stat__value is-loading" id="crAnalyticsStatAvg">—</strong>
        </div>
    </div>
</section>

<div class="cr-analytics-toolbar">
    <div class="cr-analytics-period" id="crAnalyticsPeriod" role="tablist" aria-label="<?php echo htmlspecialchars(__t('col_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip" data-period="week" role="tab"><?php echo __t('cr_analytics_period_week', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('cr_analytics_period_month', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-period="year" role="tab"><?php echo __t('cr_analytics_period_year', 'admin'); ?></button>
    </div>
    <div class="cr-analytics-toolbar__actions">
        <button type="button" class="cr-btn cr-btn--ghost" id="crAnalyticsExportBtn">
            <span class="material-icons-round">download</span>
            <?php echo __t('cr_export_csv', 'admin'); ?>
        </button>
        <button type="button" class="cr-btn" id="crAnalyticsRefreshBtn">
            <span class="material-icons-round">refresh</span>
            <?php echo __t('refresh', 'admin'); ?>
        </button>
    </div>
</div>

<div class="cr-analytics-charts">
    <section class="cr-analytics-panel cr-analytics-panel--wide">
        <header class="cr-analytics-panel__head">
            <h3><?php echo __t('cr_analytics_chart_daily', 'admin'); ?></h3>
        </header>
        <div class="cr-analytics-panel__body">
            <div class="cr-analytics-chart-wrap">
                <canvas id="crDailyChart" height="240"></canvas>
                <p class="cr-analytics-empty" id="crDailyEmpty" hidden><?php echo __t('cr_no_data', 'admin'); ?></p>
            </div>
        </div>
    </section>

    <section class="cr-analytics-panel">
        <header class="cr-analytics-panel__head">
            <h3><?php echo __t('cr_analytics_chart_payments', 'admin'); ?></h3>
        </header>
        <div class="cr-analytics-panel__body cr-analytics-panel__body--donut">
            <canvas id="crPaymentChart" height="220"></canvas>
            <ul class="cr-analytics-legend" id="crPaymentLegend"></ul>
            <p class="cr-analytics-empty" id="crPaymentEmpty" hidden><?php echo __t('cr_no_data', 'admin'); ?></p>
        </div>
    </section>

    <section class="cr-analytics-panel">
        <header class="cr-analytics-panel__head">
            <h3 id="crRegisterChartTitle"><?php echo __t('cr_analytics_chart_registers', 'admin'); ?></h3>
        </header>
        <div class="cr-analytics-panel__body">
            <div class="cr-analytics-chart-wrap">
                <canvas id="crRegisterChart" height="240"></canvas>
                <p class="cr-analytics-empty" id="crRegisterEmpty" hidden><?php echo __t('cr_no_data', 'admin'); ?></p>
            </div>
        </div>
    </section>

    <section class="cr-analytics-panel">
        <header class="cr-analytics-panel__head">
            <h3><?php echo __t('cr_analytics_chart_refunds', 'admin'); ?></h3>
        </header>
        <div class="cr-analytics-panel__body">
            <div class="cr-analytics-chart-wrap">
                <canvas id="crRefundChart" height="240"></canvas>
                <p class="cr-analytics-empty" id="crRefundEmpty" hidden><?php echo __t('cr_no_data', 'admin'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="cr-analytics-panel cr-analytics-panel--table">
    <header class="cr-analytics-panel__head">
        <h3><?php echo __t('cr_analytics_cashier_title', 'admin'); ?></h3>
        <span class="cr-analytics-panel__meta" id="crCashierMeta">—</span>
    </header>
    <div class="cr-analytics-panel__body" id="crCashierPerf">
        <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

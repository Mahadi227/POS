<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'inventory';
$pageTitle = __t('nav_inventory', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-inventory.js'];
$pageI18n = acc_i18n([
    'inv_subtitle', 'inv_stat_total', 'inv_stat_store', 'inv_stat_warehouse', 'inv_stat_losses',
    'inv_insight_skus', 'inv_insight_units', 'inv_insight_low_stock', 'inv_insight_warehouse_share',
    'inv_insight_loss_ratio', 'inv_chart_composition', 'inv_chart_categories', 'inv_chart_top_products',
    'inv_chart_loss_trend', 'inv_section_top', 'inv_section_low_stock', 'inv_col_sku', 'inv_col_product',
    'inv_col_qty', 'inv_col_cost', 'inv_col_value', 'inv_col_min', 'inv_damaged', 'inv_expired',
    'dash_period_week', 'dash_period_month', 'rpt_period_quarter', 'dash_period_year',
    'kpi_inventory', 'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading',
    'no_data', 'load_error', 'start_date', 'end_date',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-inv-hero" aria-labelledby="accInvHeroTitle">
    <div class="acc-inv-hero__intro">
        <h2 class="acc-inv-hero__title" id="accInvHeroTitle"><?php echo __t('inv_subtitle', 'accounting'); ?></h2>
        <p class="acc-inv-hero__period" id="accInvPeriodLabel" aria-live="polite">—</p>
        <p class="acc-inv-hero__scope" id="accInvStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-inv-hero__stats" id="accInvHeroStats" role="group">
        <div class="acc-inv-stat acc-inv-stat--primary">
            <span class="acc-inv-stat__label"><?php echo __t('inv_stat_total', 'accounting'); ?></span>
            <strong class="acc-inv-stat__value is-loading" id="accInvStatTotal">—</strong>
        </div>
        <div class="acc-inv-stat acc-inv-stat--success">
            <span class="acc-inv-stat__label"><?php echo __t('inv_stat_store', 'accounting'); ?></span>
            <strong class="acc-inv-stat__value is-loading" id="accInvStatStore">—</strong>
        </div>
        <div class="acc-inv-stat">
            <span class="acc-inv-stat__label"><?php echo __t('inv_stat_warehouse', 'accounting'); ?></span>
            <strong class="acc-inv-stat__value is-loading" id="accInvStatWarehouse">—</strong>
        </div>
        <div class="acc-inv-stat acc-inv-stat--warn">
            <span class="acc-inv-stat__label"><?php echo __t('inv_stat_losses', 'accounting'); ?></span>
            <strong class="acc-inv-stat__value is-loading" id="accInvStatLosses">—</strong>
        </div>
    </div>
</section>

<div class="acc-inv-insights" id="accInvInsights">
    <article class="acc-inv-insight">
        <span class="acc-inv-insight__label"><?php echo __t('inv_insight_skus', 'accounting'); ?></span>
        <strong class="acc-inv-insight__value is-loading" id="accInvSkus">—</strong>
    </article>
    <article class="acc-inv-insight">
        <span class="acc-inv-insight__label"><?php echo __t('inv_insight_units', 'accounting'); ?></span>
        <strong class="acc-inv-insight__value is-loading" id="accInvUnits">—</strong>
    </article>
    <article class="acc-inv-insight">
        <span class="acc-inv-insight__label"><?php echo __t('inv_insight_low_stock', 'accounting'); ?></span>
        <strong class="acc-inv-insight__value is-loading" id="accInvLowStock">—</strong>
    </article>
    <article class="acc-inv-insight">
        <span class="acc-inv-insight__label"><?php echo __t('inv_insight_warehouse_share', 'accounting'); ?></span>
        <strong class="acc-inv-insight__value is-loading" id="accInvWarehouseShare">—</strong>
    </article>
</div>

<div class="acc-inv-toolbar">
    <div class="acc-inv-toolbar__top">
        <div class="acc-inv-period" id="accInvPeriod" role="tablist" aria-label="<?php echo htmlspecialchars(__t('inv_chart_loss_trend', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-inv-chip" data-period="week" role="tab"><?php echo __t('dash_period_week', 'accounting'); ?></button>
            <button type="button" class="acc-inv-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('dash_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-inv-chip" data-period="quarter" role="tab"><?php echo __t('rpt_period_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-inv-chip" data-period="year" role="tab"><?php echo __t('dash_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-inv-toolbar__dates">
            <label class="acc-inv-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accInvDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-inv-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accInvDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-inv-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accInvExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-inv-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accInvPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-inv-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accInvRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-inv-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-inv-charts" id="accInvCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('inv_chart_composition', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accInvComposition"></canvas>
                <p class="acc-chart-empty" id="accInvCompositionEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accInvCompositionLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('inv_chart_categories', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accInvCategories"></canvas>
                <p class="acc-chart-empty" id="accInvCategoriesEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accInvCategoriesLegend"></ul>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('inv_chart_top_products', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accInvTopProducts"></canvas>
                <p class="acc-chart-empty" id="accInvTopProductsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('inv_chart_loss_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accInvLossTrend"></canvas>
                <p class="acc-chart-empty" id="accInvLossTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-inv-panel" id="accInvPrintArea">
    <div class="acc-inv-panel__grid" id="accInvDetailRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

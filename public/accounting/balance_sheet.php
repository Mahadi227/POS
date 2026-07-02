<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'balance_sheet';
$pageTitle = __t('nav_balance_sheet', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$extraScripts = ['accounting-common.js', 'accounting-balance-sheet.js'];
$pageI18n = acc_i18n([
    'bs_subtitle', 'bs_stat_assets', 'bs_stat_liabilities', 'bs_stat_equity', 'bs_stat_net_worth',
    'bs_insight_de_ratio', 'bs_insight_equity_ratio', 'bs_insight_liability_ratio', 'bs_insight_accounts',
    'bs_insight_balanced', 'bs_balanced_yes', 'bs_balanced_no', 'bs_chart_composition', 'bs_chart_top_accounts',
    'bs_preset_today', 'bs_preset_month', 'bs_preset_quarter', 'bs_preset_year',
    'rpt_as_of_label', 'rpt_section_assets', 'rpt_section_liabilities', 'rpt_section_equity',
    'rpt_total', 'rpt_account', 'rpt_code', 'rpt_col_balance', 'report_assets', 'report_liabilities',
    'report_equity', 'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading',
    'no_data', 'load_error', 'end_date',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-bs-hero" aria-labelledby="accBsHeroTitle">
    <div class="acc-bs-hero__intro">
        <h2 class="acc-bs-hero__title" id="accBsHeroTitle"><?php echo __t('bs_subtitle', 'accounting'); ?></h2>
        <p class="acc-bs-hero__period" id="accBsPeriodLabel" aria-live="polite">—</p>
        <p class="acc-bs-hero__scope" id="accBsStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-bs-hero__stats" id="accBsHeroStats" role="group">
        <div class="acc-bs-stat acc-bs-stat--primary">
            <span class="acc-bs-stat__label"><?php echo __t('bs_stat_assets', 'accounting'); ?></span>
            <strong class="acc-bs-stat__value is-loading" id="accBsStatAssets">—</strong>
        </div>
        <div class="acc-bs-stat acc-bs-stat--warn">
            <span class="acc-bs-stat__label"><?php echo __t('bs_stat_liabilities', 'accounting'); ?></span>
            <strong class="acc-bs-stat__value is-loading" id="accBsStatLiabilities">—</strong>
        </div>
        <div class="acc-bs-stat acc-bs-stat--success">
            <span class="acc-bs-stat__label"><?php echo __t('bs_stat_equity', 'accounting'); ?></span>
            <strong class="acc-bs-stat__value is-loading" id="accBsStatEquity">—</strong>
        </div>
        <div class="acc-bs-stat">
            <span class="acc-bs-stat__label"><?php echo __t('bs_stat_net_worth', 'accounting'); ?></span>
            <strong class="acc-bs-stat__value is-loading" id="accBsStatNetWorth">—</strong>
        </div>
    </div>
</section>

<div class="acc-bs-insights" id="accBsInsights">
    <article class="acc-bs-insight">
        <span class="acc-bs-insight__label"><?php echo __t('bs_insight_de_ratio', 'accounting'); ?></span>
        <strong class="acc-bs-insight__value is-loading" id="accBsDeRatio">—</strong>
    </article>
    <article class="acc-bs-insight">
        <span class="acc-bs-insight__label"><?php echo __t('bs_insight_equity_ratio', 'accounting'); ?></span>
        <strong class="acc-bs-insight__value is-loading" id="accBsEquityRatio">—</strong>
    </article>
    <article class="acc-bs-insight">
        <span class="acc-bs-insight__label"><?php echo __t('bs_insight_liability_ratio', 'accounting'); ?></span>
        <strong class="acc-bs-insight__value is-loading" id="accBsLiabilityRatio">—</strong>
    </article>
    <article class="acc-bs-insight">
        <span class="acc-bs-insight__label"><?php echo __t('bs_insight_balanced', 'accounting'); ?></span>
        <strong class="acc-bs-insight__value is-loading" id="accBsBalanced">—</strong>
    </article>
</div>

<div class="acc-bs-toolbar">
    <div class="acc-bs-toolbar__top">
        <div class="acc-bs-period" id="accBsPeriod" role="tablist">
            <button type="button" class="acc-bs-chip is-active" data-preset="today" role="tab" aria-selected="true"><?php echo __t('bs_preset_today', 'accounting'); ?></button>
            <button type="button" class="acc-bs-chip" data-preset="month" role="tab"><?php echo __t('bs_preset_month', 'accounting'); ?></button>
            <button type="button" class="acc-bs-chip" data-preset="quarter" role="tab"><?php echo __t('bs_preset_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-bs-chip" data-preset="year" role="tab"><?php echo __t('bs_preset_year', 'accounting'); ?></button>
        </div>
        <label class="acc-bs-date">
            <span class="material-icons-round">calendar_today</span>
            <span class="acc-bs-date__label"><?php echo __t('rpt_as_of_label', 'accounting'); ?></span>
            <input type="date" id="accBsAsOf" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
        <div class="acc-bs-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accBsExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-bs-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accBsPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-bs-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accBsRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-bs-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-bs-charts" id="accBsCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('bs_chart_composition', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accBsComposition"></canvas>
                <p class="acc-chart-empty" id="accBsCompositionEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accBsCompositionLegend"></ul>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('bs_chart_top_accounts', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accBsTopAccounts"></canvas>
                <p class="acc-chart-empty" id="accBsTopAccountsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-bs-panel" id="accBsPrintArea">
    <div class="acc-bs-panel__grid" id="accBsDetailRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

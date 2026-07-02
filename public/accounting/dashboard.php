<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'dashboard';
$pageTitle = __t('nav_dashboard', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-dashboard.js'];
$pageI18n = acc_i18n([
    'dash_subtitle', 'dash_period_week', 'dash_period_month', 'dash_period_year', 'dash_period_custom',
    'dash_hero_treasury', 'dash_section_pl', 'dash_section_treasury', 'dash_section_sales',
    'dash_pending_alert', 'dash_treasury_mix', 'dash_branch_meta', 'dash_all_stores',
    'kpi_revenue', 'kpi_expenses', 'kpi_gross_profit', 'kpi_net_profit', 'kpi_treasury_total',
    'kpi_cash', 'kpi_bank', 'kpi_mobile', 'kpi_receivable', 'kpi_payable', 'kpi_inventory',
    'kpi_daily_sales', 'kpi_monthly_sales', 'kpi_outstanding', 'kpi_pending_expenses',
    'chart_revenue', 'chart_expenses', 'chart_expense_breakdown', 'branch_comparison',
    'quick_new_expense', 'quick_journal', 'quick_reports', 'quick_analytics',
    'cr_export_csv', 'refresh', 'loading', 'no_data', 'branch', 'load_error',
    'start_date', 'end_date',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-dash-hero" aria-labelledby="accDashHeroTitle">
    <div class="acc-dash-hero__intro">
        <h2 class="acc-dash-hero__title" id="accDashHeroTitle"><?php echo __t('dash_subtitle', 'accounting'); ?></h2>
        <p class="acc-dash-hero__period" id="accDashPeriodLabel" aria-live="polite">—</p>
        <p class="acc-dash-hero__scope" id="accDashStoreScope" aria-live="polite"></p>
    </div>
    <div class="acc-kpi-grid acc-kpi-grid--hero" id="accDashHeroStats" role="group" aria-label="<?php echo htmlspecialchars(__t('nav_dashboard', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <article class="acc-kpi acc-kpi--primary is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_revenue', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="accHeroRevenue">—</strong>
            <span class="acc-kpi__meta" id="accHeroCurrency"></span>
        </article>
        <article class="acc-kpi acc-kpi--warn is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_expenses', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="accHeroExpenses">—</strong>
        </article>
        <article class="acc-kpi acc-kpi--success is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_net_profit', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="accHeroNet">—</strong>
        </article>
        <article class="acc-kpi acc-kpi--neutral is-loading">
            <span class="acc-kpi__label"><?php echo __t('dash_hero_treasury', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="accHeroTreasury">—</strong>
        </article>
    </div>
    <div class="acc-quick-actions acc-dash-hero__actions">
        <a href="expenses.php" class="acc-quick-btn"><span class="material-icons-round">add</span><?php echo __t('quick_new_expense', 'accounting'); ?></a>
        <a href="journal_entries.php" class="acc-quick-btn"><span class="material-icons-round">edit_note</span><?php echo __t('quick_journal', 'accounting'); ?></a>
        <a href="reports.php" class="acc-quick-btn"><span class="material-icons-round">summarize</span><?php echo __t('quick_reports', 'accounting'); ?></a>
        <a href="analytics.php" class="acc-quick-btn"><span class="material-icons-round">analytics</span><?php echo __t('quick_analytics', 'accounting'); ?></a>
    </div>
</section>

<div class="acc-dash-toolbar">
    <div class="acc-dash-toolbar__top">
        <div class="acc-dash-period" id="accDashPeriod" role="tablist" aria-label="<?php echo htmlspecialchars(__t('col_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-dash-chip" data-period="week" role="tab"><?php echo __t('dash_period_week', 'accounting'); ?></button>
            <button type="button" class="acc-dash-chip is-active" data-period="month" role="tab" aria-selected="true"><?php echo __t('dash_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-dash-chip" data-period="year" role="tab"><?php echo __t('dash_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-dash-toolbar__dates">
            <label class="acc-dash-date">
                <span class="material-icons-round" aria-hidden="true">calendar_today</span>
                <input type="date" id="accDashDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-dash-date">
                <span class="material-icons-round" aria-hidden="true">calendar_today</span>
                <input type="date" id="accDashDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-dash-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accDashExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-dash-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accDashRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-dash-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<a href="expenses.php" class="ad-alert-strip ad-alert-strip--warn acc-dash-alert" id="accDashPendingAlert" hidden>
    <span class="ad-alert-strip__icon" aria-hidden="true">
        <span class="material-icons-round">pending_actions</span>
    </span>
    <span class="ad-alert-strip__body">
        <strong class="ad-alert-strip__title"><?php echo __t('kpi_pending_expenses', 'accounting'); ?></strong>
        <span class="ad-alert-strip__msg" id="accDashPendingText"></span>
    </span>
    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
</a>

<section class="acc-dash-section" aria-labelledby="accDashPlTitle">
    <h3 class="acc-dash-section__title" id="accDashPlTitle"><?php echo __t('dash_section_pl', 'accounting'); ?></h3>
    <div class="acc-kpi-grid acc-kpi-grid--pl" id="accKpiPl">
        <article class="acc-kpi acc-kpi--success is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_gross_profit', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="AccKpiGross">—</strong>
        </article>
        <article class="acc-kpi acc-kpi--success is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_net_profit', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="AccKpiNet">—</strong>
        </article>
        <article class="acc-kpi acc-kpi--primary is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_revenue', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="AccKpiRevenue">—</strong>
        </article>
        <article class="acc-kpi acc-kpi--warn acc-kpi--wide is-loading">
            <span class="acc-kpi__label"><?php echo __t('kpi_expenses', 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="AccKpiExpenses">—</strong>
        </article>
    </div>
</section>

<section class="acc-dash-section" aria-labelledby="accDashTreasuryTitle">
    <h3 class="acc-dash-section__title" id="accDashTreasuryTitle"><?php echo __t('dash_section_treasury', 'accounting'); ?></h3>
    <div class="acc-kpi-grid acc-kpi-grid--compact" id="accKpiTreasury">
        <?php foreach ([
            ['id' => 'AccKpiCash', 'label' => 'kpi_cash', 'mod' => ''],
            ['id' => 'AccKpiBank', 'label' => 'kpi_bank', 'mod' => ''],
            ['id' => 'AccKpiMobile', 'label' => 'kpi_mobile', 'mod' => ''],
            ['id' => 'AccKpiAr', 'label' => 'kpi_receivable', 'mod' => ''],
            ['id' => 'AccKpiAp', 'label' => 'kpi_payable', 'mod' => ''],
            ['id' => 'AccKpiPending', 'label' => 'kpi_pending_expenses', 'mod' => 'warn'],
        ] as $kpi): ?>
        <article class="acc-kpi<?php echo $kpi['mod'] ? ' acc-kpi--' . htmlspecialchars($kpi['mod'], ENT_QUOTES, 'UTF-8') : ''; ?> is-loading">
            <span class="acc-kpi__label"><?php echo __t($kpi['label'], 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="<?php echo $kpi['id']; ?>">—</strong>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<section class="acc-dash-section" aria-labelledby="accDashSalesTitle">
    <h3 class="acc-dash-section__title" id="accDashSalesTitle"><?php echo __t('dash_section_sales', 'accounting'); ?></h3>
    <div class="acc-kpi-grid acc-kpi-grid--compact" id="accKpiSales">
        <?php foreach ([
            ['id' => 'AccKpiDaily', 'label' => 'kpi_daily_sales', 'mod' => 'primary'],
            ['id' => 'AccKpiMonthly', 'label' => 'kpi_monthly_sales', 'mod' => 'primary'],
            ['id' => 'AccKpiInventory', 'label' => 'kpi_inventory', 'mod' => ''],
            ['id' => 'AccKpiOutstanding', 'label' => 'kpi_outstanding', 'mod' => ''],
        ] as $kpi): ?>
        <article class="acc-kpi<?php echo $kpi['mod'] ? ' acc-kpi--' . htmlspecialchars($kpi['mod'], ENT_QUOTES, 'UTF-8') : ''; ?> is-loading">
            <span class="acc-kpi__label"><?php echo __t($kpi['label'], 'accounting'); ?></span>
            <strong class="acc-kpi__value" id="<?php echo $kpi['id']; ?>">—</strong>
        </article>
        <?php endforeach; ?>
    </div>
</section>

<div class="acc-charts acc-dash-charts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_revenue', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accRevenueChart"></canvas>
                <p class="acc-chart-empty" id="accRevenueEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_expenses', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accExpenseChart"></canvas>
                <p class="acc-chart-empty" id="accExpenseEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('chart_expense_breakdown', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accExpenseBreakdownChart"></canvas>
                <p class="acc-chart-empty" id="accBreakdownEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accBreakdownLegend" aria-hidden="true"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('dash_treasury_mix', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accTreasuryChart"></canvas>
                <p class="acc-chart-empty" id="accTreasuryEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accTreasuryLegend" aria-hidden="true"></ul>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head">
            <h3><?php echo __t('branch_comparison', 'accounting'); ?></h3>
            <span class="acc-panel__meta" id="accBranchMeta">—</span>
        </header>
        <div class="acc-panel__body" id="accBranchCompare">
            <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

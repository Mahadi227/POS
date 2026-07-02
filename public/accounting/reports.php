<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'reports';
$pageTitle = __t('nav_reports', 'accounting');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-reports.js'];
$pageI18n = acc_i18n([
    'rpt_subtitle', 'rpt_stat_net_profit', 'rpt_stat_assets', 'rpt_stat_cashflow', 'rpt_stat_treasury',
    'rpt_tab_pl', 'rpt_tab_balance', 'rpt_tab_cashflow', 'rpt_period_month', 'rpt_period_quarter', 'rpt_period_year',
    'rpt_as_of_label', 'rpt_period_label', 'rpt_table_summary', 'rpt_section_assets', 'rpt_section_liabilities',
    'rpt_section_equity', 'rpt_total', 'rpt_account', 'rpt_code', 'rpt_col_balance', 'rpt_treasury_balances',
    'rpt_cash_in_section', 'rpt_cash_out_section', 'rpt_sales_in', 'rpt_ar_collected', 'rpt_expenses_out', 'rpt_ap_paid',
    'kpi_revenue', 'kpi_expenses', 'kpi_gross_profit', 'kpi_net_profit', 'report_assets', 'report_liabilities',
    'report_equity', 'cash_in', 'cash_out', 'net_cash_flow', 'kpi_cash', 'kpi_bank', 'kpi_mobile', 'balance_sheet',
    'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data', 'cr_no_data', 'load_error', 'error',
    'start_date', 'end_date', 'last_updated',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-rpt-hero" aria-labelledby="accRptHeroTitle">
    <div class="acc-rpt-hero__intro">
        <h2 class="acc-rpt-hero__title" id="accRptHeroTitle"><?php echo __t('rpt_subtitle', 'accounting'); ?></h2>
        <p class="acc-rpt-hero__period" id="accRptPeriodLabel" aria-live="polite">—</p>
    </div>
    <div class="acc-rpt-hero__stats" id="accRptStats" role="group">
        <button type="button" class="acc-rpt-stat acc-rpt-stat--success acc-rpt-stat--click" data-rpt-tab="profit-loss">
            <span class="acc-rpt-stat__label"><?php echo __t('rpt_stat_net_profit', 'accounting'); ?></span>
            <strong class="acc-rpt-stat__value is-loading" id="accRptStatNet">—</strong>
        </button>
        <button type="button" class="acc-rpt-stat acc-rpt-stat--primary acc-rpt-stat--click" data-rpt-tab="balance-sheet">
            <span class="acc-rpt-stat__label"><?php echo __t('rpt_stat_assets', 'accounting'); ?></span>
            <strong class="acc-rpt-stat__value is-loading" id="accRptStatAssets">—</strong>
        </button>
        <button type="button" class="acc-rpt-stat acc-rpt-stat--click" data-rpt-tab="cashflow">
            <span class="acc-rpt-stat__label"><?php echo __t('rpt_stat_cashflow', 'accounting'); ?></span>
            <strong class="acc-rpt-stat__value is-loading" id="accRptStatCashflow">—</strong>
        </button>
        <div class="acc-rpt-stat">
            <span class="acc-rpt-stat__label"><?php echo __t('rpt_stat_treasury', 'accounting'); ?></span>
            <strong class="acc-rpt-stat__value is-loading" id="accRptStatTreasury">—</strong>
        </div>
    </div>
</section>

<div class="acc-rpt-toolbar">
    <div class="acc-rpt-toolbar__top">
        <div class="acc-rpt-tabs" id="accRptTabs" role="tablist" aria-label="<?php echo htmlspecialchars(__t('nav_reports', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-rpt-tab is-active" data-report="profit-loss" role="tab" aria-selected="true">
                <span class="material-icons-round">assessment</span>
                <?php echo __t('rpt_tab_pl', 'accounting'); ?>
            </button>
            <button type="button" class="acc-rpt-tab" data-report="balance-sheet" role="tab">
                <span class="material-icons-round">balance</span>
                <?php echo __t('rpt_tab_balance', 'accounting'); ?>
            </button>
            <button type="button" class="acc-rpt-tab" data-report="cashflow" role="tab">
                <span class="material-icons-round">waterfall_chart</span>
                <?php echo __t('rpt_tab_cashflow', 'accounting'); ?>
            </button>
        </div>
        <div class="acc-rpt-period" id="accRptPeriod" role="tablist">
            <button type="button" class="acc-rpt-chip" data-period="month"><?php echo __t('rpt_period_month', 'accounting'); ?></button>
            <button type="button" class="acc-rpt-chip" data-period="quarter"><?php echo __t('rpt_period_quarter', 'accounting'); ?></button>
            <button type="button" class="acc-rpt-chip is-active" data-period="year"><?php echo __t('rpt_period_year', 'accounting'); ?></button>
        </div>
        <div class="acc-rpt-toolbar__dates" id="accRptDateRange">
            <label class="acc-rpt-date" id="accRptDateFromWrap">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accRptDateFrom" value="<?php echo date('Y-01-01'); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-rpt-date" id="accRptDateToWrap">
                <span class="material-icons-round">calendar_today</span>
                <span class="acc-rpt-date__label" id="accRptDateToLabel"><?php echo __t('end_date', 'accounting'); ?></span>
                <input type="date" id="accRptDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-rpt-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accRptExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-rpt-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accRptPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-rpt-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accRptRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-rpt-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
</div>

<div class="acc-rpt-panel" id="accRptPrintArea">
    <header class="acc-rpt-panel__head">
        <div>
            <h3 class="acc-rpt-panel__title" id="accRptPanelTitle"><?php echo __t('rpt_tab_pl', 'accounting'); ?></h3>
            <p class="acc-rpt-panel__meta" id="accRptMeta"><?php echo __t('loading', 'accounting'); ?></p>
        </div>
        <span class="acc-rpt-store" id="accRptStoreScope"></span>
    </header>
    <div class="acc-rpt-panel__body" id="accRptRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

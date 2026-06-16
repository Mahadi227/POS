<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'analytics';
$crPageData = 'analytics';
$pageTitle = __t('cr_analytics_title', 'admin');
$loadChart = true;
$extraScripts = ['cash-registers-common.js', 'cash-registers-analytics.js'];
$pageI18n = cr_i18n(['cr_stat_cash', 'cr_stat_cash_balance', 'cr_no_data']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar">
    <select id="crAnalyticsPeriod"><option value="week">7 days</option><option value="month" selected>30 days</option><option value="year">12 months</option></select>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3>Daily collection</h3><canvas id="crDailyChart" height="220"></canvas></section>
    <section class="cr-panel"><h3>Branch comparison</h3><canvas id="crBranchChart" height="220"></canvas></section>
</div>
<section class="cr-panel"><h3>Cashier performance</h3><div id="crCashierPerf"></div></section>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

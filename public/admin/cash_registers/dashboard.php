<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'dashboard';
$pageTitle = __t('cr_dashboard_title', 'admin');
$loadChart = true;
$extraScripts = ['cash-registers-common.js', 'cash-registers-dashboard.js'];
$pageI18n = cr_i18n([
    'cr_dashboard_subtitle', 'cr_stat_total_registers', 'cr_stat_open', 'cr_stat_closed',
    'cr_stat_cash_balance', 'cr_stat_expected', 'cr_stat_difference', 'cr_stat_sales_today',
    'cr_stat_cash', 'cr_stat_mobile', 'cr_stat_card', 'cr_stat_pending_recon', 'cr_stat_active_cashiers',
    'cr_chart_collection', 'cr_chart_performance', 'cr_register_status', 'cr_recent_activity',
    'cr_no_registers', 'cr_no_data', 'cr_session_open', 'cr_session_closed',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<p class="cr-intro"><?php echo __t('cr_dashboard_subtitle', 'admin'); ?></p>
<div class="cr-kpi-grid">
    <?php
    $kpis = [
        ['id' => 'crTotalRegisters', 'label' => 'cr_stat_total_registers', 'icon' => 'storefront'],
        ['id' => 'crOpenRegisters', 'label' => 'cr_stat_open', 'icon' => 'lock_open'],
        ['id' => 'crClosedRegisters', 'label' => 'cr_stat_closed', 'icon' => 'lock'],
        ['id' => 'crCashBalance', 'label' => 'cr_stat_cash_balance', 'icon' => 'account_balance_wallet'],
        ['id' => 'crExpectedCash', 'label' => 'cr_stat_expected', 'icon' => 'calculate'],
        ['id' => 'crCashDifference', 'label' => 'cr_stat_difference', 'icon' => 'difference'],
        ['id' => 'crSalesToday', 'label' => 'cr_stat_sales_today', 'icon' => 'payments'],
        ['id' => 'crCashCollected', 'label' => 'cr_stat_cash', 'icon' => 'payments'],
        ['id' => 'crMobileCollected', 'label' => 'cr_stat_mobile', 'icon' => 'smartphone'],
        ['id' => 'crCardCollected', 'label' => 'cr_stat_card', 'icon' => 'credit_card'],
        ['id' => 'crPendingRecon', 'label' => 'cr_stat_pending_recon', 'icon' => 'pending_actions'],
        ['id' => 'crActiveCashiers', 'label' => 'cr_stat_active_cashiers', 'icon' => 'badge'],
    ];
    foreach ($kpis as $kpi): ?>
    <div class="card stat-card cr-kpi-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round"><?php echo $kpi['icon']; ?></span></div>
        <div class="card-info"><h3><?php echo __t($kpi['label'], 'admin'); ?></h3><h2 id="<?php echo $kpi['id']; ?>">—</h2></div>
    </div>
    <?php endforeach; ?>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">show_chart</span><?php echo __t('cr_chart_collection', 'admin'); ?></h3><canvas id="crCollectionChart" height="200"></canvas></section>
    <section class="cr-panel"><h3><span class="material-icons-round">bar_chart</span><?php echo __t('cr_chart_performance', 'admin'); ?></h3><canvas id="crPerformanceChart" height="200"></canvas></section>
</div>
<div class="cr-grid-2">
    <section class="cr-panel"><h3><span class="material-icons-round">sensors</span><?php echo __t('cr_register_status', 'admin'); ?></h3><div id="crStatusList"></div></section>
    <section class="cr-panel"><h3><span class="material-icons-round">history</span><?php echo __t('cr_recent_activity', 'admin'); ?></h3><div id="crActivityList"></div></section>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

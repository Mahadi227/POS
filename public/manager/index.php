<?php

$activePage = 'dashboard';
$bodyClass = 'mgr-page mgr-pro-page ad-page';
$pageCss = ['manager-dashboard.css'];
$pageScripts = ['manager-dashboard.js'];

require __DIR__ . '/includes/auth-guard.php';

$pageI18n = [];
foreach ([
    'kpi_transactions', 'loading', 'no_pending_approvals', 'no_active_registers',
    'register_online', 'register_offline', 'register_idle', 'last_activity',
    'sales_today_short', 'live_registers_sub', 'load_error', 'last_updated',
] as $key) {
    $pageI18n[$key] = __t($key, 'manager');
}

require __DIR__ . '/includes/layout-start.php';

?>



<div class="ad-error-banner" id="mgrError">

    <span class="material-icons-round">error_outline</span>

    <span class="ad-error-text"></span>

</div>



<nav class="mgr-quick-nav" aria-label="<?php echo htmlspecialchars(__t('menu', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">

    <a href="<?php echo $mgrPrefix; ?>index.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">

        <span class="material-icons-round">dashboard</span>

        <span><?php echo __t('nav_dashboard', 'manager'); ?></span>

    </a>

    <a href="<?php echo $mgrPrefix; ?>supervision/live-registers.php" class="mgr-quick-nav__item">

        <span class="material-icons-round">sensors</span>

        <span><?php echo __t('nav_live', 'manager'); ?></span>

    </a>

    <a href="<?php echo $mgrPrefix; ?>approvals/index.php" class="mgr-quick-nav__item">

        <span class="material-icons-round">pending_actions</span>

        <span><?php echo __t('nav_approvals', 'manager'); ?></span>

    </a>

    <a href="<?php echo $mgrPrefix; ?>operations/inventory-alerts.php" class="mgr-quick-nav__item">

        <span class="material-icons-round">inventory</span>

        <span><?php echo __t('nav_inventory', 'manager'); ?></span>

    </a>

    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-quick-nav__item">

        <span class="material-icons-round">summarize</span>

        <span><?php echo __t('nav_reports', 'manager'); ?></span>

    </a>

</nav>



<div class="mgr-kpi-grid ad-stat-cards">

    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="kpi-sales">

        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>

        <div class="card-info">

            <h3><?php echo __t('kpi_sales_today', 'manager'); ?></h3>

            <h2 id="kpi-sales-val">—</h2>

            <p class="trend ad-trend--neutral" id="kpi-sales-sub">—</p>

        </div>

    </div>

    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="kpi-pending">

        <div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div>

        <div class="card-info">

            <h3><?php echo __t('kpi_pending_approvals', 'manager'); ?></h3>

            <h2 id="kpi-pending-val">—</h2>

            <p class="trend negative"><?php echo __t('kpi_action_required', 'manager'); ?></p>

        </div>

    </div>

    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="kpi-live">

        <div class="card-icon success"><span class="material-icons-round">sensors</span></div>

        <div class="card-info">

            <h3><?php echo __t('kpi_live_registers', 'manager'); ?></h3>

            <h2 id="kpi-live-val">—</h2>

            <p class="trend ad-trend--neutral" id="kpi-live-sub">—</p>

        </div>

    </div>

    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="kpi-alerts">

        <div class="card-icon mgr-icon-danger">

            <span class="material-icons-round">inventory</span>

        </div>

        <div class="card-info">

            <h3><?php echo __t('kpi_stock_alerts', 'manager'); ?></h3>

            <h2 id="kpi-alerts-val">—</h2>

            <p class="trend ad-trend--neutral"><?php echo __t('kpi_low_out', 'manager'); ?></p>

        </div>

    </div>

</div>



<div class="mgr-panels">

    <section class="card mgr-panel">

        <div class="mgr-panel-head">

            <h2><span class="material-icons-round">pending_actions</span> <?php echo __t('panel_approvals', 'manager'); ?></h2>

            <a href="<?php echo $mgrPrefix; ?>approvals/index.php" class="mgr-link"><?php echo __t('view_all', 'manager'); ?></a>

        </div>

        <div id="dashboardApprovals" class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>

    </section>



    <section class="card mgr-panel">

        <div class="mgr-panel-head">

            <h2><span class="material-icons-round">sensors</span> <?php echo __t('panel_live_registers', 'manager'); ?></h2>

            <span class="mgr-count" id="dashboardLiveCount">—</span>

            <a href="<?php echo $mgrPrefix; ?>supervision/live-registers.php" class="mgr-link"><?php echo __t('details', 'manager'); ?></a>

        </div>

        <div id="dashboardLiveRegisters" class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>

    </section>

</div>



<?php require __DIR__ . '/includes/layout-end.php'; ?>


<?php
$activePage = 'daily-summary';
$bodyClass = 'mgr-page mgr-pro-page mgr-ds-page ad-page';
$pageCss = ['reports.css'];
$pageScripts = ['reports-daily-summary.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('daily_summary_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'daily_summary_intro', 'daily_summary_refresh_hint', 'daily_summary_panel_payments',
    'daily_summary_panel_hourly', 'daily_summary_panel_cashiers', 'daily_summary_panel_shifts',
    'subnav_daily_summary', 'subnav_audit_trail',
    'stat_ds_revenue', 'stat_ds_transactions', 'stat_ds_avg_ticket', 'stat_ds_returns',
    'stat_ds_shifts_closed', 'stat_ds_pending_approvals', 'stat_ds_stock_alerts', 'stat_ds_cash_variance',
    'date_today', 'date_yesterday', 'date_apply', 'vs_previous_day', 'no_payments_day', 'no_hourly_sales',
    'no_daily_shifts', 'ds_returns_count', 'ds_tx_short',
    'pay_cash', 'pay_card', 'pay_mobile', 'pay_split',
    'col_rank', 'cashier_label', 'col_transactions', 'col_revenue', 'col_avg_ticket', 'col_returns',
    'col_status', 'col_opened', 'col_sales', 'col_variance', 'col_recon_status',
    'shift_status_open', 'shift_status_closed',
    'recon_status_open', 'recon_status_balanced', 'recon_status_short', 'recon_status_over',
    'no_team_data',
] as $key) {
    $pageI18n[$key] = __t($key, 'manager');
}

$managerConfig['pagePrefix'] = $mgrPrefix;

require __DIR__ . '/../includes/layout-start.php';

$today = date('Y-m-d');
$yesterday = date('Y-m-d', strtotime('-1 day'));
?>

<div class="ad-error-banner" id="mgrError">
    <span class="material-icons-round">error_outline</span>
    <span class="ad-error-text"></span>
</div>

<nav class="mgr-quick-nav" aria-label="<?php echo htmlspecialchars(__t('menu', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <a href="<?php echo $mgrPrefix; ?>index.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">dashboard</span>
        <span><?php echo __t('nav_dashboard', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>supervision/live-registers.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">sensors</span>
        <span><?php echo __t('nav_live', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>supervision/shifts.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">schedule</span>
        <span><?php echo __t('nav_shifts', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>supervision/team-performance.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">groups</span>
        <span><?php echo __t('nav_team', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>approvals/index.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">pending_actions</span>
        <span><?php echo __t('nav_approvals', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>operations/inventory-alerts.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">inventory</span>
        <span><?php echo __t('nav_inventory', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('nav_reports', 'manager'); ?></span>
    </a>
</nav>

<nav class="mgr-reports-subnav" aria-label="<?php echo htmlspecialchars(__t('nav_reports', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-reports-subnav__item mgr-reports-subnav__item--accent">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('subnav_daily_summary', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>reports/audit-trail.php" class="mgr-reports-subnav__item">
        <span class="material-icons-round">history</span>
        <span><?php echo __t('subnav_audit_trail', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-ds-intro">
    <span class="material-icons-round">summarize</span>
    <p><?php echo __t('daily_summary_intro', 'manager'); ?></p>
</div>

<div class="mgr-ds-toolbar">
    <div class="mgr-ds-toolbar__quick mgr-period-filter" id="dsDateQuick" role="tablist">
        <button type="button" class="mgr-period-btn is-active" data-date="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" role="tab">
            <?php echo __t('date_today', 'manager'); ?>
        </button>
        <button type="button" class="mgr-period-btn" data-date="<?php echo htmlspecialchars($yesterday, ENT_QUOTES, 'UTF-8'); ?>" role="tab">
            <?php echo __t('date_yesterday', 'manager'); ?>
        </button>
    </div>
    <div class="mgr-ds-date-field">
        <span class="material-icons-round" aria-hidden="true">event</span>
        <input type="date" id="dsDateInput" max="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('date_apply', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    </div>
    <button type="button" class="mgr-ds-date-apply" id="dsDateApply">
        <span class="material-icons-round">check</span>
        <?php echo __t('date_apply', 'manager'); ?>
    </button>
</div>

<p class="mgr-ds-date-label" id="dsReportDate">—</p>

<div class="mgr-ds-summary ad-stat-cards" id="dsSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_revenue', 'manager'); ?></h3>
            <h2 id="dsRevenue">—</h2>
            <div class="mgr-ds-trend" id="dsRevenueTrend" hidden></div>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">receipt_long</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_transactions', 'manager'); ?></h3>
            <h2 id="dsTxCount">—</h2>
            <div class="mgr-ds-trend" id="dsTxTrend" hidden></div>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">shopping_cart</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_avg_ticket', 'manager'); ?></h3>
            <h2 id="dsAvgTicket">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">assignment_return</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_returns', 'manager'); ?></h3>
            <h2 id="dsReturnsAmount">—</h2>
            <p class="mgr-ds-sub" id="dsReturnsCount">—</p>
        </div>
    </div>
</div>

<div class="mgr-ds-secondary ad-stat-cards" id="dsSecondary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">schedule</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_shifts_closed', 'manager'); ?></h3>
            <h2 id="dsShiftsClosed">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_pending_approvals', 'manager'); ?></h3>
            <h2 id="dsPendingApprovals">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">inventory</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_stock_alerts', 'manager'); ?></h3>
            <h2 id="dsStockAlerts">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">account_balance_wallet</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_ds_cash_variance', 'manager'); ?></h3>
            <h2 id="dsCashVariance">—</h2>
        </div>
    </div>
</div>

<p class="mgr-ds-hint"><?php echo __t('daily_summary_refresh_hint', 'manager'); ?></p>

<div class="mgr-ds-grid">
    <section class="card mgr-panel mgr-ds-panel">
        <div class="mgr-panel-head">
            <h2><span class="material-icons-round">pie_chart</span> <?php echo __t('daily_summary_panel_payments', 'manager'); ?></h2>
        </div>
        <div class="mgr-workspace" id="dsPaymentBars">
            <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
        </div>
    </section>

    <section class="card mgr-panel mgr-ds-panel">
        <div class="mgr-panel-head">
            <h2><span class="material-icons-round">schedule</span> <?php echo __t('daily_summary_panel_hourly', 'manager'); ?></h2>
        </div>
        <div class="mgr-workspace" id="dsHourlyBars">
            <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
        </div>
    </section>
</div>

<section class="card mgr-panel mgr-ds-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">groups</span> <?php echo __t('daily_summary_panel_cashiers', 'manager'); ?></h2>
        <span class="mgr-count" id="dsCashiersCount">—</span>
    </div>
    <div class="mgr-workspace" id="dsCashiersRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<section class="card mgr-panel mgr-ds-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">account_balance_wallet</span> <?php echo __t('daily_summary_panel_shifts', 'manager'); ?></h2>
        <span class="mgr-count" id="dsShiftsCount">—</span>
    </div>
    <div class="mgr-workspace" id="dsShiftsRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

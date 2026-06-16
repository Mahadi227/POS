<?php
$activePage = 'shifts';
$bodyClass = 'mgr-page mgr-pro-page mgr-shifts-page ad-page';
$pageCss = ['supervision.css'];
$pageScripts = ['supervision-shifts.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('nav_shifts', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_approvals', 'nav_inventory', 'nav_reports', 'menu', 'cashier_label',
    'shifts_intro', 'shifts_panel_title', 'shifts_refresh_hint',
    'stat_shifts_open', 'stat_shifts_sales', 'stat_shifts_transactions', 'stat_shifts_float',
    'col_opened', 'col_opening_float', 'col_sales', 'col_transactions', 'col_status',
    'shift_status_open', 'shift_status_closed', 'no_open_shifts',
] as $key) {
    $pageI18n[$key] = __t($key, 'manager');
}

require __DIR__ . '/../includes/layout-start.php';
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
    <a href="<?php echo $mgrPrefix; ?>supervision/shifts.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
        <span class="material-icons-round">schedule</span>
        <span><?php echo __t('nav_shifts', 'manager'); ?></span>
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

<div class="mgr-page-intro mgr-shifts-intro">
    <span class="material-icons-round">schedule</span>
    <p><?php echo __t('shifts_intro', 'manager'); ?></p>
</div>

<div class="mgr-shifts-summary ad-stat-cards" id="shiftsSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="shifts-stat-open">
        <div class="card-icon success"><span class="material-icons-round">play_circle</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_shifts_open', 'manager'); ?></h3>
            <h2 id="shiftsCountOpen">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="shifts-stat-sales">
        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_shifts_sales', 'manager'); ?></h3>
            <h2 id="shiftsTotalSales">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="shifts-stat-tx">
        <div class="card-icon warning"><span class="material-icons-round">receipt_long</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_shifts_transactions', 'manager'); ?></h3>
            <h2 id="shiftsTotalTx">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="shifts-stat-float">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">account_balance_wallet</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_shifts_float', 'manager'); ?></h3>
            <h2 id="shiftsTotalFloat">—</h2>
        </div>
    </div>
</div>

<p class="mgr-shifts-hint" id="shiftsRefreshHint"><?php echo __t('shifts_refresh_hint', 'manager'); ?></p>

<section class="card mgr-panel mgr-shifts-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">schedule</span> <?php echo __t('shifts_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="shiftsTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="shiftsRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

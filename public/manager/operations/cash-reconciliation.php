<?php
$activePage = 'cash-reconciliation';
$bodyClass = 'mgr-page mgr-pro-page mgr-cr-page ad-page';
$pageCss = ['operations.css'];
$pageScripts = ['operations-cash-recon.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('cash_recon_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'cash_recon_intro', 'cash_recon_panel_title', 'cash_recon_refresh_hint', 'cash_recon_formula_hint',
    'stat_cr_open', 'stat_cr_expected', 'stat_cr_counted', 'stat_cr_variance',
    'subnav_inventory', 'subnav_cash_recon', 'subnav_sales_review',
    'filter_cr_open', 'filter_cr_closed', 'filter_cr_variance', 'filter_cr_all',
    'cashier_label', 'col_opened', 'col_opening_float', 'col_cash_sales',
    'col_expected_cash', 'col_counted_cash', 'col_variance', 'col_status', 'col_recon_status',
    'shift_status_open', 'shift_status_closed',
    'recon_status_open', 'recon_status_balanced', 'recon_status_short', 'recon_status_over',
    'no_cash_recon',
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
    <a href="<?php echo $mgrPrefix; ?>operations/inventory-alerts.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
        <span class="material-icons-round">inventory</span>
        <span><?php echo __t('nav_inventory', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('nav_reports', 'manager'); ?></span>
    </a>
</nav>

<nav class="mgr-ops-subnav" aria-label="<?php echo htmlspecialchars(__t('nav_inventory', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <a href="<?php echo $mgrPrefix; ?>operations/inventory-alerts.php" class="mgr-ops-subnav__item">
        <span class="material-icons-round">inventory</span>
        <span><?php echo __t('subnav_inventory', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>operations/cash-reconciliation.php" class="mgr-ops-subnav__item mgr-ops-subnav__item--accent">
        <span class="material-icons-round">account_balance_wallet</span>
        <span><?php echo __t('subnav_cash_recon', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>operations/sales-review.php" class="mgr-ops-subnav__item">
        <span class="material-icons-round">fact_check</span>
        <span><?php echo __t('subnav_sales_review', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-cr-intro">
    <span class="material-icons-round">account_balance_wallet</span>
    <p><?php echo __t('cash_recon_intro', 'manager'); ?></p>
</div>

<div class="mgr-cr-summary ad-stat-cards" id="crSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon success"><span class="material-icons-round">schedule</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_cr_open', 'manager'); ?></h3>
            <h2 id="crCountOpen">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">account_balance_wallet</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_cr_expected', 'manager'); ?></h3>
            <h2 id="crTotalExpected">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_cr_counted', 'manager'); ?></h3>
            <h2 id="crTotalCounted">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">compare_arrows</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_cr_variance', 'manager'); ?></h3>
            <h2 id="crTotalVariance">—</h2>
        </div>
    </div>
</div>

<p class="mgr-cr-hint"><?php echo __t('cash_recon_formula_hint', 'manager'); ?></p>
<p class="mgr-cr-hint"><?php echo __t('cash_recon_refresh_hint', 'manager'); ?></p>

<div class="mgr-inv-filters mgr-cr-filters" id="crFilterBar" role="tablist">
    <button type="button" class="mgr-inv-filters__btn is-active" data-filter="open" role="tab">
        <span class="material-icons-round">schedule</span>
        <span><?php echo __t('filter_cr_open', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="closed" role="tab">
        <span class="material-icons-round">lock</span>
        <span><?php echo __t('filter_cr_closed', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="variance" role="tab">
        <span class="material-icons-round">warning_amber</span>
        <span><?php echo __t('filter_cr_variance', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="all" role="tab">
        <span class="material-icons-round">list</span>
        <span><?php echo __t('filter_cr_all', 'manager'); ?></span>
    </button>
</div>

<section class="card mgr-panel mgr-cr-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">account_balance_wallet</span> <?php echo __t('cash_recon_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="crTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="cashReconRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$activePage = 'sales-review';
$bodyClass = 'mgr-page mgr-pro-page mgr-sr-page ad-page';
$pageCss = ['supervision.css', 'operations.css'];
$pageScripts = ['operations-sales-review.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('sales_review_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'sales_review_intro', 'sales_review_panel_title', 'sales_review_refresh_hint',
    'sales_review_threshold_hint',
    'stat_sr_total', 'stat_sr_cancelled', 'stat_sr_discount', 'stat_sr_high',
    'subnav_inventory', 'subnav_cash_recon', 'subnav_sales_review',
    'filter_sr_all', 'filter_sr_cancelled', 'filter_sr_discount', 'filter_sr_high', 'filter_sr_pending',
    'period_today', 'period_week', 'period_month', 'period_all',
    'col_receipt', 'col_date', 'col_total', 'col_discount', 'col_status', 'col_flag', 'col_actions',
    'cashier_label', 'flag_cancelled', 'flag_pending', 'flag_high_discount', 'flag_high_amount',
    'sale_status_completed', 'sale_status_cancelled', 'sale_status_pending',
    'view_sale_btn', 'no_sales_review',
] as $key) {
    $pageI18n[$key] = __t($key, 'manager');
}

$managerConfig['pagePrefix'] = $mgrPrefix;

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
    <a href="<?php echo $mgrPrefix; ?>operations/cash-reconciliation.php" class="mgr-ops-subnav__item">
        <span class="material-icons-round">account_balance_wallet</span>
        <span><?php echo __t('subnav_cash_recon', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>operations/sales-review.php" class="mgr-ops-subnav__item mgr-ops-subnav__item--accent">
        <span class="material-icons-round">fact_check</span>
        <span><?php echo __t('subnav_sales_review', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-sr-intro">
    <span class="material-icons-round">fact_check</span>
    <p><?php echo __t('sales_review_intro', 'manager'); ?></p>
</div>

<div class="mgr-period-filter" id="srPeriodFilter" role="tablist" aria-label="<?php echo htmlspecialchars(__t('sales_review_panel_title', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="mgr-period-btn is-active" data-period="today" role="tab" aria-selected="true">
        <?php echo __t('period_today', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="week" role="tab" aria-selected="false">
        <?php echo __t('period_week', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="month" role="tab" aria-selected="false">
        <?php echo __t('period_month', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="all" role="tab" aria-selected="false">
        <?php echo __t('period_all', 'manager'); ?>
    </button>
</div>

<div class="mgr-sr-summary ad-stat-cards" id="srSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">fact_check</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_sr_total', 'manager'); ?></h3>
            <h2 id="srCountTotal">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">block</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_sr_cancelled', 'manager'); ?></h3>
            <h2 id="srCountCancelled">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">percent</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_sr_discount', 'manager'); ?></h3>
            <h2 id="srCountDiscount">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_sr_high', 'manager'); ?></h3>
            <h2 id="srCountHigh">—</h2>
        </div>
    </div>
</div>

<p class="mgr-sr-hint" id="srThresholdHint">—</p>
<p class="mgr-sr-hint"><?php echo __t('sales_review_refresh_hint', 'manager'); ?></p>

<div class="mgr-inv-filters mgr-sr-filters" id="srFlagBar" role="tablist">
    <button type="button" class="mgr-inv-filters__btn is-active" data-filter="all" role="tab">
        <span class="material-icons-round">list</span>
        <span><?php echo __t('filter_sr_all', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="cancelled" role="tab">
        <span class="material-icons-round">block</span>
        <span><?php echo __t('filter_sr_cancelled', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="discount" role="tab">
        <span class="material-icons-round">percent</span>
        <span><?php echo __t('filter_sr_discount', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="high" role="tab">
        <span class="material-icons-round">payments</span>
        <span><?php echo __t('filter_sr_high', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="pending" role="tab">
        <span class="material-icons-round">pending</span>
        <span><?php echo __t('filter_sr_pending', 'manager'); ?></span>
    </button>
</div>

<section class="card mgr-panel mgr-sr-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">fact_check</span> <?php echo __t('sales_review_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="srTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="salesReviewRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

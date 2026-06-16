<?php
$activePage = 'returns';
$bodyClass = 'mgr-page mgr-pro-page mgr-approvals-page mgr-returns-page ad-page';
$pageCss = ['approvals.css'];
$pageScripts = ['approvals-filter.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('returns_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'returns_intro', 'returns_panel_title', 'returns_refresh_hint',
    'stat_returns_pending', 'stat_returns_total_amount', 'stat_returns_avg_amount',
    'subnav_queue', 'filter_returns', 'filter_discounts', 'filter_voids',
    'type_return', 'approve_btn', 'reject_btn', 'approve_prompt', 'reject_prompt', 'no_reason',
    'approved_ok', 'rejected_ok', 'action_error', 'no_pending_returns',
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
    <a href="<?php echo $mgrPrefix; ?>approvals/index.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
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

<nav class="mgr-approvals-subnav" aria-label="<?php echo htmlspecialchars(__t('nav_approvals', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <a href="<?php echo $mgrPrefix; ?>approvals/index.php" class="mgr-approvals-subnav__item">
        <span class="material-icons-round">pending_actions</span>
        <span><?php echo __t('subnav_queue', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>approvals/returns.php" class="mgr-approvals-subnav__item mgr-approvals-subnav__item--accent">
        <span class="material-icons-round">assignment_return</span>
        <span><?php echo __t('filter_returns', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>approvals/discounts.php" class="mgr-approvals-subnav__item">
        <span class="material-icons-round">percent</span>
        <span><?php echo __t('filter_discounts', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>approvals/voids.php" class="mgr-approvals-subnav__item">
        <span class="material-icons-round">block</span>
        <span><?php echo __t('filter_voids', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-approvals-intro">
    <span class="material-icons-round">assignment_return</span>
    <p><?php echo __t('returns_intro', 'manager'); ?></p>
</div>

<div class="mgr-approvals-summary mgr-returns-summary ad-stat-cards" id="apprFilterSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="ret-stat-count">
        <div class="card-icon warning"><span class="material-icons-round">assignment_return</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_returns_pending', 'manager'); ?></h3>
            <h2 id="apprFilterCount">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="ret-stat-total">
        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_returns_total_amount', 'manager'); ?></h3>
            <h2 id="apprFilterTotal">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="ret-stat-avg">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">receipt_long</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_returns_avg_amount', 'manager'); ?></h3>
            <h2 id="apprFilterAvg">—</h2>
        </div>
    </div>
</div>

<p class="mgr-approvals-hint"><?php echo __t('returns_refresh_hint', 'manager'); ?></p>

<section class="card mgr-panel mgr-approvals-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">assignment_return</span> <?php echo __t('returns_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="apprFilterListCount">—</span>
    </div>
    <div class="mgr-workspace mgr-approvals-list mgr-approvals-filter-root"
         id="approvalsReturnsRoot"
         data-approval-filter="return"
         data-empty-key="no_pending_returns"
         data-type-label-key="type_return"
         data-type-icon="assignment_return">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$activePage = 'approvals';
$bodyClass = 'mgr-page mgr-pro-page mgr-approvals-page ad-page';
$pageCss = ['approvals.css'];
$pageScripts = ['approvals-index.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('approvals_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'approvals_intro', 'approvals_panel_title', 'approvals_refresh_hint',
    'stat_approvals_total', 'stat_approvals_returns', 'stat_approvals_discounts', 'stat_approvals_voids',
    'filter_all', 'filter_returns', 'filter_discounts', 'filter_voids', 'filter_stock',
    'type_return', 'type_discount', 'type_void', 'type_stock_adjustment',
    'approve_btn', 'reject_btn', 'approve_prompt', 'reject_prompt', 'no_reason',
    'approved_ok', 'rejected_ok', 'action_error', 'no_pending_approvals', 'requester_label',
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

<div class="mgr-page-intro mgr-approvals-intro">
    <span class="material-icons-round">pending_actions</span>
    <p><?php echo __t('approvals_intro', 'manager'); ?></p>
</div>

<div class="mgr-approvals-summary ad-stat-cards" id="approvalsSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="appr-stat-total">
        <div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_approvals_total', 'manager'); ?></h3>
            <h2 id="apprCountTotal">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="appr-stat-returns">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">assignment_return</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_approvals_returns', 'manager'); ?></h3>
            <h2 id="apprCountReturns">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="appr-stat-discounts">
        <div class="card-icon primary"><span class="material-icons-round">percent</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_approvals_discounts', 'manager'); ?></h3>
            <h2 id="apprCountDiscounts">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="appr-stat-voids">
        <div class="card-icon danger"><span class="material-icons-round">block</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_approvals_voids', 'manager'); ?></h3>
            <h2 id="apprCountVoids">—</h2>
        </div>
    </div>
</div>

<div class="mgr-type-filter" id="approvalsTypeFilter" role="tablist" aria-label="<?php echo htmlspecialchars(__t('approvals_panel_title', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="mgr-period-btn is-active" data-type="all" role="tab" aria-selected="true">
        <?php echo __t('filter_all', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-type="return" role="tab" aria-selected="false">
        <?php echo __t('filter_returns', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-type="discount" role="tab" aria-selected="false">
        <?php echo __t('filter_discounts', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-type="void" role="tab" aria-selected="false">
        <?php echo __t('filter_voids', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-type="stock_adjustment" role="tab" aria-selected="false">
        <?php echo __t('filter_stock', 'manager'); ?>
    </button>
</div>

<p class="mgr-approvals-hint"><?php echo __t('approvals_refresh_hint', 'manager'); ?></p>

<section class="card mgr-panel mgr-approvals-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">pending_actions</span> <?php echo __t('approvals_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="approvalsListCount">—</span>
    </div>
    <div class="mgr-workspace mgr-approvals-list" id="approvalsQueueRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$activePage = 'audit-trail';
$bodyClass = 'mgr-page mgr-pro-page mgr-at-page ad-page';
$pageCss = ['reports.css'];
$pageScripts = ['reports-audit-trail.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('audit_trail_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'audit_trail_intro', 'audit_trail_refresh_hint', 'audit_trail_panel_title', 'audit_migration_hint',
    'subnav_daily_summary', 'subnav_audit_trail',
    'stat_at_total', 'stat_at_approved', 'stat_at_rejected', 'stat_at_users',
    'filter_at_all', 'filter_at_approved', 'filter_at_rejected',
    'period_today', 'period_week', 'period_month', 'period_all', 'period_custom',
    'date_from', 'date_to', 'date_apply',
    'audit_search_placeholder',
    'col_date', 'audit_col_manager', 'audit_col_action', 'audit_col_entity',
    'audit_col_details', 'audit_col_ip',
    'audit_action_approved', 'audit_action_rejected',
    'audit_entity_approval', 'audit_entity_generic',
    'no_audit_events',
] as $key) {
    $pageI18n[$key] = __t($key, 'manager');
}

$managerConfig['pagePrefix'] = $mgrPrefix;

require __DIR__ . '/../includes/layout-start.php';

$today = date('Y-m-d');
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
    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-reports-subnav__item">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('subnav_daily_summary', 'manager'); ?></span>
    </a>
    <a href="<?php echo $mgrPrefix; ?>reports/audit-trail.php" class="mgr-reports-subnav__item mgr-reports-subnav__item--accent">
        <span class="material-icons-round">history</span>
        <span><?php echo __t('subnav_audit_trail', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-at-intro">
    <span class="material-icons-round">history</span>
    <p><?php echo __t('audit_trail_intro', 'manager'); ?></p>
</div>

<p class="mgr-at-hint mgr-at-hint--warn" id="atMigrationHint" hidden>
    <span class="material-icons-round">info</span>
    <?php echo __t('audit_migration_hint', 'manager'); ?>
</p>

<div class="mgr-period-filter" id="atPeriodFilter" role="tablist" aria-label="<?php echo htmlspecialchars(__t('audit_trail_panel_title', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
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
    <button type="button" class="mgr-period-btn" data-period="custom" role="tab" aria-selected="false">
        <?php echo __t('period_custom', 'manager'); ?>
    </button>
</div>

<div class="mgr-at-date-range" id="atDateRange" hidden>
    <label>
        <span><?php echo __t('date_from', 'manager'); ?></span>
        <input type="date" id="atDateFrom" max="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label>
        <span><?php echo __t('date_to', 'manager'); ?></span>
        <input type="date" id="atDateTo" max="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>" value="<?php echo htmlspecialchars($today, ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <button type="button" class="mgr-ds-date-apply" id="atDateApply">
        <span class="material-icons-round">check</span>
        <?php echo __t('date_apply', 'manager'); ?>
    </button>
</div>

<div class="mgr-at-toolbar">
    <div class="mgr-at-search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="atSearchInput" placeholder="<?php echo htmlspecialchars(__t('audit_search_placeholder', 'manager'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
    </div>
</div>

<div class="mgr-at-summary ad-stat-cards" id="atSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">history</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_at_total', 'manager'); ?></h3>
            <h2 id="atCountTotal">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">check_circle</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_at_approved', 'manager'); ?></h3>
            <h2 id="atCountApproved">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">cancel</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_at_rejected', 'manager'); ?></h3>
            <h2 id="atCountRejected">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">badge</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_at_users', 'manager'); ?></h3>
            <h2 id="atCountUsers">—</h2>
        </div>
    </div>
</div>

<p class="mgr-at-hint"><?php echo __t('audit_trail_refresh_hint', 'manager'); ?></p>

<div class="mgr-inv-filters mgr-at-filters" id="atFilterBar" role="tablist">
    <button type="button" class="mgr-inv-filters__btn is-active" data-filter="all" role="tab">
        <span class="material-icons-round">list</span>
        <span><?php echo __t('filter_at_all', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="approved" role="tab">
        <span class="material-icons-round">check_circle</span>
        <span><?php echo __t('filter_at_approved', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="rejected" role="tab">
        <span class="material-icons-round">cancel</span>
        <span><?php echo __t('filter_at_rejected', 'manager'); ?></span>
    </button>
</div>

<section class="card mgr-panel mgr-at-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">history</span> <?php echo __t('audit_trail_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="atTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="auditTrailRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

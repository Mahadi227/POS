<?php
$activePage = 'team-performance';
$bodyClass = 'mgr-page mgr-pro-page mgr-team-page ad-page';
$pageCss = ['supervision.css'];
$pageScripts = ['supervision-team.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('nav_team', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'nav_dashboard', 'nav_live', 'nav_shifts', 'nav_team',
    'nav_approvals', 'nav_inventory', 'nav_reports', 'menu', 'cashier_label',
    'team_intro', 'team_panel_title', 'team_refresh_hint',
    'stat_team_active', 'stat_team_revenue', 'stat_team_avg_ticket', 'stat_team_returns',
    'col_rank', 'col_revenue', 'col_avg_ticket', 'col_returns', 'col_last_sale',
    'period_today', 'period_week', 'period_month', 'period_all', 'period_custom',
    'date_from', 'date_to', 'date_apply', 'date_range_error', 'no_team_data',
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
    <a href="<?php echo $mgrPrefix; ?>supervision/team-performance.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
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
    <a href="<?php echo $mgrPrefix; ?>reports/daily-summary.php" class="mgr-quick-nav__item">
        <span class="material-icons-round">summarize</span>
        <span><?php echo __t('nav_reports', 'manager'); ?></span>
    </a>
</nav>

<div class="mgr-page-intro mgr-team-intro">
    <span class="material-icons-round">groups</span>
    <p><?php echo __t('team_intro', 'manager'); ?></p>
</div>

<div class="mgr-period-filter" id="teamPeriodFilter" role="tablist" aria-label="<?php echo htmlspecialchars(__t('team_panel_title', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="mgr-period-btn" data-period="all" role="tab" aria-selected="false">
        <?php echo __t('period_all', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn is-active" data-period="today" role="tab" aria-selected="true">
        <?php echo __t('period_today', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="week" role="tab" aria-selected="false">
        <?php echo __t('period_week', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="month" role="tab" aria-selected="false">
        <?php echo __t('period_month', 'manager'); ?>
    </button>
    <button type="button" class="mgr-period-btn" data-period="custom" role="tab" aria-selected="false" id="teamPeriodCustomBtn">
        <?php echo __t('period_custom', 'manager'); ?>
    </button>
</div>

<div class="mgr-date-range" id="teamDateRange" hidden>
    <label class="mgr-date-field">
        <span><?php echo __t('date_from', 'manager'); ?></span>
        <input type="date" id="teamDateFrom" aria-label="<?php echo htmlspecialchars(__t('date_from', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <label class="mgr-date-field">
        <span><?php echo __t('date_to', 'manager'); ?></span>
        <input type="date" id="teamDateTo" aria-label="<?php echo htmlspecialchars(__t('date_to', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    </label>
    <button type="button" class="mgr-date-apply" id="teamDateApply">
        <span class="material-icons-round">check</span>
        <?php echo __t('date_apply', 'manager'); ?>
    </button>
</div>

<div class="mgr-team-summary ad-stat-cards" id="teamSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="team-stat-active">
        <div class="card-icon success"><span class="material-icons-round">groups</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_team_active', 'manager'); ?></h3>
            <h2 id="teamCountActive">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="team-stat-revenue">
        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_team_revenue', 'manager'); ?></h3>
            <h2 id="teamTotalRevenue">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="team-stat-avg">
        <div class="card-icon warning"><span class="material-icons-round">shopping_cart</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_team_avg_ticket', 'manager'); ?></h3>
            <h2 id="teamAvgTicket">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="team-stat-returns">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">assignment_return</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_team_returns', 'manager'); ?></h3>
            <h2 id="teamTotalReturns">—</h2>
        </div>
    </div>
</div>

<p class="mgr-team-hint" id="teamRefreshHint"><?php echo __t('team_refresh_hint', 'manager'); ?></p>

<section class="card mgr-panel mgr-team-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">leaderboard</span> <?php echo __t('team_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="teamTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="teamPerformanceRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

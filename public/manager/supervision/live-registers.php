<?php
$activePage = 'live-registers';
$bodyClass = 'mgr-page mgr-pro-page mgr-live-page ad-page';
$pageCss = ['supervision.css'];
$pageScripts = ['supervision-live.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('nav_live', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'no_terminals',
    'register_online', 'register_offline', 'register_idle',
    'source_shift', 'source_presence', 'col_status', 'col_last_activity',
    'col_sales_today', 'col_source', 'cashier_label', 'live_panel_title',
    'stat_registers_online', 'stat_registers_idle', 'stat_registers_shift',
    'stat_registers_total', 'live_refresh_hint',
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
    <a href="<?php echo $mgrPrefix; ?>supervision/live-registers.php" class="mgr-quick-nav__item mgr-quick-nav__item--accent">
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

<div class="mgr-page-intro mgr-live-intro">
    <span class="material-icons-round">sensors</span>
    <p><?php echo __t('live_intro', 'manager'); ?></p>
</div>

<div class="mgr-live-summary ad-stat-cards" id="liveSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="live-stat-online">
        <div class="card-icon success"><span class="material-icons-round">wifi</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_registers_online', 'manager'); ?></h3>
            <h2 id="liveCountOnline">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="live-stat-idle">
        <div class="card-icon warning"><span class="material-icons-round">hourglass_empty</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_registers_idle', 'manager'); ?></h3>
            <h2 id="liveCountIdle">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="live-stat-shift">
        <div class="card-icon primary"><span class="material-icons-round">schedule</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_registers_shift', 'manager'); ?></h3>
            <h2 id="liveCountShift">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading" id="live-stat-total">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">point_of_sale</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_registers_total', 'manager'); ?></h3>
            <h2 id="liveCountTotal">—</h2>
        </div>
    </div>
</div>

<p class="mgr-live-hint" id="liveRefreshHint"><?php echo __t('live_refresh_hint', 'manager'); ?></p>

<section class="card mgr-panel mgr-live-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">sensors</span> <?php echo __t('live_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="liveTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="liveRegistersRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

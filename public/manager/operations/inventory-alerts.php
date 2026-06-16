<?php
$activePage = 'inventory-alerts';
$bodyClass = 'mgr-page mgr-pro-page mgr-inv-page ad-page';
$pageCss = ['operations.css'];
$pageScripts = ['operations-inventory.js'];

require __DIR__ . '/../includes/auth-guard.php';

$pageTitle = __t('inventory_alerts_title', 'manager');
$pageI18n = [];
foreach ([
    'loading', 'load_error', 'last_updated', 'menu', 'nav_dashboard', 'nav_live', 'nav_shifts',
    'nav_team', 'nav_approvals', 'nav_inventory', 'nav_reports',
    'inventory_alerts_intro', 'inventory_alerts_panel_title', 'inventory_alerts_refresh_hint',
    'stat_inv_total', 'stat_inv_out', 'stat_inv_low', 'stat_inv_expiry',
    'filter_inv_all', 'filter_inv_out', 'filter_inv_low', 'filter_inv_expiring', 'filter_inv_expired',
    'col_product', 'col_sku', 'col_category', 'col_stock', 'col_min_stock', 'col_expiry', 'col_alert',
    'alert_out_of_stock', 'alert_low_stock', 'alert_expired', 'alert_expiring',
    'days_expired', 'days_until_expiry', 'expires_today', 'no_inventory_alerts',
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

<div class="mgr-page-intro mgr-inv-intro">
    <span class="material-icons-round">inventory</span>
    <p><?php echo __t('inventory_alerts_intro', 'manager'); ?></p>
</div>

<div class="mgr-inv-summary ad-stat-cards" id="invSummary">
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">warning_amber</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_inv_total', 'manager'); ?></h3>
            <h2 id="invCountTotal">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon mgr-icon-slate"><span class="material-icons-round">remove_shopping_cart</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_inv_out', 'manager'); ?></h3>
            <h2 id="invCountOut">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon primary"><span class="material-icons-round">inventory_2</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_inv_low', 'manager'); ?></h3>
            <h2 id="invCountLow">—</h2>
        </div>
    </div>
    <div class="card stat-card mgr-kpi ad-stat-card is-loading">
        <div class="card-icon warning"><span class="material-icons-round">event_busy</span></div>
        <div class="card-info">
            <h3><?php echo __t('stat_inv_expiry', 'manager'); ?></h3>
            <h2 id="invCountExpiring">—</h2>
        </div>
    </div>
</div>

<p class="mgr-inv-hint"><?php echo __t('inventory_alerts_refresh_hint', 'manager'); ?></p>

<div class="mgr-inv-filters" id="invFilterBar" role="tablist" aria-label="<?php echo htmlspecialchars(__t('inventory_alerts_panel_title', 'manager'), ENT_QUOTES, 'UTF-8'); ?>">
    <button type="button" class="mgr-inv-filters__btn is-active" data-filter="all" role="tab">
        <span class="material-icons-round">list</span>
        <span><?php echo __t('filter_inv_all', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="out" role="tab">
        <span class="material-icons-round">remove_shopping_cart</span>
        <span><?php echo __t('filter_inv_out', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="low" role="tab">
        <span class="material-icons-round">inventory_2</span>
        <span><?php echo __t('filter_inv_low', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="expiring" role="tab">
        <span class="material-icons-round">schedule</span>
        <span><?php echo __t('filter_inv_expiring', 'manager'); ?></span>
    </button>
    <button type="button" class="mgr-inv-filters__btn" data-filter="expired" role="tab">
        <span class="material-icons-round">event_busy</span>
        <span><?php echo __t('filter_inv_expired', 'manager'); ?></span>
    </button>
</div>

<section class="card mgr-panel mgr-inv-panel">
    <div class="mgr-panel-head">
        <h2><span class="material-icons-round">inventory</span> <?php echo __t('inventory_alerts_panel_title', 'manager'); ?></h2>
        <span class="mgr-count" id="invTableCount">—</span>
    </div>
    <div class="mgr-workspace" id="inventoryAlertsRoot">
        <div class="mgr-list mgr-list--loading"><?php echo __t('loading', 'manager'); ?></div>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

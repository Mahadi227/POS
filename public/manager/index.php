<?php
$pageTitle   = 'Tableau de bord supervision';
$activePage  = 'dashboard';
$pageCss     = ['manager-dashboard.css'];
$pageScripts = ['manager-dashboard.js'];
require __DIR__ . '/includes/auth-guard.php';
require __DIR__ . '/includes/layout-start.php';
?>

<div class="ad-error-banner" id="mgrError">
    <span class="material-icons-round">error_outline</span>
    <span class="ad-error-text"></span>
</div>

<div class="mgr-kpi-grid stat-cards">
    <div class="card stat-card mgr-kpi is-loading" id="kpi-sales">
        <div class="card-icon primary"><span class="material-icons-round">payments</span></div>
        <div class="card-info">
            <h3>Ventes aujourd'hui</h3>
            <h2 id="kpi-sales-val">—</h2>
            <p class="trend ad-trend--neutral" id="kpi-sales-sub">— transactions</p>
        </div>
    </div>
    <div class="card stat-card mgr-kpi is-loading" id="kpi-pending">
        <div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div>
        <div class="card-info">
            <h3>Approbations en attente</h3>
            <h2 id="kpi-pending-val">—</h2>
            <p class="trend negative">Action requise</p>
        </div>
    </div>
    <div class="card stat-card mgr-kpi is-loading" id="kpi-live">
        <div class="card-icon success"><span class="material-icons-round">sensors</span></div>
        <div class="card-info">
            <h3>Caisses actives</h3>
            <h2 id="kpi-live-val">—</h2>
            <p class="trend ad-trend--neutral" id="kpi-live-sub">En ligne</p>
        </div>
    </div>
    <div class="card stat-card mgr-kpi is-loading" id="kpi-alerts">
        <div class="card-icon mgr-icon-danger">
            <span class="material-icons-round">inventory</span>
        </div>
        <div class="card-info">
            <h3>Alertes stock</h3>
            <h2 id="kpi-alerts-val">—</h2>
            <p class="trend ad-trend--neutral">Faible / rupture</p>
        </div>
    </div>
</div>

<div class="mgr-panels">
    <section class="card mgr-panel">
        <div class="mgr-panel-head">
            <h2><span class="material-icons-round">pending_actions</span> File d'approbations</h2>
            <a href="approvals/index.php" class="mgr-link">Voir tout</a>
        </div>
        <div id="dashboardApprovals" class="mgr-list mgr-list--loading">Chargement…</div>
    </section>

    <section class="card mgr-panel">
        <div class="mgr-panel-head">
            <h2><span class="material-icons-round">sensors</span> Caisses en direct</h2>
            <a href="supervision/live-registers.php" class="mgr-link">Détails</a>
        </div>
        <div id="dashboardLiveRegisters" class="mgr-list mgr-list--loading">Chargement…</div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

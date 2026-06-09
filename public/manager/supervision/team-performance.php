<?php
$pageTitle   = 'Performance équipe';
$activePage  = 'team-performance';
$pageCss     = array (
  0 => 'supervision.css',
);
$pageScripts = array (
  0 => 'manager-dashboard.js',
);
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">groups</span>
    <p>Indicateurs par caissier : ventes, panier moyen, retours.</p>
</div>

<div class="card mgr-workspace" id="teamPerformanceRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$pageTitle   = 'Journal d'audit';
$activePage  = 'audit-trail';
$pageCss     = array (
  0 => 'manager-dashboard.css',
);
$pageScripts = array (
  0 => 'manager-dashboard.js',
);
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">history</span>
    <p>Historique des actions manager (approbations, shifts).</p>
</div>

<div class="card mgr-workspace" id="auditTrailRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$pageTitle   = 'Alertes stock';
$activePage  = 'inventory-alerts';
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
    <span class="material-icons-round">inventory</span>
    <p>Produits en stock faible, rupture ou proche expiration.</p>
</div>

<div class="card mgr-workspace" id="inventoryAlertsRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

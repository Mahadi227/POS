<?php
$pageTitle   = 'Revue des ventes';
$activePage  = 'sales-review';
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
    <span class="material-icons-round">fact_check</span>
    <p>Transactions signalées ou montants inhabituels.</p>
</div>

<div class="card mgr-workspace" id="salesReviewRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

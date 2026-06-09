<?php
$pageTitle   = 'Réconciliation caisse';
$activePage  = 'cash-reconciliation';
$pageCss     = array (
  0 => 'supervision.css',
);
$pageScripts = array (
  0 => 'shifts.js',
);
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">account_balance_wallet</span>
    <p>Comptage caisse vs ventes espèces par shift.</p>
</div>

<div class="card mgr-workspace" id="cashReconRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

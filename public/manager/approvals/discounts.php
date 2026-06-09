<?php
$pageTitle   = 'Approbations remises';
$activePage  = 'discounts';
$pageCss     = array (
  0 => 'approvals.css',
);
$pageScripts = array (
  0 => 'approvals-queue.js',
);
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">percent</span>
    <p>Remises au-delà du seuil caissier.</p>
</div>

<div class="card mgr-workspace" id="approvalsDiscountsRoot"
    data-approval-filter="discount">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

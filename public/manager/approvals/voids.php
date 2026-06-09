<?php
$pageTitle   = 'Approbations annulations';
$activePage  = 'voids';
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
    <span class="material-icons-round">block</span>
    <p>Annulations de ventes après encaissement.</p>
</div>

<div class="card mgr-workspace" id="approvalsVoidsRoot"
    data-approval-filter="void">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

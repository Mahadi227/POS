<?php
$pageTitle   = 'File d'approbations';
$activePage  = 'approvals';
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
    <span class="material-icons-round">pending_actions</span>
    <p>Toutes les demandes en attente de validation manager.</p>
</div>

<div class="card mgr-workspace" id="approvalsQueueRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
$pageTitle   = 'Approbations retours';
$activePage  = 'returns';
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
    <span class="material-icons-round">assignment_return</span>
    <p>Retours et remboursements nécessitant une approbation.</p>
</div>

<div class="card mgr-workspace" id="approvalsReturnsRoot"
    data-approval-filter="return">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

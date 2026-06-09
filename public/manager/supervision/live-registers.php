<?php
$pageTitle   = 'Caisse en direct';
$activePage  = 'live-registers';
$pageCss     = array (
  0 => 'supervision.css',
);
$pageScripts = array (
  0 => 'supervision-live.js',
);
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">sensors</span>
    <p>Surveillance des terminaux caisse et statut en ligne des caissiers.</p>
</div>

<div class="card mgr-workspace" id="liveRegistersRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

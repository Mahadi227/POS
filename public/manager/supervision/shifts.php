<?php
$pageTitle   = 'Quarts / Shifts';
$activePage  = 'shifts';
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
    <span class="material-icons-round">schedule</span>
    <p>Ouverture, clôture et passation de caisse par quart.</p>
</div>

<div class="card mgr-workspace" id="shiftsRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

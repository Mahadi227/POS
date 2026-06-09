<?php
$pageTitle   = 'Résumé journalier';
$activePage  = 'daily-summary';
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
    <span class="material-icons-round">summarize</span>
    <p>Synthèse de fin de journée pour le magasin.</p>
</div>

<div class="card mgr-workspace" id="dailySummaryRoot">
    <div class="mgr-list mgr-list--loading">Chargement…</div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

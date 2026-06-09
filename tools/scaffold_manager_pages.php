<?php
/**
 * Scaffold generator for manager workflow pages.
 * Run once: php tools/scaffold_manager_pages.php
 */
$base = dirname(__DIR__) . '/public/manager';

$pages = [
    'supervision/live-registers.php' => [
        'title' => 'Caisse en direct',
        'active' => 'live-registers',
        'css' => ['supervision.css'],
        'js' => ['supervision-live.js'],
        'icon' => 'sensors',
        'desc' => 'Surveillance des terminaux caisse et statut en ligne des caissiers.',
        'mount' => 'liveRegistersRoot',
    ],
    'supervision/shifts.php' => [
        'title' => 'Quarts / Shifts',
        'active' => 'shifts',
        'css' => ['supervision.css'],
        'js' => ['shifts.js'],
        'icon' => 'schedule',
        'desc' => 'Ouverture, clôture et passation de caisse par quart.',
        'mount' => 'shiftsRoot',
    ],
    'supervision/team-performance.php' => [
        'title' => 'Performance équipe',
        'active' => 'team-performance',
        'css' => ['supervision.css'],
        'js' => ['manager-dashboard.js'],
        'icon' => 'groups',
        'desc' => 'Indicateurs par caissier : ventes, panier moyen, retours.',
        'mount' => 'teamPerformanceRoot',
    ],
    'approvals/index.php' => [
        'title' => "File d'approbations",
        'active' => 'approvals',
        'css' => ['approvals.css'],
        'js' => ['approvals-queue.js'],
        'icon' => 'pending_actions',
        'desc' => 'Toutes les demandes en attente de validation manager.',
        'mount' => 'approvalsQueueRoot',
    ],
    'approvals/returns.php' => [
        'title' => 'Approbations retours',
        'active' => 'returns',
        'css' => ['approvals.css'],
        'js' => ['approvals-queue.js'],
        'icon' => 'assignment_return',
        'desc' => 'Retours et remboursements nécessitant une approbation.',
        'mount' => 'approvalsReturnsRoot',
        'filter' => 'return',
    ],
    'approvals/discounts.php' => [
        'title' => 'Approbations remises',
        'active' => 'discounts',
        'css' => ['approvals.css'],
        'js' => ['approvals-queue.js'],
        'icon' => 'percent',
        'desc' => 'Remises au-delà du seuil caissier.',
        'mount' => 'approvalsDiscountsRoot',
        'filter' => 'discount',
    ],
    'approvals/voids.php' => [
        'title' => 'Approbations annulations',
        'active' => 'voids',
        'css' => ['approvals.css'],
        'js' => ['approvals-queue.js'],
        'icon' => 'block',
        'desc' => 'Annulations de ventes après encaissement.',
        'mount' => 'approvalsVoidsRoot',
        'filter' => 'void',
    ],
    'operations/inventory-alerts.php' => [
        'title' => 'Alertes stock',
        'active' => 'inventory-alerts',
        'css' => ['manager-dashboard.css'],
        'js' => ['manager-dashboard.js'],
        'icon' => 'inventory',
        'desc' => 'Produits en stock faible, rupture ou proche expiration.',
        'mount' => 'inventoryAlertsRoot',
    ],
    'operations/cash-reconciliation.php' => [
        'title' => 'Réconciliation caisse',
        'active' => 'cash-reconciliation',
        'css' => ['supervision.css'],
        'js' => ['shifts.js'],
        'icon' => 'account_balance_wallet',
        'desc' => 'Comptage caisse vs ventes espèces par shift.',
        'mount' => 'cashReconRoot',
    ],
    'operations/sales-review.php' => [
        'title' => 'Revue des ventes',
        'active' => 'sales-review',
        'css' => ['manager-dashboard.css'],
        'js' => ['manager-dashboard.js'],
        'icon' => 'fact_check',
        'desc' => 'Transactions signalées ou montants inhabituels.',
        'mount' => 'salesReviewRoot',
    ],
    'reports/daily-summary.php' => [
        'title' => 'Résumé journalier',
        'active' => 'daily-summary',
        'css' => ['manager-dashboard.css'],
        'js' => ['manager-dashboard.js'],
        'icon' => 'summarize',
        'desc' => 'Synthèse de fin de journée pour le magasin.',
        'mount' => 'dailySummaryRoot',
    ],
    'reports/audit-trail.php' => [
        'title' => "Journal d'audit",
        'active' => 'audit-trail',
        'css' => ['manager-dashboard.css'],
        'js' => ['manager-dashboard.js'],
        'icon' => 'history',
        'desc' => 'Historique des actions manager (approbations, shifts).',
        'mount' => 'auditTrailRoot',
    ],
];

foreach ($pages as $relPath => $cfg) {
    $dir = dirname("$base/$relPath");
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }

    $cssList = var_export($cfg['css'], true);
    $jsList = var_export($cfg['js'], true);
    $filter = isset($cfg['filter']) ? "\n    data-approval-filter=\"{$cfg['filter']}\"" : '';

    $content = <<<PHP
<?php
\$pageTitle   = '{$cfg['title']}';
\$activePage  = '{$cfg['active']}';
\$pageCss     = {$cssList};
\$pageScripts = {$jsList};
require __DIR__ . '/../includes/auth-guard.php';
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="mgr-page-intro">
    <span class="material-icons-round">{$cfg['icon']}</span>
    <p>{$cfg['desc']}</p>
</div>

<div class="card mgr-workspace" id="{$cfg['mount']}"{$filter}>
    <div class="mgr-list mgr-list--loading">Chargement…</motion>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

PHP;

    $content = str_replace(['<motion', '</motion>'], ['<div', '</motion>'], $content);
    $content = str_replace('</motion>', '</div>', $content);

    file_put_contents("$base/$relPath", $content);
    echo "Created: $relPath\n";
}

echo "Done.\n";

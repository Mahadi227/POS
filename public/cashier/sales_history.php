<?php

/**
 * Historique des ventes — caissier (recherche, filtres, réimpression).
 */
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['cashier', 'admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

require_once __DIR__ . '/includes/pos-config.php';

$displayName = htmlspecialchars($_SESSION['name'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
$displayRole = htmlspecialchars($_SESSION['role'] ?? 'Caissier', ENT_QUOTES, 'UTF-8');
$storeName = htmlspecialchars($posConfig['store']['name'] ?? 'RetailPOS', ENT_QUOTES, 'UTF-8');
$currencySymbol = htmlspecialchars($posConfig['settings']['currency_symbol'] ?? 'FCFA', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Historique des ventes — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-sales-history.css?v=1">
</head>

<body class="sh-page">
    <div class="admin-layout">
        <?php include 'includes/sidebar.php'; ?>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div>
                        <h1>Historique des ventes</h1>
                    </div>
                </div>
                <div class="header-right">
                    <a href="pos.php" class="sh-refresh-btn" style="text-decoration:none;">
                        <span class="material-icons-round">point_of_sale</span>
                        Caisse
                    </a>
                    <div class="user-profile">
                        <div class="user-info">
                            <span class="user-name"><?php echo $displayName; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="sh-toolbar">
                    <div class="sh-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="salesSearch" placeholder="Rechercher un n° ticket ou client…"
                            autocomplete="off">
                        <button type="button" class="sh-search-clear" id="salesSearchClear" aria-label="Effacer">
                            <span class="material-icons-round">close</span>
                        </button>
                    </div>
                    <div class="sh-filters" role="tablist" aria-label="Période">
                        <button type="button" class="sh-filter-btn active" data-period="today">Aujourd'hui</button>
                        <button type="button" class="sh-filter-btn" data-period="all">Toutes</button>
                    </div>
                    <button type="button" class="sh-refresh-btn" id="salesRefreshBtn">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                </div>

                <div class="sh-summary">
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--blue">
                            <span class="material-icons-round">receipt_long</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label">Tickets affichés</div>
                            <div class="sh-summary-card__value" id="summaryCount">0</div>
                        </div>
                    </div>
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--green">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label">Total filtré</div>
                            <div class="sh-summary-card__value" id="summaryRevenue">0 FCFA</div>
                        </div>
                    </div>
                    <div class="sh-summary-card">
                        <div class="sh-summary-card__icon sh-summary-card__icon--slate">
                            <span class="material-icons-round">filter_list</span>
                        </div>
                        <div>
                            <div class="sh-summary-card__label">Période</div>
                            <div class="sh-summary-card__value" id="summaryFiltered" style="font-size:0.95rem;">—</div>
                        </div>
                    </div>
                </div>

                <section class="sh-panel">
                    <div class="sh-panel__head">
                        <h2>Liste des tickets</h2>
                        <span class="sh-count" id="salesCountLabel">Chargement…</span>
                    </div>

                    <div class="sh-table-wrap">
                        <table class="sh-table" id="salesTable">
                            <thead>
                                <tr>
                                    <th>N° ticket</th>
                                    <th>Date & heure</th>
                                    <th>Total</th>
                                    <th>Paiement</th>
                                    <th>Client</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody">
                                <tr class="sh-loading-row">
                                    <td colspan="6">Chargement de l'historique…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="sh-cards" id="salesCards" aria-label="Ventes (mobile)">
                        <div class="sh-state">
                            <span class="material-icons-round">hourglass_empty</span>
                            <h3>Chargement…</h3>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/sales-history.js?v=1"></script>
</body>

</html>
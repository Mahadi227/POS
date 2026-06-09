<?php

/**
 * Retours & remboursements — recherche ticket, sélection articles, restock.
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
$currencySymbol = htmlspecialchars($posConfig['settings']['currency_symbol'] ?? 'FCFA', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Retours & remboursements — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/cashier-returns.css?v=1">
</head>

<body class="rt-page">
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
                        <h1>Retours & remboursements</h1>
                    </div>
                </div>
                <div class="header-right">
                    <a href="sales_history.php" class="rt-btn rt-btn--outline"
                        style="height:40px;padding:0 14px;font-size:0.85rem;">
                        <span class="material-icons-round">history</span>
                        Historique
                    </a>
                    <div class="user-profile">
                        <div class="user-info">
                            <span class="user-name"><?php echo $displayName; ?></span>
                        </div>
                    </div>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <section class="rt-search-hero">
                    <div class="rt-search-hero__icon">
                        <span class="material-icons-round">assignment_return</span>
                    </div>
                    <h2>Traiter un retour client</h2>
                    <p>Scannez ou saisissez le numéro du ticket pour afficher les articles vendus.</p>
                    <div class="rt-search-form">
                        <div class="rt-search-input-wrap">
                            <span class="material-icons-round">confirmation_number</span>
                            <input type="text" id="receiptNumber" placeholder="N° ticket ou ID vente" autocomplete="off"
                                autofocus>
                        </div>
                        <button type="button" class="rt-search-btn" id="searchBtn">
                            <span class="material-icons-round">search</span>
                            Rechercher
                        </button>
                    </div>
                    <p class="rt-hint">Appuyez sur Entrée pour lancer la recherche. Ex : R1-20250522… ou l'ID numérique.
                    </p>
                </section>

                <div id="resultArea"></div>
            </div>
        </main>
    </div>

    <?php include 'includes/scripts.php'; ?>
    <script>
    window.POS_CONFIG = <?php echo json_encode($posConfig, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/cashier/returns.js?v=2"></script>
</body>

</html>
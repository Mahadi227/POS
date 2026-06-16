<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Config/config.php';
require_once '../../includes/Database/Database.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}
$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$userId = (int) ($_SESSION['user_id'] ?? 0);
$storeId = (int) ($_SESSION['store_id'] ?? 0);
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Ajustements de stock — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=2">
</head>

<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">edit</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section">Inventaire</li>
                <li>
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span>Produits</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_history.php" class="nav-link">
                        <span class="material-icons-round">history</span>
                        <span>Historique</span>
                    </a>
                </li>
                <li>
                    <a href="stock_movements.php" class="nav-link">
                        <span class="material-icons-round">swap_horiz</span>
                        <span>Mouvements</span>
                    </a>
                </li>
                <li>
                    <a href="stock_adjustments.php" class="nav-link active">
                        <span class="material-icons-round">edit</span>
                        <span>Ajustements</span>
                    </a>
                </li>
                <?php $activePage = 'inventory';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
            </ul>
        </aside>
        <div class="sidebar-overlay" id="sidebarOverlay"></div>
        <main class="main-content">
            <header class="top-header">
                <div class="header-left" style="display:flex;align-items:center;gap:16px;">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div>
                        <h1>Ajustements de stock</h1>
                        <p class="date-display" id="adjustmentsDate">—</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshAdjustments" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="card table-widget">
                    <div class="inv-table-meta">
                        <span>Journal des ajustements manuels et des corrections de stock.</span>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Produit</th>
                                    <th>Type</th>
                                    <th>Quantité</th>
                                    <th>Nouveau stock</th>
                                    <th>Motif</th>
                                    <th>Utilisateur</th>
                                    <th>Magasin</th>
                                </tr>
                            </thead>
                            <tbody id="stockAdjustmentsBody">
                                <tr>
                                    <td colspan="8" class="ad-empty-row">Chargement des ajustements…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>
    <script src="../../assets/js/admin/admin-api.js?v=4"></script>
    <script src="../../assets/js/admin/inventory-history.js?v=1"></script>
    <script>
        document.getElementById('adjustmentsDate').textContent = new Date().toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        });
        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            document.querySelector('#theme-toggle .material-icons-round').textContent = next === 'dark' ? 'light_mode' : 'dark_mode';
        });
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            const icon = document.querySelector('#theme-toggle .material-icons-round');
            if (icon) icon.textContent = 'light_mode';
        }
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>
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
    <title>Analytics d'inventaire — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=2">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">bar_chart</span>
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
                    <a href="inventory_analytics.php" class="nav-link active">
                        <span class="material-icons-round">bar_chart</span>
                        <span>Analytics</span>
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
                        <h1>Analytics d'inventaire</h1>
                        <p class="date-display" id="analyticsDate">—</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshAnalytics" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>
            <div class="dashboard-scroll-area">
                <div class="stat-cards">
                    <div class="card stat-card">
                        <div class="card-icon primary"><span class="material-icons-round">analytics</span></div>
                        <div class="card-info">
                            <h3>Mouvements ce mois</h3>
                            <h2 id="analyticsTotalMovements">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="card-icon success"><span class="material-icons-round">trending_up</span></div>
                        <div class="card-info">
                            <h3>Profit estimé</h3>
                            <h2 id="analyticsProfit">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card">
                        <div class="card-icon warning"><span class="material-icons-round">warning_amber</span></div>
                        <div class="card-info">
                            <h3>Stock faible</h3>
                            <h2 id="analyticsLowStock">—</h2>
                        </div>
                    </div>
                </div>
                <div class="charts-section">
                    <div class="card chart-container">
                        <div class="card-header">
                            <h3>Tendance des mouvements</h3>
                        </div>
                        <div class="chart-wrapper"><canvas id="movementTrendChart"></canvas></div>
                    </div>
                    <div class="card chart-container">
                        <div class="card-header">
                            <h3>Top produits vendus</h3>
                        </div>
                        <div class="chart-wrapper"><canvas id="topProductsChart"></canvas></div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>
    <script src="../../assets/js/admin/admin-api.js?v=4"></script>
    <script src="../../assets/js/admin/inventory-history.js?v=1"></script>
    <script>
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebar = document.querySelector('.sidebar');
        const overlay = document.getElementById('sidebarOverlay');

        function toggleSidebar() {
            sidebar?.classList.toggle('open');
            overlay?.classList.toggle('active');
        }
        mobileMenuBtn?.addEventListener('click', toggleSidebar);
        overlay?.addEventListener('click', toggleSidebar);
        document.getElementById('analyticsDate').textContent = new Date().toLocaleDateString('fr-FR', {
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
</body>

</html>
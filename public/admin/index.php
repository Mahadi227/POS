<?php
require_once '../../includes/Config/session.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$storeName = '';
$storeCurrency = 'FCFA';
try {
    require_once '../../includes/Database/Database.php';
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    $storeRow = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($storeRow) {
        $storeName = $storeRow['name'] ?? '';
        $storeCurrency = $storeRow['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
    // ignore and fallback to defaults
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Tableau de bord — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=1">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>

<body>
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">storefront</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section">Principal</li>
                <li>
                    <a href="index.php" class="nav-link active">
                        <span class="material-icons-round">dashboard</span>
                        <span>Tableau de bord</span>
                    </a>
                </li>
                <li>
                    <a href="sales.php" class="nav-link">
                        <span class="material-icons-round">point_of_sale</span>
                        <span>Ventes</span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span>Inventaire</span>
                        <span class="badge warning hidden" id="sidebar-low-stock-badge">0</span>
                    </a>
                </li>
                <?php $activePage = 'dashboard';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section">Système</li>
                <li>
                    <a href="../cashier/pos.php" class="nav-link">
                        <span class="material-icons-round">shopping_cart</span>
                        <span>Terminal caisse</span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="nav-link" style="color: var(--danger); margin-top: 12px;">
                        <span class="material-icons-round">logout</span>
                        <span>Déconnexion</span>
                    </a>
                </li>
            </ul>
            <div class="user-profile-widget">
                <span class="avatar-initial" id="sidebar-user-avatar"><?php echo htmlspecialchars($initial); ?></span>
                <div class="user-info">
                    <p class="name" id="sidebar-user-name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?>
                    </p>
                    <p class="role" id="sidebar-user-role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
                </div>
            </div>
        </aside>

        <div class="sidebar-overlay" id="sidebarOverlay"></div>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left" style="display:flex;align-items:center;gap:16px;">
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn" aria-label="Menu">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div>
                        <h1>Vue d'ensemble</h1>
                        <p class="date-display" id="current-date">Chargement…</p>
                        <span class="ad-store-pill hidden" id="store-pill">
                            <span class="material-icons-round">store</span>
                            <span id="store-pill-text"></span>
                        </span>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshDashboard" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="dashboardError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="ad-month-banner">
                    <div>
                        <span>Chiffre d'affaires du mois</span>
                        <strong id="revenue-month-val">—</strong>
                    </div>
                    <a href="sales.php" class="btn-text" style="text-decoration:none;">
                        Voir toutes les ventes
                        <span class="material-icons-round"
                            style="font-size:16px;vertical-align:middle;">arrow_forward</span>
                    </a>
                </div>

                <div class="stat-cards">
                    <div class="card stat-card is-loading">
                        <div class="card-icon primary">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div class="card-info">
                            <h3>Revenu du jour</h3>
                            <h2 id="revenue-today-val">—</h2>
                            <p class="trend" id="revenue-trend"></p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon success">
                            <span class="material-icons-round">shopping_bag</span>
                        </div>
                        <div class="card-info">
                            <h3>Ventes aujourd'hui</h3>
                            <h2 id="sales-today-val">—</h2>
                            <p class="trend" id="sales-trend"></p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon warning">
                            <span class="material-icons-round">warning_amber</span>
                        </div>
                        <div class="card-info">
                            <h3>Stock faible</h3>
                            <h2 id="low-stock-val">—</h2>
                            <p class="trend negative">
                                <span class="material-icons-round">inventory_2</span>
                                <a href="inventory.php" style="color:inherit;text-decoration:none;">Voir inventaire</a>
                            </p>
                        </div>
                    </div>
                    <div class="card stat-card is-loading">
                        <div class="card-icon info">
                            <span class="material-icons-round">groups</span>
                        </div>
                        <div class="card-info">
                            <h3>Clients actifs</h3>
                            <h2 id="active-customers-val">—</h2>
                            <p class="trend ad-trend--neutral">Base clients enregistrée</p>
                        </div>
                    </div>
                </div>

                <div class="charts-section">
                    <div class="card chart-container main-chart">
                        <div class="card-header">
                            <h3>Revenus — 7 derniers mois</h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="revenueChart"></canvas>
                        </div>
                    </div>
                    <div class="card chart-container secondary-chart">
                        <div class="card-header">
                            <h3>Ventes par catégorie (mois)</h3>
                        </div>
                        <div class="chart-wrapper">
                            <canvas id="categoryChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="bottom-widgets">
                    <div class="card table-widget">
                        <div class="card-header">
                            <h3>Transactions récentes</h3>
                            <a href="sales.php" class="btn-text">Voir tout</a>
                        </div>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Ticket</th>
                                        <th>Client</th>
                                        <th>Date</th>
                                        <th>Montant</th>
                                        <th>Statut</th>
                                        <th>Paiement</th>
                                    </tr>
                                </thead>
                                <tbody id="recent-transactions-list">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">Chargement…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="side-widgets">
                        <div class="card list-widget">
                            <div class="card-header">
                                <h3>Meilleures ventes (30 j)</h3>
                            </div>
                            <ul class="item-list" id="top-products-list">
                                <li class="item">
                                    <div class="item-details">
                                        <p>Chargement…</p>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
    window.ADMIN_PAGE = window.ADMIN_PAGE || {};
    window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
    window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
    window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=4"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=1"></script>
    <script src="../../assets/js/admin/dashboard.js?v=2"></script>
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
    </script>
</body>

</html>
<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Database/Database.php';
requireLogin();

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}
$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'analytics';

// Fetch store info with currency
$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$storeName = 'RetailPOS';
$storeCurrency = 'FCFA';

try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare(
        'SELECT id, name, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
    );
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeName = $row['name'] ?? 'RetailPOS';
        $storeCurrency = $row['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
    // Use defaults if query fails
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Analyses & rapports — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-analytics.css?v=1">
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
                    <a href="index.php" class="nav-link">
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
                    </a>
                </li>
                <?php include __DIR__ . '/includes/sidebar-extra.php'; ?>
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
                <span class="avatar-initial"><?php echo htmlspecialchars($initial); ?></span>
                <div class="user-info">
                    <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? 'Admin'); ?></p>
                    <p class="role"><?php echo htmlspecialchars($_SESSION['role'] ?? ''); ?></p>
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
                        <h1>Analyses & rapports</h1>
                        <p class="date-display" id="analytics-period-label">Chargement…</p>
                        <span class="ad-store-pill hidden" id="store-pill">
                            <span class="material-icons-round">store</span>
                            <span id="store-pill-text"></span>
                        </span>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ar-btn ar-btn--outline" id="exportReportBtn" title="Exporter CSV">
                        <span class="material-icons-round">download</span>
                        Générer rapport
                    </button>
                    <button type="button" class="ad-refresh-btn" id="refreshAnalytics" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="analyticsError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="ar-toolbar">
                    <div class="as-chips" role="tablist" aria-label="Période">
                        <button type="button" class="as-chip active" data-period="today">Aujourd'hui</button>
                        <button type="button" class="as-chip" data-period="week">7 jours</button>
                        <button type="button" class="as-chip" data-period="month">30 jours</button>
                        <button type="button" class="as-chip" data-period="90d">90 jours</button>
                    </div>
                </div>

                <div class="stat-cards ar-summary-cards">
                    <div class="card stat-card ar-stat is-loading">
                        <div class="card-icon primary">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div class="card-info">
                            <h3>Chiffre d'affaires</h3>
                            <h2 id="ar-revenue">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ar-stat is-loading">
                        <div class="card-icon success">
                            <span class="material-icons-round">receipt_long</span>
                        </div>
                        <div class="card-info">
                            <h3>Transactions</h3>
                            <h2 id="ar-transactions">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ar-stat is-loading">
                        <div class="card-icon info">
                            <span class="material-icons-round">shopping_bag</span>
                        </div>
                        <div class="card-info">
                            <h3>Panier moyen</h3>
                            <h2 id="ar-avg-ticket">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ar-stat is-loading">
                        <div class="card-icon warning">
                            <span class="material-icons-round">groups</span>
                        </div>
                        <div class="card-info">
                            <h3>Clients actifs</h3>
                            <h2 id="ar-active-customers">—</h2>
                            <p class="trend ad-trend--neutral" id="ar-new-customers">—</p>
                        </div>
                    </div>
                </div>

                <div class="ar-tabs" role="tablist">
                    <button type="button" class="ar-tab active" data-panel="daily">Ventes quotidiennes</button>
                    <button type="button" class="ar-tab" data-panel="branches">Succursales</button>
                    <button type="button" class="ar-tab" data-panel="cashiers">Performance caissiers</button>
                    <button type="button" class="ar-tab" data-panel="inventory">Inventaire</button>
                    <button type="button" class="ar-tab" data-panel="customers">Clients</button>
                </div>

                <!-- Daily sales -->
                <section id="panel-daily" class="ar-panel">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>Évolution des ventes</h3>
                            <div class="ar-chart-wrap"><canvas id="dailyRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3>Nombre de transactions</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="dailyCountChart"></canvas></div>
                        </div>
                    </div>
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>Répartition des paiements</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="paymentMixChart"></canvas></div>
                        </div>
                        <div class="card table-widget">
                            <h3>Résumé quotidien</h3>
                            <div class="table-responsive">
                                <table class="modern-table">
                                    <thead>
                                        <tr>
                                            <th>Date</th>
                                            <th>CA</th>
                                            <th>Transactions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="dailyTableBody">
                                        <tr>
                                            <td colspan="3" class="ad-empty-row">—</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Branches -->
                <section id="panel-branches" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>CA par succursale</h3>
                            <div class="ar-chart-wrap"><canvas id="branchRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3>Transactions par succursale</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="branchTxChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3>Détail succursales</h3>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Succursale</th>
                                        <th>Code</th>
                                        <th>CA</th>
                                        <th>Transactions</th>
                                        <th>Panier moy.</th>
                                    </tr>
                                </thead>
                                <tbody id="branchTableBody">
                                    <tr>
                                        <td colspan="5" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Cashiers -->
                <section id="panel-cashiers" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>CA par caissier</h3>
                            <div class="ar-chart-wrap"><canvas id="cashierRevenueChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3>Transactions par caissier</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="cashierCountChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3>Classement caissiers</h3>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>Caissier</th>
                                        <th>CA</th>
                                        <th>Transactions</th>
                                        <th>Panier moy.</th>
                                    </tr>
                                </thead>
                                <tbody id="cashierTableBody">
                                    <tr>
                                        <td colspan="5" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Inventory -->
                <section id="panel-inventory" class="ar-panel hidden">
                    <div class="stat-cards ar-inv-mini">
                        <div class="card stat-card ar-stat-sm">
                            <div class="card-info">
                                <h3>Produits</h3>
                                <h2 id="inv-total">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card ar-stat-sm">
                            <div class="card-info">
                                <h3>Rupture</h3>
                                <h2 id="inv-out">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card ar-stat-sm">
                            <div class="card-info">
                                <h3>Stock bas</h3>
                                <h2 id="inv-low">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card ar-stat-sm">
                            <div class="card-info">
                                <h3>Valeur stock</h3>
                                <h2 id="inv-value">—</h2>
                            </div>
                        </div>
                    </div>
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>Valeur stock par catégorie</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="invCategoryChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3>État des stocks</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="invStockChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3>Produits les plus vendus</h3>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Produit</th>
                                        <th>Qté vendue</th>
                                        <th>CA généré</th>
                                    </tr>
                                </thead>
                                <tbody id="invMovingBody">
                                    <tr>
                                        <td colspan="3" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <!-- Customers -->
                <section id="panel-customers" class="ar-panel hidden">
                    <div class="ar-grid-2">
                        <div class="card ar-chart-card">
                            <h3>Nouveaux clients</h3>
                            <div class="ar-chart-wrap"><canvas id="customerGrowthChart"></canvas></div>
                        </div>
                        <div class="card ar-chart-card">
                            <h3>Clients identifiés vs anonymes</h3>
                            <div class="ar-chart-wrap ar-chart-wrap--sm"><canvas id="customerSplitChart"></canvas></div>
                        </div>
                    </div>
                    <div class="card table-widget">
                        <h3>Meilleurs clients</h3>
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Client</th>
                                        <th>Téléphone</th>
                                        <th>Visites</th>
                                        <th>Total dépensé</th>
                                    </tr>
                                </thead>
                                <tbody id="customerTopBody">
                                    <tr>
                                        <td colspan="4" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=7"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=2"></script>
    <script src="../../assets/js/admin/analytics.js?v=1"></script>
    <script>
        document.getElementById('mobileMenuBtn')?.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.toggle('open');
            document.getElementById('sidebarOverlay')?.classList.toggle('active');
        });
        document.getElementById('sidebarOverlay')?.addEventListener('click', () => {
            document.querySelector('.sidebar')?.classList.remove('open');
            document.getElementById('sidebarOverlay')?.classList.remove('active');
        });
    </script>
</body>

</html>
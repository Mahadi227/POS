<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Database/Database.php';
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
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeName = $row['name'] ?? '';
        $storeCurrency = $row['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
    // fallback to defaults
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'sync';
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Sync hors ligne — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link
        href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap"
        rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-sync-monitor.css?v=1">
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
                <li><a href="index.php" class="nav-link"><span
                            class="material-icons-round">dashboard</span><span>Tableau de bord</span></a></li>
                <li><a href="sales.php" class="nav-link"><span
                            class="material-icons-round">point_of_sale</span><span>Ventes</span></a></li>
                <li><a href="inventory.php" class="nav-link"><span
                            class="material-icons-round">inventory_2</span><span>Inventaire</span></a></li>
                <?php include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section">Système</li>
                <li><a href="../cashier/pos.php" class="nav-link"><span
                            class="material-icons-round">shopping_cart</span><span>Terminal caisse</span></a></li>
                <li><a href="../logout.php" class="nav-link" style="color:var(--danger);margin-top:12px;"><span
                            class="material-icons-round">logout</span><span>Déconnexion</span></a></li>
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
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn"><span
                            class="material-icons-round">menu</span></button>
                    <div>
                        <h1>Synchronisation hors ligne</h1>
                        <p class="date-display">Surveillance file d'attente & succursales</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshSync" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle"><span
                            class="material-icons-round">dark_mode</span></button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="syncError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="stat-cards sm-stats">
                    <div class="card stat-card sm-stat">
                        <div class="card-icon warning"><span class="material-icons-round">cloud_off</span></div>
                        <div class="card-info">
                            <h3>Succursales hors ligne</h3>
                            <h2 id="st-offline-branches">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card sm-stat">
                        <div class="card-icon primary"><span class="material-icons-round">pending_actions</span></div>
                        <div class="card-info">
                            <h3>File en attente</h3>
                            <h2 id="st-pending">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card sm-stat">
                        <div class="card-icon danger"><span class="material-icons-round">error</span></div>
                        <div class="card-info">
                            <h3>Échecs sync</h3>
                            <h2 id="st-failed">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card sm-stat">
                        <div class="card-icon info"><span class="material-icons-round">gavel</span></div>
                        <div class="card-info">
                            <h3>Conflits</h3>
                            <h2 id="st-conflicts">—</h2>
                        </div>
                    </div>
                </div>

                <div class="card ar-chart-card sm-chart-card">
                    <h3>Activité sync (7 jours)</h3>
                    <div class="sm-chart-wrap"><canvas id="syncActivityChart"></canvas></div>
                </div>

                <div class="sm-tabs">
                    <button type="button" class="sm-tab active" data-panel="branches">Succursales</button>
                    <button type="button" class="sm-tab" data-panel="queue">File d'attente</button>
                    <button type="button" class="sm-tab" data-panel="failed">Échecs</button>
                    <button type="button" class="sm-tab" data-panel="conflicts">Conflits</button>
                </div>

                <section id="panel-branches" class="sm-panel">
                    <div class="sm-grid" id="branchesGrid">
                        <p class="ad-empty-row">Chargement…</p>
                    </div>
                </section>

                <section id="panel-queue" class="sm-panel hidden">
                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Succursale</th>
                                        <th>Action</th>
                                        <th>Ticket</th>
                                        <th>Statut</th>
                                        <th></th>
                                    </tr>
                                </thead>
                                <tbody id="queueBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-failed" class="sm-panel hidden">
                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th>Succursale</th>
                                        <th>Ticket / UUID</th>
                                        <th>Erreur</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="failedBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-conflicts" class="sm-panel hidden">
                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Source</th>
                                        <th>Succursale</th>
                                        <th>Ticket</th>
                                        <th>Raison</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="conflictsBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">—</td>
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
    <script src="../../assets/js/admin/admin-api.js?v=9"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=2"></script>
    <script src="../../assets/js/admin/sync-monitor.js?v=1"></script>
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
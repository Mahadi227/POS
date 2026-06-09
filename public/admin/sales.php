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
    <title>Ventes — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-sales.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=1">
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
                    <a href="sales.php" class="nav-link active">
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
                <?php $activePage = 'sales';
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
                        <h1>Historique des ventes</h1>
                        <p class="date-display" id="sales-date">—</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshSales" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="salesError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="stat-cards">
                    <div class="card stat-card as-stat is-loading">
                        <div class="card-icon primary">
                            <span class="material-icons-round">today</span>
                        </div>
                        <div class="card-info">
                            <h3>Ventes aujourd'hui</h3>
                            <h2 id="stat-today-count">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-today-revenue">—</p>
                        </div>
                    </div>
                    <div class="card stat-card as-stat is-loading">
                        <div class="card-icon success">
                            <span class="material-icons-round">payments</span>
                        </div>
                        <div class="card-info">
                            <h3>Panier moyen (jour)</h3>
                            <h2 id="stat-today-avg">—</h2>
                            <p class="trend ad-trend--neutral">Par transaction</p>
                        </div>
                    </div>
                    <div class="card stat-card as-stat is-loading">
                        <div class="card-icon info">
                            <span class="material-icons-round">date_range</span>
                        </div>
                        <div class="card-info">
                            <h3>7 derniers jours</h3>
                            <h2 id="stat-week-count">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-week-revenue">—</p>
                        </div>
                    </div>
                    <div class="card stat-card as-stat is-loading">
                        <div class="card-icon warning">
                            <span class="material-icons-round">calendar_month</span>
                        </div>
                        <div class="card-info">
                            <h3>30 derniers jours</h3>
                            <h2 id="stat-month-count">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-month-revenue">—</p>
                        </div>
                    </div>
                </div>

                <div class="as-chips" role="tablist" aria-label="Période">
                    <button type="button" class="as-chip active" data-period="today">Aujourd'hui</button>
                    <button type="button" class="as-chip" data-period="week">7 jours</button>
                    <button type="button" class="as-chip" data-period="month">30 jours</button>
                    <button type="button" class="as-chip" data-period="all">Toutes</button>
                </div>

                <div class="as-toolbar">
                    <div class="as-filters-row">
                        <div class="as-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="searchInput" placeholder="N° ticket, client, caissier…" autocomplete="off">
                            <button type="button" class="as-search-clear" id="searchClear" aria-label="Effacer">
                                <span class="material-icons-round">close</span>
                            </button>
                        </div>
                        <select id="paymentFilter" class="as-select" aria-label="Paiement">
                            <option value="">Tous paiements</option>
                            <option value="cash">Espèces</option>
                            <option value="card">Carte</option>
                            <option value="mobile_money">Mobile Money</option>
                        </select>
                        <div class="as-date-filter">
                            <label class="as-date-field">
                                <span class="material-icons-round">calendar_today</span>
                                <input type="date" id="salesStartDate" aria-label="Date de début">
                            </label>
                            <label class="as-date-field">
                                <span class="material-icons-round">calendar_today</span>
                                <input type="date" id="salesEndDate" aria-label="Date de fin">
                            </label>
                            <button type="button" class="as-btn as-btn--secondary" id="applyDateFilter">Appliquer</button>
                            <button type="button" class="as-btn as-btn--ghost" id="clearDateFilter">Effacer</button>
                        </div>
                    </div>
                </div>

                <div class="card table-widget">
                    <div class="as-table-meta">
                        <span id="tableSummary">Chargement…</span>
                        <div class="as-pagination">
                            <button type="button" id="pagePrev" disabled aria-label="Précédent">
                                <span class="material-icons-round">chevron_left</span>
                            </button>
                            <span id="pageInfo">1 / 1</span>
                            <button type="button" id="pageNext" disabled aria-label="Suivant">
                                <span class="material-icons-round">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table" id="salesTable">
                            <thead>
                                <tr>
                                    <th>N° Reçu</th>
                                    <th>Date</th>
                                    <th>Client</th>
                                    <th>Caissier</th>
                                    <th>Total</th>
                                    <th>Paiement</th>
                                    <th>Statut</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="salesTableBody">
                                <tr>
                                    <td colspan="8" class="ad-empty-row">Chargement…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="as-modal-overlay" id="saleDetailsModal">
        <div class="as-modal">
            <h2 id="modalTitle">Détails de la vente</h2>
            <div id="saleDetailsContent">
                <p class="ad-empty-row">Chargement…</p>
            </div>
            <div class="as-modal-actions">
                <button type="button" class="as-btn" id="printReceiptBtn" style="margin-right:auto;">
                    <span class="material-icons-round" style="font-size:18px;">print</span>
                    Imprimer reçu
                </button>
                <button type="button" class="as-btn" id="closeModalBtn">Fermer</button>
            </div>
        </div>
    </div>

    <div id="asToast" class="as-toast" role="status"></div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=4"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=1"></script>
    <script src="../../assets/js/admin/sales.js?v=2"></script>
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
        document.getElementById('sales-date').textContent = new Date().toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long',
            year: 'numeric',
        });
        document.getElementById('theme-toggle')?.addEventListener('click', () => {
            const html = document.documentElement;
            const next = html.getAttribute('data-theme') === 'dark' ? 'light' : 'dark';
            html.setAttribute('data-theme', next);
            localStorage.setItem('theme', next);
            document.querySelector('#theme-toggle .material-icons-round').textContent =
                next === 'dark' ? 'light_mode' : 'dark_mode';
        });
        if (localStorage.getItem('theme') === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            const icon = document.querySelector('#theme-toggle .material-icons-round');
            if (icon) icon.textContent = 'light_mode';
        }
    </script>
</body>

</html>
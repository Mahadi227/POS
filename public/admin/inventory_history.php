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
$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row && !empty($row['currency'])) $storeCurrency = $row['currency'];
} catch (Throwable $e) {
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Historique d'inventaire — RetailPOS</title>
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
                    <span class="material-icons-round">inventory_2</span>
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
                    <a href="inventory_history.php" class="nav-link active">
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
                    <a href="inventory_reports.php" class="nav-link">
                        <span class="material-icons-round">article</span>
                        <span>Rapports</span>
                    </a>
                </li>
                <li>
                    <a href="inventory_analytics.php" class="nav-link">
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
                        <h1>Historique d'inventaire</h1>
                        <p class="date-display" id="historyDate">—</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshHistory" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="card table-widget" style="padding: 18px 20px 20px 20px; margin-bottom: 18px;">
                    <div style="display:flex;flex-wrap:wrap;gap:14px;justify-content:space-between;align-items:flex-end;">
                        <div style="display:grid;gap:12px;flex:1;min-width:240px;">
                            <div class="inv-search" style="display:flex;align-items:center;gap:10px;">
                                <span class="material-icons-round">search</span>
                                <input type="search" id="historySearch" placeholder="Rechercher produit, SKU, utilisateur…" autocomplete="off" style="width:100%;padding:10px;" />
                            </div>
                            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:12px;">
                                <select id="historyMovementType" class="inv-select">
                                    <option value="">Tous les types</option>
                                    <option value="purchase">Achat</option>
                                    <option value="sale">Vente</option>
                                    <option value="return">Retour</option>
                                    <option value="transfer_in">Transfert entrant</option>
                                    <option value="transfer_out">Transfert sortant</option>
                                    <option value="adjustment">Ajustement</option>
                                    <option value="damaged">Endommagé</option>
                                    <option value="expired">Périmé</option>
                                    <option value="manual_edit">Édition manuelle</option>
                                </select>
                                <select id="historyStore" class="inv-select">
                                    <option value="">Tous les magasins</option>
                                </select>
                            </div>
                        </div>
                        <div style="display:grid;gap:12px;min-width:220px;">
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                                <label style="display:flex;flex-direction:column;gap:6px;font-size:0.85rem;color:var(--text-secondary);">
                                    Du
                                    <input type="date" id="historyDateFrom" class="inv-select" />
                                </label>
                                <label style="display:flex;flex-direction:column;gap:6px;font-size:0.85rem;color:var(--text-secondary);">
                                    Au
                                    <input type="date" id="historyDateTo" class="inv-select" />
                                </label>
                            </div>
                            <div style="display:flex;gap:10px;justify-content:flex-end;flex-wrap:wrap;">
                                <button type="button" class="inv-btn inv-btn-outline" id="clearHistoryFilters">Effacer</button>
                                <button type="button" class="inv-btn inv-btn-primary" id="refreshHistory">Actualiser</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card table-widget">
                    <div class="inv-table-meta" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
                        <span>Tableau de bord de l'historique des mouvements</span>
                        <div style="display:flex;gap:8px;flex-wrap:wrap;">
                            <span class="badge success" id="historyTotalEntries">0 entrées</span>
                            <span class="badge warning" id="historyTraceCount">0 traçabilité</span>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table">
                            <thead>
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th>Produit</th>
                                    <th>SKU / Code-barre</th>
                                    <th>Stock ouverture</th>
                                    <th>Entrée</th>
                                    <th>Sortie</th>
                                    <th>Stock actuel</th>
                                    <th>Valeur ouverture</th>
                                    <th>Valeur sortie</th>
                                    <th>Valeur actuelle</th>
                                    <th>Profit estimé</th>
                                    <th>Utilisateur</th>
                                    <th>Magasin</th>
                                    <th>Notes</th>
                                    <th>Traceability</th>
                                </tr>
                            </thead>
                            <tbody id="inventoryHistoryBody">
                                <tr>
                                    <td colspan="16" class="ad-empty-row">Chargement des historiques…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
        <div id="traceabilityModalOverlay" class="inv-modal-overlay" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);backdrop-filter:blur(2px);z-index:1000;justify-content:center;align-items:center;padding:20px;">
            <div class="inv-modal" style="background:#fff;border-radius:16px;max-width:760px;width:100%;padding:24px;box-shadow:0 24px 64px rgba(0,0,0,.12);">
                <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;margin-bottom:18px;">
                    <div>
                        <h2 style="margin:0;font-size:1.25rem;">Détails de traçabilité</h2>
                        <p style="margin:4px 0 0;color:var(--text-secondary);font-size:0.95rem;">Suivez l'origine, l'utilisateur et le commentaire du mouvement.</p>
                    </div>
                    <button type="button" class="icon-btn" id="closeTraceabilityModalBtn" aria-label="Fermer" style="font-size:1.35rem;">&#x2715;</button>
                </div>
                <div id="traceabilityModalContent" style="display:grid;gap:14px;font-size:0.95rem;color:#1f2937;"></div>
            </div>
        </div>
    </div>

    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>

    <script>
        window.INVENTORY_CONFIG = {
            userId: <?php echo json_encode($userId); ?>,
            storeId: <?php echo json_encode($storeId); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
        };
    </script>
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
        document.getElementById('historyDate').textContent = new Date().toLocaleDateString('fr-FR', {
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
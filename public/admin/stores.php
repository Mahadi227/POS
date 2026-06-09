<?php
require_once '../../includes/Config/session.php';
requireLogin();

require_once '../../includes/Database/Database.php';

$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$_SESSION['store_id'] ?? 1]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['currency'])) $storeCurrency = $r['currency'];
} catch (Throwable $e) {
    // fallback
}

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
// Restrict access: only super_admin may view this page
if ($roleSlug !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}
$storeCurrency = htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8');
$canManage = ($roleSlug === 'super_admin');
$isSuperAdmin = true;
$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title>Succursales — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=2">
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
                <li><a href="index.php" class="nav-link"><span class="material-icons-round">dashboard</span><span>Tableau de bord</span></a></li>
                <li><a href="sales.php" class="nav-link"><span class="material-icons-round">point_of_sale</span><span>Ventes</span></a></li>
                <li><a href="inventory.php" class="nav-link"><span class="material-icons-round">inventory_2</span><span>Inventaire</span></a></li>
                <?php $activePage = 'stores';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section">Système</li>
                <li><a href="../cashier/pos.php" class="nav-link"><span class="material-icons-round">shopping_cart</span><span>Terminal caisse</span></a></li>
                <li><a href="../logout.php" class="nav-link" style="color:var(--danger);margin-top:12px;"><span class="material-icons-round">logout</span><span>Déconnexion</span></a></li>
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
                    <button type="button" class="icon-btn mobile-menu-btn" id="mobileMenuBtn"><span class="material-icons-round">menu</span></button>
                    <div>
                        <h1>Succursales</h1>
                        <p class="date-display">Réseau multi-magasins</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshStoresBtn" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                    </button>
                    <?php if ($canManage): ?>
                        <button type="button" class="ad-refresh-btn" id="addStoreBtn">
                            <span class="material-icons-round">add_business</span>
                            Nouvelle succursale
                        </button>
                    <?php endif; ?>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle"><span class="material-icons-round">dark_mode</span></button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="storesError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="ms-tabs">
                    <button type="button" class="ms-tab active" data-panel="list">Liste des succursales</button>
                    <button type="button" class="ms-tab" data-panel="transfers">Transferts de stock</button>
                </div>

                <div id="panel-list" class="ms-panel">
                    <div class="ms-grid" id="storesGrid">
                        <p class="ad-empty-row">Chargement…</p>
                    </div>
                </div>

                <div id="panel-transfers" class="ms-panel hidden">
                    <div class="ms-tr-stats stat-cards">
                        <div class="card stat-card ms-tr-stat">
                            <div class="card-icon warning"><span class="material-icons-round">hourglass_top</span></div>
                            <div class="card-info">
                                <h3>En attente</h3>
                                <h2 id="trStatPending">—</h2>
                                <p class="trend ad-trend--neutral" id="trStatUnits">—</p>
                            </div>
                        </div>
                        <div class="card stat-card ms-tr-stat">
                            <div class="card-icon success"><span class="material-icons-round">check_circle</span></div>
                            <div class="card-info">
                                <h3>Acceptés</h3>
                                <h2 id="trStatAccepted">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card ms-tr-stat">
                            <div class="card-icon info"><span class="material-icons-round">cancel</span></div>
                            <div class="card-info">
                                <h3>Refusés</h3>
                                <h2 id="trStatRejected">—</h2>
                            </div>
                        </div>
                    </div>

                    <div class="ms-tr-toolbar">
                        <div class="as-chips" role="tablist" aria-label="Filtrer transferts">
                            <button type="button" class="as-chip active" data-tr-status="">Tous</button>
                            <button type="button" class="as-chip" data-tr-status="pending">En attente</button>
                            <button type="button" class="as-chip" data-tr-status="accepted">Acceptés</button>
                            <button type="button" class="as-chip" data-tr-status="rejected">Refusés</button>
                        </div>
                        <div class="ms-tr-filters">
                            <select id="trFilterFrom" class="um-select" title="Source">
                                <option value="">Toutes sources</option>
                            </select>
                            <select id="trFilterTo" class="um-select" title="Destination">
                                <option value="">Toutes destinations</option>
                            </select>
                            <div class="ms-tr-search">
                                <span class="material-icons-round">search</span>
                                <input type="search" id="trSearch" placeholder="Produit, SKU, succursale…" autocomplete="off">
                            </div>
                            <button type="button" class="ad-refresh-btn" id="refreshTransfersBtn" title="Actualiser">
                                <span class="material-icons-round">refresh</span>
                            </button>
                            <?php if ($canManage): ?>
                                <button type="button" class="ad-refresh-btn" id="newTransferBtn">
                                    <span class="material-icons-round">swap_horiz</span>
                                    Nouveau transfert
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card table-widget">
                        <div class="table-responsive">
                            <table class="modern-table ms-tr-table">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Produit</th>
                                        <th>Itinéraire</th>
                                        <th>Qté</th>
                                        <th>Statut</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="transfersBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row">Chargement…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Store modal -->
    <div class="as-modal-overlay" id="storeModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;padding:16px;">
        <div class="as-modal" style="background:var(--bg-surface);padding:24px;border-radius:16px;max-width:520px;width:100%;">
            <h2 id="storeModalTitle">Nouvelle succursale</h2>
            <p id="storeFormError" class="ad-error-banner" style="display:none;margin-bottom:12px;padding:10px 14px;">
                <span class="material-icons-round">error_outline</span>
                <span class="ad-error-text"></span>
            </p>
            <form id="storeForm">
                <input type="hidden" id="storeFormId">
                <div class="ms-form-grid">
                    <div class="inv-form-group"><label>Nom *</label><input type="text" id="sfName" required></div>
                    <div class="inv-form-group"><label>Code</label><input type="text" id="sfCode" placeholder="Auto"></div>
                    <div class="inv-form-group" style="grid-column:1/-1;"><label>Adresse</label><input type="text" id="sfLocation"></div>
                    <div class="inv-form-group"><label>Téléphone</label><input type="text" id="sfPhone"></div>
                    <div class="inv-form-group"><label>Email</label><input type="email" id="sfEmail"></div>
                    <div class="inv-form-group"><label>TVA %</label><input type="number" id="sfTax" value="18" step="0.01"></div>
                    <div class="inv-form-group"><label>Devise</label><input type="text" id="sfCurrency" value="<?php echo $storeCurrency; ?>"></div>
                    <div class="inv-form-group ms-form-check">
                        <label class="ms-check-label">
                            <input type="checkbox" id="sfActive" checked>
                            Succursale active
                        </label>
                    </div>
                </div>
                <div class="inv-modal-actions" style="margin-top:16px;">
                    <button type="button" class="as-btn" id="closeStoreModal">Annuler</button>
                    <button type="submit" class="as-btn as-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Transfer modal -->
    <div class="as-modal-overlay" id="transferModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,.45);z-index:100;align-items:center;justify-content:center;padding:16px;">
        <div class="as-modal ms-tr-modal" style="background:var(--bg-surface);padding:24px;border-radius:16px;max-width:560px;width:100%;max-height:90vh;overflow:auto;">
            <div class="ms-tr-modal-head">
                <h2>Nouveau transfert</h2>
                <button type="button" class="icon-btn" id="swapStoresBtn" title="Inverser source / destination">
                    <span class="material-icons-round">swap_horiz</span>
                </button>
            </div>
            <p id="transferFormError" class="ad-error-banner" style="display:none;margin-bottom:12px;padding:10px 14px;">
                <span class="material-icons-round">error_outline</span>
                <span class="ad-error-text"></span>
            </p>
            <form id="transferForm">
                <div class="ms-tr-route">
                    <div class="inv-form-group">
                        <label>De (source)</label>
                        <select id="tfFrom" required></select>
                    </div>
                    <span class="material-icons-round ms-tr-arrow">arrow_forward</span>
                    <div class="inv-form-group">
                        <label>Vers (destination)</label>
                        <select id="tfTo" required></select>
                    </div>
                </div>
                <div class="inv-form-group">
                    <label>Rechercher un produit</label>
                    <div class="ms-tr-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="tfProductSearch" placeholder="Nom, SKU ou code-barres…" autocomplete="off">
                    </div>
                </div>
                <div class="ms-tr-product-list" id="tfProductList">
                    <p class="ad-empty-row">Choisissez une succursale source</p>
                </div>
                <input type="hidden" id="tfProduct" required>
                <div class="ms-tr-selected hidden" id="tfSelectedBox">
                    <strong id="tfSelectedName">—</strong>
                    <span id="tfSelectedMeta">—</span>
                    <button type="button" class="as-btn" id="tfClearProduct">Changer</button>
                </div>
                <div class="inv-form-group">
                    <label>Quantité</label>
                    <input type="number" id="tfQty" min="1" value="1" required>
                    <small id="tfStockHint" class="ms-tr-hint"></small>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="as-btn" id="closeTransferModal">Annuler</button>
                    <button type="submit" class="as-btn as-btn-primary" id="tfSubmitBtn">Créer le transfert</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        window.STORES_PAGE = {
            canManage: <?php echo $canManage ? 'true' : 'false'; ?>,
            isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
        };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=8"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=1"></script>
    <script src="../../assets/js/admin/stores.js?v=6"></script>
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
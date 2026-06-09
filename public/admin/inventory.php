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
$storeName = '';
$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r) {
        if (!empty($r['currency'])) $storeCurrency = $r['currency'];
        $storeName = $r['name'] ?? '';
    }
} catch (Throwable $e) {
    // fallback to default
}
?>
<!DOCTYPE html>
<html lang="fr" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title>Inventaire — RetailPOS</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=1">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=2">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=1">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
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
                    <a href="inventory.php" class="nav-link active">
                        <span class="material-icons-round">inventory_2</span>
                        <span>Inventaire</span>
                        <span class="badge warning hidden" id="sidebar-low-stock-badge">0</span>
                    </a>
                </li>
                <?php $activePage = 'inventory';
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
                        <h1>Gestion de l'inventaire</h1>
                        <p class="date-display" id="inv-date">—</p>
                    </div>
                </div>
                <div class="header-right">
                    <button type="button" class="ad-refresh-btn" id="refreshInventory" title="Actualiser">
                        <span class="material-icons-round">refresh</span>
                        Actualiser
                    </button>
                    <button type="button" class="icon-btn theme-toggle" id="theme-toggle" aria-label="Thème">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="inventoryError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <div class="stat-cards">
                    <div class="card stat-card inv-stat is-loading" id="stat-total">
                        <div class="card-icon primary">
                            <span class="material-icons-round">inventory_2</span>
                        </div>
                        <div class="card-info">
                            <h3>Produits actifs</h3>
                            <h2 id="stat-total-val">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-categories-text">— catégories</p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-low">
                        <div class="card-icon warning">
                            <span class="material-icons-round">warning_amber</span>
                        </div>
                        <div class="card-info">
                            <h3>Stock faible</h3>
                            <h2 id="stat-low-val">—</h2>
                            <p class="trend negative">Sous le seuil d'alerte</p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-out">
                        <div class="card-icon" style="background:rgba(239,68,68,0.12);color:var(--danger);">
                            <span class="material-icons-round">remove_shopping_cart</span>
                        </div>
                        <div class="card-info">
                            <h3>Rupture de stock</h3>
                            <h2 id="stat-out-val">—</h2>
                            <p class="trend ad-trend--neutral">Quantité = 0</p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-value">
                        <div class="card-icon success">
                            <span class="material-icons-round">savings</span>
                        </div>
                        <div class="card-info">
                            <h3>Valeur stock (vente)</h3>
                            <h2 id="stat-value-val">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-units-text">— unités</p>
                        </div>
                    </div>
                </div>

                <div class="inv-chips" role="tablist" aria-label="Filtre stock">
                    <button type="button" class="inv-chip active" data-stock="all">Tous <span class="inv-chip-count" id="chip-all">0</span></button>
                    <button type="button" class="inv-chip" data-stock="ok">En stock <span class="inv-chip-count" id="chip-ok">0</span></button>
                    <button type="button" class="inv-chip" data-stock="low">Stock faible <span class="inv-chip-count" id="chip-low">0</span></button>
                    <button type="button" class="inv-chip" data-stock="out">Rupture <span class="inv-chip-count" id="chip-out">0</span></button>
                </div>

                <div class="inv-toolbar">
                    <div class="inv-filters">
                        <div class="inv-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="searchInput" placeholder="Nom, SKU, code-barre…" autocomplete="off">
                        </div>
                        <select id="categoryFilter" class="inv-select" aria-label="Catégorie">
                            <option value="">Toutes les catégories</option>
                        </select>
                        <select id="sortSelect" class="inv-select" aria-label="Tri">
                            <option value="name_asc">Nom A → Z</option>
                            <option value="name_desc">Nom Z → A</option>
                            <option value="stock_asc">Stock croissant</option>
                            <option value="stock_desc">Stock décroissant</option>
                            <option value="price_desc">Prix décroissant</option>
                        </select>
                    </div>
                    <div class="inv-actions">
                        <button type="button" class="inv-btn inv-btn-outline" id="importBtn" title="Bientôt disponible">
                            <span class="material-icons-round">upload_file</span>
                            Importer
                        </button>
                        <button type="button" class="inv-btn inv-btn-outline" id="scanBarcodeBtn">
                            <span class="material-icons-round">qr_code_scanner</span>
                            Scanner
                        </button>
                        <button type="button" class="inv-btn inv-btn-primary" id="addProductBtn">
                            <span class="material-icons-round">add</span>
                            Nouveau produit
                        </button>
                    </div>
                    <div class="inv-actions" style="gap:10px;margin-top:16px;flex-wrap:wrap;">
                        <a href="inventory_history.php" class="inv-btn inv-btn-outline">Historique</a>
                        <a href="stock_movements.php" class="inv-btn inv-btn-outline">Mouvements</a>
                        <a href="stock_transfers.php" class="inv-btn inv-btn-outline">Transferts</a>
                        <a href="inventory_reports.php" class="inv-btn inv-btn-outline">Rapports</a>
                        <a href="inventory_analytics.php" class="inv-btn inv-btn-outline">Analytics</a>
                        <a href="damaged_products.php" class="inv-btn inv-btn-outline">Endommagés</a>
                        <a href="expired_products.php" class="inv-btn inv-btn-outline">Périmés</a>
                    </div>
                </div>

                <div class="card table-widget">
                    <div class="inv-table-meta">
                        <span id="tableSummary">Chargement…</span>
                        <div class="inv-pagination">
                            <button type="button" id="pagePrev" aria-label="Page précédente" disabled>
                                <span class="material-icons-round">chevron_left</span>
                            </button>
                            <span id="pageInfo">1 / 1</span>
                            <button type="button" id="pageNext" aria-label="Page suivante" disabled>
                                <span class="material-icons-round">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table" id="productsTable">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Produit</th>
                                    <th>SKU / Code-barre</th>
                                    <th>Catégorie</th>
                                    <th>Prix</th>
                                    <th>Stock</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                                <tr>
                                    <td colspan="7" class="ad-empty-row">Chargement des produits…</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Product modal -->
    <div class="inv-modal-overlay" id="productModalOverlay">
        <div class="inv-modal">
            <h2 id="modalTitle">Ajouter un produit</h2>
            <form id="productForm">
                <input type="hidden" id="productId">
                <div class="inv-form-group">
                    <label for="productName">Nom du produit</label>
                    <input type="text" id="productName" required>
                </div>
                <div class="inv-form-group inv-image-field">
                    <label>Image du produit</label>
                    <div class="inv-image-picker">
                        <div class="inv-image-preview" id="productImagePreview">
                            <span class="material-icons-round">image</span>
                            <span class="inv-image-preview-hint">Aucune image</span>
                        </div>
                        <div class="inv-image-actions">
                            <label class="inv-btn inv-btn-outline inv-image-upload-btn" for="productImage">
                                <span class="material-icons-round">upload</span>
                                Choisir une image
                            </label>
                            <input type="file" id="productImage" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                            <button type="button" class="inv-btn inv-btn-outline" id="clearProductImageBtn" hidden>
                                <span class="material-icons-round">delete</span>
                                Retirer
                            </button>
                        </div>
                        <p class="inv-image-help">JPG, PNG, GIF ou WebP — affichée dans la caisse et l'inventaire.</p>
                    </div>
                </div>
                <div class="inv-form-group">
                    <label for="productCategory">Catégorie</label>
                    <div style="display:flex;gap:8px;">
                        <select id="productCategory" required style="flex:1;">
                            <option value="">Sélectionner…</option>
                        </select>
                        <button type="button" class="inv-btn inv-btn-outline" id="addCategoryBtn" title="Nouvelle catégorie">
                            <span class="material-icons-round">add</span>
                        </button>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productSku">SKU</label>
                        <input type="text" id="productSku" required>
                    </div>
                    <div class="inv-form-group">
                        <label for="productBarcode">Code-barre</label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="productBarcode" style="flex:1;">
                            <button type="button" class="inv-btn inv-btn-outline" id="generateBarcodeBtn" title="Générer">
                                <span class="material-icons-round">autorenew</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productPrice">Prix vente (<?php echo htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?>)</label>
                        <input type="number" id="productPrice" min="0" step="1" required>
                    </div>
                    <div class="inv-form-group">
                        <label for="productCost">Coût (<?php echo htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?>)</label>
                        <input type="number" id="productCost" min="0" step="1" required>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productStock">Stock</label>
                        <input type="number" id="productStock" min="0" value="0">
                    </div>
                    <div class="inv-form-group">
                        <label for="productMinStock">Alerte stock faible</label>
                        <input type="number" id="productMinStock" min="0" value="5">
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productUnit">Unité</label>
                        <select id="productUnit">
                            <option value="piece">Pièce</option>
                            <option value="kg">Kg</option>
                            <option value="liter">Litre</option>
                            <option value="box">Boîte</option>
                        </select>
                    </div>
                    <div class="inv-form-group">
                        <label for="productExpiry">Expiration (optionnel)</label>
                        <input type="date" id="productExpiry">
                    </div>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeModalBtn">Annuler</button>
                    <button type="submit" class="inv-btn inv-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Category modal -->
    <div class="inv-modal-overlay" id="categoryModalOverlay">
        <div class="inv-modal" style="max-width:420px;">
            <h2>Nouvelle catégorie</h2>
            <form id="categoryForm">
                <div class="inv-form-group">
                    <label for="categoryName">Nom</label>
                    <input type="text" id="categoryName" required>
                </div>
                <div class="inv-form-group">
                    <label for="categoryDescription">Description</label>
                    <textarea id="categoryDescription" rows="3"></textarea>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeCategoryModalBtn">Annuler</button>
                    <button type="submit" class="inv-btn inv-btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Scanner modal -->
    <div class="inv-modal-overlay" id="scannerModalOverlay">
        <div class="inv-modal" style="max-width:600px;">
            <h2>Scanner un code-barre</h2>
            <div id="qr-reader" style="min-height:280px;background:#111;border-radius:10px;overflow:hidden;"></div>
            <div class="inv-modal-actions">
                <button type="button" class="inv-btn inv-btn-outline" id="closeScannerBtn">Fermer</button>
            </div>
        </div>
    </div>

    <!-- Quick adjust modal -->
    <div class="inv-modal-overlay" id="quickAdjustModalOverlay">
        <div class="inv-modal" style="max-width:440px;">
            <h2>Ajuster le stock</h2>
            <div style="padding:14px;background:var(--bg-main);border-radius:10px;margin-bottom:16px;">
                <h3 id="qaProductName" style="margin:0 0 8px;font-size:1.1rem;"></h3>
                <p style="margin:0;color:var(--text-secondary);font-size:0.9rem;">SKU: <span id="qaProductSku"></span></p>
                <p style="margin:8px 0 0;color:var(--text-secondary);font-size:0.9rem;">Stock actuel: <strong id="qaCurrentStock"></strong></p>
            </div>
            <form id="quickAdjustForm">
                <input type="hidden" id="qaProductId">
                <div class="inv-form-group">
                    <label for="qaAddStock">Quantité à ajouter</label>
                    <input type="number" id="qaAddStock" value="1" min="1" required>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeQuickAdjustBtn">Annuler</button>
                    <button type="submit" class="inv-btn inv-btn-primary">Mettre à jour</button>
                </div>
            </form>
        </div>
    </div>

    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>
    <audio id="scan-beep" preload="auto" src="data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqPb3BxfoaPk5+wvc7W4unx+P7//Pvw5tza0sa9r6KVjIdvX1ZPRUJDPUpTV2NvdHyKkp6juMDL1+Lo8fj+//78+fLn3drXyr6wnpeQh25eVExGQ0I/SVRZYG1yeIeRm6C3wc3Y4+rx+f7/+vrl3drXyr6wnpeQh25eVExGQ0I+SlRZYG1yeIeRm6C3wc3Y4+rx+f7/+vrl3tvZy8GwopmPh3BcVE1HQkI+SlRZYG1yeIeRm6C3wc3Y4+rx+f7/+vrl3tvZy8GwopmPh3BcVE1HQkI/SVRZYG1xd4aPk5+wvc7W4unx+P7//Pvw5tza0sa9r6KVjIdvX1ZPRUJDPQ=="></audio>

    <script>
        window.INVENTORY_CONFIG = {
            userId: <?php echo $userId; ?>,
            storeId: <?php echo $storeId ?: 1; ?>,
            appUrl: <?php echo json_encode(rtrim(APP_URL, '/')); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            storeName: <?php echo json_encode($storeName); ?>,
        };
    </script>
    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=4"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=1"></script>
    <script src="../../assets/js/admin/inventory.js?v=6"></script>
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
        document.getElementById('inv-date').textContent = new Date().toLocaleDateString('fr-FR', {
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
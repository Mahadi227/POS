<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Config/config.php';
require_once '../../includes/Database/Database.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if (!in_array($roleSlug, ['admin', 'manager', 'super_admin'], true)) {
    header('Location: ../login.php');
    exit;
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';

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

$inventoryI18nKeys = [
    'loading', 'categories_count', 'units_in_stock', 'no_products', 'table_summary', 'no_products_found',
    'uncategorized', 'no_image', 'adjust_stock', 'edit', 'print', 'delete', 'select_category', 'all_categories',
    'load_error', 'connection_error', 'network_error', 'error', 'refreshed', 'delete_confirm', 'product_deleted',
    'no_barcode', 'label_title', 'modal_add_product', 'modal_edit_product', 'modal_new_scanned', 'image_required',
    'import_csv_soon', 'product_updated', 'product_created', 'category_created', 'stock_updated', 'scanner_not_loaded',
    'view_in_history', 'adjust_highlight_hint',
    'col_image', 'col_product', 'col_sku_barcode', 'col_category', 'col_price', 'stock', 'col_actions',
];
$inventoryI18n = [];
foreach ($inventoryI18nKeys as $key) {
    $inventoryI18n[$key] = __t($key, 'inventory');
}
foreach (['menu', 'refresh', 'theme', 'last_updated', 'nav_main', 'nav_dashboard', 'nav_sales', 'nav_analytics', 'nav_pos'] as $key) {
    $inventoryI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$currencyEsc = htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('title', 'inventory'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=9">
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.0/dist/JsBarcode.all.min.js"></script>
    <script src="https://unpkg.com/html5-qrcode" type="text/javascript"></script>
</head>

<body class="inv-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">storefront</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section"><?php echo __t('nav_main', 'admin'); ?></li>
                <li>
                    <a href="index.php" class="nav-link">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="sales.php" class="nav-link">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_sales', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory.php" class="nav-link active">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_inventory', 'admin'); ?></span>
                        <span class="badge warning hidden" id="sidebar-low-stock-badge">0</span>
                    </a>
                </li>
                <?php $activePage = 'inventory';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
                <li>
                    <a href="../cashier/pos.php" class="nav-link">
                        <span class="material-icons-round">shopping_cart</span>
                        <span><?php echo __t('nav_pos', 'admin'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="../logout.php" class="nav-link" style="color: var(--danger); margin-top: 12px;">
                        <span class="material-icons-round">logout</span>
                        <span><?php echo __t('logout', 'admin'); ?></span>
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
            <header class="top-header admin-page-header ad-page-header">
                <div class="header-left ad-header-left">
                    <button type="button" class="icon-btn mobile-menu-btn ad-header-menu" id="mobileMenuBtn" aria-label="<?php echo __t('menu', 'admin'); ?>">
                        <span class="material-icons-round">menu</span>
                    </button>
                    <div class="header-title-group">
                        <h1><?php echo __t('heading', 'inventory'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="inv-date">—</span>
                            <span class="header-dot" aria-hidden="true">·</span>
                            <span class="ih-last-updated" id="lastUpdated" aria-live="polite"></span>
                        </div>
                    </div>
                </div>

                <div class="header-tools ad-header-tools">
                    <div id="headerStoreSlot" class="header-store-slot"></div>
                    <?php include __DIR__ . '/../includes/language_switcher.php'; ?>
                </div>

                <div class="header-actions ad-header-actions">
                    <button type="button" class="ad-refresh-btn" id="refreshInventory" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="inventoryError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <nav class="ad-quick-nav" aria-label="<?php echo __t('nav_main', 'admin'); ?>">
                    <a href="index.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'admin'); ?></span>
                    </a>
                    <a href="sales.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">point_of_sale</span>
                        <span><?php echo __t('nav_sales', 'admin'); ?></span>
                    </a>
                    <a href="analytics.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">insights</span>
                        <span><?php echo __t('nav_analytics', 'admin'); ?></span>
                    </a>
                    <a href="../cashier/pos.php" class="ad-quick-nav__item ad-quick-nav__item--accent">
                        <span class="material-icons-round">shopping_cart</span>
                        <span><?php echo __t('nav_pos', 'admin'); ?></span>
                    </a>
                </nav>

                <div class="stat-cards ad-stat-cards inv-summary-cards">
                    <div class="card stat-card inv-stat is-loading" id="stat-total">
                        <div class="card-icon primary">
                            <span class="material-icons-round">inventory_2</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('active_products', 'inventory'); ?></h3>
                            <h2 id="stat-total-val">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-categories-text">—</p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-low">
                        <div class="card-icon warning">
                            <span class="material-icons-round">warning_amber</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('low_stock', 'inventory'); ?></h3>
                            <h2 id="stat-low-val">—</h2>
                            <p class="trend negative"><?php echo __t('below_threshold', 'inventory'); ?></p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-out">
                        <div class="card-icon danger">
                            <span class="material-icons-round">remove_shopping_cart</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('out_of_stock', 'inventory'); ?></h3>
                            <h2 id="stat-out-val">—</h2>
                            <p class="trend ad-trend--neutral"><?php echo __t('qty_zero', 'inventory'); ?></p>
                        </div>
                    </div>
                    <div class="card stat-card inv-stat is-loading" id="stat-value">
                        <div class="card-icon success">
                            <span class="material-icons-round">savings</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('stock_value', 'inventory'); ?></h3>
                            <h2 id="stat-value-val">—</h2>
                            <p class="trend ad-trend--neutral" id="stat-units-text">—</p>
                        </div>
                    </div>
                </div>

                <div class="inv-chips" role="tablist" aria-label="<?php echo __t('stock_filter_label', 'inventory'); ?>">
                    <button type="button" class="inv-chip active" data-stock="all"><?php echo __t('chip_all', 'inventory'); ?> <span class="inv-chip-count" id="chip-all">0</span></button>
                    <button type="button" class="inv-chip" data-stock="ok"><?php echo __t('chip_ok', 'inventory'); ?> <span class="inv-chip-count" id="chip-ok">0</span></button>
                    <button type="button" class="inv-chip" data-stock="low"><?php echo __t('low_stock', 'inventory'); ?> <span class="inv-chip-count" id="chip-low">0</span></button>
                    <button type="button" class="inv-chip" data-stock="out"><?php echo __t('chip_out', 'inventory'); ?> <span class="inv-chip-count" id="chip-out">0</span></button>
                </div>

                <div class="inv-toolbar">
                    <div class="inv-filters">
                        <div class="inv-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="searchInput" placeholder="<?php echo __t('search_placeholder', 'inventory'); ?>" autocomplete="off">
                        </div>
                        <select id="categoryFilter" class="inv-select" aria-label="<?php echo __t('category_filter', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_categories', 'inventory'); ?></option>
                        </select>
                        <select id="sortSelect" class="inv-select" aria-label="<?php echo __t('sort_filter', 'inventory'); ?>">
                            <option value="name_asc"><?php echo __t('sort_name_asc', 'inventory'); ?></option>
                            <option value="name_desc"><?php echo __t('sort_name_desc', 'inventory'); ?></option>
                            <option value="stock_asc"><?php echo __t('sort_stock_asc', 'inventory'); ?></option>
                            <option value="stock_desc"><?php echo __t('sort_stock_desc', 'inventory'); ?></option>
                            <option value="price_desc"><?php echo __t('sort_price_desc', 'inventory'); ?></option>
                        </select>
                    </div>
                    <div class="inv-actions">
                        <button type="button" class="inv-btn inv-btn-outline" id="importBtn" title="<?php echo __t('import_soon', 'inventory'); ?>">
                            <span class="material-icons-round">upload_file</span>
                            <?php echo __t('import', 'inventory'); ?>
                        </button>
                        <button type="button" class="inv-btn inv-btn-outline" id="scanBarcodeBtn">
                            <span class="material-icons-round">qr_code_scanner</span>
                            <?php echo __t('scan', 'inventory'); ?>
                        </button>
                        <button type="button" class="inv-btn inv-btn-primary" id="addProductBtn">
                            <span class="material-icons-round">add</span>
                            <?php echo __t('new_product', 'inventory'); ?>
                        </button>
                    </div>
                    <div class="inv-actions inv-links-row">
                        <a href="inventory_history.php" class="inv-btn inv-btn-outline"><?php echo __t('link_history', 'inventory'); ?></a>
                        <a href="stock_movements.php" class="inv-btn inv-btn-outline"><?php echo __t('link_movements', 'inventory'); ?></a>
                        <a href="stock_transfers.php" class="inv-btn inv-btn-outline"><?php echo __t('link_transfers', 'inventory'); ?></a>
                        <a href="inventory_reports.php" class="inv-btn inv-btn-outline"><?php echo __t('link_reports', 'inventory'); ?></a>
                        <a href="inventory_analytics.php" class="inv-btn inv-btn-outline"><?php echo __t('link_analytics', 'inventory'); ?></a>
                        <a href="damaged_products.php" class="inv-btn inv-btn-outline"><?php echo __t('link_damaged', 'inventory'); ?></a>
                        <a href="expired_products.php" class="inv-btn inv-btn-outline"><?php echo __t('link_expired', 'inventory'); ?></a>
                    </div>
                </div>

                <div class="card table-widget">
                    <div class="inv-table-meta">
                        <span id="tableSummary"><?php echo __t('loading', 'inventory'); ?></span>
                        <div class="inv-pagination">
                            <button type="button" id="pagePrev" aria-label="<?php echo __t('prev_page', 'inventory'); ?>" disabled>
                                <span class="material-icons-round">chevron_left</span>
                            </button>
                            <span id="pageInfo">1 / 1</span>
                            <button type="button" id="pageNext" aria-label="<?php echo __t('next_page', 'inventory'); ?>" disabled>
                                <span class="material-icons-round">chevron_right</span>
                            </button>
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="modern-table inv-products-table" id="productsTable">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_image', 'inventory'); ?></th>
                                    <th><?php echo __t('col_product', 'inventory'); ?></th>
                                    <th><?php echo __t('col_sku_barcode', 'inventory'); ?></th>
                                    <th><?php echo __t('col_category', 'inventory'); ?></th>
                                    <th><?php echo __t('col_price', 'inventory'); ?></th>
                                    <th><?php echo __t('stock', 'inventory'); ?></th>
                                    <th><?php echo __t('col_actions', 'inventory'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="productTableBody">
                                <tr>
                                    <td colspan="7" class="ad-empty-row"><?php echo __t('loading_products', 'inventory'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <div class="inv-modal-overlay" id="productModalOverlay">
        <div class="inv-modal">
            <h2 id="modalTitle"><?php echo __t('modal_add_product', 'inventory'); ?></h2>
            <form id="productForm">
                <input type="hidden" id="productId">
                <div class="inv-form-group">
                    <label for="productName"><?php echo __t('product_name', 'inventory'); ?></label>
                    <input type="text" id="productName" required>
                </div>
                <div class="inv-form-group inv-image-field">
                    <label><?php echo __t('product_image', 'inventory'); ?></label>
                    <div class="inv-image-picker">
                        <div class="inv-image-preview" id="productImagePreview">
                            <span class="material-icons-round">image</span>
                            <span class="inv-image-preview-hint"><?php echo __t('no_image', 'inventory'); ?></span>
                        </div>
                        <div class="inv-image-actions">
                            <label class="inv-btn inv-btn-outline inv-image-upload-btn" for="productImage">
                                <span class="material-icons-round">upload</span>
                                <?php echo __t('choose_image', 'inventory'); ?>
                            </label>
                            <input type="file" id="productImage" accept="image/jpeg,image/png,image/gif,image/webp" hidden>
                            <button type="button" class="inv-btn inv-btn-outline" id="clearProductImageBtn" hidden>
                                <span class="material-icons-round">delete</span>
                                <?php echo __t('remove_image', 'inventory'); ?>
                            </button>
                        </div>
                        <p class="inv-image-help"><?php echo __t('image_help', 'inventory'); ?></p>
                    </div>
                </div>
                <div class="inv-form-group">
                    <label for="productCategory"><?php echo __t('category', 'inventory'); ?></label>
                    <div style="display:flex;gap:8px;">
                        <select id="productCategory" required style="flex:1;">
                            <option value=""><?php echo __t('select_category', 'inventory'); ?></option>
                        </select>
                        <button type="button" class="inv-btn inv-btn-outline" id="addCategoryBtn" title="<?php echo __t('new_category_btn', 'inventory'); ?>">
                            <span class="material-icons-round">add</span>
                        </button>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productSku"><?php echo __t('sku', 'inventory'); ?></label>
                        <input type="text" id="productSku" required>
                    </div>
                    <div class="inv-form-group">
                        <label for="productBarcode"><?php echo __t('barcode', 'inventory'); ?></label>
                        <div style="display:flex;gap:8px;">
                            <input type="text" id="productBarcode" style="flex:1;">
                            <button type="button" class="inv-btn inv-btn-outline" id="generateBarcodeBtn" title="<?php echo __t('generate', 'inventory'); ?>">
                                <span class="material-icons-round">autorenew</span>
                            </button>
                        </div>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productPrice"><?php echo sprintf(__t('sale_price', 'inventory'), $currencyEsc); ?></label>
                        <input type="number" id="productPrice" min="0" step="1" required>
                    </div>
                    <div class="inv-form-group">
                        <label for="productCost"><?php echo sprintf(__t('cost', 'inventory'), $currencyEsc); ?></label>
                        <input type="number" id="productCost" min="0" step="1" required>
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productStock"><?php echo __t('stock', 'inventory'); ?></label>
                        <input type="number" id="productStock" min="0" value="0">
                    </div>
                    <div class="inv-form-group">
                        <label for="productMinStock"><?php echo __t('min_stock_alert', 'inventory'); ?></label>
                        <input type="number" id="productMinStock" min="0" value="5">
                    </div>
                </div>
                <div class="inv-form-row">
                    <div class="inv-form-group">
                        <label for="productUnit"><?php echo __t('unit', 'inventory'); ?></label>
                        <select id="productUnit">
                            <option value="piece"><?php echo __t('unit_piece', 'inventory'); ?></option>
                            <option value="kg"><?php echo __t('unit_kg', 'inventory'); ?></option>
                            <option value="liter"><?php echo __t('unit_liter', 'inventory'); ?></option>
                            <option value="box"><?php echo __t('unit_box', 'inventory'); ?></option>
                        </select>
                    </div>
                    <div class="inv-form-group">
                        <label for="productExpiry"><?php echo __t('expiry_optional', 'inventory'); ?></label>
                        <input type="date" id="productExpiry">
                    </div>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeModalBtn"><?php echo __t('cancel', 'inventory'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('save', 'inventory'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="inv-modal-overlay" id="categoryModalOverlay">
        <div class="inv-modal" style="max-width:420px;">
            <h2><?php echo __t('category_modal_title', 'inventory'); ?></h2>
            <form id="categoryForm">
                <div class="inv-form-group">
                    <label for="categoryName"><?php echo __t('category_name', 'inventory'); ?></label>
                    <input type="text" id="categoryName" required>
                </div>
                <div class="inv-form-group">
                    <label for="categoryDescription"><?php echo __t('category_description', 'inventory'); ?></label>
                    <textarea id="categoryDescription" rows="3"></textarea>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeCategoryModalBtn"><?php echo __t('cancel', 'inventory'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('save', 'inventory'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div class="inv-modal-overlay" id="scannerModalOverlay">
        <div class="inv-modal" style="max-width:600px;">
            <h2><?php echo __t('scanner_title', 'inventory'); ?></h2>
            <div id="qr-reader" style="min-height:280px;background:#111;border-radius:10px;overflow:hidden;"></div>
            <div class="inv-modal-actions">
                <button type="button" class="inv-btn inv-btn-outline" id="closeScannerBtn"><?php echo __t('close', 'inventory'); ?></button>
            </div>
        </div>
    </div>

    <div class="inv-modal-overlay" id="quickAdjustModalOverlay">
        <div class="inv-modal" style="max-width:440px;">
            <h2><?php echo __t('adjust_stock', 'inventory'); ?></h2>
            <div style="padding:14px;background:var(--bg-main);border-radius:10px;margin-bottom:16px;">
                <h3 id="qaProductName" style="margin:0 0 8px;font-size:1.1rem;"></h3>
                <p style="margin:0;color:var(--text-secondary);font-size:0.9rem;"><?php echo __t('qa_sku', 'inventory'); ?> <span id="qaProductSku"></span></p>
                <p style="margin:8px 0 0;color:var(--text-secondary);font-size:0.9rem;"><?php echo __t('qa_current_stock', 'inventory'); ?> <strong id="qaCurrentStock"></strong></p>
            </div>
            <form id="quickAdjustForm">
                <input type="hidden" id="qaProductId">
                <div class="inv-form-group">
                    <label for="qaAddStock"><?php echo __t('qty_to_add', 'inventory'); ?></label>
                    <input type="number" id="qaAddStock" value="1" min="1" required>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="closeQuickAdjustBtn"><?php echo __t('cancel', 'inventory'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('update', 'inventory'); ?></button>
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
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
        };
        window.INVENTORY_I18N = <?php echo json_encode($inventoryI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/inventory.js?v=9"></script>
    <script>
        const themeBtn = document.getElementById('theme-toggle');
        const savedTheme = localStorage.getItem('admin-theme');
        if (savedTheme) {
            document.documentElement.setAttribute('data-theme', savedTheme);
            const icon = themeBtn?.querySelector('.material-icons-round');
            if (icon) icon.textContent = savedTheme === 'dark' ? 'light_mode' : 'dark_mode';
        }
        themeBtn?.addEventListener('click', () => {
            const html = document.documentElement;
            const isDark = html.getAttribute('data-theme') === 'dark';
            html.setAttribute('data-theme', isDark ? 'light' : 'dark');
            localStorage.setItem('admin-theme', isDark ? 'light' : 'dark');
            const icon = themeBtn.querySelector('.material-icons-round');
            if (icon) icon.textContent = isDark ? 'dark_mode' : 'light_mode';
        });
    </script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

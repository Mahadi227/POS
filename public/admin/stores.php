<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Config/config.php';
require_once '../../includes/Database/Database.php';
requireLogin();

require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

$roleSlug = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
if ($roleSlug !== 'super_admin') {
    header('Location: ../login.php');
    exit;
}

$activeLang = $_SESSION['lang'] ?? (defined('ACTIVE_LANG') ? ACTIVE_LANG : 'en');
$locale = $activeLang === 'fr' ? 'fr-FR' : 'en-US';
$changeUrl = '../change_language.php';

$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$_SESSION['store_id'] ?? 1]);
    $r = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($r && !empty($r['currency'])) {
        $storeCurrency = $r['currency'];
    }
} catch (Throwable $e) {
}

$storesI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'connection_error', 'last_updated',
    'nav_main', 'nav_dashboard', 'nav_sales', 'nav_inventory', 'nav_management', 'nav_stores',
    'nav_users', 'nav_analytics', 'nav_inventory_analytics', 'nav_sync', 'nav_system', 'nav_pos',
    'stores_heading', 'stores_subtitle', 'new_store', 'stores_search_placeholder',
    'filter_all_stores', 'filter_active_stores', 'filter_inactive_stores',
    'stat_total_stores', 'stat_active_stores', 'stat_inactive_stores', 'stat_pending_transfers',
    'view_transfers', 'no_stores', 'no_stores_found', 'stores_table_summary',
    'stores_section_list', 'stores_scope', 'stores_kpi_total_meta', 'stores_kpi_active_meta',
    'stores_kpi_inactive_meta', 'stores_kpi_pending_meta', 'pending_transfers_alert', 'dash_all_stores',
    'store_active', 'store_inactive', 'store_modal_new', 'store_modal_edit',
    'store_name', 'store_code', 'store_location', 'store_phone', 'store_email',
    'store_tax', 'store_currency', 'store_active_label', 'store_code_auto',
    'edit_store', 'switch_store', 'delete_store', 'delete_store_title', 'delete_store_confirm',
    'delete_store_hint', 'delete_store_deps_users', 'delete_store_deps_products', 'delete_store_deps_sales',
    'store_saved', 'store_deleted', 'store_delete_error', 'staff_count', 'product_count', 'tax_rate_label',
];
$storesI18n = [];
foreach ($storesI18nKeys as $key) {
    $storesI18n[$key] = __t($key, 'admin');
}

$canManage = true;
$isSuperAdmin = true;
$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('stores_title', 'admin'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-stores.css?v=5">
</head>

<body class="ms-page ad-page">
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
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_inventory', 'admin'); ?></span>
                    </a>
                </li>
                <?php $activePage = 'stores';
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
                        <h1><?php echo __t('stores_heading', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="storesDate">—</span>
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
                    <button type="button" class="inv-btn inv-btn-primary ms-header-new" id="addStoreBtn">
                        <span class="material-icons-round">add_business</span>
                        <span class="btn-label"><?php echo __t('new_store', 'admin'); ?></span>
                    </button>
                    <button type="button" class="ad-refresh-btn" id="refreshStoresBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <a href="stock_transfers.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('view_transfers', 'admin'); ?>">
                        <span class="material-icons-round">compare_arrows</span>
                        <span class="btn-label"><?php echo __t('view_transfers', 'admin'); ?></span>
                    </a>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="storesError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="msHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="msHeroTitle"><?php echo __t('stores_subtitle', 'admin'); ?></h2>
                        <p class="ad-dash-hero__period" id="msHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="msHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero ms-summary-cards" id="msSummaryCards" role="group" aria-label="<?php echo __t('stores_heading', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading" id="stat-total">
                            <span class="ad-kpi__label"><?php echo __t('stat_total_stores', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-total-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('stores_kpi_total_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="stat-active">
                            <span class="ad-kpi__label"><?php echo __t('stat_active_stores', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-active-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('stores_kpi_active_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="stat-inactive">
                            <span class="ad-kpi__label"><?php echo __t('stat_inactive_stores', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-inactive-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('stores_kpi_inactive_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="stat-pending-tr">
                            <span class="ad-kpi__label"><?php echo __t('stat_pending_transfers', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-pending-tr-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('stores_kpi_pending_meta', 'admin'); ?></span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_management', 'admin'); ?>">
                        <a href="index.php" class="ad-quick-btn"><span class="material-icons-round">dashboard</span><?php echo __t('nav_dashboard', 'admin'); ?></a>
                        <a href="users.php" class="ad-quick-btn"><span class="material-icons-round">group</span><?php echo __t('nav_users', 'admin'); ?></a>
                        <a href="stock_transfers.php" class="ad-quick-btn"><span class="material-icons-round">compare_arrows</span><?php echo __t('view_transfers', 'admin'); ?></a>
                        <button type="button" class="ad-quick-btn ad-quick-btn--accent" id="addStoreBtnHero">
                            <span class="material-icons-round">add_business</span><?php echo __t('new_store', 'admin'); ?>
                        </button>
                    </nav>
                </section>

                <a href="stock_transfers.php" class="ad-alert-strip ad-alert-strip--info ad-dash-alert" id="msPendingAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">swap_horiz</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('stat_pending_transfers', 'admin'); ?></strong>
                        <span class="ad-alert-strip__msg" id="msPendingAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <div class="ms-dash-toolbar">
                    <div class="ms-dash-toolbar__top">
                        <div class="inv-chips ms-chips" role="tablist" aria-label="<?php echo __t('filter_all_stores', 'admin'); ?>">
                            <button type="button" class="inv-chip active" data-status="all" role="tab" aria-selected="true"><?php echo __t('filter_all_stores', 'admin'); ?></button>
                            <button type="button" class="inv-chip" data-status="active" role="tab"><?php echo __t('filter_active_stores', 'admin'); ?></button>
                            <button type="button" class="inv-chip" data-status="inactive" role="tab"><?php echo __t('filter_inactive_stores', 'admin'); ?></button>
                        </div>
                    </div>
                    <div class="ms-toolbar ms-toolbar--inline">
                        <div class="inv-search ms-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="storesSearch" placeholder="<?php echo __t('stores_search_placeholder', 'admin'); ?>" autocomplete="off">
                        </div>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="msStoresListTitle">
                    <h3 class="ad-dash-section__title" id="msStoresListTitle"><?php echo __t('stores_section_list', 'admin'); ?></h3>
                    <div class="ad-panel ms-grid-panel">
                        <div class="ms-table-meta">
                            <span id="storesSummary"><?php echo __t('loading', 'admin'); ?></span>
                        </div>
                        <div class="ad-panel__body ms-grid-body">
                            <div class="ms-grid" id="storesGrid">
                                <p class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></p>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <!-- Store form modal -->
    <div id="storeModalOverlay" class="inv-modal-overlay ms-modal-overlay">
        <div class="inv-modal ms-modal">
            <div class="ih-modal-head">
                <div>
                    <h2 id="storeModalTitle"><?php echo __t('store_modal_new', 'admin'); ?></h2>
                </div>
                <button type="button" class="icon-btn" id="closeStoreModal" aria-label="<?php echo __t('close', 'admin'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="ad-error-banner" id="storeFormError" style="display:none;margin-bottom:12px;">
                <span class="material-icons-round">error_outline</span>
                <span class="ad-error-text"></span>
            </div>
            <form id="storeForm">
                <input type="hidden" id="storeFormId">
                <div class="ms-form-grid">
                    <div class="inv-form-group">
                        <label for="sfName"><?php echo __t('store_name', 'admin'); ?> *</label>
                        <input type="text" id="sfName" required>
                    </div>
                    <div class="inv-form-group">
                        <label for="sfCode"><?php echo __t('store_code', 'admin'); ?></label>
                        <input type="text" id="sfCode" placeholder="<?php echo __t('store_code_auto', 'admin'); ?>">
                    </div>
                    <div class="inv-form-group" style="grid-column:1/-1;">
                        <label for="sfLocation"><?php echo __t('store_location', 'admin'); ?></label>
                        <input type="text" id="sfLocation">
                    </div>
                    <div class="inv-form-group">
                        <label for="sfPhone"><?php echo __t('store_phone', 'admin'); ?></label>
                        <input type="text" id="sfPhone">
                    </div>
                    <div class="inv-form-group">
                        <label for="sfEmail"><?php echo __t('store_email', 'admin'); ?></label>
                        <input type="email" id="sfEmail">
                    </div>
                    <div class="inv-form-group">
                        <label for="sfTax"><?php echo __t('store_tax', 'admin'); ?></label>
                        <input type="number" id="sfTax" value="18" step="0.01">
                    </div>
                    <div class="inv-form-group">
                        <label for="sfCurrency"><?php echo __t('store_currency', 'admin'); ?></label>
                        <input type="text" id="sfCurrency" value="<?php echo htmlspecialchars($storeCurrency, ENT_QUOTES, 'UTF-8'); ?>">
                    </div>
                    <div class="inv-form-group ms-form-check">
                        <label class="ms-check-label">
                            <input type="checkbox" id="sfActive" checked>
                            <?php echo __t('store_active_label', 'admin'); ?>
                        </label>
                    </div>
                </div>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="cancelStoreModal"><?php echo __t('cancel', 'admin'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('save', 'admin'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete confirmation modal -->
    <div id="deleteStoreModalOverlay" class="inv-modal-overlay ms-modal-overlay">
        <div class="inv-modal ms-modal ms-delete-modal">
            <h2 class="ms-delete-modal-title">
                <span class="material-icons-round ms-delete-icon">warning</span>
                <?php echo __t('delete_store_title', 'admin'); ?>
            </h2>
            <p class="ms-delete-text" id="deleteStoreText"></p>
            <ul class="ms-delete-deps hidden" id="deleteStoreDeps"></ul>
            <p class="ms-delete-hint"><?php echo __t('delete_store_hint', 'admin'); ?></p>
            <div class="inv-modal-actions">
                <button type="button" class="inv-btn inv-btn-outline" id="cancelDeleteStore"><?php echo __t('cancel', 'admin'); ?></button>
                <button type="button" class="inv-btn inv-btn-danger" id="confirmDeleteStore"><?php echo __t('delete_store', 'admin'); ?></button>
            </div>
        </div>
    </div>

    <div id="storesToast" class="inv-toast" role="status" aria-live="polite"></div>

    <script>
        window.STORES_PAGE = {
            canManage: <?php echo $canManage ? 'true' : 'false'; ?>,
            isSuperAdmin: <?php echo $isSuperAdmin ? 'true' : 'false'; ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
        };
        window.STORES_I18N = <?php echo json_encode($storesI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/stores.js?v=10"></script>
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

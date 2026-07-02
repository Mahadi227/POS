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
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        if (!empty($row['name'])) $storeName = $row['name'];
        if (!empty($row['currency'])) $storeCurrency = $row['currency'];
    }
} catch (Throwable $e) {
}

$transfersI18nKeys = [
    'loading', 'loading_transfers', 'no_transfers', 'transfers_table_summary',
    'col_product', 'col_quantity', 'col_from_store', 'col_to_store', 'col_status', 'col_sku', 'actions',
    'status_pending', 'status_accepted', 'status_rejected',
    'load_error', 'connection_error', 'error', 'stores_load_error', 'last_updated', 'store_fallback',
    'transfers_search_placeholder', 'all_statuses', 'all_from_stores', 'all_to_stores',
    'clear_filters', 'apply_filters',
    'stat_pending_transfers', 'stat_accepted', 'stat_rejected', 'stat_pending_units',
    'new_transfer', 'create_transfer', 'from_store_label', 'to_store_label',
    'select_product', 'search_product_placeholder', 'quantity_label',
    'accept_transfer', 'reject_transfer',
    'transfer_created', 'transfer_accepted', 'transfer_rejected',
    'confirm_accept', 'confirm_reject',
    'select_from_store_first', 'select_product_required', 'quantity_required',
    'stores_must_differ', 'insufficient_stock', 'available_stock',
    'cancel', 'close', 'prev_page', 'next_page', 'no_products_found',
    'transfers_subtitle', 'transfers_section_list',
    'st_kpi_pending_meta', 'st_kpi_accepted_meta', 'st_kpi_rejected_meta', 'st_kpi_units_meta',
    'nav_products', 'link_history', 'link_movements', 'link_reports', 'link_analytics', 'link_transfers',
];
$transfersI18n = [];
foreach ($transfersI18nKeys as $key) {
    $transfersI18n[$key] = __t($key, 'inventory');
}
foreach (['menu', 'refresh', 'theme', 'col_date', 'pending_transfers_alert'] as $key) {
    $transfersI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('transfers_title', 'inventory'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-stock-transfers.css?v=1">
</head>

<body class="st-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">compare_arrows</span>
                    <h2>RetailPOS<span class="dot">.</span></h2>
                </div>
            </div>
            <ul class="nav-menu">
                <li class="nav-section"><?php echo __t('nav_inventory_section', 'inventory'); ?></li>
                <li>
                    <a href="inventory.php" class="nav-link">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_products', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_history.php" class="nav-link">
                        <span class="material-icons-round">history</span>
                        <span><?php echo __t('link_history', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="stock_movements.php" class="nav-link">
                        <span class="material-icons-round">swap_horiz</span>
                        <span><?php echo __t('link_movements', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="stock_transfers.php" class="nav-link active">
                        <span class="material-icons-round">compare_arrows</span>
                        <span><?php echo __t('link_transfers', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_reports.php" class="nav-link">
                        <span class="material-icons-round">article</span>
                        <span><?php echo __t('link_reports', 'inventory'); ?></span>
                    </a>
                </li>
                <li>
                    <a href="inventory_analytics.php" class="nav-link">
                        <span class="material-icons-round">bar_chart</span>
                        <span><?php echo __t('link_analytics', 'inventory'); ?></span>
                    </a>
                </li>
                <?php $activePage = 'inventory';
                include __DIR__ . '/includes/sidebar-extra.php'; ?>
                <li class="nav-section"><?php echo __t('nav_system', 'admin'); ?></li>
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
                        <h1><?php echo __t('transfers_heading', 'inventory'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="transfersDate">—</span>
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
                    <button type="button" class="inv-btn inv-btn-primary st-header-new" id="newTransferBtn" title="<?php echo __t('new_transfer', 'inventory'); ?>">
                        <span class="material-icons-round">add</span>
                        <span class="btn-label"><?php echo __t('new_transfer', 'inventory'); ?></span>
                    </button>
                    <button type="button" class="ad-refresh-btn" id="refreshTransfersBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <a href="stock_movements.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('back_movements', 'inventory'); ?>">
                        <span class="material-icons-round">swap_horiz</span>
                        <span class="btn-label"><?php echo __t('back_movements', 'inventory'); ?></span>
                    </a>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="transfersError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="stHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="stHeroTitle"><?php echo __t('transfers_subtitle', 'inventory'); ?></h2>
                        <p class="ad-dash-hero__period" id="stHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="stHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero st-summary-cards" id="stSummaryCards" role="group" aria-label="<?php echo __t('transfers_heading', 'inventory'); ?>">
                        <article class="ad-kpi ad-kpi--warn is-loading" id="st-kpi-pending">
                            <span class="ad-kpi__label"><?php echo __t('stat_pending_transfers', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-pending-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('st_kpi_pending_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="st-kpi-accepted">
                            <span class="ad-kpi__label"><?php echo __t('stat_accepted', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-accepted-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('st_kpi_accepted_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="st-kpi-rejected">
                            <span class="ad-kpi__label"><?php echo __t('stat_rejected', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-rejected-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('st_kpi_rejected_meta', 'inventory'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="st-kpi-units">
                            <span class="ad-kpi__label"><?php echo __t('stat_pending_units', 'inventory'); ?></span>
                            <strong class="ad-kpi__value" id="stat-pending-units-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('st_kpi_units_meta', 'inventory'); ?></span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_inventory_section', 'inventory'); ?>">
                        <a href="inventory.php" class="ad-quick-btn"><span class="material-icons-round">inventory_2</span><?php echo __t('nav_products', 'inventory'); ?></a>
                        <a href="inventory_history.php" class="ad-quick-btn"><span class="material-icons-round">history</span><?php echo __t('link_history', 'inventory'); ?></a>
                        <a href="stock_movements.php" class="ad-quick-btn"><span class="material-icons-round">swap_horiz</span><?php echo __t('link_movements', 'inventory'); ?></a>
                        <button type="button" class="ad-quick-btn ad-quick-btn--accent" id="newTransferBtnHero">
                            <span class="material-icons-round">add</span><?php echo __t('new_transfer', 'inventory'); ?>
                        </button>
                    </nav>
                </section>

                <a href="#" class="ad-alert-strip ad-alert-strip--info ad-dash-alert" id="stPendingAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">hourglass_top</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('stat_pending_transfers', 'inventory'); ?></strong>
                        <span class="ad-alert-strip__msg" id="stPendingAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <div class="st-dash-toolbar">
                    <div class="st-dash-toolbar__top">
                        <div class="inv-chips st-chips" role="tablist" aria-label="<?php echo __t('col_status', 'inventory'); ?>">
                            <button type="button" class="inv-chip active" data-status="" role="tab" aria-selected="true"><?php echo __t('all_statuses', 'inventory'); ?></button>
                            <button type="button" class="inv-chip" data-status="pending" role="tab"><?php echo __t('status_pending', 'inventory'); ?></button>
                            <button type="button" class="inv-chip" data-status="accepted" role="tab"><?php echo __t('status_accepted', 'inventory'); ?></button>
                            <button type="button" class="inv-chip" data-status="rejected" role="tab"><?php echo __t('status_rejected', 'inventory'); ?></button>
                        </div>
                    </div>
                    <div class="ih-filters">
                        <div class="inv-search ih-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="transfersSearch" placeholder="<?php echo __t('transfers_search_placeholder', 'inventory'); ?>" autocomplete="off">
                        </div>
                        <select id="transfersFromStore" class="inv-select" aria-label="<?php echo __t('from_store_label', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_from_stores', 'inventory'); ?></option>
                        </select>
                        <select id="transfersToStore" class="inv-select" aria-label="<?php echo __t('to_store_label', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_to_stores', 'inventory'); ?></option>
                        </select>
                        <div class="ih-filter-actions">
                            <button type="button" class="inv-btn inv-btn-outline" id="clearTransfersFilters"><?php echo __t('clear_filters', 'inventory'); ?></button>
                            <button type="button" class="inv-btn inv-btn-primary" id="applyTransfersFilters"><?php echo __t('apply_filters', 'inventory'); ?></button>
                        </div>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="stListTitle">
                    <h3 class="ad-dash-section__title" id="stListTitle"><?php echo __t('transfers_section_list', 'inventory'); ?></h3>
                    <div class="ad-panel st-table-panel">
                        <div class="inv-table-meta ih-table-meta">
                            <span id="tableSummary"><?php echo __t('loading_transfers', 'inventory'); ?></span>
                            <div class="inv-pagination">
                                <button type="button" id="pagePrev" disabled aria-label="<?php echo __t('prev_page', 'inventory'); ?>">
                                    <span class="material-icons-round">chevron_left</span>
                                </button>
                                <span id="pageInfo">1 / 1</span>
                                <button type="button" id="pageNext" disabled aria-label="<?php echo __t('next_page', 'inventory'); ?>">
                                    <span class="material-icons-round">chevron_right</span>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive ih-table-wrap">
                            <table class="modern-table st-transfers-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_product', 'inventory'); ?></th>
                                        <th><?php echo __t('col_sku', 'inventory'); ?></th>
                                        <th><?php echo __t('col_quantity', 'inventory'); ?></th>
                                        <th><?php echo __t('col_from_store', 'inventory'); ?></th>
                                        <th><?php echo __t('col_to_store', 'inventory'); ?></th>
                                        <th><?php echo __t('col_status', 'inventory'); ?></th>
                                        <th><?php echo __t('actions', 'inventory'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="stockTransfersBody">
                                    <tr>
                                        <td colspan="8" class="ad-empty-row"><?php echo __t('loading_transfers', 'inventory'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="inv-modal-overlay" id="createTransferOverlay">
        <div class="inv-modal" style="max-width:560px;">
            <div class="ih-modal-head">
                <div>
                    <h2><?php echo __t('new_transfer', 'inventory'); ?></h2>
                    <p><?php echo __t('transfers_subtitle', 'inventory'); ?></p>
                </div>
                <button type="button" class="icon-btn" id="closeCreateTransfer" aria-label="<?php echo __t('close', 'inventory'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <form id="createTransferForm" class="st-modal-form">
                <label>
                    <?php echo __t('from_store_label', 'inventory'); ?>
                    <select id="transferFromStore" class="inv-select" required></select>
                </label>
                <label>
                    <?php echo __t('to_store_label', 'inventory'); ?>
                    <select id="transferToStore" class="inv-select" required></select>
                </label>
                <label>
                    <?php echo __t('select_product', 'inventory'); ?>
                    <div class="inv-search">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="transferProductSearch" placeholder="<?php echo __t('search_product_placeholder', 'inventory'); ?>" autocomplete="off">
                    </div>
                    <div id="transferProductList" class="st-product-list">
                        <div class="st-empty-products"><?php echo __t('select_from_store_first', 'inventory'); ?></div>
                    </div>
                    <input type="hidden" id="transferProductId" value="">
                </label>
                <label>
                    <?php echo __t('quantity_label', 'inventory'); ?>
                    <input type="number" id="transferQuantity" class="inv-select" min="1" step="1" required>
                    <p class="st-stock-hint" id="transferStockHint"></p>
                </label>
                <div class="inv-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="cancelCreateTransfer"><?php echo __t('cancel', 'inventory'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary" id="submitCreateTransfer"><?php echo __t('create_transfer', 'inventory'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="invToast" class="inv-toast" role="status" aria-live="polite"></div>

    <script>
        window.INVENTORY_CONFIG = {
            userId: <?php echo json_encode($userId); ?>,
            storeId: <?php echo json_encode($storeId); ?>,
            storeName: <?php echo json_encode($storeName); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
        };
        window.INVENTORY_I18N = <?php echo json_encode($transfersI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/stock-transfers.js?v=4"></script>
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

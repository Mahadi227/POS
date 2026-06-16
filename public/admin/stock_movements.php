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

$movementsI18nKeys = [
    'loading', 'loading_movements', 'no_movements', 'movements_table_summary',
    'mov_purchase', 'mov_sale', 'mov_return', 'mov_transfer_in', 'mov_transfer_out', 'mov_adjustment',
    'mov_damaged', 'mov_expired', 'mov_manual_edit', 'type_transfer',
    'col_type', 'col_product', 'col_user', 'col_notes', 'col_from_store', 'col_to_store', 'col_store',
    'col_quantity', 'col_status', 'status_completed', 'status_pending', 'status_accepted', 'status_rejected',
    'load_error', 'connection_error', 'error', 'stores_load_error', 'last_updated', 'store_fallback',
    'period_today', 'period_week', 'period_month', 'period_all', 'period_label',
    'movements_search_placeholder', 'all_movement_types', 'all_stores', 'all_statuses',
    'date_from_label', 'date_to_label', 'clear_filters', 'apply_filters',
    'stat_movements', 'stat_total_in', 'stat_total_out', 'stat_pending_transfers',
];
$movementsI18n = [];
foreach ($movementsI18nKeys as $key) {
    $movementsI18n[$key] = __t($key, 'inventory');
}
foreach (['menu', 'refresh', 'theme', 'col_date', 'prev_page', 'next_page'] as $key) {
    $movementsI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('movements_title', 'inventory'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=13">
</head>

<body class="mv-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">swap_horiz</span>
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
                    <a href="stock_movements.php" class="nav-link active">
                        <span class="material-icons-round">swap_horiz</span>
                        <span><?php echo __t('link_movements', 'inventory'); ?></span>
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
                        <h1><?php echo __t('movements_heading', 'inventory'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="movementsDate">—</span>
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
                    <button type="button" class="ad-refresh-btn" id="refreshMovementsBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <a href="stock_transfers.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('link_transfers', 'inventory'); ?>">
                        <span class="material-icons-round">local_shipping</span>
                        <span class="btn-label"><?php echo __t('link_transfers', 'inventory'); ?></span>
                    </a>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="movementsError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <nav class="ad-quick-nav" aria-label="<?php echo __t('nav_inventory_section', 'inventory'); ?>">
                    <a href="inventory.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">inventory_2</span>
                        <span><?php echo __t('nav_products', 'inventory'); ?></span>
                    </a>
                    <a href="inventory_history.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">history</span>
                        <span><?php echo __t('link_history', 'inventory'); ?></span>
                    </a>
                    <a href="stock_transfers.php" class="ad-quick-nav__item ad-quick-nav__item--accent">
                        <span class="material-icons-round">compare_arrows</span>
                        <span><?php echo __t('link_transfers', 'inventory'); ?></span>
                    </a>
                    <a href="inventory_reports.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">article</span>
                        <span><?php echo __t('link_reports', 'inventory'); ?></span>
                    </a>
                </nav>

                <p class="mv-subtitle"><?php echo __t('movements_subtitle', 'inventory'); ?></p>

                <div class="stat-cards ad-stat-cards mv-summary-cards">
                    <div class="card stat-card ih-stat is-loading">
                        <div class="card-icon primary">
                            <span class="material-icons-round">swap_horiz</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('stat_movements', 'inventory'); ?></h3>
                            <h2 id="stat-movements">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ih-stat is-loading">
                        <div class="card-icon success">
                            <span class="material-icons-round">add_box</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('stat_total_in', 'inventory'); ?></h3>
                            <h2 id="stat-total-in">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ih-stat is-loading">
                        <div class="card-icon warning">
                            <span class="material-icons-round">indeterminate_check_box</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('stat_total_out', 'inventory'); ?></h3>
                            <h2 id="stat-total-out">—</h2>
                        </div>
                    </div>
                    <div class="card stat-card ih-stat is-loading">
                        <div class="card-icon info">
                            <span class="material-icons-round">hourglass_top</span>
                        </div>
                        <div class="card-info">
                            <h3><?php echo __t('stat_pending_transfers', 'inventory'); ?></h3>
                            <h2 id="stat-pending">—</h2>
                        </div>
                    </div>
                </div>

                <div class="inv-chips ih-chips" role="tablist" aria-label="<?php echo __t('period_label', 'inventory'); ?>">
                    <button type="button" class="inv-chip active" data-period="all"><?php echo __t('period_all', 'inventory'); ?></button>
                    <button type="button" class="inv-chip" data-period="today"><?php echo __t('period_today', 'inventory'); ?></button>
                    <button type="button" class="inv-chip" data-period="week"><?php echo __t('period_week', 'inventory'); ?></button>
                    <button type="button" class="inv-chip" data-period="month"><?php echo __t('period_month', 'inventory'); ?></button>
                </div>

                <div class="card table-widget ih-filters-card">
                    <div class="ih-filters">
                        <div class="inv-search ih-search">
                            <span class="material-icons-round">search</span>
                            <input type="search" id="movementsSearch" placeholder="<?php echo __t('movements_search_placeholder', 'inventory'); ?>" autocomplete="off">
                        </div>
                        <select id="movementsType" class="inv-select" aria-label="<?php echo __t('col_type', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_movement_types', 'inventory'); ?></option>
                            <option value="purchase"><?php echo __t('mov_purchase', 'inventory'); ?></option>
                            <option value="sale"><?php echo __t('mov_sale', 'inventory'); ?></option>
                            <option value="return"><?php echo __t('mov_return', 'inventory'); ?></option>
                            <option value="transfer_in"><?php echo __t('mov_transfer_in', 'inventory'); ?></option>
                            <option value="transfer_out"><?php echo __t('mov_transfer_out', 'inventory'); ?></option>
                            <option value="transfer"><?php echo __t('type_transfer', 'inventory'); ?></option>
                            <option value="adjustment"><?php echo __t('mov_adjustment', 'inventory'); ?></option>
                            <option value="damaged"><?php echo __t('mov_damaged', 'inventory'); ?></option>
                            <option value="expired"><?php echo __t('mov_expired', 'inventory'); ?></option>
                            <option value="manual_edit"><?php echo __t('mov_manual_edit', 'inventory'); ?></option>
                        </select>
                        <select id="movementsStore" class="inv-select" aria-label="<?php echo __t('col_store', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_stores', 'inventory'); ?></option>
                        </select>
                        <select id="movementsStatus" class="inv-select" aria-label="<?php echo __t('col_status', 'inventory'); ?>">
                            <option value=""><?php echo __t('all_statuses', 'inventory'); ?></option>
                            <option value="completed"><?php echo __t('status_completed', 'inventory'); ?></option>
                            <option value="pending"><?php echo __t('status_pending', 'inventory'); ?></option>
                            <option value="accepted"><?php echo __t('status_accepted', 'inventory'); ?></option>
                            <option value="rejected"><?php echo __t('status_rejected', 'inventory'); ?></option>
                        </select>
                        <label class="ih-date-field">
                            <span><?php echo __t('date_from_label', 'inventory'); ?></span>
                            <input type="date" id="movementsDateFrom" class="inv-select">
                        </label>
                        <label class="ih-date-field">
                            <span><?php echo __t('date_to_label', 'inventory'); ?></span>
                            <input type="date" id="movementsDateTo" class="inv-select">
                        </label>
                        <div class="ih-filter-actions">
                            <button type="button" class="inv-btn inv-btn-outline" id="clearMovementsFilters"><?php echo __t('clear_filters', 'inventory'); ?></button>
                            <button type="button" class="inv-btn inv-btn-primary" id="applyMovementsFilters"><?php echo __t('apply_filters', 'inventory'); ?></button>
                        </div>
                    </div>
                </div>

                <div class="card table-widget">
                    <div class="inv-table-meta ih-table-meta">
                        <span id="tableSummary"><?php echo __t('loading_movements', 'inventory'); ?></span>
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
                        <table class="modern-table mv-movements-table">
                            <thead>
                                <tr>
                                    <th><?php echo __t('col_date', 'admin'); ?></th>
                                    <th><?php echo __t('col_product', 'inventory'); ?></th>
                                    <th><?php echo __t('col_type', 'inventory'); ?></th>
                                    <th><?php echo __t('col_quantity', 'inventory'); ?></th>
                                    <th><?php echo __t('col_from_store', 'inventory'); ?></th>
                                    <th><?php echo __t('col_to_store', 'inventory'); ?></th>
                                    <th><?php echo __t('col_user', 'inventory'); ?></th>
                                    <th><?php echo __t('col_status', 'inventory'); ?></th>
                                    <th><?php echo __t('col_notes', 'inventory'); ?></th>
                                </tr>
                            </thead>
                            <tbody id="stockMovementsBody">
                                <tr>
                                    <td colspan="9" class="ad-empty-row"><?php echo __t('loading_movements', 'inventory'); ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
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
        window.INVENTORY_I18N = <?php echo json_encode($movementsI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/stock-movements.js?v=2"></script>
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

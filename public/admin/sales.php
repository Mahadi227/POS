<?php
require_once '../../includes/Config/session.php';
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

$adminI18nKeys = [
    'loading', 'date_from', 'date_until', 'date_range', 'period_today', 'last_7_days', 'last_30_days', 'period_all_sales',
    'no_sales', 'table_summary', 'no_sales_found', 'system_cashier', 'details', 'print', 'popup_blocked',
    'load_error', 'connection_error', 'network_error', 'error', 'sale_title',
    'modal_receipt', 'modal_date', 'modal_cashier', 'modal_customer', 'modal_payment', 'modal_status', 'modal_store',
    'col_product', 'col_qty', 'col_unit_price', 'col_subtotal', 'no_items',
    'subtotal_label', 'tax_label', 'discount_label', 'total_label',
    'pay_cash', 'pay_card', 'pay_mobile_money',
    'status_completed', 'status_cancelled', 'status_refunded', 'status_pending',
    'last_updated', 'menu', 'refresh', 'theme', 'clear_search',
    'col_receipt_no', 'col_date', 'col_customer', 'col_cashier', 'col_total', 'col_payment', 'col_status', 'col_actions',
    'new_sale', 'edit_sale', 'edit', 'action_cancel', 'action_delete', 'sale_status_label', 'sale_discount_label',
    'cancel_sale_confirm', 'delete_sale_confirm', 'sale_updated', 'sale_cancelled', 'sale_deleted',     'sale_delete_blocked', 'status_completed',
    'stat_transactions', 'stat_avg_ticket', 'stat_total_revenue', 'stat_completed_share', 'stat_cancelled_amount',
    'stat_search_filter', 'stat_payment_filter',
    'nav_dashboard', 'nav_inventory', 'nav_analytics', 'nav_pos', 'period_week', 'period_month', 'period_all',
    'sales_subtitle', 'sales_section_list', 'dash_all_stores',
];
$adminI18n = [];
foreach ($adminI18nKeys as $key) {
    $adminI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('sales_title', 'admin'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=13">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=7">
    <link rel="stylesheet" href="../../assets/css/admin-sales.css?v=11">
</head>

<body class="as-page ad-page">
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
                    <a href="sales.php" class="nav-link active">
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
                <?php $activePage = 'sales';
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
                        <h1><?php echo __t('sales_heading', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="sales-date">—</span>
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
                    <a href="../cashier/pos.php" class="as-btn as-btn-primary as-btn--header">
                        <span class="material-icons-round">add_shopping_cart</span>
                        <span class="btn-label"><?php echo __t('new_sale', 'admin'); ?></span>
                    </a>
                    <button type="button" class="ad-refresh-btn" id="refreshSales" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="salesError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="asSalesHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="asSalesHeroTitle"><?php echo __t('sales_subtitle', 'admin'); ?></h2>
                        <p class="ad-dash-hero__period" id="asSalesPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="asSalesStoreScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero as-summary-cards" id="salesSummaryCards" role="group" aria-label="<?php echo __t('sales_heading', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--primary is-loading">
                            <span class="ad-kpi__label" id="stat-card-1-title"><?php echo __t('stat_transactions', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-today-count">—</strong>
                            <span class="ad-kpi__meta" id="stat-today-revenue">—</span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading">
                            <span class="ad-kpi__label" id="stat-card-2-title"><?php echo __t('stat_avg_ticket', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-today-avg">—</strong>
                            <span class="ad-kpi__meta" id="stat-card-2-sub"><?php echo __t('per_transaction', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--neutral is-loading">
                            <span class="ad-kpi__label" id="stat-card-3-title"><?php echo __t('status_completed', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-week-count">—</strong>
                            <span class="ad-kpi__meta" id="stat-week-revenue">—</span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading">
                            <span class="ad-kpi__label" id="stat-card-4-title"><?php echo __t('status_cancelled', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="stat-month-count">—</strong>
                            <span class="ad-kpi__meta" id="stat-month-revenue">—</span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_main', 'admin'); ?>">
                        <a href="index.php" class="ad-quick-btn"><span class="material-icons-round">dashboard</span><?php echo __t('nav_dashboard', 'admin'); ?></a>
                        <a href="inventory.php" class="ad-quick-btn"><span class="material-icons-round">inventory_2</span><?php echo __t('nav_inventory', 'admin'); ?></a>
                        <a href="analytics.php" class="ad-quick-btn"><span class="material-icons-round">insights</span><?php echo __t('nav_analytics', 'admin'); ?></a>
                        <a href="../cashier/pos.php" class="ad-quick-btn ad-quick-btn--accent"><span class="material-icons-round">add_shopping_cart</span><?php echo __t('new_sale', 'admin'); ?></a>
                    </nav>
                </section>

                <div class="as-dash-toolbar">
                    <div class="as-dash-toolbar__top">
                        <div class="as-chips" role="tablist" aria-label="<?php echo __t('period_label', 'admin'); ?>">
                            <button type="button" class="as-chip active" data-period="today" role="tab" aria-selected="true"><?php echo __t('period_today', 'admin'); ?></button>
                            <button type="button" class="as-chip" data-period="week" role="tab"><?php echo __t('period_week', 'admin'); ?></button>
                            <button type="button" class="as-chip" data-period="month" role="tab"><?php echo __t('period_month', 'admin'); ?></button>
                            <button type="button" class="as-chip" data-period="all" role="tab"><?php echo __t('period_all', 'admin'); ?></button>
                        </div>
                    </div>
                    <div class="as-toolbar as-toolbar--inline">
                        <div class="as-filters-row">
                            <div class="as-search">
                                <span class="material-icons-round">search</span>
                                <input type="search" id="searchInput" placeholder="<?php echo __t('sales_search_placeholder', 'admin'); ?>" autocomplete="off">
                                <button type="button" class="as-search-clear" id="searchClear" aria-label="<?php echo __t('clear_search', 'admin'); ?>">
                                    <span class="material-icons-round">close</span>
                                </button>
                            </div>
                            <select id="paymentFilter" class="as-select" aria-label="<?php echo __t('payment_filter', 'admin'); ?>">
                                <option value=""><?php echo __t('all_payments', 'admin'); ?></option>
                                <option value="cash"><?php echo __t('pay_cash', 'admin'); ?></option>
                                <option value="card"><?php echo __t('pay_card', 'admin'); ?></option>
                                <option value="mobile_money"><?php echo __t('pay_mobile_money', 'admin'); ?></option>
                            </select>
                            <div class="as-date-filter">
                                <label class="as-date-field">
                                    <span class="material-icons-round">calendar_today</span>
                                    <input type="date" id="salesStartDate" aria-label="<?php echo __t('start_date', 'admin'); ?>">
                                </label>
                                <label class="as-date-field">
                                    <span class="material-icons-round">calendar_today</span>
                                    <input type="date" id="salesEndDate" aria-label="<?php echo __t('end_date', 'admin'); ?>">
                                </label>
                                <button type="button" class="as-btn as-btn--secondary" id="applyDateFilter"><?php echo __t('apply_filter', 'admin'); ?></button>
                                <button type="button" class="as-btn as-btn--ghost" id="clearDateFilter"><?php echo __t('clear_filter', 'admin'); ?></button>
                            </div>
                        </div>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="asSalesListTitle">
                    <h3 class="ad-dash-section__title" id="asSalesListTitle"><?php echo __t('sales_section_list', 'admin'); ?></h3>
                    <div class="ad-panel as-table-panel">
                        <div class="as-table-meta">
                            <span id="tableSummary"><?php echo __t('loading', 'admin'); ?></span>
                            <div class="as-pagination">
                                <button type="button" id="pagePrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>">
                                    <span class="material-icons-round">chevron_left</span>
                                </button>
                                <span id="pageInfo">1 / 1</span>
                                <button type="button" id="pageNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>">
                                    <span class="material-icons-round">chevron_right</span>
                                </button>
                            </div>
                        </div>
                        <div class="ad-panel__body table-responsive">
                            <table class="modern-table as-sales-table" id="salesTable">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_receipt_no', 'admin'); ?></th>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_customer', 'admin'); ?></th>
                                        <th><?php echo __t('col_cashier', 'admin'); ?></th>
                                        <th><?php echo __t('col_total', 'admin'); ?></th>
                                        <th><?php echo __t('col_payment', 'admin'); ?></th>
                                        <th><?php echo __t('col_status', 'admin'); ?></th>
                                        <th><?php echo __t('col_actions', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="salesTableBody">
                                    <tr>
                                        <td colspan="8" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <div class="as-modal-overlay" id="saleEditModal">
        <div class="as-modal as-modal--sm">
            <h2 id="saleEditTitle"><?php echo __t('edit_sale', 'admin'); ?></h2>
            <form id="saleEditForm" class="as-edit-form">
                <label class="as-field">
                    <span><?php echo __t('sale_status_label', 'admin'); ?></span>
                    <select id="editSaleStatus" class="as-select">
                        <option value="completed"><?php echo __t('status_completed', 'admin'); ?></option>
                        <option value="pending"><?php echo __t('status_pending', 'admin'); ?></option>
                        <option value="cancelled"><?php echo __t('status_cancelled', 'admin'); ?></option>
                    </select>
                </label>
                <label class="as-field">
                    <span><?php echo __t('sale_discount_label', 'admin'); ?></span>
                    <input type="number" id="editSaleDiscount" class="as-input" min="0" step="0.01" value="0">
                </label>
                <div class="as-modal-actions">
                    <button type="button" class="as-btn as-btn--ghost" id="closeEditModalBtn"><?php echo __t('cancel', 'admin'); ?></button>
                    <button type="submit" class="as-btn as-btn-primary">
                        <span class="material-icons-round">save</span>
                        <?php echo __t('save', 'admin'); ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <div class="as-modal-overlay" id="saleDetailsModal">
        <div class="as-modal">
            <h2 id="modalTitle"><?php echo __t('sale_details', 'admin'); ?></h2>
            <div id="saleDetailsContent">
                <p class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></p>
            </div>
            <div class="as-modal-actions">
                <button type="button" class="as-btn as-btn--ghost" id="editFromDetailsBtn">
                    <span class="material-icons-round">edit</span>
                    <?php echo __t('edit', 'admin'); ?>
                </button>
                <button type="button" class="as-btn as-btn--print" id="printReceiptBtn">
                    <span class="material-icons-round">print</span>
                    <?php echo __t('print_receipt', 'admin'); ?>
                </button>
                <button type="button" class="as-btn as-btn-primary" id="closeModalBtn"><?php echo __t('close', 'admin'); ?></button>
            </div>
        </div>
    </div>

    <div id="asToast" class="as-toast" role="status"></div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = window.ADMIN_PAGE.currency || <?php echo json_encode($storeCurrency); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
        window.ADMIN_I18N = <?php echo json_encode($adminI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=11"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/sales.js?v=12"></script>
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

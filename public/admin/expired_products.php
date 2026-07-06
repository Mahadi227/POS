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



$expiredI18nKeys = [

    'loading', 'loading_expired', 'no_expired', 'expired_table_summary',

    'expired_subtitle', 'expired_search_placeholder',

    'stat_expired_products', 'stat_expired_units', 'stat_expired_value', 'stat_expiring_soon',

    'col_product', 'col_sku', 'col_quantity', 'col_value', 'col_store', 'col_notes', 'col_actions', 'col_expiry', 'col_category',

    'load_error', 'connection_error', 'error', 'last_updated', 'store_fallback',

    'period_today', 'period_week', 'period_month', 'period_all', 'period_label',

    'all_stores', 'date_from_label', 'date_to_label', 'clear_filters', 'apply_filters',

    'prev_page', 'next_page', 'view_history', 'edit_product', 'back_inventory',

    'link_damaged', 'link_expired', 'nav_products', 'link_history', 'link_movements',

    'link_transfers', 'link_reports', 'link_analytics', 'nav_inventory_section',

    'days_expired', 'days_until_expiry', 'expires_today',

];

$expiredI18n = [];

foreach ($expiredI18nKeys as $key) {

    $expiredI18n[$key] = __t($key, 'inventory');

}

foreach (['menu', 'refresh', 'theme', 'col_date', 'nav_system', 'logout'] as $key) {

    $expiredI18n[$key] = __t($key, 'admin');

}



$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));

require __DIR__ . '/includes/admin-branding.php';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');

?>

<!DOCTYPE html>

<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="admin" data-theme-accent="<?php echo $accentEsc; ?>">



<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

    <?php require __DIR__ . '/includes/admin-head-theme.php'; ?>

    <title><?php echo __t('expired_title', 'inventory'); ?></title>

    <link rel="preconnect" href="https://fonts.googleapis.com">

    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>

    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">

    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">

    <link rel="stylesheet" href="../../assets/css/admin.css">

    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=5">

    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=16">

</head>



<body class="ep-page ad-page">

    <div class="admin-layout">

        <aside class="sidebar">            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>

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

                    <a href="stock_transfers.php" class="nav-link">

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

                <li>

                    <a href="damaged_products.php" class="nav-link">

                        <span class="material-icons-round">report_problem</span>

                        <span><?php echo __t('link_damaged', 'inventory'); ?></span>

                    </a>

                </li>

                <li>

                    <a href="expired_products.php" class="nav-link active">

                        <span class="material-icons-round">event_busy</span>

                        <span><?php echo __t('link_expired', 'inventory'); ?></span>

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

                        <h1><?php echo __t('expired_heading', 'inventory'); ?></h1>

                        <div class="header-subline">

                            <span class="date-display" id="expiredDate">—</span>

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

                    <button type="button" class="ad-refresh-btn" id="refreshExpired" title="<?php echo __t('refresh', 'admin'); ?>">

                        <span class="material-icons-round">refresh</span>

                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>

                    </button>

                    <a href="inventory.php" class="inv-btn inv-btn-outline ih-back-link" title="<?php echo __t('back_inventory', 'inventory'); ?>">

                        <span class="material-icons-round">inventory_2</span>

                        <span class="btn-label"><?php echo __t('back_inventory', 'inventory'); ?></span>

                    </a>

                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">

                        <span class="material-icons-round">dark_mode</span>

                    </button>

                </div>

            </header>



            <div class="dashboard-scroll-area">

                <div class="ad-error-banner" id="expiredError">

                    <span class="material-icons-round">error_outline</span>

                    <span class="ad-error-text"></span>

                </div>



                <nav class="ad-quick-nav" aria-label="<?php echo __t('nav_inventory_section', 'inventory'); ?>">

                    <a href="inventory.php" class="ad-quick-nav__item">

                        <span class="material-icons-round">inventory_2</span>

                        <span><?php echo __t('nav_products', 'inventory'); ?></span>

                    </a>

                    <a href="damaged_products.php" class="ad-quick-nav__item">

                        <span class="material-icons-round">report_problem</span>

                        <span><?php echo __t('link_damaged', 'inventory'); ?></span>

                    </a>

                    <a href="expired_products.php" class="ad-quick-nav__item ad-quick-nav__item--accent">

                        <span class="material-icons-round">event_busy</span>

                        <span><?php echo __t('link_expired', 'inventory'); ?></span>

                    </a>

                    <a href="inventory_reports.php" class="ad-quick-nav__item">

                        <span class="material-icons-round">article</span>

                        <span><?php echo __t('link_reports', 'inventory'); ?></span>

                    </a>

                </nav>



                <p class="ep-subtitle"><?php echo __t('expired_subtitle', 'inventory'); ?></p>



                <div class="stat-cards ad-stat-cards ep-summary-cards">

                    <div class="card stat-card ep-stat is-loading">

                        <div class="card-icon primary">

                            <span class="material-icons-round">inventory_2</span>

                        </div>

                        <div class="card-info">

                            <h3><?php echo __t('stat_expired_products', 'inventory'); ?></h3>

                            <h2 id="stat-products">—</h2>

                        </div>

                    </div>

                    <div class="card stat-card ep-stat is-loading">

                        <div class="card-icon warning">

                            <span class="material-icons-round">production_quantity_limits</span>

                        </div>

                        <div class="card-info">

                            <h3><?php echo __t('stat_expired_units', 'inventory'); ?></h3>

                            <h2 id="stat-units">—</h2>

                        </div>

                    </div>

                    <div class="card stat-card ep-stat is-loading">

                        <div class="card-icon danger">

                            <span class="material-icons-round">money_off</span>

                        </div>

                        <div class="card-info">

                            <h3><?php echo __t('stat_expired_value', 'inventory'); ?></h3>

                            <h2 id="stat-value">—</h2>

                        </div>

                    </div>

                    <div class="card stat-card ep-stat is-loading">

                        <div class="card-icon info">

                            <span class="material-icons-round">schedule</span>

                        </div>

                        <div class="card-info">

                            <h3><?php echo __t('stat_expiring_soon', 'inventory'); ?></h3>

                            <h2 id="stat-expiring">—</h2>

                        </div>

                    </div>

                </div>



                <div class="inv-chips ih-chips ep-chips" role="tablist" aria-label="<?php echo __t('period_label', 'inventory'); ?>">

                    <button type="button" class="inv-chip active" data-period="all"><?php echo __t('period_all', 'inventory'); ?></button>
                    <button type="button" class="inv-chip" data-period="today"><?php echo __t('period_today', 'inventory'); ?></button>

                    <button type="button" class="inv-chip" data-period="week"><?php echo __t('period_week', 'inventory'); ?></button>

                    <button type="button" class="inv-chip" data-period="month"><?php echo __t('period_month', 'inventory'); ?></button>

                </div>



                <div class="card table-widget ih-filters-card">

                    <div class="ih-filters">

                        <div class="inv-search ih-search">

                            <span class="material-icons-round">search</span>

                            <input type="search" id="expiredSearch" placeholder="<?php echo __t('expired_search_placeholder', 'inventory'); ?>" autocomplete="off">

                        </div>

                        <select id="expiredStore" class="inv-select" aria-label="<?php echo __t('col_store', 'inventory'); ?>">

                            <option value=""><?php echo __t('all_stores', 'inventory'); ?></option>

                        </select>

                        <label class="ih-date-field">

                            <span><?php echo __t('date_from_label', 'inventory'); ?></span>

                            <input type="date" id="expiredDateFrom" class="inv-select">

                        </label>

                        <label class="ih-date-field">

                            <span><?php echo __t('date_to_label', 'inventory'); ?></span>

                            <input type="date" id="expiredDateTo" class="inv-select">

                        </label>

                        <div class="ih-filter-actions">

                            <button type="button" class="inv-btn inv-btn-outline" id="clearExpiredFilters"><?php echo __t('clear_filters', 'inventory'); ?></button>

                            <button type="button" class="inv-btn inv-btn-primary" id="applyExpiredFilters"><?php echo __t('apply_filters', 'inventory'); ?></button>

                        </div>

                    </div>

                </div>



                <div class="card table-widget">

                    <div class="inv-table-meta ih-table-meta">

                        <span id="tableSummary"><?php echo __t('loading_expired', 'inventory'); ?></span>

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

                        <table class="modern-table ep-expired-table">

                            <thead>

                                <tr>

                                    <th><?php echo __t('col_expiry', 'inventory'); ?></th>

                                    <th><?php echo __t('col_product', 'inventory'); ?></th>

                                    <th><?php echo __t('col_sku', 'inventory'); ?></th>

                                    <th><?php echo __t('col_quantity', 'inventory'); ?></th>

                                    <th><?php echo __t('col_value', 'inventory'); ?></th>

                                    <th><?php echo __t('col_category', 'inventory'); ?></th>

                                    <th><?php echo __t('col_store', 'inventory'); ?></th>

                                    <th><?php echo __t('col_notes', 'inventory'); ?></th>

                                    <th><?php echo __t('col_actions', 'inventory'); ?></th>

                                </tr>

                            </thead>

                            <tbody id="expiredProductsBody">

                                <tr>

                                    <td colspan="9" class="ad-empty-row"><?php echo __t('loading_expired', 'inventory'); ?></td>

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

        window.INVENTORY_I18N = <?php echo json_encode($expiredI18n, JSON_UNESCAPED_UNICODE); ?>;

        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };

        window.ADMIN_PAGE = window.ADMIN_PAGE || {};

        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;

        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;

        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;

    </script>

    <script src="../../assets/js/admin/admin-api.js?v=11"></script>

    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>

    <script src="../../assets/js/admin/expired-products.js?v=3"></script>

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


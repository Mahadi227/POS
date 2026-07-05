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

$storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : 1;
$storeName = '';
$storeCurrency = 'FCFA';
try {
    $db = Database::getInstance()->getConnection();
    $stmt = $db->prepare('SELECT name, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$storeId]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $storeName = $row['name'] ?? '';
        $storeCurrency = $row['currency'] ?? 'FCFA';
    }
} catch (Throwable $e) {
}

$syncI18nKeys = [
    'loading', 'load_error', 'connection_error', 'error', 'last_updated',
    'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save',
    'nav_main', 'nav_dashboard', 'nav_sales', 'nav_inventory', 'nav_management',
    'nav_stores', 'nav_users', 'nav_analytics', 'nav_inventory_analytics', 'nav_sync', 'nav_system', 'nav_pos',
    'sync_heading', 'sync_subtitle', 'sync_section_monitor', 'sync_scope',
    'sync_kpi_online_meta', 'sync_kpi_offline_meta', 'sync_kpi_pending_meta', 'sync_kpi_failed_meta',
    'sync_alert_issues', 'dash_all_stores',
    'stat_offline_branches', 'stat_online_branches', 'stat_degraded_branches',
    'stat_pending_queue', 'stat_sync_failures', 'stat_conflicts',
    'stat_synced_today', 'stat_total_branches', 'chart_sync_activity', 'chart_synced', 'chart_failed_conflicts',
    'tab_branches', 'tab_queue', 'tab_failed', 'tab_conflicts',
    'conn_online', 'conn_offline', 'conn_degraded', 'conn_unknown',
    'branch_last_seen', 'branch_last_sync', 'branch_local_queue', 'branch_server_queue', 'branch_failures_conflicts',
    'branch_never_seen', 'col_date', 'col_store', 'col_action', 'col_receipt', 'col_status', 'col_actions',
    'col_source', 'col_error', 'col_reason',
    'no_branches', 'queue_empty', 'no_failures', 'no_conflicts',
    'btn_retry', 'btn_dismiss', 'retry_success', 'resolve_success', 'action_failed',
    'branches_search_placeholder', 'filter_all_connectivity', 'filter_online', 'filter_offline', 'filter_degraded', 'filter_unknown',
    'auto_refresh_hint', 'source_queue', 'source_offline', 'minutes_ago', 'branches_summary',
];
$syncI18n = [];
foreach ($syncI18nKeys as $key) {
    $syncI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'sync';
require __DIR__ . '/includes/admin-branding.php';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light" data-portal="admin" data-theme-accent="<?php echo $accentEsc; ?>">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <?php require __DIR__ . '/includes/admin-head-theme.php'; ?>
    <title><?php echo __t('sync_title', 'admin'); ?> — <?php echo htmlspecialchars($adminBrandName, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=14">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=17">
    <link rel="stylesheet" href="../../assets/css/admin-sync-monitor.css?v=6">
    <?php require __DIR__ . '/includes/admin-tail-theme.php'; ?>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</head>

<body class="sm-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">            <?php include __DIR__ . '/includes/sidebar-header.php'; ?>
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
                <?php include __DIR__ . '/includes/sidebar-extra.php'; ?>
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
                        <h1><?php echo __t('sync_heading', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="syncDate">—</span>
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
                    <button type="button" class="ad-refresh-btn" id="refreshSync" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="syncError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <section class="ad-dash-hero" aria-labelledby="smHeroTitle">
                    <div class="ad-dash-hero__intro">
                        <h2 class="ad-dash-hero__title" id="smHeroTitle"><?php echo __t('sync_subtitle', 'admin'); ?></h2>
                        <p class="ad-dash-hero__period" id="smHeroPeriod" aria-live="polite">—</p>
                        <p class="ad-dash-hero__scope" id="smHeroScope" aria-live="polite"></p>
                    </div>
                    <div class="ad-kpi-grid ad-kpi-grid--hero sm-summary-cards" id="smSummaryCards" role="group" aria-label="<?php echo __t('sync_heading', 'admin'); ?>">
                        <article class="ad-kpi ad-kpi--neutral is-loading" id="sm-kpi-online">
                            <span class="ad-kpi__label"><?php echo __t('stat_online_branches', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="st-online-branches-val">—</strong>
                            <span class="ad-kpi__meta" id="sm-kpi-synced-meta">—</span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="sm-kpi-offline">
                            <span class="ad-kpi__label"><?php echo __t('stat_offline_branches', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="st-offline-branches-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('sync_kpi_offline_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--primary is-loading" id="sm-kpi-pending">
                            <span class="ad-kpi__label"><?php echo __t('stat_pending_queue', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="st-pending-val">—</strong>
                            <span class="ad-kpi__meta"><?php echo __t('sync_kpi_pending_meta', 'admin'); ?></span>
                        </article>
                        <article class="ad-kpi ad-kpi--warn is-loading" id="sm-kpi-failed">
                            <span class="ad-kpi__label"><?php echo __t('stat_sync_failures', 'admin'); ?></span>
                            <strong class="ad-kpi__value" id="st-failed-val">—</strong>
                            <span class="ad-kpi__meta" id="sm-kpi-conflicts-meta">—</span>
                        </article>
                    </div>
                    <nav class="ad-quick-actions ad-dash-hero__actions" aria-label="<?php echo __t('nav_management', 'admin'); ?>">
                        <a href="index.php" class="ad-quick-btn"><span class="material-icons-round">dashboard</span><?php echo __t('nav_dashboard', 'admin'); ?></a>
                        <a href="stores.php" class="ad-quick-btn"><span class="material-icons-round">storefront</span><?php echo __t('nav_stores', 'admin'); ?></a>
                        <a href="users.php" class="ad-quick-btn"><span class="material-icons-round">group</span><?php echo __t('nav_users', 'admin'); ?></a>
                        <button type="button" class="ad-quick-btn ad-quick-btn--accent" id="refreshSyncHero">
                            <span class="material-icons-round">refresh</span><?php echo __t('refresh', 'admin'); ?>
                        </button>
                    </nav>
                </section>

                <a href="#" class="ad-alert-strip ad-alert-strip--error ad-dash-alert" id="smSyncAlert" hidden>
                    <span class="ad-alert-strip__icon" aria-hidden="true">
                        <span class="material-icons-round">sync_problem</span>
                    </span>
                    <span class="ad-alert-strip__body">
                        <strong class="ad-alert-strip__title"><?php echo __t('stat_sync_failures', 'admin'); ?></strong>
                        <span class="ad-alert-strip__msg" id="smSyncAlertText"></span>
                    </span>
                    <span class="ad-alert-strip__chev material-icons-round" aria-hidden="true">chevron_right</span>
                </a>

                <div class="ad-panel sm-chart-panel">
                    <div class="sm-chart-head">
                        <h3><?php echo __t('chart_sync_activity', 'admin'); ?></h3>
                        <span class="sm-auto-hint"><?php echo __t('auto_refresh_hint', 'admin'); ?></span>
                    </div>
                    <div class="sm-chart-wrap"><canvas id="syncActivityChart"></canvas></div>
                </div>

                <div class="sm-dash-toolbar">
                    <div class="inv-toolbar sm-toolbar sm-toolbar--inline">
                        <div class="inv-filters sm-filters">
                            <div class="inv-search sm-search">
                                <span class="material-icons-round">search</span>
                                <input type="search" id="branchSearch" placeholder="<?php echo __t('branches_search_placeholder', 'admin'); ?>" autocomplete="off">
                            </div>
                            <select id="connectivityFilter" class="inv-select sm-select">
                                <option value="all"><?php echo __t('filter_all_connectivity', 'admin'); ?></option>
                                <option value="online"><?php echo __t('filter_online', 'admin'); ?></option>
                                <option value="degraded"><?php echo __t('filter_degraded', 'admin'); ?></option>
                                <option value="offline"><?php echo __t('filter_offline', 'admin'); ?></option>
                                <option value="unknown"><?php echo __t('filter_unknown', 'admin'); ?></option>
                            </select>
                        </div>
                    </div>
                </div>

                <section class="ad-dash-section" aria-labelledby="smSectionTitle">
                    <h3 class="ad-dash-section__title" id="smSectionTitle"><?php echo __t('sync_section_monitor', 'admin'); ?></h3>
                <div class="sm-tabs" role="tablist">
                    <button type="button" class="sm-tab active" data-panel="branches">
                        <?php echo __t('tab_branches', 'admin'); ?>
                        <span class="sm-tab-badge" id="badge-branches">0</span>
                    </button>
                    <button type="button" class="sm-tab" data-panel="queue">
                        <?php echo __t('tab_queue', 'admin'); ?>
                        <span class="sm-tab-badge" id="badge-queue">0</span>
                    </button>
                    <button type="button" class="sm-tab" data-panel="failed">
                        <?php echo __t('tab_failed', 'admin'); ?>
                        <span class="sm-tab-badge sm-tab-badge--danger" id="badge-failed">0</span>
                    </button>
                    <button type="button" class="sm-tab" data-panel="conflicts">
                        <?php echo __t('tab_conflicts', 'admin'); ?>
                        <span class="sm-tab-badge sm-tab-badge--warn" id="badge-conflicts">0</span>
                    </button>
                </div>

                <section id="panel-branches" class="sm-panel">
                    <div class="sm-grid" id="branchesGrid">
                        <p class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></p>
                    </div>
                </section>

                <section id="panel-queue" class="sm-panel hidden">
                    <div class="ad-panel sm-table-panel">
                        <div class="ad-panel__body table-responsive sm-table-wrap">
                            <table class="modern-table sm-sync-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_store', 'admin'); ?></th>
                                        <th><?php echo __t('col_action', 'admin'); ?></th>
                                        <th><?php echo __t('col_receipt', 'admin'); ?></th>
                                        <th><?php echo __t('col_status', 'admin'); ?></th>
                                        <th><?php echo __t('col_actions', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="queueBody">
                                    <tr><td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-failed" class="sm-panel hidden">
                    <div class="ad-panel sm-table-panel">
                        <div class="ad-panel__body table-responsive sm-table-wrap">
                            <table class="modern-table sm-sync-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_source', 'admin'); ?></th>
                                        <th><?php echo __t('col_store', 'admin'); ?></th>
                                        <th><?php echo __t('col_receipt', 'admin'); ?></th>
                                        <th><?php echo __t('col_error', 'admin'); ?></th>
                                        <th><?php echo __t('col_actions', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="failedBody">
                                    <tr><td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>

                <section id="panel-conflicts" class="sm-panel hidden">
                    <div class="ad-panel sm-table-panel">
                        <div class="ad-panel__body table-responsive sm-table-wrap">
                            <table class="modern-table sm-sync-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_source', 'admin'); ?></th>
                                        <th><?php echo __t('col_store', 'admin'); ?></th>
                                        <th><?php echo __t('col_receipt', 'admin'); ?></th>
                                        <th><?php echo __t('col_reason', 'admin'); ?></th>
                                        <th><?php echo __t('col_actions', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="conflictsBody">
                                    <tr><td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
                </section>
            </div>
        </main>
    </div>

    <div class="inv-toast sm-toast" id="syncToast" role="status" aria-live="polite"></div>

    <script>
        window.ADMIN_PAGE = window.ADMIN_PAGE || {};
        window.ADMIN_PAGE.storeId = <?php echo json_encode($storeId); ?>;
        window.ADMIN_PAGE.storeName = <?php echo json_encode($storeName); ?>;
        window.ADMIN_PAGE.currency = <?php echo json_encode($storeCurrency); ?>;
        window.ADMIN_PAGE.locale = <?php echo json_encode($locale); ?>;
        window.ADMIN_PAGE.lang = <?php echo json_encode($activeLang); ?>;
        window.ADMIN_CONFIG = {
            locale: <?php echo json_encode($locale); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            currency: <?php echo json_encode($storeCurrency); ?>,
            storeName: <?php echo json_encode($storeName); ?>,
            accent: <?php echo json_encode($adminAccent); ?>,
        };
        window.SYNC_I18N = <?php echo json_encode($syncI18n, JSON_UNESCAPED_UNICODE); ?>;
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/sync-monitor.js?v=6"></script>
    <?php include __DIR__ . '/includes/sidebar-scripts.php'; ?>

</body>

</html>

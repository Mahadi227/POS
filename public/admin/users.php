<?php
require_once '../../includes/Config/session.php';
require_once '../../includes/Config/config.php';
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

$usersI18nKeys = [
    'loading', 'refresh', 'theme', 'menu', 'logout', 'close', 'cancel', 'save', 'error',
    'load_error', 'connection_error', 'last_updated', 'prev_page', 'next_page',
    'nav_main', 'nav_dashboard', 'nav_sales', 'nav_inventory', 'nav_management', 'nav_stores',
    'nav_users', 'nav_analytics', 'nav_inventory_analytics', 'nav_sync', 'nav_system', 'nav_pos',
    'users_heading', 'users_subtitle', 'tab_users', 'tab_permissions', 'tab_activity',
    'new_user', 'users_search_placeholder', 'filter_all_roles', 'filter_all_stores', 'filter_all_statuses',
    'filter_active_users', 'filter_suspended_users', 'role_admin', 'role_manager', 'role_cashier', 'role_staff',
    'stat_total_users', 'stat_active_users', 'stat_suspended_users',
    'stat_logins_today', 'stat_failed_logins', 'stat_admin_actions', 'stat_unique_users_today',
    'col_user', 'col_role', 'col_store', 'col_status', 'col_last_login', 'col_actions',
    'col_date', 'col_type', 'col_action', 'col_ip',
    'user_active', 'user_suspended', 'no_users', 'users_table_summary',
    'user_modal_new', 'user_modal_edit', 'user_name', 'user_email', 'user_role', 'user_store', 'user_store_none',
    'user_password', 'user_password_edit', 'reset_password_title', 'reset_password_for', 'reset_password_label',
    'reset_password_success', 'user_saved', 'permissions_saved', 'permissions_role_label', 'save_permissions',
    'select_role_permissions', 'activity_all_events', 'activity_login_events', 'activity_admin_events',
    'activity_all_users', 'activity_type_login', 'activity_type_logout', 'activity_type_admin',
    'activity_action_logout', 'activity_action_login_success', 'activity_action_login_failed',
    'activity_status_success', 'activity_status_failed', 'activity_status_error', 'no_activity',
    'confirm_suspend', 'edit_user', 'reset_password', 'suspend_user', 'activate_user', 'password_required',
];
$usersI18n = [];
foreach ($usersI18nKeys as $key) {
    $usersI18n[$key] = __t($key, 'admin');
}

$initial = strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1));
$activePage = 'users';
?>
<!DOCTYPE html>
<html lang="<?php echo htmlspecialchars($activeLang, ENT_QUOTES, 'UTF-8'); ?>" data-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="theme-color" content="#2563eb">
    <title><?php echo __t('users_title', 'admin'); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons+Round" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/admin.css">
    <link rel="stylesheet" href="../../assets/css/admin-dashboard.css?v=5">
    <link rel="stylesheet" href="../../assets/css/admin-inventory.css?v=16">
    <link rel="stylesheet" href="../../assets/css/admin-users.css?v=5">
</head>

<body class="um-page ad-page">
    <div class="admin-layout">
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <span class="material-icons-round">groups</span>
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
                    <p class="name"><?php echo htmlspecialchars($_SESSION['name'] ?? ''); ?></p>
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
                        <h1><?php echo __t('users_heading', 'admin'); ?></h1>
                        <div class="header-subline">
                            <span class="date-display" id="usersDate">—</span>
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
                    <button type="button" class="inv-btn inv-btn-primary um-header-new" id="addUserBtn">
                        <span class="material-icons-round">person_add</span>
                        <span class="btn-label"><?php echo __t('new_user', 'admin'); ?></span>
                    </button>
                    <button type="button" class="ad-refresh-btn" id="refreshUsersBtn" title="<?php echo __t('refresh', 'admin'); ?>">
                        <span class="material-icons-round">refresh</span>
                        <span class="btn-label"><?php echo __t('refresh', 'admin'); ?></span>
                    </button>
                    <button type="button" class="icon-btn theme-toggle ad-header-icon" id="theme-toggle" aria-label="<?php echo __t('theme', 'admin'); ?>">
                        <span class="material-icons-round">dark_mode</span>
                    </button>
                </div>
            </header>

            <div class="dashboard-scroll-area">
                <div class="ad-error-banner" id="usersError">
                    <span class="material-icons-round">error_outline</span>
                    <span class="ad-error-text"></span>
                </div>

                <nav class="ad-quick-nav" aria-label="<?php echo __t('nav_management', 'admin'); ?>">
                    <a href="index.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">dashboard</span>
                        <span><?php echo __t('nav_dashboard', 'admin'); ?></span>
                    </a>
                    <a href="stores.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">storefront</span>
                        <span><?php echo __t('nav_stores', 'admin'); ?></span>
                    </a>
                    <a href="users.php" class="ad-quick-nav__item ad-quick-nav__item--accent">
                        <span class="material-icons-round">group</span>
                        <span><?php echo __t('nav_users', 'admin'); ?></span>
                    </a>
                    <a href="sync-monitor.php" class="ad-quick-nav__item">
                        <span class="material-icons-round">sync</span>
                        <span><?php echo __t('nav_sync', 'admin'); ?></span>
                    </a>
                </nav>

                <p class="um-subtitle"><?php echo __t('users_subtitle', 'admin'); ?></p>

                <div class="um-tabs" role="tablist">
                    <button type="button" class="um-tab active" data-panel="users"><?php echo __t('tab_users', 'admin'); ?></button>
                    <button type="button" class="um-tab" data-panel="permissions"><?php echo __t('tab_permissions', 'admin'); ?></button>
                    <button type="button" class="um-tab" data-panel="activity"><?php echo __t('tab_activity', 'admin'); ?></button>
                </div>

                <div id="panel-users" class="um-panel">
                    <div class="stat-cards ad-stat-cards um-summary-cards um-user-stats">
                        <div class="card stat-card um-stat is-loading">
                            <div class="card-icon primary">
                                <span class="material-icons-round">groups</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_total_users', 'admin'); ?></h3>
                                <h2 id="stat-total-users">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-stat is-loading">
                            <div class="card-icon success">
                                <span class="material-icons-round">check_circle</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_active_users', 'admin'); ?></h3>
                                <h2 id="stat-active-users">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-stat is-loading">
                            <div class="card-icon warning">
                                <span class="material-icons-round">pause_circle</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_suspended_users', 'admin'); ?></h3>
                                <h2 id="stat-suspended-users">—</h2>
                            </div>
                        </div>
                    </div>

                    <div class="inv-chips um-chips" role="tablist">
                        <button type="button" class="inv-chip active" data-status=""><?php echo __t('filter_all_statuses', 'admin'); ?></button>
                        <button type="button" class="inv-chip" data-status="active"><?php echo __t('filter_active_users', 'admin'); ?></button>
                        <button type="button" class="inv-chip" data-status="suspended"><?php echo __t('filter_suspended_users', 'admin'); ?></button>
                    </div>

                    <div class="card table-widget um-filters-card">
                        <div class="um-toolbar">
                            <div class="inv-search um-search">
                                <span class="material-icons-round">search</span>
                                <input type="search" id="userSearch" placeholder="<?php echo __t('users_search_placeholder', 'admin'); ?>" autocomplete="off">
                            </div>
                            <select id="roleFilter" class="inv-select um-select" aria-label="<?php echo __t('col_role', 'admin'); ?>">
                                <option value=""><?php echo __t('filter_all_roles', 'admin'); ?></option>
                                <option value="admin"><?php echo __t('role_admin', 'admin'); ?></option>
                                <option value="manager"><?php echo __t('role_manager', 'admin'); ?></option>
                                <option value="cashier"><?php echo __t('role_cashier', 'admin'); ?></option>
                                <option value="staff"><?php echo __t('role_staff', 'admin'); ?></option>
                            </select>
                            <select id="storeFilter" class="inv-select um-select" aria-label="<?php echo __t('col_store', 'admin'); ?>">
                                <option value=""><?php echo __t('filter_all_stores', 'admin'); ?></option>
                            </select>
                        </div>
                    </div>

                    <div class="card table-widget">
                        <div class="inv-table-meta um-table-meta">
                            <span id="usersSummary"><?php echo __t('loading', 'admin'); ?></span>
                            <div class="inv-pagination">
                                <button type="button" id="pagePrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>">
                                    <span class="material-icons-round">chevron_left</span>
                                </button>
                                <span id="pageInfo">1 / 1</span>
                                <button type="button" id="pageNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>">
                                    <span class="material-icons-round">chevron_right</span>
                                </button>
                            </div>
                        </div>
                        <div class="table-responsive um-table-wrap">
                            <table class="modern-table um-users-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_user', 'admin'); ?></th>
                                        <th><?php echo __t('col_role', 'admin'); ?></th>
                                        <th><?php echo __t('col_store', 'admin'); ?></th>
                                        <th><?php echo __t('col_status', 'admin'); ?></th>
                                        <th><?php echo __t('col_last_login', 'admin'); ?></th>
                                        <th><?php echo __t('col_actions', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="usersTableBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="panel-permissions" class="um-panel hidden">
                    <div class="card table-widget um-perm-card">
                        <div class="um-perm-toolbar">
                            <div class="inv-form-group um-perm-role">
                                <label for="permRoleSelect"><?php echo __t('permissions_role_label', 'admin'); ?></label>
                                <select id="permRoleSelect" class="inv-select"></select>
                            </div>
                            <button type="button" class="inv-btn inv-btn-primary" id="savePermissionsBtn">
                                <span class="material-icons-round">save</span>
                                <?php echo __t('save_permissions', 'admin'); ?>
                            </button>
                        </div>
                        <div class="um-perm-grid" id="permissionsGrid"><?php echo __t('loading', 'admin'); ?></div>
                    </div>
                </div>

                <div id="panel-activity" class="um-panel hidden">
                    <div class="stat-cards ad-stat-cards um-summary-cards um-activity-stats">
                        <div class="card stat-card um-stat um-act-stat is-loading">
                            <div class="card-icon success">
                                <span class="material-icons-round">login</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_logins_today', 'admin'); ?></h3>
                                <h2 id="actStatLogins">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-stat um-act-stat is-loading">
                            <div class="card-icon warning">
                                <span class="material-icons-round">gpp_bad</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_failed_logins', 'admin'); ?></h3>
                                <h2 id="actStatFailed">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-stat um-act-stat is-loading">
                            <div class="card-icon primary">
                                <span class="material-icons-round">admin_panel_settings</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_admin_actions', 'admin'); ?></h3>
                                <h2 id="actStatAdmin">—</h2>
                            </div>
                        </div>
                        <div class="card stat-card um-stat um-act-stat is-loading">
                            <div class="card-icon info">
                                <span class="material-icons-round">groups</span>
                            </div>
                            <div class="card-info">
                                <h3><?php echo __t('stat_unique_users_today', 'admin'); ?></h3>
                                <h2 id="actStatUsers">—</h2>
                            </div>
                        </div>
                    </div>

                    <div class="card table-widget um-filters-card">
                        <div class="um-toolbar um-activity-toolbar">
                            <select id="activityTypeFilter" class="inv-select um-select">
                                <option value="all"><?php echo __t('activity_all_events', 'admin'); ?></option>
                                <option value="login"><?php echo __t('activity_login_events', 'admin'); ?></option>
                                <option value="admin"><?php echo __t('activity_admin_events', 'admin'); ?></option>
                            </select>
                            <select id="activityUserFilter" class="inv-select um-select">
                                <option value=""><?php echo __t('activity_all_users', 'admin'); ?></option>
                            </select>
                            <button type="button" class="ad-refresh-btn" id="refreshActivity">
                                <span class="material-icons-round">refresh</span>
                                <?php echo __t('refresh', 'admin'); ?>
                            </button>
                        </div>
                    </div>

                    <div class="card table-widget">
                        <div class="table-responsive um-table-wrap">
                            <table class="modern-table um-activity-table">
                                <thead>
                                    <tr>
                                        <th><?php echo __t('col_date', 'admin'); ?></th>
                                        <th><?php echo __t('col_user', 'admin'); ?></th>
                                        <th><?php echo __t('col_type', 'admin'); ?></th>
                                        <th><?php echo __t('col_action', 'admin'); ?></th>
                                        <th><?php echo __t('col_ip', 'admin'); ?></th>
                                        <th><?php echo __t('col_status', 'admin'); ?></th>
                                    </tr>
                                </thead>
                                <tbody id="activityTableBody">
                                    <tr>
                                        <td colspan="6" class="ad-empty-row"><?php echo __t('loading', 'admin'); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- User modal -->
    <div class="um-modal-overlay" id="userModal">
        <div class="um-modal">
            <div class="ih-modal-head">
                <h2 id="userModalTitle"><?php echo __t('user_modal_new', 'admin'); ?></h2>
                <button type="button" class="icon-btn" id="closeUserModal" aria-label="<?php echo __t('close', 'admin'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="um-form-error" id="userFormError"></div>
            <form id="userForm">
                <input type="hidden" id="userId">
                <div class="um-form-group">
                    <label for="ufName"><?php echo __t('user_name', 'admin'); ?> *</label>
                    <input type="text" id="ufName" required>
                </div>
                <div class="um-form-group">
                    <label for="ufEmail"><?php echo __t('user_email', 'admin'); ?> *</label>
                    <input type="email" id="ufEmail" required>
                </div>
                <div class="um-form-row">
                    <div class="um-form-group">
                        <label for="ufRole"><?php echo __t('user_role', 'admin'); ?> *</label>
                        <select id="ufRole" required></select>
                    </div>
                    <div class="um-form-group">
                        <label for="ufStore"><?php echo __t('user_store', 'admin'); ?></label>
                        <select id="ufStore">
                            <option value=""><?php echo __t('user_store_none', 'admin'); ?></option>
                        </select>
                    </div>
                </div>
                <div class="um-form-group" id="passwordGroup">
                    <label for="ufPassword" id="passwordLabel"><?php echo __t('user_password', 'admin'); ?></label>
                    <input type="password" id="ufPassword" minlength="8" autocomplete="new-password">
                </div>
                <div class="inv-modal-actions um-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="cancelUserModal"><?php echo __t('cancel', 'admin'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('save', 'admin'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <!-- Reset password modal -->
    <div class="um-modal-overlay" id="resetModal">
        <div class="um-modal um-modal-sm">
            <div class="ih-modal-head">
                <h2><?php echo __t('reset_password_title', 'admin'); ?></h2>
                <button type="button" class="icon-btn" id="closeResetModal" aria-label="<?php echo __t('close', 'admin'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </div>
            <div class="um-form-error" id="resetFormError"></div>
            <form id="resetForm">
                <input type="hidden" id="resetUserId">
                <p class="um-reset-label" id="resetUserLabel"></p>
                <div class="um-form-group">
                    <label for="resetPassword"><?php echo __t('reset_password_label', 'admin'); ?> *</label>
                    <input type="password" id="resetPassword" minlength="8" required autocomplete="new-password">
                </div>
                <div class="inv-modal-actions um-modal-actions">
                    <button type="button" class="inv-btn inv-btn-outline" id="cancelResetModal"><?php echo __t('cancel', 'admin'); ?></button>
                    <button type="submit" class="inv-btn inv-btn-primary"><?php echo __t('reset_password', 'admin'); ?></button>
                </div>
            </form>
        </div>
    </div>

    <div id="usersToast" class="inv-toast" role="status" aria-live="polite"></div>

    <script>
        window.USERS_PAGE = {
            role: <?php echo json_encode($roleSlug); ?>,
            storeId: <?php echo json_encode((int) ($_SESSION['store_id'] ?? 0)); ?>,
            lang: <?php echo json_encode($activeLang); ?>,
            locale: <?php echo json_encode($locale); ?>,
        };
        window.USERS_I18N = <?php echo json_encode($usersI18n, JSON_UNESCAPED_UNICODE); ?>;
        window.ADMIN_CONFIG = { lang: <?php echo json_encode($activeLang); ?>, locale: <?php echo json_encode($locale); ?> };
    </script>
    <script src="../../assets/js/admin/admin-api.js?v=10"></script>
    <script src="../../assets/js/admin/store-switcher.js?v=3"></script>
    <script src="../../assets/js/admin/users.js?v=5"></script>
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

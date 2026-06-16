<?php
require __DIR__ . '/includes/bootstrap.php';

if (($roleSlug ?? '') !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$activeWmsPage = 'users';
$pageTitle = __t('nav_users', 'admin');
$extraScripts = ['wms-common.js', 'wms-users.js'];
$pageI18n = array_merge(
    wms_i18n(['loading', 'refresh', 'wms_no_data']),
    [
        'users_title' => __t('users_title', 'admin'),
        'users_heading' => __t('users_heading', 'admin'),
        'users_subtitle' => __t('users_subtitle', 'admin'),
        'users_search_placeholder' => __t('users_search_placeholder', 'admin'),
        'filter_all_roles' => __t('filter_all_roles', 'admin'),
        'filter_all_stores' => __t('filter_all_stores', 'admin'),
        'filter_all_statuses' => __t('filter_all_statuses', 'admin'),
        'role_admin' => __t('role_admin', 'admin'),
        'role_manager' => __t('role_manager', 'admin'),
        'role_cashier' => __t('role_cashier', 'admin'),
        'role_staff' => __t('role_staff', 'admin'),
        'stat_total_users' => __t('stat_total_users', 'admin'),
        'stat_active_users' => __t('stat_active_users', 'admin'),
        'stat_suspended_users' => __t('stat_suspended_users', 'admin'),
        'col_user' => __t('col_user', 'admin'),
        'col_role' => __t('col_role', 'admin'),
        'col_store' => __t('col_store', 'admin'),
        'col_status' => __t('col_status', 'admin'),
        'col_last_login' => __t('col_last_login', 'admin'),
        'user_active' => __t('user_active', 'admin'),
        'user_suspended' => __t('user_suspended', 'admin'),
        'no_users' => __t('no_users', 'admin'),
        'new_user' => __t('new_user', 'admin'),
    ]
);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('users_subtitle', 'admin'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
        <input id="wmsUsersSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('users_search_placeholder', 'admin')); ?>" style="max-width:340px;">
        <select id="wmsUsersRole" class="form-input" style="max-width:220px;">
            <option value=""><?php echo __t('filter_all_roles', 'admin'); ?></option>
            <option value="admin"><?php echo __t('role_admin', 'admin'); ?></option>
            <option value="manager"><?php echo __t('role_manager', 'admin'); ?></option>
            <option value="cashier"><?php echo __t('role_cashier', 'admin'); ?></option>
            <option value="staff"><?php echo __t('role_staff', 'admin'); ?></option>
        </select>
        <select id="wmsUsersStore" class="form-input" style="max-width:220px;">
            <option value=""><?php echo __t('filter_all_stores', 'admin'); ?></option>
        </select>
        <select id="wmsUsersStatus" class="form-input" style="max-width:220px;">
            <option value=""><?php echo __t('filter_all_statuses', 'admin'); ?></option>
            <option value="active"><?php echo __t('user_active', 'admin'); ?></option>
            <option value="suspended"><?php echo __t('user_suspended', 'admin'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsUsersRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'admin'); ?></button>
        <a href="../users.php" class="cr-btn cr-btn--ghost"><span class="material-icons-round">person_add</span><?php echo __t('new_user', 'admin'); ?></a>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">groups</span></div><div class="card-info"><h3><?php echo __t('stat_total_users', 'admin'); ?></h3><h2 id="wmsUsersTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('stat_active_users', 'admin'); ?></h3><h2 id="wmsUsersActive">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pause_circle</span></div><div class="card-info"><h3><?php echo __t('stat_suspended_users', 'admin'); ?></h3><h2 id="wmsUsersSuspended">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">group</span><?php echo __t('users_heading', 'admin'); ?></h3>
    <div id="wmsUsersRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

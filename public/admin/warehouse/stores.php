<?php
require __DIR__ . '/includes/bootstrap.php';

if (($roleSlug ?? '') !== 'super_admin') {
    header('Location: dashboard.php');
    exit;
}

$activeWmsPage = 'stores';
$pageTitle = __t('nav_stores', 'admin');
$extraScripts = ['wms-common.js', 'wms-stores.js'];
$pageI18n = [
    'loading' => __t('loading', 'admin'),
    'refresh' => __t('refresh', 'admin'),
    'stores_subtitle' => __t('stores_subtitle', 'admin'),
    'stores_search_placeholder' => __t('stores_search_placeholder', 'admin'),
    'filter_all_stores' => __t('filter_all_stores', 'admin'),
    'filter_active_stores' => __t('filter_active_stores', 'admin'),
    'filter_inactive_stores' => __t('filter_inactive_stores', 'admin'),
    'stat_total_stores' => __t('stat_total_stores', 'admin'),
    'stat_active_stores' => __t('stat_active_stores', 'admin'),
    'stat_inactive_stores' => __t('stat_inactive_stores', 'admin'),
    'col_store' => __t('col_store', 'admin'),
    'col_status' => __t('col_status', 'admin'),
    'store_active' => __t('store_active', 'admin'),
    'store_inactive' => __t('store_inactive', 'admin'),
    'no_stores' => __t('no_stores', 'admin'),
    'staff_count' => __t('staff_count', 'admin'),
    'product_count' => __t('product_count', 'admin'),
    'view_all' => __t('view_all', 'admin'),
];
require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('stores_subtitle', 'admin'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%;">
        <input id="wmsStoresSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('stores_search_placeholder', 'admin')); ?>" style="max-width:340px;">
        <select id="wmsStoresStatus" class="form-input" style="max-width:240px;">
            <option value="all"><?php echo __t('filter_all_stores', 'admin'); ?></option>
            <option value="active"><?php echo __t('filter_active_stores', 'admin'); ?></option>
            <option value="inactive"><?php echo __t('filter_inactive_stores', 'admin'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsStoresRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'admin'); ?></button>
        <a href="../stores.php" class="cr-btn cr-btn--ghost"><span class="material-icons-round">open_in_new</span><?php echo __t('view_all', 'admin'); ?></a>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">store</span></div><div class="card-info"><h3><?php echo __t('stat_total_stores', 'admin'); ?></h3><h2 id="wmsStoresTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('stat_active_stores', 'admin'); ?></h3><h2 id="wmsStoresActive">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pause_circle</span></div><div class="card-info"><h3><?php echo __t('stat_inactive_stores', 'admin'); ?></h3><h2 id="wmsStoresInactive">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">storefront</span><?php echo __t('nav_stores', 'admin'); ?></h3>
    <div id="wmsStoresRoot"><div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

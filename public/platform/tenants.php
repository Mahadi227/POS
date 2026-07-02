<?php
require __DIR__ . '/includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'tenants';
$pageTitle = __t('plat_nav_tenants', 'platform');
$extraScripts = ['platform-common.js', 'platform-tenants.js'];
$pageI18n = plat_i18n([
    'plat_col_name', 'plat_col_slug', 'plat_col_status', 'plat_col_stores', 'plat_col_users', 'plat_col_created',
    'plat_col_plan', 'plat_search', 'plat_view_detail', 'no_data',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="plat-panel">
    <div class="plat-filters" id="platTenantFilters">
        <input type="search" id="platSearchInput" class="plat-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
        <select id="platStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
            <option value=""><?php echo __t('plat_filter_all_status', 'platform'); ?></option>
            <option value="trial">trial</option>
            <option value="active">active</option>
            <option value="suspended">suspended</option>
            <option value="cancelled">cancelled</option>
        </select>
    </div>
    <div class="plat-table-wrap">
        <table class="plat-table" id="platTenantsTable">
            <thead>
                <tr>
                    <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_slug', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_plan', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_stores', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_users', 'platform'); ?></th>
                    <th><?php echo __t('plat_col_created', 'platform'); ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody id="platTenantsBody">
                <tr><td colspan="8"><?php echo __t('loading', 'platform'); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

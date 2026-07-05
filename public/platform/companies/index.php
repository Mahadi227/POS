<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'companies';
$pageTitle = __t('plat_nav_companies', 'platform');
$extraStyles = ['platform-tenants.css'];
$extraScripts = ['platform-common.js', 'platform-tenants.js'];
$pageI18n = plat_i18n([
    'plat_col_name', 'plat_col_slug', 'plat_col_status', 'plat_col_stores', 'plat_col_users', 'plat_col_created',
    'plat_col_plan', 'plat_search', 'plat_view_detail', 'plat_no_data', 'plat_filter_all_status',
    'plat_tenants_subtitle', 'plat_tenants_badge', 'plat_tenants_count', 'plat_tenants_load_error',
    'plat_tenants_empty', 'plat_tenants_empty_hint', 'plat_clear_filters', 'loading', 'load_error',
    'plat_status_trial', 'plat_status_active', 'plat_status_suspended', 'plat_status_cancelled',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-tenants">
    <div class="plat-tenants-error" id="platTenantsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platTenantsErrorText"></span>
    </div>

    <section class="plat-tenants-hero" aria-labelledby="platTenantsHeroTitle">
        <div class="plat-tenants-hero__intro">
            <div class="plat-tenants-badge">
                <span class="material-icons-round" aria-hidden="true">business</span>
                <?php echo __t('plat_tenants_badge', 'platform'); ?>
            </div>
            <h2 class="plat-tenants-hero__title" id="platTenantsHeroTitle"><?php echo __t('plat_nav_companies', 'platform'); ?></h2>
            <p class="plat-tenants-hero__desc"><?php echo __t('plat_tenants_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-tenants-count" id="platTenantsCount" aria-live="polite"></p>
    </section>

    <section class="plat-panel plat-tenants-panel">
        <div class="plat-tenants-toolbar" id="platTenantFilters">
            <div class="plat-tenants-search-wrap">
                <span class="material-icons-round plat-tenants-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platSearchInput" class="plat-search plat-tenants-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platStatusFilter" class="plat-select plat-tenants-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_filter_all_status', 'platform'); ?></option>
                <option value="trial"><?php echo __t('plat_status_trial', 'platform'); ?></option>
                <option value="active"><?php echo __t('plat_status_active', 'platform'); ?></option>
                <option value="suspended"><?php echo __t('plat_status_suspended', 'platform'); ?></option>
                <option value="cancelled"><?php echo __t('plat_status_cancelled', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-tenants-clear-btn" id="platClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-tenants-table-wrap">
            <table class="plat-table plat-tenants-table" id="platTenantsTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_slug', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_plan', 'platform'); ?></th>
                        <th class="plat-col-num"><?php echo __t('plat_col_stores', 'platform'); ?></th>
                        <th class="plat-col-num"><?php echo __t('plat_col_users', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_created', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platTenantsBody">
                    <tr class="plat-tenants-loading-row">
                        <td colspan="8">
                            <span class="plat-tenants-loading">
                                <span class="plat-tenants-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-tenants-empty" id="platTenantsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">domain_disabled</span>
            <h3><?php echo __t('plat_tenants_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_tenants_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

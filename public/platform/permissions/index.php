<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'permissions';
$pageTitle = __t('plat_nav_permissions', 'platform');
$extraStyles = ['platform-permissions.css'];
$extraScripts = ['platform-common.js', 'platform-permissions.js'];
$pageI18n = plat_i18n([
    'plat_nav_permissions', 'plat_nav_roles', 'plat_no_data', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error',
    'plat_perms_subtitle', 'plat_perms_badge', 'plat_perms_count', 'plat_perms_load_error',
    'plat_perms_empty', 'plat_perms_empty_hint', 'plat_perms_kpi_total', 'plat_perms_kpi_categories',
    'plat_perms_kpi_view', 'plat_perms_kpi_manage', 'plat_perms_view_roles', 'plat_perms_col_key',
    'plat_perms_col_capability', 'plat_perms_col_action', 'plat_perms_col_category', 'plat_perms_col_roles',
    'plat_perms_filter_all_categories', 'plat_perms_filter_all_actions', 'plat_perms_action_view',
    'plat_perms_action_manage', 'plat_role_platform_admin', 'plat_role_support',
    'plat_perm_cat_core', 'plat_perm_cat_billing', 'plat_perm_cat_product', 'plat_perm_cat_operations',
    'plat_perm_cat_security',
    'plat_perm_organizations', 'plat_perm_subscriptions', 'plat_perm_billing', 'plat_perm_payments',
    'plat_perm_licenses', 'plat_perm_domains', 'plat_perm_marketplace', 'plat_perm_modules',
    'plat_perm_monitoring', 'plat_perm_incidents', 'plat_perm_users', 'plat_perm_impersonation',
    'plat_perm_audit', 'plat_perm_settings',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-perms">
    <div class="plat-perms-error" id="platPermsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platPermsErrorText"></span>
    </div>

    <section class="plat-perms-hero" aria-labelledby="platPermsHeroTitle">
        <div class="plat-perms-hero__intro">
            <div class="plat-perms-badge">
                <span class="material-icons-round" aria-hidden="true">lock</span>
                <?php echo __t('plat_perms_badge', 'platform'); ?>
            </div>
            <h2 class="plat-perms-hero__title" id="platPermsHeroTitle"><?php echo __t('plat_nav_permissions', 'platform'); ?></h2>
            <p class="plat-perms-hero__desc"><?php echo __t('plat_perms_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-perms-hero__actions">
            <p class="plat-perms-count" id="platPermsCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('roles/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-perms-link-btn">
                <span class="material-icons-round" aria-hidden="true">badge</span>
                <?php echo __t('plat_perms_view_roles', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-perms-kpi-grid" id="platPermsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">lock</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_perms_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPermKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">category</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_perms_kpi_categories', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPermKpiCats">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">visibility</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_perms_kpi_view', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPermKpiView">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">edit</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_perms_kpi_manage', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platPermKpiManage">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-perms-panel">
        <div class="plat-perms-toolbar">
            <div class="plat-perms-search-wrap">
                <span class="material-icons-round plat-perms-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platPermsSearch" class="plat-search plat-perms-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platPermsCategoryFilter" class="plat-select" aria-label="<?php echo __t('plat_perms_col_category', 'platform'); ?>">
                <option value=""><?php echo __t('plat_perms_filter_all_categories', 'platform'); ?></option>
                <option value="core"><?php echo __t('plat_perm_cat_core', 'platform'); ?></option>
                <option value="billing"><?php echo __t('plat_perm_cat_billing', 'platform'); ?></option>
                <option value="product"><?php echo __t('plat_perm_cat_product', 'platform'); ?></option>
                <option value="operations"><?php echo __t('plat_perm_cat_operations', 'platform'); ?></option>
                <option value="security"><?php echo __t('plat_perm_cat_security', 'platform'); ?></option>
            </select>
            <select id="platPermsActionFilter" class="plat-select" aria-label="<?php echo __t('plat_perms_col_action', 'platform'); ?>">
                <option value=""><?php echo __t('plat_perms_filter_all_actions', 'platform'); ?></option>
                <option value="view"><?php echo __t('plat_perms_action_view', 'platform'); ?></option>
                <option value="manage"><?php echo __t('plat_perms_action_manage', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-perms-clear-btn" id="platPermsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-perms-table-wrap">
            <table class="plat-table plat-perms-table" id="platPermsTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_perms_col_key', 'platform'); ?></th>
                        <th><?php echo __t('plat_perms_col_capability', 'platform'); ?></th>
                        <th><?php echo __t('plat_perms_col_action', 'platform'); ?></th>
                        <th><?php echo __t('plat_perms_col_category', 'platform'); ?></th>
                        <th><?php echo __t('plat_perms_col_roles', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platPermsBody">
                    <tr class="plat-perms-loading-row">
                        <td colspan="5">
                            <span class="plat-perms-loading">
                                <span class="plat-perms-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-perms-empty" id="platPermsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">lock_open</span>
            <h3><?php echo __t('plat_perms_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_perms_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

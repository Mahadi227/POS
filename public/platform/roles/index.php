<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'roles';
$pageTitle = __t('plat_nav_roles', 'platform');
$extraStyles = ['platform-roles.css'];
$extraScripts = ['platform-common.js', 'platform-roles.js'];
$pageI18n = plat_i18n([
    'plat_nav_roles', 'plat_nav_users', 'plat_no_data', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error',
    'plat_roles_subtitle', 'plat_roles_badge', 'plat_roles_count', 'plat_roles_load_error',
    'plat_roles_empty', 'plat_roles_empty_hint', 'plat_roles_kpi_roles', 'plat_roles_kpi_permissions',
    'plat_roles_kpi_users', 'plat_roles_kpi_active', 'plat_roles_view_users', 'plat_roles_matrix_title',
    'plat_roles_matrix_capability', 'plat_roles_scope_full', 'plat_roles_scope_limited',
    'plat_roles_access_full', 'plat_roles_access_view', 'plat_roles_access_none',
    'plat_roles_users_assigned', 'plat_roles_users_active',
    'plat_role_platform_admin', 'plat_role_support', 'plat_role_desc_platform_admin', 'plat_role_desc_support',
    'plat_perm_organizations', 'plat_perm_subscriptions', 'plat_perm_billing', 'plat_perm_payments',
    'plat_perm_licenses', 'plat_perm_domains', 'plat_perm_marketplace', 'plat_perm_modules',
    'plat_perm_monitoring', 'plat_perm_incidents', 'plat_perm_users', 'plat_perm_impersonation',
    'plat_perm_audit', 'plat_perm_settings',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-roles">
    <div class="plat-roles-error" id="platRolesError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platRolesErrorText"></span>
    </div>

    <section class="plat-roles-hero" aria-labelledby="platRolesHeroTitle">
        <div class="plat-roles-hero__intro">
            <div class="plat-roles-badge">
                <span class="material-icons-round" aria-hidden="true">badge</span>
                <?php echo __t('plat_roles_badge', 'platform'); ?>
            </div>
            <h2 class="plat-roles-hero__title" id="platRolesHeroTitle"><?php echo __t('plat_nav_roles', 'platform'); ?></h2>
            <p class="plat-roles-hero__desc"><?php echo __t('plat_roles_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-roles-hero__actions">
            <p class="plat-roles-count" id="platRolesCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('users/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-roles-link-btn">
                <span class="material-icons-round" aria-hidden="true">group</span>
                <?php echo __t('plat_roles_view_users', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-roles-kpi-grid" id="platRolesKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">badge</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_roles_kpi_roles', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRoleKpiRoles">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">lock</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_roles_kpi_permissions', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRoleKpiPerms">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">group</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_roles_kpi_users', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRoleKpiUsers">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_roles_kpi_active', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRoleKpiActive">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-roles-panel">
        <div class="plat-roles-toolbar">
            <div class="plat-roles-search-wrap">
                <span class="material-icons-round plat-roles-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platRolesSearch" class="plat-search plat-roles-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <button type="button" class="plat-roles-clear-btn" id="platRolesClearSearch" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-roles-grid" id="platRolesGrid" aria-live="polite"></div>

        <div class="plat-roles-empty" id="platRolesEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">badge</span>
            <h3><?php echo __t('plat_roles_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_roles_empty_hint', 'platform'); ?></p>
        </div>
    </section>

    <section class="plat-panel plat-roles-matrix-panel" aria-labelledby="platRolesMatrixTitle">
        <header class="plat-roles-matrix-head">
            <h3 id="platRolesMatrixTitle"><?php echo __t('plat_roles_matrix_title', 'platform'); ?></h3>
        </header>
        <div class="plat-table-wrap plat-roles-matrix-wrap">
            <table class="plat-table plat-roles-matrix" id="platRolesMatrix">
                <thead id="platRolesMatrixHead">
                    <tr>
                        <th><?php echo __t('plat_roles_matrix_capability', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platRolesMatrixBody">
                    <tr class="plat-roles-loading-row">
                        <td colspan="3">
                            <span class="plat-roles-loading">
                                <span class="plat-roles-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

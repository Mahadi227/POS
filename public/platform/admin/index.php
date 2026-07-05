<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'admin';
$pageTitle = __t('plat_nav_admin', 'platform');
$extraStyles = ['platform-governance.css', 'platform-admin.css'];
$extraScripts = ['platform-common.js', 'platform-admin.js'];
$pageI18n = plat_i18n([
    'plat_nav_admin', 'plat_admin_badge', 'plat_admin_subtitle', 'plat_admin_welcome',
    'plat_admin_kpi_operators', 'plat_admin_kpi_active_ops', 'plat_admin_kpi_audit_today',
    'plat_admin_kpi_failed_logins', 'plat_admin_modules_title', 'plat_admin_recent_audit',
    'plat_admin_view_all', 'plat_admin_load_error', 'plat_admin_mod_users_desc',
    'plat_admin_mod_roles_desc', 'plat_admin_mod_permissions_desc', 'plat_admin_mod_security_desc',
    'plat_admin_mod_audit_desc', 'plat_admin_mod_settings_desc', 'plat_nav_users', 'plat_nav_roles',
    'plat_nav_permissions', 'plat_nav_security', 'plat_nav_audit', 'plat_nav_settings',
    'loading', 'load_error', 'plat_no_data',
    'plat_audit_action_platform_login_success', 'plat_audit_action_platform_login_failed',
    'plat_audit_action_platform_logout', 'plat_audit_action_tenant_impersonate_start',
    'plat_audit_action_tenant_impersonate_end', 'plat_audit_action_tenant_status_change',
    'plat_audit_action_platform_user_create', 'plat_audit_action_platform_user_activate',
    'plat_audit_action_platform_user_deactivate', 'plat_audit_action_platform_settings_update',
    'plat_audit_col_date', 'plat_audit_col_action', 'plat_audit_col_user', 'plat_audit_col_ip',
]);
$platformName = htmlspecialchars($_SESSION['platform_name'] ?? '', ENT_QUOTES, 'UTF-8');
$platformRole = htmlspecialchars($_SESSION['platform_role'] ?? '', ENT_QUOTES, 'UTF-8');

$adminModules = [
    [
        'id' => 'users',
        'path' => 'users/index.php',
        'icon' => 'group',
        'label' => 'plat_nav_users',
        'desc' => 'plat_admin_mod_users_desc',
        'accent' => '#2563eb',
    ],
    [
        'id' => 'roles',
        'path' => 'roles/index.php',
        'icon' => 'badge',
        'label' => 'plat_nav_roles',
        'desc' => 'plat_admin_mod_roles_desc',
        'accent' => '#7c3aed',
    ],
    [
        'id' => 'permissions',
        'path' => 'permissions/index.php',
        'icon' => 'lock',
        'label' => 'plat_nav_permissions',
        'desc' => 'plat_admin_mod_permissions_desc',
        'accent' => '#6366f1',
    ],
    [
        'id' => 'security',
        'path' => 'security/index.php',
        'icon' => 'security',
        'label' => 'plat_nav_security',
        'desc' => 'plat_admin_mod_security_desc',
        'accent' => '#dc2626',
    ],
    [
        'id' => 'audit',
        'path' => 'audit/index.php',
        'icon' => 'fact_check',
        'label' => 'plat_nav_audit',
        'desc' => 'plat_admin_mod_audit_desc',
        'accent' => '#0891b2',
    ],
    [
        'id' => 'settings',
        'path' => 'settings/index.php',
        'icon' => 'settings',
        'label' => 'plat_nav_settings',
        'desc' => 'plat_admin_mod_settings_desc',
        'accent' => '#64748b',
    ],
];

require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-admin plat-gov">
    <div class="plat-gov-error" id="platAdminError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platAdminErrorText"></span>
    </div>

    <section class="plat-gov-hero plat-admin-hero" aria-labelledby="platAdminHeroTitle">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge plat-admin-badge">
                <span class="material-icons-round" aria-hidden="true">admin_panel_settings</span>
                <?php echo __t('plat_admin_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title" id="platAdminHeroTitle"><?php echo __t('plat_nav_admin', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_admin_subtitle', 'platform'); ?></p>
            <?php if ($platformName !== ''): ?>
            <p class="plat-admin-welcome"><?php echo sprintf(__t('plat_admin_welcome', 'platform'), $platformName); ?>
                <?php if ($platformRole !== ''): ?>
                <span class="plat-admin-role-pill"><?php echo $platformRole; ?></span>
                <?php endif; ?>
            </p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plat-kpi-grid plat-admin-kpi-grid" id="platAdminKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">group</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_admin_kpi_operators', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAdminKpiOperators">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_admin_kpi_active_ops', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAdminKpiActive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">fact_check</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_admin_kpi_audit_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAdminKpiAudit">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--warn is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">gpp_bad</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_admin_kpi_failed_logins', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAdminKpiFailed">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-admin-modules" aria-labelledby="platAdminModulesTitle">
        <div class="plat-admin-modules__head">
            <h2 id="platAdminModulesTitle">
                <span class="material-icons-round" aria-hidden="true">grid_view</span>
                <?php echo __t('plat_admin_modules_title', 'platform'); ?>
            </h2>
        </div>
        <div class="plat-admin-modules-grid">
            <?php foreach ($adminModules as $mod): ?>
            <a href="<?php echo htmlspecialchars(plat_href($mod['path']), ENT_QUOTES, 'UTF-8'); ?>"
               class="plat-admin-module-card"
               style="--plat-admin-accent: <?php echo htmlspecialchars($mod['accent'], ENT_QUOTES, 'UTF-8'); ?>">
                <span class="plat-admin-module-card__icon" aria-hidden="true">
                    <span class="material-icons-round"><?php echo htmlspecialchars($mod['icon'], ENT_QUOTES, 'UTF-8'); ?></span>
                </span>
                <span class="plat-admin-module-card__body">
                    <strong><?php echo __t($mod['label'], 'platform'); ?></strong>
                    <span><?php echo __t($mod['desc'], 'platform'); ?></span>
                </span>
                <span class="material-icons-round plat-admin-module-card__arrow" aria-hidden="true">arrow_forward</span>
            </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="plat-panel plat-admin-audit" aria-labelledby="platAdminAuditTitle">
        <div class="plat-admin-audit__head">
            <h2 id="platAdminAuditTitle">
                <span class="material-icons-round" aria-hidden="true">history</span>
                <?php echo __t('plat_admin_recent_audit', 'platform'); ?>
            </h2>
            <a href="<?php echo htmlspecialchars(plat_href('audit/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-admin-view-all">
                <?php echo __t('plat_admin_view_all', 'platform'); ?>
                <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
            </a>
        </div>
        <div class="plat-table-wrap">
            <table class="plat-table plat-admin-audit-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_audit_col_date', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_action', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_user', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_ip', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platAdminAuditBody">
                    <tr>
                        <td colspan="4" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

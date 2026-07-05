<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'audit';
$pageTitle = __t('plat_nav_audit', 'platform');
$extraStyles = ['platform-governance.css'];
$extraScripts = ['platform-common.js', 'platform-audit.js'];
$pageI18n = plat_i18n([
    'plat_nav_audit', 'plat_nav_reports', 'plat_nav_security', 'plat_col_name', 'plat_no_data',
    'plat_search', 'plat_clear_filters', 'loading', 'load_error', 'plat_view_detail',
    'plat_audit_badge', 'plat_audit_subtitle', 'plat_audit_load_error', 'plat_audit_count',
    'plat_audit_kpi_total', 'plat_audit_kpi_today', 'plat_audit_kpi_users', 'plat_audit_kpi_tenants',
    'plat_audit_log_title', 'plat_audit_actions_title', 'plat_audit_col_action', 'plat_audit_col_user',
    'plat_audit_col_org', 'plat_audit_col_ip', 'plat_audit_col_date', 'plat_audit_filter_all_actions',
    'plat_audit_detail_title', 'plat_audit_detail_close', 'plat_audit_detail_details',
    'plat_audit_action_platform_login_success', 'plat_audit_action_platform_login_failed',
    'plat_audit_action_platform_logout', 'plat_audit_action_tenant_impersonate_start',
    'plat_audit_action_tenant_impersonate_end', 'plat_audit_action_tenant_status_change',
    'plat_audit_action_tenant_trial_extended', 'plat_audit_action_tenant_plan_change',
    'plat_audit_action_tenant_module_overrides', 'plat_audit_action_tenant_feature_flags',
    'plat_audit_action_license_issue', 'plat_audit_action_license_revoke',
    'plat_audit_action_payment_mobile_money_confirm', 'plat_audit_action_domain_verify',
    'plat_audit_action_platform_user_create', 'plat_audit_action_platform_user_activate',
    'plat_audit_action_platform_user_deactivate', 'plat_audit_view_reports',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-gov-audit">
    <div class="plat-gov-error" id="platAuditError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platAuditErrorText"></span>
    </div>

    <section class="plat-gov-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge">
                <span class="material-icons-round" aria-hidden="true">fact_check</span>
                <?php echo __t('plat_audit_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_audit', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_audit_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-gov-hero__actions">
            <p class="plat-gov-count" id="platAuditCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('reports/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-gov-link-btn">
                <span class="material-icons-round" aria-hidden="true">download</span>
                <?php echo __t('plat_audit_view_reports', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platAuditKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">history</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_audit_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAuditKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">today</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_audit_kpi_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAuditKpiToday">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">group</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_audit_kpi_users', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAuditKpiUsers">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_audit_kpi_tenants', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platAuditKpiTenants">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-gov-panel">
        <header class="plat-gov-panel-head">
            <h3><span class="material-icons-round" aria-hidden="true">pie_chart</span><?php echo __t('plat_audit_actions_title', 'platform'); ?></h3>
        </header>
        <div class="plat-gov-actions-grid" id="platAuditActions">
            <span class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</span>
        </div>
    </section>

    <section class="plat-panel plat-gov-panel">
        <header class="plat-gov-panel-head">
            <h3><span class="material-icons-round" aria-hidden="true">list_alt</span><?php echo __t('plat_audit_log_title', 'platform'); ?></h3>
        </header>
        <div class="plat-gov-toolbar">
            <div class="plat-gov-search-wrap">
                <span class="material-icons-round plat-gov-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platAuditSearch" class="plat-search plat-gov-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platAuditActionFilter" class="plat-select" aria-label="<?php echo __t('plat_audit_col_action', 'platform'); ?>">
                <option value=""><?php echo __t('plat_audit_filter_all_actions', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-gov-btn" id="platAuditClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>
        <div class="plat-table-wrap plat-gov-table-wrap">
            <table class="plat-table plat-gov-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_audit_col_action', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_user', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_org', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_ip', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_date', 'platform'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="platAuditLogs">
                    <tr><td colspan="6" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="plat-gov-modal" id="platAuditModal" hidden role="dialog" aria-modal="true">
    <div class="plat-gov-modal__backdrop" data-close-audit-modal></div>
    <div class="plat-gov-modal__panel">
        <header class="plat-gov-modal__head">
            <h3><?php echo __t('plat_audit_detail_title', 'platform'); ?></h3>
        </header>
        <div class="plat-gov-modal__body" id="platAuditDetail"></div>
        <footer class="plat-gov-modal__foot">
            <button type="button" class="plat-gov-btn" data-close-audit-modal><?php echo __t('plat_audit_detail_close', 'platform'); ?></button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

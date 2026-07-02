<?php
require __DIR__ . '/includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$tenantId = (int) ($_GET['id'] ?? 0);
if ($tenantId <= 0) {
    header('Location: tenants.php');
    exit;
}

$activePlatPage = 'tenants';
$pageTitle = __t('plat_tenant_detail', 'platform');
$extraScripts = ['platform-common.js', 'platform-tenant-detail.js'];
$pageI18n = plat_i18n([
    'plat_tenant_detail', 'plat_col_name', 'plat_col_slug', 'plat_col_status', 'plat_col_plan',
    'plat_col_stores', 'plat_col_users', 'plat_trial_ends', 'plat_usage', 'plat_modules',
    'plat_billing_events', 'plat_audit_log', 'plat_feature_flags', 'plat_actions',
    'plat_suspend', 'plat_restore', 'plat_extend_trial', 'plat_change_plan', 'plat_impersonate',
    'plat_save_modules', 'plat_save_flags', 'plat_module_inherit', 'plat_module_on', 'plat_module_off',
    'plat_no_data', 'plat_confirm_suspend',     'plat_confirm_impersonate', 'plat_days',
    'plat_event_type', 'plat_event_amount', 'plat_event_date', 'plat_audit_action',
    'plat_audit_user', 'plat_audit_date', 'plat_usage_metrics', 'plat_usage_period',
    'action_success', 'action_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<input type="hidden" id="platTenantId" value="<?php echo $tenantId; ?>">

<section class="plat-detail-header" id="platTenantHeader">
    <p><?php echo __t('loading', 'platform'); ?></p>
</section>

<div class="plat-detail-grid">
    <section class="plat-panel">
        <h2><?php echo __t('plat_usage', 'platform'); ?></h2>
        <div class="plat-usage-grid" id="platUsageGrid">—</div>
    </section>

    <section class="plat-panel">
        <h2><?php echo __t('plat_actions', 'platform'); ?></h2>
        <div class="plat-action-bar" id="platActionBar">
            <button type="button" class="btn-secondary" id="platBtnSuspend"><?php echo __t('plat_suspend', 'platform'); ?></button>
            <button type="button" class="btn-secondary" id="platBtnRestore"><?php echo __t('plat_restore', 'platform'); ?></button>
            <button type="button" class="btn-secondary" id="platBtnTrial"><?php echo __t('plat_extend_trial', 'platform'); ?></button>
            <select id="platPlanSelect" class="plat-select" aria-label="<?php echo __t('plat_change_plan', 'platform'); ?>"></select>
            <button type="button" class="btn-primary" id="platBtnPlan"><?php echo __t('plat_change_plan', 'platform'); ?></button>
            <button type="button" class="btn-primary plat-btn-impersonate" id="platBtnImpersonate">
                <span class="material-icons-round">login</span>
                <?php echo __t('plat_impersonate', 'platform'); ?>
            </button>
        </div>
        <p class="plat-action-msg" id="platActionMsg" hidden></p>
    </section>

    <section class="plat-panel plat-panel--wide">
        <h2><?php echo __t('plat_modules', 'platform'); ?></h2>
        <div class="plat-module-grid" id="platModuleGrid"></div>
        <button type="button" class="btn-primary" id="platBtnSaveModules"><?php echo __t('plat_save_modules', 'platform'); ?></button>
    </section>

    <section class="plat-panel">
        <h2><?php echo __t('plat_feature_flags', 'platform'); ?></h2>
        <div id="platFeatureFlags"></div>
        <button type="button" class="btn-secondary" id="platBtnSaveFlags"><?php echo __t('plat_save_flags', 'platform'); ?></button>
    </section>

    <section class="plat-panel plat-panel--wide">
        <h2><?php echo __t('plat_usage_metrics', 'platform'); ?></h2>
        <div class="plat-usage-grid" id="platMetricsGrid"><p><?php echo __t('loading', 'platform'); ?></p></div>
    </section>

    <section class="plat-panel plat-panel--wide">
        <h2><?php echo __t('plat_billing_events', 'platform'); ?></h2>
        <div class="plat-table-wrap">
            <table class="plat-table" id="platBillingTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_event_type', 'platform'); ?></th>
                        <th><?php echo __t('plat_event_amount', 'platform'); ?></th>
                        <th><?php echo __t('plat_event_date', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platBillingBody"><tr><td colspan="3"><?php echo __t('loading', 'platform'); ?></td></tr></tbody>
            </table>
        </div>
    </section>

    <section class="plat-panel plat-panel--wide">
        <h2><?php echo __t('plat_audit_log', 'platform'); ?></h2>
        <div class="plat-table-wrap">
            <table class="plat-table" id="platAuditTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_audit_action', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_user', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_date', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platAuditBody"><tr><td colspan="3"><?php echo __t('loading', 'platform'); ?></td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

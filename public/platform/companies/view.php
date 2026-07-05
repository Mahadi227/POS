<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$tenantId = (int) ($_GET['id'] ?? 0);
if ($tenantId <= 0) {
    header('Location: index.php');
    exit;
}

$activePlatPage = 'companies';
$pageTitle = __t('plat_tenant_detail', 'platform');
$extraStyles = ['platform-company-view.css'];
$extraScripts = ['platform-common.js', 'platform-tenant-detail.js'];
$pageI18n = plat_i18n([
    'plat_tenant_detail', 'plat_col_name', 'plat_col_slug', 'plat_col_status', 'plat_col_plan',
    'plat_col_stores', 'plat_col_users', 'plat_trial_ends', 'plat_usage', 'plat_modules',
    'plat_billing_events', 'plat_audit_log', 'plat_feature_flags', 'plat_actions',
    'plat_suspend', 'plat_restore', 'plat_extend_trial', 'plat_change_plan', 'plat_impersonate',
    'plat_save_modules', 'plat_save_flags', 'plat_module_inherit', 'plat_module_on', 'plat_module_off',
    'plat_no_data', 'plat_confirm_suspend', 'plat_confirm_impersonate', 'plat_days',
    'plat_event_type', 'plat_event_amount', 'plat_event_date', 'plat_audit_action',
    'plat_audit_user', 'plat_audit_date', 'plat_usage_metrics', 'plat_usage_period',
    'action_success', 'action_error', 'loading', 'load_error',
    'plat_company_back', 'plat_company_badge', 'plat_company_load_error', 'plat_company_stores',
    'plat_company_subscriptions_link', 'plat_status_trial', 'plat_status_active',
    'plat_status_suspended', 'plat_status_cancelled', 'plat_sub_status_active', 'plat_sub_status_trial',
    'plat_sub_status_past_due', 'plat_sub_status_cancelled',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<input type="hidden" id="platTenantId" value="<?php echo $tenantId; ?>">

<div class="plat-company-view">
    <div class="plat-company-error" id="platCompanyError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platCompanyErrorText"></span>
    </div>
    <div class="plat-company-alert" id="platCompanyAlert" hidden role="status"></div>

    <div class="plat-company-topbar">
        <a href="index.php" class="plat-company-back">
            <span class="material-icons-round" aria-hidden="true">arrow_back</span>
            <?php echo __t('plat_company_back', 'platform'); ?>
        </a>
        <a href="<?php echo htmlspecialchars(plat_href('subscriptions/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-company-sub-link">
            <span class="material-icons-round" aria-hidden="true">autorenew</span>
            <?php echo __t('plat_company_subscriptions_link', 'platform'); ?>
        </a>
    </div>

    <section class="plat-company-hero" id="platTenantHeader" aria-live="polite">
        <div class="plat-company-loading">
            <span class="plat-company-spinner" aria-hidden="true"></span>
            <?php echo __t('loading', 'platform'); ?>…
        </div>
    </section>

    <div class="plat-company-grid plat-detail-grid">
        <section class="plat-panel plat-company-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">insights</span>
                <?php echo __t('plat_usage', 'platform'); ?>
            </h2>
            <div class="plat-usage-grid" id="platUsageGrid">
                <div class="plat-company-loading plat-company-loading--inline">
                    <span class="plat-company-spinner" aria-hidden="true"></span>
                </div>
            </div>
        </section>

        <section class="plat-panel plat-company-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">bolt</span>
                <?php echo __t('plat_actions', 'platform'); ?>
            </h2>
            <div class="plat-action-bar plat-company-actions" id="platActionBar">
                <button type="button" class="plat-company-btn plat-company-btn--warn" id="platBtnSuspend">
                    <span class="material-icons-round" aria-hidden="true">block</span>
                    <?php echo __t('plat_suspend', 'platform'); ?>
                </button>
                <button type="button" class="plat-company-btn" id="platBtnRestore">
                    <span class="material-icons-round" aria-hidden="true">check_circle</span>
                    <?php echo __t('plat_restore', 'platform'); ?>
                </button>
                <button type="button" class="plat-company-btn" id="platBtnTrial">
                    <span class="material-icons-round" aria-hidden="true">schedule</span>
                    <?php echo __t('plat_extend_trial', 'platform'); ?>
                </button>
                <div class="plat-company-plan-row">
                    <select id="platPlanSelect" class="plat-select" aria-label="<?php echo __t('plat_change_plan', 'platform'); ?>"></select>
                    <button type="button" class="plat-company-btn plat-company-btn--primary" id="platBtnPlan">
                        <?php echo __t('plat_change_plan', 'platform'); ?>
                    </button>
                </div>
                <button type="button" class="plat-company-btn plat-company-btn--accent" id="platBtnImpersonate">
                    <span class="material-icons-round" aria-hidden="true">login</span>
                    <?php echo __t('plat_impersonate', 'platform'); ?>
                </button>
            </div>
        </section>

        <section class="plat-panel plat-company-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">store</span>
                <?php echo __t('plat_company_stores', 'platform'); ?>
            </h2>
            <div class="plat-company-stores" id="platStoresList">
                <p class="plat-company-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>

        <section class="plat-panel plat-company-panel plat-panel--wide">
            <h2>
                <span class="material-icons-round" aria-hidden="true">extension</span>
                <?php echo __t('plat_modules', 'platform'); ?>
            </h2>
            <div class="plat-module-grid" id="platModuleGrid"></div>
            <button type="button" class="plat-company-btn plat-company-btn--primary" id="platBtnSaveModules">
                <?php echo __t('plat_save_modules', 'platform'); ?>
            </button>
        </section>

        <section class="plat-panel plat-company-panel">
            <h2>
                <span class="material-icons-round" aria-hidden="true">flag</span>
                <?php echo __t('plat_feature_flags', 'platform'); ?>
            </h2>
            <div id="platFeatureFlags"></div>
            <button type="button" class="plat-company-btn" id="platBtnSaveFlags">
                <?php echo __t('plat_save_flags', 'platform'); ?>
            </button>
        </section>

        <section class="plat-panel plat-company-panel plat-panel--wide">
            <h2>
                <span class="material-icons-round" aria-hidden="true">monitoring</span>
                <?php echo __t('plat_usage_metrics', 'platform'); ?>
            </h2>
            <div class="plat-usage-grid" id="platMetricsGrid">
                <p class="plat-company-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>

        <section class="plat-panel plat-company-panel plat-panel--wide">
            <h2>
                <span class="material-icons-round" aria-hidden="true">receipt_long</span>
                <?php echo __t('plat_billing_events', 'platform'); ?>
            </h2>
            <div class="plat-table-wrap">
                <table class="plat-table plat-company-table" id="platBillingTable">
                    <thead>
                        <tr>
                            <th><?php echo __t('plat_event_type', 'platform'); ?></th>
                            <th><?php echo __t('plat_event_amount', 'platform'); ?></th>
                            <th><?php echo __t('plat_event_date', 'platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="platBillingBody">
                        <tr><td colspan="3"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="plat-panel plat-company-panel plat-panel--wide">
            <h2>
                <span class="material-icons-round" aria-hidden="true">history</span>
                <?php echo __t('plat_audit_log', 'platform'); ?>
            </h2>
            <div class="plat-table-wrap">
                <table class="plat-table plat-company-table" id="platAuditTable">
                    <thead>
                        <tr>
                            <th><?php echo __t('plat_audit_action', 'platform'); ?></th>
                            <th><?php echo __t('plat_audit_user', 'platform'); ?></th>
                            <th><?php echo __t('plat_audit_date', 'platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="platAuditBody">
                        <tr><td colspan="3"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'integrations';
$pageTitle = __t('plat_nav_integrations', 'platform');
$extraStyles = ['platform-governance.css', 'platform-integrations.css'];
$extraScripts = ['platform-common.js', 'platform-integrations.js'];
$canManage = ($_SESSION['platform_role'] ?? '') === 'platform_admin';
$pageI18n = plat_i18n([
    'plat_nav_integrations', 'plat_int_badge', 'plat_int_subtitle', 'plat_int_load_error',
    'plat_int_count', 'plat_int_kpi_providers', 'plat_int_kpi_enabled', 'plat_int_kpi_connections',
    'plat_int_kpi_connected', 'plat_int_kpi_tenants', 'plat_int_tab_providers', 'plat_int_tab_connections',
    'plat_int_cat_payments', 'plat_int_cat_communications', 'plat_int_cat_developer', 'plat_int_cat_analytics',
    'plat_int_cat_shipping', 'plat_int_cat_other', 'plat_int_status_enabled', 'plat_int_status_disabled',
    'plat_int_conn_connected', 'plat_int_conn_disconnected', 'plat_int_conn_pending', 'plat_int_conn_error',
    'plat_int_col_provider', 'plat_int_col_tenant', 'plat_int_col_ref', 'plat_int_col_last_sync',
    'plat_int_connections_count', 'plat_int_active_count', 'plat_int_official', 'plat_int_enable',
    'plat_int_disable', 'plat_int_admin_only', 'plat_int_view_org', 'plat_int_filter_all_categories',
    'plat_int_filter_all_status', 'plat_col_status', 'plat_search', 'plat_clear_filters', 'loading',
    'load_error', 'plat_no_data', 'action_success', 'action_error',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-int">
    <div class="plat-gov-error" id="platIntError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platIntErrorText"></span>
    </div>
    <div class="plat-int-alert" id="platIntAlert" hidden role="status"></div>

    <section class="plat-gov-hero plat-int-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge plat-int-badge">
                <span class="material-icons-round" aria-hidden="true">hub</span>
                <?php echo __t('plat_int_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_integrations', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_int_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-gov-hero__actions">
            <p class="plat-gov-count" id="platIntCount" aria-live="polite"></p>
            <?php if (!$canManage): ?>
            <p class="plat-int-admin-hint"><?php echo __t('plat_int_admin_only', 'platform'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platIntKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hub</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_int_kpi_providers', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platIntKpiProviders">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">toggle_on</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_int_kpi_enabled', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platIntKpiEnabled">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">link</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_int_kpi_connections', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platIntKpiConnections">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_int_kpi_connected', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platIntKpiConnected">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_int_kpi_tenants', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platIntKpiTenants">—</strong>
        </article>
    </section>

    <div class="plat-int-tabs" role="tablist">
        <button type="button" class="plat-int-tab is-active" role="tab" aria-selected="true" data-tab="providers" id="platIntTabProviders">
            <?php echo __t('plat_int_tab_providers', 'platform'); ?>
        </button>
        <button type="button" class="plat-int-tab" role="tab" aria-selected="false" data-tab="connections" id="platIntTabConnections">
            <?php echo __t('plat_int_tab_connections', 'platform'); ?>
        </button>
    </div>

    <section class="plat-panel plat-int-panel" id="platIntProvidersPanel" role="tabpanel">
        <div class="plat-int-toolbar">
            <div class="plat-int-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="platIntProviderSearch" class="plat-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platIntProviderCategory" class="plat-select" aria-label="<?php echo __t('plat_int_filter_all_categories', 'platform'); ?>">
                <option value=""><?php echo __t('plat_int_filter_all_categories', 'platform'); ?></option>
                <option value="payments"><?php echo __t('plat_int_cat_payments', 'platform'); ?></option>
                <option value="communications"><?php echo __t('plat_int_cat_communications', 'platform'); ?></option>
                <option value="developer"><?php echo __t('plat_int_cat_developer', 'platform'); ?></option>
                <option value="analytics"><?php echo __t('plat_int_cat_analytics', 'platform'); ?></option>
                <option value="shipping"><?php echo __t('plat_int_cat_shipping', 'platform'); ?></option>
                <option value="other"><?php echo __t('plat_int_cat_other', 'platform'); ?></option>
            </select>
            <select id="platIntProviderStatus" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_int_filter_all_status', 'platform'); ?></option>
                <option value="enabled"><?php echo __t('plat_int_status_enabled', 'platform'); ?></option>
                <option value="disabled"><?php echo __t('plat_int_status_disabled', 'platform'); ?></option>
            </select>
        </div>
        <div class="plat-int-grid" id="platIntProviderGrid">
            <p class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</p>
        </div>
    </section>

    <section class="plat-panel plat-int-panel" id="platIntConnectionsPanel" role="tabpanel" hidden>
        <div class="plat-int-toolbar">
            <div class="plat-int-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="platIntConnSearch" class="plat-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platIntConnStatus" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_int_filter_all_status', 'platform'); ?></option>
                <option value="connected"><?php echo __t('plat_int_conn_connected', 'platform'); ?></option>
                <option value="disconnected"><?php echo __t('plat_int_conn_disconnected', 'platform'); ?></option>
                <option value="pending"><?php echo __t('plat_int_conn_pending', 'platform'); ?></option>
                <option value="error"><?php echo __t('plat_int_conn_error', 'platform'); ?></option>
            </select>
            <select id="platIntConnCategory" class="plat-select" aria-label="<?php echo __t('plat_int_filter_all_categories', 'platform'); ?>">
                <option value=""><?php echo __t('plat_int_filter_all_categories', 'platform'); ?></option>
                <option value="payments"><?php echo __t('plat_int_cat_payments', 'platform'); ?></option>
                <option value="communications"><?php echo __t('plat_int_cat_communications', 'platform'); ?></option>
                <option value="developer"><?php echo __t('plat_int_cat_developer', 'platform'); ?></option>
                <option value="analytics"><?php echo __t('plat_int_cat_analytics', 'platform'); ?></option>
            </select>
        </div>
        <div class="plat-table-wrap">
            <table class="plat-table plat-int-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_int_col_provider', 'platform'); ?></th>
                        <th><?php echo __t('plat_int_col_tenant', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_int_col_ref', 'platform'); ?></th>
                        <th><?php echo __t('plat_int_col_last_sync', 'platform'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="platIntConnBody">
                    <tr><td colspan="6" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<script>window.PLATFORM_INTEGRATIONS = { canManage: <?php echo $canManage ? 'true' : 'false'; ?> };</script>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

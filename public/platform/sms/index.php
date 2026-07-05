<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'sms';
$pageTitle = __t('plat_nav_sms', 'platform');
$extraStyles = ['platform-comms.css'];
$extraScripts = ['platform-common.js', 'platform-sms.js'];
$pageI18n = plat_i18n([
    'plat_nav_sms', 'plat_search', 'plat_no_data', 'loading', 'load_error',
    'plat_sms_subtitle', 'plat_sms_badge', 'plat_sms_load_error', 'plat_sms_count',
    'plat_sms_kpi_total', 'plat_sms_kpi_today', 'plat_sms_kpi_failed', 'plat_sms_kpi_templates',
    'plat_sms_templates_title', 'plat_sms_log_title', 'plat_sms_col_template', 'plat_sms_col_recipient',
    'plat_sms_col_tenant', 'plat_sms_col_status', 'plat_sms_col_sent', 'plat_sms_filter_all_templates',
    'plat_sms_status_sent', 'plat_sms_status_failed',
    'plat_sms_tpl_account_locked', 'plat_sms_tpl_trial_ending', 'plat_sms_tpl_payment_failed',
    'plat_sms_tpl_security_alert', 'plat_sms_tpl_otp_verification',
    'plat_sms_cat_security', 'plat_sms_cat_billing',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-comms plat-comms-sms">
    <div class="plat-comms-error" id="platSmsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platSmsErrorText"></span>
    </div>

    <section class="plat-comms-hero">
        <div class="plat-comms-hero__intro">
            <div class="plat-comms-badge"><span class="material-icons-round" aria-hidden="true">sms</span><?php echo __t('plat_sms_badge', 'platform'); ?></div>
            <h2 class="plat-comms-hero__title"><?php echo __t('plat_nav_sms', 'platform'); ?></h2>
            <p class="plat-comms-hero__desc"><?php echo __t('plat_sms_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-comms-count" id="platSmsCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-comms-kpi-grid" id="platSmsKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">sms</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_sms_kpi_total', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platSmsKpiTotal">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">today</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_sms_kpi_today', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platSmsKpiToday">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">error</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_sms_kpi_failed', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platSmsKpiFailed">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">description</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_sms_kpi_templates', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platSmsKpiTemplates">—</strong></article>
    </section>

    <section class="plat-panel plat-comms-panel">
        <h3 style="padding:16px 20px 0;margin:0;font-size:1rem;"><?php echo __t('plat_sms_templates_title', 'platform'); ?></h3>
        <div class="plat-comms-grid" id="platSmsTemplates"></div>
        <div class="plat-comms-toolbar">
            <div class="plat-comms-search-wrap">
                <span class="material-icons-round plat-comms-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platSmsSearch" class="plat-search plat-comms-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platSmsTemplateFilter" class="plat-select">
                <option value=""><?php echo __t('plat_sms_filter_all_templates', 'platform'); ?></option>
            </select>
        </div>
        <h3 style="padding:0 20px;margin:0;font-size:1rem;"><?php echo __t('plat_sms_log_title', 'platform'); ?></h3>
        <div class="plat-table-wrap plat-comms-table-wrap">
            <table class="plat-table plat-comms-table">
                <thead><tr>
                    <th><?php echo __t('plat_sms_col_template', 'platform'); ?></th>
                    <th><?php echo __t('plat_sms_col_recipient', 'platform'); ?></th>
                    <th><?php echo __t('plat_sms_col_tenant', 'platform'); ?></th>
                    <th><?php echo __t('plat_sms_col_status', 'platform'); ?></th>
                    <th><?php echo __t('plat_sms_col_sent', 'platform'); ?></th>
                </tr></thead>
                <tbody id="platSmsLogs"><tr><td colspan="5" class="plat-comms-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

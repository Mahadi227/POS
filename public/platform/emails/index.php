<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'emails';
$pageTitle = __t('plat_nav_emails', 'platform');
$extraStyles = ['platform-comms.css'];
$extraScripts = ['platform-common.js', 'platform-emails.js'];
$pageI18n = plat_i18n([
    'plat_nav_emails', 'plat_search', 'plat_clear_filters', 'plat_no_data', 'loading', 'load_error',
    'plat_email_subtitle', 'plat_email_badge', 'plat_email_load_error', 'plat_email_count',
    'plat_email_kpi_total', 'plat_email_kpi_today', 'plat_email_kpi_templates', 'plat_email_kpi_tenants',
    'plat_email_templates_title', 'plat_email_log_title', 'plat_email_col_template', 'plat_email_col_recipient',
    'plat_email_col_tenant', 'plat_email_col_sent', 'plat_email_filter_all_templates',
    'plat_email_tpl_welcome', 'plat_email_tpl_trial_ending_7', 'plat_email_tpl_trial_ending_3',
    'plat_email_tpl_trial_ending_1', 'plat_email_tpl_payment_failed',
    'plat_email_cat_onboarding', 'plat_email_cat_billing',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-comms plat-comms-email">
    <div class="plat-comms-error" id="platEmailError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platEmailErrorText"></span>
    </div>

    <section class="plat-comms-hero">
        <div class="plat-comms-hero__intro">
            <div class="plat-comms-badge"><span class="material-icons-round" aria-hidden="true">mail</span><?php echo __t('plat_email_badge', 'platform'); ?></div>
            <h2 class="plat-comms-hero__title"><?php echo __t('plat_nav_emails', 'platform'); ?></h2>
            <p class="plat-comms-hero__desc"><?php echo __t('plat_email_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-comms-count" id="platEmailCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-comms-kpi-grid" id="platEmailKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">mail</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_email_kpi_total', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platEmailKpiTotal">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">today</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_email_kpi_today', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platEmailKpiToday">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">description</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_email_kpi_templates', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platEmailKpiTemplates">—</strong></article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading"><span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">business</span></span><span class="plat-kpi-card__label"><?php echo __t('plat_email_kpi_tenants', 'platform'); ?></span><strong class="plat-kpi-card__value" id="platEmailKpiTenants">—</strong></article>
    </section>

    <section class="plat-panel plat-comms-panel">
        <h3 style="padding:16px 20px 0;margin:0;font-size:1rem;"><?php echo __t('plat_email_templates_title', 'platform'); ?></h3>
        <div class="plat-comms-grid" id="platEmailTemplates"></div>
        <div class="plat-comms-toolbar">
            <div class="plat-comms-search-wrap">
                <span class="material-icons-round plat-comms-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platEmailSearch" class="plat-search plat-comms-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platEmailTemplateFilter" class="plat-select">
                <option value=""><?php echo __t('plat_email_filter_all_templates', 'platform'); ?></option>
            </select>
        </div>
        <h3 style="padding:0 20px;margin:0;font-size:1rem;"><?php echo __t('plat_email_log_title', 'platform'); ?></h3>
        <div class="plat-table-wrap plat-comms-table-wrap">
            <table class="plat-table plat-comms-table">
                <thead><tr>
                    <th><?php echo __t('plat_email_col_template', 'platform'); ?></th>
                    <th><?php echo __t('plat_email_col_recipient', 'platform'); ?></th>
                    <th><?php echo __t('plat_email_col_tenant', 'platform'); ?></th>
                    <th><?php echo __t('plat_email_col_sent', 'platform'); ?></th>
                </tr></thead>
                <tbody id="platEmailLogs"><tr><td colspan="4" class="plat-comms-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr></tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'settings';
$pageTitle = __t('plat_nav_settings', 'platform');
$extraStyles = ['platform-settings.css'];
$extraScripts = ['platform-common.js', 'platform-settings.js'];
$pageI18n = plat_i18n([
    'plat_nav_settings', 'plat_no_data', 'loading', 'load_error', 'action_success', 'action_error',
    'plat_settings_badge', 'plat_settings_subtitle', 'plat_settings_load_error',
    'plat_settings_kpi_settings', 'plat_settings_kpi_categories', 'plat_settings_kpi_flags',
    'plat_settings_cat_general', 'plat_settings_cat_security', 'plat_settings_cat_communications',
    'plat_settings_cat_billing', 'plat_settings_flags_title', 'plat_settings_save',
    'plat_settings_key_product_name', 'plat_settings_key_support_email', 'plat_settings_key_default_locale',
    'plat_settings_key_lockout_threshold', 'plat_settings_key_lockout_window_minutes',
    'plat_settings_key_email_from', 'plat_settings_key_trial_days',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-settings">
    <div class="plat-settings-error" id="platSettingsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platSettingsErrorText"></span>
    </div>
    <div class="plat-settings-alert" id="platSettingsAlert" hidden role="status"></div>

    <section class="plat-settings-hero">
        <div class="plat-settings-hero__intro">
            <div class="plat-settings-badge">
                <span class="material-icons-round" aria-hidden="true">tune</span>
                <?php echo __t('plat_settings_badge', 'platform'); ?>
            </div>
            <h2 class="plat-settings-hero__title"><?php echo __t('plat_nav_settings', 'platform'); ?></h2>
            <p class="plat-settings-hero__desc"><?php echo __t('plat_settings_subtitle', 'platform'); ?></p>
        </div>
    </section>

    <section class="plat-kpi-grid plat-settings-kpi-grid" id="platSettingsKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">settings</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_settings_kpi_settings', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSetKpiSettings">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">category</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_settings_kpi_categories', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSetKpiCategories">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">flag</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_settings_kpi_flags', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSetKpiFlags">—</strong>
        </article>
    </section>

    <div class="plat-settings-sections" id="platSettingsSections">
        <p class="plat-gov-muted" style="padding:20px;"><?php echo __t('loading', 'platform'); ?>…</p>
    </div>

    <section class="plat-panel plat-settings-section" id="platSettingsFlagsPanel" hidden>
        <header class="plat-settings-section__head">
            <h3><span class="material-icons-round" aria-hidden="true">flag</span><?php echo __t('plat_settings_flags_title', 'platform'); ?></h3>
            <button type="button" class="plat-settings-save-btn" id="platSettingsSaveFlags">
                <span class="material-icons-round" aria-hidden="true">save</span>
                <?php echo __t('plat_settings_save', 'platform'); ?>
            </button>
        </header>
        <div class="plat-settings-flags" id="platSettingsFlags"></div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

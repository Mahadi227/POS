<?php
require __DIR__ . '/includes/bootstrap.php';
WarehousePortalAuth::assertModule('settings');

$activeWhPage = 'settings';
$pageTitle = __t('wh_nav_settings', 'warehouse');
$extraScripts = ['warehouse-common.js', 'theme-settings.js', 'warehouse-settings.js'];
$pageI18n = wh_i18n([
    'wh_sett_title', 'wh_sett_subtitle', 'wh_sett_loading', 'wh_sett_loading_warehouse',
    'wh_sett_loading_inventory', 'wh_sett_loading_notifications', 'wh_sett_refreshing',
    'wh_sett_saved', 'wh_sett_error', 'wh_sett_reset', 'wh_sett_cancel',
    'wh_sett_no_warehouse',
    'wh_sett_save', 'wh_sett_readonly', 'wh_sett_offline_cached', 'wh_sett_confirm_reset', 'wh_sett_confirm_save',
    'wh_sett_nav_general', 'wh_sett_nav_warehouse', 'wh_sett_nav_inventory', 'wh_sett_nav_transfers',
    'wh_sett_nav_receiving', 'wh_sett_nav_dispatch', 'wh_sett_nav_barcode', 'wh_sett_nav_notifications',
    'wh_sett_nav_security', 'wh_sett_nav_offline', 'wh_sett_nav_reports', 'wh_sett_nav_logs', 'wh_sett_nav_theme',
    'wh_sett_wh_select', 'wh_sett_audit_user', 'wh_sett_audit_action', 'wh_sett_audit_date', 'wh_sett_audit_ip',
    'wh_sett_audit_old', 'wh_sett_audit_new', 'wh_sett_audit_search', 'wh_sett_audit_empty',
    'loading', 'load_error', 'save', 'cancel', 'refresh', 'connection_error', 'theme', 'theme_light', 'theme_dark', 'theme_system',
    'wh_settings_hint',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whSettOfflineBadge" class="wh-sett-offline-badge" hidden><?php echo __t('wh_sett_offline_cached', 'warehouse'); ?></div>
<div id="whSettToast" class="wh-sett-toast" role="status" aria-live="polite"></div>

<section class="wh-sett-page" id="whSettPage">
    <header class="wh-sett-page__head">
        <p class="wh-sett-page__intro wh-muted"><?php echo __t('wh_sett_subtitle', 'warehouse'); ?></p>
        <div class="wh-sett-page__actions" id="whSettHeadActions" hidden>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSettCancelBtn"><?php echo __t('wh_sett_cancel', 'warehouse'); ?></button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSettResetBtn"><?php echo __t('wh_sett_reset', 'warehouse'); ?></button>
            <button type="button" class="wh-btn" id="whSettSaveBtn"><span class="material-icons-round">save</span><?php echo __t('wh_sett_save', 'warehouse'); ?></button>
        </div>
    </header>
    <div class="wh-sett-loading" id="whSettLoading" aria-busy="true" aria-live="polite">
        <div class="wh-sett-loading__bar" aria-hidden="true"><span class="wh-sett-loading__bar-fill"></span></div>
        <div class="wh-sett-loading__status">
            <span class="wh-sett-loading__spinner" aria-hidden="true"></span>
            <span class="wh-sett-loading__text" id="whSettLoadingText"><?php echo __t('wh_sett_loading', 'warehouse'); ?></span>
        </div>
        <div class="wh-sett-loading__shell" aria-hidden="true">
            <div class="wh-sett-loading__layout">
                <div class="wh-sett-loading__nav">
                    <div class="wh-sett-skeleton wh-sett-skeleton--select"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item wh-sett-skeleton--delay-1"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item wh-sett-skeleton--delay-2"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--nav-item wh-sett-skeleton--delay-3"></div>
                </div>
                <div class="wh-sett-loading__main">
                    <div class="wh-sett-skeleton wh-sett-skeleton--card"></div>
                    <div class="wh-sett-loading__fields">
                        <div class="wh-sett-skeleton wh-sett-skeleton--field"></div>
                        <div class="wh-sett-skeleton wh-sett-skeleton--field wh-sett-skeleton--delay-1"></div>
                        <div class="wh-sett-skeleton wh-sett-skeleton--field wh-sett-skeleton--delay-2"></div>
                        <div class="wh-sett-skeleton wh-sett-skeleton--field wh-sett-skeleton--delay-3"></div>
                    </div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--toggle"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--toggle wh-sett-skeleton--delay-1"></div>
                    <div class="wh-sett-skeleton wh-sett-skeleton--toggle wh-sett-skeleton--delay-2"></div>
                </div>
            </div>
        </div>
    </div>
    <div class="wh-sett-layout" id="whSettRoot" hidden></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php';

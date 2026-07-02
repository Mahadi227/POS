<?php

require __DIR__ . '/includes/bootstrap.php';

WarehousePortalAuth::assertModule('profile');



$activeWhPage = 'profile';

$pageTitle = __t('wh_prof_title', 'warehouse');

$extraScripts = ['warehouse-common.js', 'warehouse-profile.js'];

$pageI18n = array_merge(

    wh_i18n([

        'wh_prof_title', 'wh_prof_subtitle', 'wh_prof_loading', 'wh_prof_loading_account',
        'wh_prof_loading_performance', 'wh_prof_loading_preferences', 'wh_prof_refreshing',
        'wh_prof_offline_cached',

        'wh_prof_readonly_banner', 'wh_prof_section_personal', 'wh_prof_section_contact',

        'wh_prof_notif_channels', 'wh_prof_notif_warehouse', 'wh_prof_security_password',

        'wh_prof_perf_title', 'wh_prof_activity_title', 'wh_prof_login_title',

        'wh_prof_tab_overview', 'wh_prof_tab_personal', 'wh_prof_tab_employment', 'wh_prof_tab_account',

        'wh_prof_tab_performance', 'wh_prof_tab_security', 'wh_prof_tab_notifications', 'wh_prof_tab_preferences',

        'wh_prof_tab_activity', 'wh_prof_tab_login',

        'wh_prof_employee_id', 'wh_prof_role', 'wh_prof_warehouse', 'wh_prof_branch', 'wh_prof_online',

        'wh_prof_member_since', 'wh_prof_account_status', 'wh_prof_first_name', 'wh_prof_last_name',

        'wh_prof_phone', 'wh_prof_email', 'wh_prof_address', 'wh_prof_emergency', 'wh_prof_language', 'wh_prof_timezone',

        'wh_prof_photo', 'wh_prof_upload_photo', 'wh_prof_remove_photo', 'wh_prof_readonly_hint',

        'wh_prof_department', 'wh_prof_supervisor', 'wh_prof_date_joined', 'wh_prof_employment_status', 'wh_prof_shift',

        'wh_prof_username', 'wh_prof_last_login', 'wh_prof_last_activity', 'wh_prof_session', 'wh_prof_device',

        'wh_prof_browser', 'wh_prof_os', 'wh_prof_ip',

        'wh_prof_perf_period', 'wh_prof_save', 'wh_prof_saved', 'wh_prof_error',

        'wh_prof_current_password', 'wh_prof_new_password', 'wh_prof_confirm_password', 'wh_prof_change_password',

        'wh_prof_2fa', 'wh_prof_2fa_hint', 'wh_prof_logout_devices', 'wh_prof_logout_devices_hint',

        'wh_prof_notif_dashboard', 'wh_prof_notif_email', 'wh_prof_notif_sms', 'wh_prof_notif_push',

        'wh_prof_notif_whatsapp', 'wh_prof_notif_low_stock', 'wh_prof_notif_transfer', 'wh_prof_notif_receiving', 'wh_prof_notif_dispatch',

        'wh_prof_pref_theme', 'wh_prof_pref_date', 'wh_prof_pref_time', 'wh_prof_pref_items', 'wh_prof_pref_layout', 'wh_prof_pref_wh_view',

        'wh_prof_theme_light', 'wh_prof_theme_dark', 'wh_prof_theme_system',

        'wh_prof_activity_action', 'wh_prof_activity_date', 'wh_prof_activity_status', 'wh_prof_activity_empty',

        'wh_prof_login_date', 'wh_prof_login_status', 'wh_prof_login_search', 'wh_prof_login_empty',

        'wh_prof_metric_receipts', 'wh_prof_metric_units_received', 'wh_prof_metric_po', 'wh_prof_metric_dispatches',

        'wh_prof_metric_deliveries', 'wh_prof_metric_transfers', 'wh_prof_metric_adjustments', 'wh_prof_metric_stock_counts',

        'wh_prof_metric_products', 'wh_prof_metric_inventory_value', 'wh_prof_metric_actions',

        'loading', 'load_error', 'save', 'refresh', 'connection_error', 'no_data',

        'theme_light', 'theme_dark', 'theme_system',

    ]),

    [

        'account_active' => __t('account_active', 'settings'),

        'account_inactive' => __t('account_inactive', 'settings'),

        'password_min_length' => __t('password_min_length', 'settings'),

        'password_mismatch' => __t('password_mismatch', 'settings'),

        'current_password_required' => __t('current_password_required', 'settings'),

    ]

);

require __DIR__ . '/includes/layout-start.php';

?>



<div id="whErrorBanner" class="ad-error-banner" hidden></div>

<div id="whProfOfflineBadge" class="wh-prof-offline-badge" hidden><?php echo __t('wh_prof_offline_cached', 'warehouse'); ?></div>

<div id="whProfToast" class="wh-prof-toast" role="status" aria-live="polite"></div>



<section class="wh-prof-page" id="whProfPage">

    <p class="wh-prof-page__intro wh-muted"><?php echo __t('wh_prof_subtitle', 'warehouse'); ?></p>

    <div class="wh-prof-loading" id="whProfLoading" aria-busy="true" aria-live="polite">
        <div class="wh-prof-loading__bar" aria-hidden="true"><span class="wh-prof-loading__bar-fill"></span></div>
        <div class="wh-prof-loading__status">
            <span class="wh-prof-loading__spinner" aria-hidden="true"></span>
            <span class="wh-prof-loading__text" id="whProfLoadingText"><?php echo __t('wh_prof_loading', 'warehouse'); ?></span>
        </div>
        <div class="wh-prof-loading__shell" aria-hidden="true">
            <div class="wh-prof-loading__hero">
                <div class="wh-prof-skeleton wh-prof-skeleton--avatar"></div>
                <div class="wh-prof-loading__hero-lines">
                    <div class="wh-prof-skeleton wh-prof-skeleton--line wh-prof-skeleton--lg"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--line"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--line wh-prof-skeleton--sm"></div>
                </div>
            </div>
            <div class="wh-prof-loading__layout">
                <div class="wh-prof-loading__nav">
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-user"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item wh-prof-skeleton--delay-1"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--nav-item wh-prof-skeleton--delay-2"></div>
                </div>
                <div class="wh-prof-loading__main">
                    <div class="wh-prof-skeleton wh-prof-skeleton--card"></div>
                    <div class="wh-prof-skeleton wh-prof-skeleton--card wh-prof-skeleton--delay-1"></div>
                    <div class="wh-prof-loading__kpi">
                        <div class="wh-prof-skeleton wh-prof-skeleton--kpi"></div>
                        <div class="wh-prof-skeleton wh-prof-skeleton--kpi wh-prof-skeleton--delay-1"></div>
                        <div class="wh-prof-skeleton wh-prof-skeleton--kpi wh-prof-skeleton--delay-2"></div>
                        <div class="wh-prof-skeleton wh-prof-skeleton--kpi wh-prof-skeleton--delay-3"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div id="whProfRoot" class="wh-prof-layout" hidden></div>

</section>



<?php require __DIR__ . '/includes/layout-end.php';



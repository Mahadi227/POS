<?php
require __DIR__ . '/includes/bootstrap.php';
WarehousePortalAuth::assertModule('help');

$activeWhPage = 'help';
$pageTitle = __t('wh_nav_help', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-help.js'];
$pageI18n = wh_i18n([
    'wh_hlp_title', 'wh_hlp_subtitle', 'wh_hlp_search_ph', 'wh_hlp_search_empty', 'wh_hlp_loading',
    'wh_hlp_offline_cached', 'wh_hlp_quick_actions', 'wh_hlp_guides', 'wh_hlp_faq', 'wh_hlp_videos',
    'wh_hlp_manuals', 'wh_hlp_contact', 'wh_hlp_report', 'wh_hlp_status', 'wh_hlp_updates', 'wh_hlp_tips',
    'wh_hlp_ticket_sent', 'wh_hlp_ticket_error', 'wh_hlp_categories', 'wh_hlp_view_article',
    'wh_hlp_subject', 'wh_hlp_description', 'wh_hlp_priority', 'wh_hlp_category', 'wh_hlp_submit',
    'wh_hlp_name', 'wh_hlp_email', 'wh_hlp_attachment', 'wh_hlp_problem_type', 'wh_hlp_my_tickets',
    'wh_hlp_status_open', 'wh_hlp_status_resolved', 'wh_hlp_download_manual', 'wh_hlp_breadcrumb_home',
    'loading', 'load_error', 'connection_error', 'search', 'close', 'cancel',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whHlpOfflineBadge" class="wh-hlp-offline-badge" hidden><?php echo __t('wh_hlp_offline_cached', 'warehouse'); ?></div>
<div id="whHlpToast" class="wh-hlp-toast" role="status" aria-live="polite"></div>

<section class="wh-hlp-page" id="whHlpPage">
    <div class="wh-hlp-loading" id="whHlpLoading">
        <div class="wh-hlp-skeleton wh-hlp-skeleton--hero"></div>
        <div class="wh-hlp-skeleton-grid"><div class="wh-hlp-skeleton"></div><div class="wh-hlp-skeleton"></div><div class="wh-hlp-skeleton"></div></div>
        <p><?php echo __t('wh_hlp_loading', 'warehouse'); ?></p>
    </div>
    <div id="whHlpRoot" hidden></div>
    <article id="whHlpArticleModal" class="wh-hlp-modal" hidden>
        <div class="wh-hlp-modal__backdrop" data-close-modal></div>
        <div class="wh-hlp-modal__panel" role="dialog" aria-modal="true">
            <header class="wh-hlp-modal__head">
                <nav class="wh-hlp-breadcrumb" id="whHlpBreadcrumb"></nav>
                <button type="button" class="wh-hlp-modal__close" data-close-modal aria-label="<?php echo __t('close', 'warehouse'); ?>"><span class="material-icons-round">close</span></button>
            </header>
            <div class="wh-hlp-modal__body" id="whHlpArticleBody"></div>
        </div>
    </article>
</section>

<?php require __DIR__ . '/includes/layout-end.php';

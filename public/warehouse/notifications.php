<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWhPage = 'notifications';
$pageTitle = __t('wh_nav_notifications', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-notifications.js'];
$pageI18n = wh_i18n([
    'wh_notif_subtitle', 'wh_notif_stat_total', 'wh_notif_stat_unread', 'wh_notif_stat_critical', 'wh_notif_stat_today',
    'wh_notif_tab_all', 'wh_notif_tab_unread', 'wh_notif_tab_pinned', 'wh_notif_tab_archived',
    'wh_notif_search', 'wh_notif_filter_priority', 'wh_notif_filter_category', 'wh_notif_filter_all',
    'wh_notif_mark_all', 'wh_notif_mark_read', 'wh_notif_archive', 'wh_notif_pin', 'wh_notif_unpin',
    'wh_notif_empty', 'wh_notif_empty_unread', 'wh_notif_open_link', 'wh_notif_priority_low', 'wh_notif_priority_normal',
    'wh_notif_priority_high', 'wh_notif_priority_critical', 'wh_notif_module_all', 'wh_notif_module_wh',
    'wh_notif_module_inventory', 'wh_notif_migration_hint',
    'loading', 'load_error', 'refresh', 'last_updated', 'no_data', 'prev_page', 'next_page', 'records',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="wh-notif-hero" aria-labelledby="whNotifHeroTitle">
    <div class="wh-notif-hero__intro">
        <h2 class="wh-notif-hero__title" id="whNotifHeroTitle"><?php echo __t('wh_notif_subtitle', 'warehouse'); ?></h2>
        <p class="wh-notif-hero__meta" id="whNotifHeroMeta" aria-live="polite">—</p>
    </div>
    <div class="wh-notif-hero__stats" role="group">
        <article class="wh-notif-stat wh-notif-stat--primary">
            <span class="wh-notif-stat__label"><?php echo __t('wh_notif_stat_total', 'warehouse'); ?></span>
            <strong class="wh-notif-stat__value is-loading" id="whNotifStatTotal">—</strong>
        </article>
        <article class="wh-notif-stat wh-notif-stat--warn">
            <span class="wh-notif-stat__label"><?php echo __t('wh_notif_stat_unread', 'warehouse'); ?></span>
            <strong class="wh-notif-stat__value is-loading" id="whNotifStatUnread">—</strong>
        </article>
        <article class="wh-notif-stat wh-notif-stat--danger">
            <span class="wh-notif-stat__label"><?php echo __t('wh_notif_stat_critical', 'warehouse'); ?></span>
            <strong class="wh-notif-stat__value is-loading" id="whNotifStatCritical">—</strong>
        </article>
        <article class="wh-notif-stat">
            <span class="wh-notif-stat__label"><?php echo __t('wh_notif_stat_today', 'warehouse'); ?></span>
            <strong class="wh-notif-stat__value is-loading" id="whNotifStatToday">—</strong>
        </article>
    </div>
</section>

<div class="wh-notif-toolbar">
    <div class="wh-notif-toolbar__row">
        <div class="wh-notif-tabs" id="whNotifTabs" role="tablist">
            <button type="button" class="wh-notif-tab is-active" data-tab="all" role="tab" aria-selected="true"><?php echo __t('wh_notif_tab_all', 'warehouse'); ?></button>
            <button type="button" class="wh-notif-tab" data-tab="unread" role="tab"><?php echo __t('wh_notif_tab_unread', 'warehouse'); ?> <span class="wh-notif-tab-badge" id="whNotifUnreadBadge" hidden>0</span></button>
            <button type="button" class="wh-notif-tab" data-tab="pinned" role="tab"><?php echo __t('wh_notif_tab_pinned', 'warehouse'); ?></button>
            <button type="button" class="wh-notif-tab" data-tab="archived" role="tab"><?php echo __t('wh_notif_tab_archived', 'warehouse'); ?></button>
        </div>
        <div class="wh-notif-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whNotifMarkAllBtn">
                <span class="material-icons-round">done_all</span>
                <span class="wh-btn-label"><?php echo __t('wh_notif_mark_all', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whNotifRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
    <div class="wh-notif-toolbar__filters">
        <label class="wh-notif-search-wrap">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="whNotifSearch" class="wh-notif-search" placeholder="<?php echo htmlspecialchars(__t('wh_notif_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
        </label>
        <select id="whNotifModule" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_notif_module_wh', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <option value=""><?php echo __t('wh_notif_module_all', 'warehouse'); ?></option>
            <option value="warehouse"><?php echo __t('wh_notif_module_wh', 'warehouse'); ?></option>
            <option value="inventory"><?php echo __t('wh_notif_module_inventory', 'warehouse'); ?></option>
        </select>
        <select id="whNotifCategory" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_notif_filter_category', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <option value=""><?php echo __t('wh_notif_filter_all', 'warehouse'); ?></option>
        </select>
        <select id="whNotifPriority" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_notif_filter_priority', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <option value=""><?php echo __t('wh_notif_filter_all', 'warehouse'); ?></option>
            <option value="low"><?php echo __t('wh_notif_priority_low', 'warehouse'); ?></option>
            <option value="normal"><?php echo __t('wh_notif_priority_normal', 'warehouse'); ?></option>
            <option value="high"><?php echo __t('wh_notif_priority_high', 'warehouse'); ?></option>
            <option value="critical"><?php echo __t('wh_notif_priority_critical', 'warehouse'); ?></option>
        </select>
    </div>
</div>

<section class="wh-notif-panel" aria-live="polite">
    <ul class="wh-notif-page-list" id="whNotifList"></ul>
    <div class="wh-notif-empty" id="whNotifEmpty" hidden>
        <span class="material-icons-round">notifications_none</span>
        <p id="whNotifEmptyText"><?php echo __t('wh_notif_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whNotifLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-notif-pagination" id="whNotifPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whNotifPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-notif-pagination__meta" id="whNotifPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whNotifNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

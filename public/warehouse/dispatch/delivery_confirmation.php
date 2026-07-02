<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$useWmsModules = true;
$activeWhPage = 'delivery_confirmation';
$pageTitle = __t('wh_nav_delivery', 'warehouse');
$whCanConfirm = $whCanDispatch && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-delivery-confirmation.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_del_subtitle', 'wh_del_hint', 'wh_del_stat_pending', 'wh_del_stat_today', 'wh_del_stat_delivered',
        'wh_del_search', 'wh_del_empty', 'wh_del_hero_meta', 'wh_del_status_breakdown',
        'wh_del_link_shipping', 'wh_del_link_dispatch', 'wh_del_link_history', 'wh_del_filter_pending',
        'wh_del_filter_active', 'wh_del_filter_done', 'wh_del_toast_confirmed', 'wh_del_confirmed_badge',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_dispatched', 'wms_status_in_transit', 'wms_status_delivered',
        'wms_col_dispatch', 'wms_col_destination', 'wms_col_items', 'wms_col_driver', 'wms_col_vehicle',
        'wms_col_delivery_date', 'wms_nav_warehouses', 'wms_col_product', 'wms_col_qty', 'wms_col_sku',
        'wms_col_received_by', 'wms_view_details', 'wms_dispatch_details', 'wms_receipt_notes',
        'wms_dest_store', 'wms_dest_warehouse', 'wms_mark_delivered', 'wms_confirm_delivery',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-del-hero" aria-labelledby="whDelHeroTitle">
    <div class="wh-del-hero__intro">
        <h2 class="wh-del-hero__title" id="whDelHeroTitle"><?php echo __t('wh_del_subtitle', 'warehouse'); ?></h2>
        <p class="wh-del-hero__meta" id="whDelHeroMeta" aria-live="polite">—</p>
        <p class="wh-del-hero__hint"><?php echo __t('wh_del_hint', 'warehouse'); ?></p>
        <div class="wh-del-hero__links">
            <a class="wh-del-hero__link" href="shipping.php"><?php echo __t('wh_del_link_shipping', 'warehouse'); ?></a>
            <a class="wh-del-hero__link" href="dispatch_orders.php"><?php echo __t('wh_del_link_dispatch', 'warehouse'); ?></a>
            <a class="wh-del-hero__link" href="dispatch_history.php"><?php echo __t('wh_del_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-del-hero__stats" role="group">
        <article class="wh-del-stat wh-del-stat--warn">
            <span class="wh-del-stat__label"><?php echo __t('wh_del_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-del-stat__value is-loading" id="whDelStatPending">—</strong>
        </article>
        <article class="wh-del-stat wh-del-stat--success">
            <span class="wh-del-stat__label"><?php echo __t('wh_del_stat_today', 'warehouse'); ?></span>
            <strong class="wh-del-stat__value is-loading" id="whDelStatToday">—</strong>
        </article>
        <article class="wh-del-stat wh-del-stat--primary">
            <span class="wh-del-stat__label"><?php echo __t('wh_del_stat_delivered', 'warehouse'); ?></span>
            <strong class="wh-del-stat__value is-loading" id="whDelStatDelivered">—</strong>
        </article>
    </div>
</section>

<section class="wh-del-breakdown" id="whDelBreakdownPanel" hidden aria-labelledby="whDelBreakdownTitle">
    <div class="wh-del-breakdown__head">
        <h3 id="whDelBreakdownTitle"><?php echo __t('wh_del_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-del-status-chips" id="whDelStatusChips"></div>
</section>

<div class="wh-del-toolbar">
    <div class="wh-del-toolbar__row">
        <div class="wh-del-toolbar__filters">
            <label class="wh-del-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whDelSearch" class="wh-del-search" placeholder="<?php echo htmlspecialchars(__t('wh_del_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whDelWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whDelStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="delivery_pending"><?php echo __t('wh_del_filter_pending', 'warehouse'); ?></option>
                <option value="delivery_active"><?php echo __t('wh_del_filter_active', 'warehouse'); ?></option>
                <option value="delivery_done"><?php echo __t('wh_del_filter_done', 'warehouse'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-del-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whDelExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDelRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-del-panel" aria-live="polite">
    <div class="wh-del-table-wrap" id="whDelTableWrap"></div>
    <div class="wh-del-empty" id="whDelEmpty" hidden>
        <span class="material-icons-round">done_all</span>
        <p><?php echo __t('wh_del_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whDelLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-del-pagination" id="whDelPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDelPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-del-pagination__meta" id="whDelPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDelNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-del-toast" id="whDelToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whDelDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--del" role="dialog" aria-labelledby="whDelDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">done_all</span></div>
                <div>
                    <h3 id="whDelDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whDelDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whDelDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whDelDetailBody" class="wh-del-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

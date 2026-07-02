<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$useWmsModules = true;
$activeWhPage = 'shipping';
$pageTitle = __t('wh_nav_shipping', 'warehouse');
$whCanShip = $whCanDispatch && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-shipping.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_ship_subtitle', 'wh_ship_hint', 'wh_ship_stat_ready', 'wh_ship_stat_transit', 'wh_ship_stat_total',
        'wh_ship_search', 'wh_ship_empty', 'wh_ship_hero_meta', 'wh_ship_status_breakdown',
        'wh_ship_link_packing', 'wh_ship_link_dispatch', 'wh_ship_link_delivery', 'wh_ship_filter_active',
        'wh_ship_filter_ready', 'wh_ship_filter_transit', 'wh_ship_toast_dispatched', 'wh_ship_in_transit_badge',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_packed', 'wms_status_dispatched', 'wms_status_in_transit',
        'wms_col_dispatch', 'wms_col_destination', 'wms_col_items', 'wms_col_value', 'wms_col_driver',
        'wms_col_vehicle', 'wms_col_delivery_date', 'wms_nav_warehouses', 'wms_col_product', 'wms_col_qty',
        'wms_col_sku', 'wms_view_details', 'wms_dispatch_btn', 'wms_confirm_dispatch', 'wms_dispatch_details',
        'wms_receipt_notes', 'wms_dest_store', 'wms_dest_warehouse',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-ship-hero" aria-labelledby="whShipHeroTitle">
    <div class="wh-ship-hero__intro">
        <h2 class="wh-ship-hero__title" id="whShipHeroTitle"><?php echo __t('wh_ship_subtitle', 'warehouse'); ?></h2>
        <p class="wh-ship-hero__meta" id="whShipHeroMeta" aria-live="polite">—</p>
        <p class="wh-ship-hero__hint"><?php echo __t('wh_ship_hint', 'warehouse'); ?></p>
        <div class="wh-ship-hero__links">
            <a class="wh-ship-hero__link" href="packing.php"><?php echo __t('wh_ship_link_packing', 'warehouse'); ?></a>
            <a class="wh-ship-hero__link" href="dispatch_orders.php"><?php echo __t('wh_ship_link_dispatch', 'warehouse'); ?></a>
            <a class="wh-ship-hero__link" href="delivery_confirmation.php"><?php echo __t('wh_ship_link_delivery', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-ship-hero__stats" role="group">
        <article class="wh-ship-stat wh-ship-stat--warn">
            <span class="wh-ship-stat__label"><?php echo __t('wh_ship_stat_ready', 'warehouse'); ?></span>
            <strong class="wh-ship-stat__value is-loading" id="whShipStatReady">—</strong>
        </article>
        <article class="wh-ship-stat wh-ship-stat--info">
            <span class="wh-ship-stat__label"><?php echo __t('wh_ship_stat_transit', 'warehouse'); ?></span>
            <strong class="wh-ship-stat__value is-loading" id="whShipStatTransit">—</strong>
        </article>
        <article class="wh-ship-stat wh-ship-stat--primary">
            <span class="wh-ship-stat__label"><?php echo __t('wh_ship_stat_total', 'warehouse'); ?></span>
            <strong class="wh-ship-stat__value is-loading" id="whShipStatTotal">—</strong>
        </article>
    </div>
</section>

<section class="wh-ship-breakdown" id="whShipBreakdownPanel" hidden aria-labelledby="whShipBreakdownTitle">
    <div class="wh-ship-breakdown__head">
        <h3 id="whShipBreakdownTitle"><?php echo __t('wh_ship_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-ship-status-chips" id="whShipStatusChips"></div>
</section>

<div class="wh-ship-toolbar">
    <div class="wh-ship-toolbar__row">
        <div class="wh-ship-toolbar__filters">
            <label class="wh-ship-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whShipSearch" class="wh-ship-search" placeholder="<?php echo htmlspecialchars(__t('wh_ship_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whShipWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whShipStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="shipping_active"><?php echo __t('wh_ship_filter_active', 'warehouse'); ?></option>
                <option value="shipping_ready"><?php echo __t('wh_ship_filter_ready', 'warehouse'); ?></option>
                <option value="in_flight"><?php echo __t('wh_ship_filter_transit', 'warehouse'); ?></option>
                <option value="packed"><?php echo __t('wms_status_packed', 'wms'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-ship-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whShipExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whShipRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-ship-panel" aria-live="polite">
    <div class="wh-ship-table-wrap" id="whShipTableWrap"></div>
    <div class="wh-ship-empty" id="whShipEmpty" hidden>
        <span class="material-icons-round">flight_takeoff</span>
        <p><?php echo __t('wh_ship_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whShipLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-ship-pagination" id="whShipPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whShipPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-ship-pagination__meta" id="whShipPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whShipNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-ship-toast" id="whShipToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whShipDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--ship" role="dialog" aria-labelledby="whShipDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">local_shipping</span></div>
                <div>
                    <h3 id="whShipDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whShipDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whShipDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whShipDetailBody" class="wh-ship-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

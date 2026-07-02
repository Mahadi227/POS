<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$useWmsModules = true;
$activeWhPage = 'packing';
$pageTitle = __t('wh_nav_packing', 'warehouse');
$whCanPack = $whCanDispatch && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-packing.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_pack_subtitle', 'wh_pack_hint', 'wh_pack_stat_queue', 'wh_pack_stat_packed', 'wh_pack_stat_total',
        'wh_pack_search', 'wh_pack_empty', 'wh_pack_hero_meta', 'wh_pack_status_breakdown',
        'wh_pack_link_pick', 'wh_pack_link_dispatch', 'wh_pack_link_shipping', 'wh_pack_filter_queue',
        'wh_pack_filter_ready', 'wh_pack_filter_active', 'wh_pack_toast_packed', 'wh_pack_ready_badge',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_picking', 'wms_status_packed', 'wms_col_dispatch',
        'wms_col_destination', 'wms_col_items', 'wms_col_driver', 'wms_col_vehicle', 'wms_col_delivery_date',
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_view_details',
        'wms_mark_packed', 'wms_confirm_pack', 'wms_dispatch_details', 'wms_receipt_notes',
        'wms_dest_store', 'wms_dest_warehouse',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-pack-hero" aria-labelledby="whPackHeroTitle">
    <div class="wh-pack-hero__intro">
        <h2 class="wh-pack-hero__title" id="whPackHeroTitle"><?php echo __t('wh_pack_subtitle', 'warehouse'); ?></h2>
        <p class="wh-pack-hero__meta" id="whPackHeroMeta" aria-live="polite">—</p>
        <p class="wh-pack-hero__hint"><?php echo __t('wh_pack_hint', 'warehouse'); ?></p>
        <div class="wh-pack-hero__links">
            <a class="wh-pack-hero__link" href="pick_list.php"><?php echo __t('wh_pack_link_pick', 'warehouse'); ?></a>
            <a class="wh-pack-hero__link" href="dispatch_orders.php"><?php echo __t('wh_pack_link_dispatch', 'warehouse'); ?></a>
            <a class="wh-pack-hero__link" href="shipping.php"><?php echo __t('wh_pack_link_shipping', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-pack-hero__stats" role="group">
        <article class="wh-pack-stat wh-pack-stat--warn">
            <span class="wh-pack-stat__label"><?php echo __t('wh_pack_stat_queue', 'warehouse'); ?></span>
            <strong class="wh-pack-stat__value is-loading" id="whPackStatQueue">—</strong>
        </article>
        <article class="wh-pack-stat wh-pack-stat--success">
            <span class="wh-pack-stat__label"><?php echo __t('wh_pack_stat_packed', 'warehouse'); ?></span>
            <strong class="wh-pack-stat__value is-loading" id="whPackStatPacked">—</strong>
        </article>
        <article class="wh-pack-stat wh-pack-stat--primary">
            <span class="wh-pack-stat__label"><?php echo __t('wh_pack_stat_total', 'warehouse'); ?></span>
            <strong class="wh-pack-stat__value is-loading" id="whPackStatTotal">—</strong>
        </article>
    </div>
</section>

<section class="wh-pack-breakdown" id="whPackBreakdownPanel" hidden aria-labelledby="whPackBreakdownTitle">
    <div class="wh-pack-breakdown__head">
        <h3 id="whPackBreakdownTitle"><?php echo __t('wh_pack_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-pack-status-chips" id="whPackStatusChips"></div>
</section>

<div class="wh-pack-toolbar">
    <div class="wh-pack-toolbar__row">
        <div class="wh-pack-toolbar__filters">
            <label class="wh-pack-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whPackSearch" class="wh-pack-search" placeholder="<?php echo htmlspecialchars(__t('wh_pack_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whPackWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whPackStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="packing_active"><?php echo __t('wh_pack_filter_active', 'warehouse'); ?></option>
                <option value="packing_queue"><?php echo __t('wh_pack_filter_queue', 'warehouse'); ?></option>
                <option value="packing_ready"><?php echo __t('wh_pack_filter_ready', 'warehouse'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="packed"><?php echo __t('wms_status_packed', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-pack-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whPackExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whPackRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-pack-panel" aria-live="polite">
    <div class="wh-pack-table-wrap" id="whPackTableWrap"></div>
    <div class="wh-pack-empty" id="whPackEmpty" hidden>
        <span class="material-icons-round">inventory_2</span>
        <p><?php echo __t('wh_pack_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whPackLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-pack-pagination" id="whPackPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPackPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-pack-pagination__meta" id="whPackPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPackNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-pack-toast" id="whPackToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whPackDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--pack" role="dialog" aria-labelledby="whPackDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></div>
                <div>
                    <h3 id="whPackDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whPackDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whPackDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whPackDetailBody" class="wh-pack-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

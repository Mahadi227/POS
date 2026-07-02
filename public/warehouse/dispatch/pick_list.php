<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$useWmsModules = true;
$activeWhPage = 'pick_list';
$pageTitle = __t('wh_nav_pick_list', 'warehouse');
$whCanPick = $whCanDispatch && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-pick-list.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_pick_subtitle', 'wh_pick_hint', 'wh_pick_stat_queue', 'wh_pick_stat_progress', 'wh_pick_stat_total',
        'wh_pick_search', 'wh_pick_empty', 'wh_pick_hero_meta', 'wh_pick_status_breakdown',
        'wh_pick_link_dispatch', 'wh_pick_link_packing', 'wh_pick_link_shipping', 'wh_pick_filter_queue',
        'wh_pick_filter_progress', 'wh_pick_filter_active', 'wh_pick_toast_started', 'wh_pick_progress_badge',
        'wh_pick_go_packing', 'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_draft', 'wms_status_picking', 'wms_col_dispatch',
        'wms_col_destination', 'wms_col_items', 'wms_col_driver', 'wms_col_vehicle', 'wms_col_delivery_date',
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_view_details',
        'wms_start_picking', 'wms_confirm_pick', 'wms_dispatch_details', 'wms_receipt_notes',
        'wms_dest_store', 'wms_dest_warehouse',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-pick-hero" aria-labelledby="whPickHeroTitle">
    <div class="wh-pick-hero__intro">
        <h2 class="wh-pick-hero__title" id="whPickHeroTitle"><?php echo __t('wh_pick_subtitle', 'warehouse'); ?></h2>
        <p class="wh-pick-hero__meta" id="whPickHeroMeta" aria-live="polite">—</p>
        <p class="wh-pick-hero__hint"><?php echo __t('wh_pick_hint', 'warehouse'); ?></p>
        <div class="wh-pick-hero__links">
            <a class="wh-pick-hero__link" href="dispatch_orders.php"><?php echo __t('wh_pick_link_dispatch', 'warehouse'); ?></a>
            <a class="wh-pick-hero__link" href="packing.php"><?php echo __t('wh_pick_link_packing', 'warehouse'); ?></a>
            <a class="wh-pick-hero__link" href="shipping.php"><?php echo __t('wh_pick_link_shipping', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-pick-hero__stats" role="group">
        <article class="wh-pick-stat wh-pick-stat--warn">
            <span class="wh-pick-stat__label"><?php echo __t('wh_pick_stat_queue', 'warehouse'); ?></span>
            <strong class="wh-pick-stat__value is-loading" id="whPickStatQueue">—</strong>
        </article>
        <article class="wh-pick-stat wh-pick-stat--primary">
            <span class="wh-pick-stat__label"><?php echo __t('wh_pick_stat_progress', 'warehouse'); ?></span>
            <strong class="wh-pick-stat__value is-loading" id="whPickStatProgress">—</strong>
        </article>
        <article class="wh-pick-stat">
            <span class="wh-pick-stat__label"><?php echo __t('wh_pick_stat_total', 'warehouse'); ?></span>
            <strong class="wh-pick-stat__value is-loading" id="whPickStatTotal">—</strong>
        </article>
    </div>
</section>

<section class="wh-pick-breakdown" id="whPickBreakdownPanel" hidden aria-labelledby="whPickBreakdownTitle">
    <div class="wh-pick-breakdown__head">
        <h3 id="whPickBreakdownTitle"><?php echo __t('wh_pick_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-pick-status-chips" id="whPickStatusChips"></div>
</section>

<div class="wh-pick-toolbar">
    <div class="wh-pick-toolbar__row">
        <div class="wh-pick-toolbar__filters">
            <label class="wh-pick-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whPickSearch" class="wh-pick-search" placeholder="<?php echo htmlspecialchars(__t('wh_pick_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whPickWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whPickStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="picking_active"><?php echo __t('wh_pick_filter_active', 'warehouse'); ?></option>
                <option value="picking_queue"><?php echo __t('wh_pick_filter_queue', 'warehouse'); ?></option>
                <option value="picking_progress"><?php echo __t('wh_pick_filter_progress', 'warehouse'); ?></option>
                <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-pick-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whPickExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whPickRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-pick-panel" aria-live="polite">
    <div class="wh-pick-table-wrap" id="whPickTableWrap"></div>
    <div class="wh-pick-empty" id="whPickEmpty" hidden>
        <span class="material-icons-round">checklist</span>
        <p><?php echo __t('wh_pick_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whPickLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-pick-pagination" id="whPickPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPickPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-pick-pagination__meta" id="whPickPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whPickNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-pick-toast" id="whPickToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whPickDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--pick" role="dialog" aria-labelledby="whPickDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">checklist</span></div>
                <div>
                    <h3 id="whPickDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whPickDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whPickDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whPickDetailBody" class="wh-pick-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('dispatch');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'dispatch_history';
$pageTitle = __t('wh_nav_dispatch_history', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-dispatch-history.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_dsph_subtitle', 'wh_dsph_hint', 'wh_dsph_stat_total', 'wh_dsph_stat_delivered', 'wh_dsph_stat_cancelled',
        'wh_dsph_stat_items', 'wh_dsph_stat_value', 'wh_dsph_search', 'wh_dsph_empty', 'wh_dsph_hero_meta',
        'wh_dsph_status_breakdown', 'wh_dsph_delivered_at', 'wh_dsph_link_orders', 'wh_dsph_link_shipping',
        'wh_dsph_link_delivery', 'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_delivered', 'wms_status_cancelled', 'wms_status_dispatched',
        'wms_status_in_transit', 'wms_col_dispatch', 'wms_col_destination', 'wms_col_items', 'wms_col_value',
        'wms_col_driver', 'wms_col_vehicle', 'wms_col_delivery_date', 'wms_nav_warehouses', 'wms_col_product',
        'wms_col_qty', 'wms_col_sku', 'wms_col_received_by', 'wms_view_details', 'wms_dispatch_details',
        'wms_receipt_notes', 'wms_dest_store', 'wms_dest_warehouse', 'wms_date_from', 'wms_date_to',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-dsph-hero" aria-labelledby="whDsphHeroTitle">
    <div class="wh-dsph-hero__intro">
        <h2 class="wh-dsph-hero__title" id="whDsphHeroTitle"><?php echo __t('wh_dsph_subtitle', 'warehouse'); ?></h2>
        <p class="wh-dsph-hero__meta" id="whDsphHeroMeta" aria-live="polite">—</p>
        <p class="wh-dsph-hero__hint"><?php echo __t('wh_dsph_hint', 'warehouse'); ?></p>
        <div class="wh-dsph-hero__links">
            <a class="wh-dsph-hero__link" href="dispatch_orders.php"><?php echo __t('wh_dsph_link_orders', 'warehouse'); ?></a>
            <a class="wh-dsph-hero__link" href="shipping.php"><?php echo __t('wh_dsph_link_shipping', 'warehouse'); ?></a>
            <a class="wh-dsph-hero__link" href="delivery_confirmation.php"><?php echo __t('wh_dsph_link_delivery', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-dsph-hero__stats" role="group">
        <article class="wh-dsph-stat wh-dsph-stat--primary">
            <span class="wh-dsph-stat__label"><?php echo __t('wh_dsph_stat_total', 'warehouse'); ?></span>
            <strong class="wh-dsph-stat__value is-loading" id="whDsphStatTotal">—</strong>
        </article>
        <article class="wh-dsph-stat wh-dsph-stat--success">
            <span class="wh-dsph-stat__label"><?php echo __t('wh_dsph_stat_delivered', 'warehouse'); ?></span>
            <strong class="wh-dsph-stat__value is-loading" id="whDsphStatDelivered">—</strong>
        </article>
        <article class="wh-dsph-stat wh-dsph-stat--danger">
            <span class="wh-dsph-stat__label"><?php echo __t('wh_dsph_stat_cancelled', 'warehouse'); ?></span>
            <strong class="wh-dsph-stat__value is-loading" id="whDsphStatCancelled">—</strong>
        </article>
        <article class="wh-dsph-stat">
            <span class="wh-dsph-stat__label"><?php echo __t('wh_dsph_stat_items', 'warehouse'); ?></span>
            <strong class="wh-dsph-stat__value is-loading" id="whDsphStatItems">—</strong>
        </article>
        <article class="wh-dsph-stat">
            <span class="wh-dsph-stat__label"><?php echo __t('wh_dsph_stat_value', 'warehouse'); ?></span>
            <strong class="wh-dsph-stat__value is-loading" id="whDsphStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-dsph-breakdown" id="whDsphBreakdownPanel" hidden aria-labelledby="whDsphBreakdownTitle">
    <div class="wh-dsph-breakdown__head">
        <h3 id="whDsphBreakdownTitle"><?php echo __t('wh_dsph_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-dsph-status-chips" id="whDsphStatusChips"></div>
</section>

<div class="wh-dsph-toolbar">
    <div class="wh-dsph-toolbar__row">
        <div class="wh-dsph-toolbar__filters">
            <label class="wh-dsph-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whDsphSearch" class="wh-dsph-search" placeholder="<?php echo htmlspecialchars(__t('wh_dsph_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whDsphWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whDsphStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
                <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
            <label class="wh-dsph-date-wrap">
                <span><?php echo __t('wms_date_from', 'wms'); ?></span>
                <input type="date" id="whDsphDateFrom" class="wh-input wh-dsph-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-dsph-date-wrap">
                <span><?php echo __t('wms_date_to', 'wms'); ?></span>
                <input type="date" id="whDsphDateTo" class="wh-input wh-dsph-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-dsph-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whDsphExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDsphRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-dsph-panel" aria-live="polite">
    <div class="wh-dsph-table-wrap" id="whDsphTableWrap"></div>
    <div class="wh-dsph-empty" id="whDsphEmpty" hidden>
        <span class="material-icons-round">history</span>
        <p><?php echo __t('wh_dsph_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whDsphLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-dsph-pagination" id="whDsphPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDsphPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-dsph-pagination__meta" id="whDsphPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDsphNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>

<div class="wms-modal-overlay" id="whDsphDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--hist" role="dialog" aria-labelledby="whDsphDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">history</span></div>
                <div>
                    <h3 id="whDsphDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whDsphDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whDsphDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whDsphDetailBody" class="wh-dsph-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

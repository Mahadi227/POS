<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$useWmsModules = true;
$activeWhPage = 'supplier_deliveries';
$pageTitle = __t('wh_nav_deliveries', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-supplier-deliveries.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_nav_deliveries', 'wh_sdel_subtitle', 'wh_sdel_stat_total', 'wh_sdel_stat_pending',
        'wh_sdel_stat_inspecting', 'wh_sdel_stat_accepted', 'wh_sdel_stat_value', 'wh_sdel_search',
        'wh_sdel_empty', 'wh_sdel_hero_meta', 'wh_sdel_status_breakdown', 'wh_sdel_complete_hint',
        'wh_sdel_link_grn', 'wh_sdel_link_history', 'wh_sdel_toast_completed',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error',
        'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_pending', 'wms_status_inspecting', 'wms_status_accepted',
        'wms_col_grn', 'wms_col_supplier', 'wms_col_value', 'wms_col_items', 'wms_col_received_by',
        'wms_receipt_notes', 'wms_receipt_details', 'wms_view_details', 'wms_complete', 'wms_reject',
        'wms_start_inspection', 'wms_accept_delivery', 'wms_confirm_inspect', 'wms_confirm_accept',
        'wms_confirm_reject_receipt', 'wms_confirm_complete', 'wms_nav_warehouses', 'wms_col_product',
        'wms_col_sku', 'wms_qty_received', 'wms_unit_cost', 'wms_line_subtotal',
        'wms_toast_inspecting', 'wms_toast_accepted', 'wms_toast_rejected',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-sdel-hero" aria-labelledby="whSdelHeroTitle">
    <div class="wh-sdel-hero__intro">
        <h2 class="wh-sdel-hero__title" id="whSdelHeroTitle"><?php echo __t('wh_sdel_subtitle', 'warehouse'); ?></h2>
        <p class="wh-sdel-hero__meta" id="whSdelHeroMeta" aria-live="polite">—</p>
        <p class="wh-sdel-hero__hint"><?php echo __t('wh_sdel_complete_hint', 'warehouse'); ?></p>
        <div class="wh-sdel-hero__links">
            <a class="wh-sdel-hero__link" href="goods_receipts.php"><?php echo __t('wh_sdel_link_grn', 'warehouse'); ?></a>
            <a class="wh-sdel-hero__link" href="receiving_history.php"><?php echo __t('wh_sdel_link_history', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-sdel-hero__stats" role="group">
        <article class="wh-sdel-stat wh-sdel-stat--primary">
            <span class="wh-sdel-stat__label"><?php echo __t('wh_sdel_stat_total', 'warehouse'); ?></span>
            <strong class="wh-sdel-stat__value is-loading" id="whSdelStatTotal">—</strong>
        </article>
        <article class="wh-sdel-stat wh-sdel-stat--warn">
            <span class="wh-sdel-stat__label"><?php echo __t('wh_sdel_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-sdel-stat__value is-loading" id="whSdelStatPending">—</strong>
        </article>
        <article class="wh-sdel-stat">
            <span class="wh-sdel-stat__label"><?php echo __t('wh_sdel_stat_inspecting', 'warehouse'); ?></span>
            <strong class="wh-sdel-stat__value is-loading" id="whSdelStatInspecting">—</strong>
        </article>
        <article class="wh-sdel-stat wh-sdel-stat--success">
            <span class="wh-sdel-stat__label"><?php echo __t('wh_sdel_stat_accepted', 'warehouse'); ?></span>
            <strong class="wh-sdel-stat__value is-loading" id="whSdelStatAccepted">—</strong>
        </article>
        <article class="wh-sdel-stat">
            <span class="wh-sdel-stat__label"><?php echo __t('wh_sdel_stat_value', 'warehouse'); ?></span>
            <strong class="wh-sdel-stat__value is-loading" id="whSdelStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-sdel-breakdown" id="whSdelBreakdownPanel" hidden aria-labelledby="whSdelBreakdownTitle">
    <div class="wh-sdel-breakdown__head">
        <h3 id="whSdelBreakdownTitle"><?php echo __t('wh_sdel_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-sdel-status-chips" id="whSdelStatusChips"></div>
</section>

<div class="wh-sdel-toolbar">
    <div class="wh-sdel-toolbar__row">
        <div class="wh-sdel-toolbar__filters">
            <label class="wh-sdel-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whSdelSearch" class="wh-sdel-search" placeholder="<?php echo htmlspecialchars(__t('wh_sdel_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whSdelWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whSdelStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
                <option value="inspecting"><?php echo __t('wms_status_inspecting', 'wms'); ?></option>
                <option value="accepted"><?php echo __t('wms_status_accepted', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-sdel-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whSdelExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whSdelRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-sdel-panel" aria-live="polite">
    <div class="wh-sdel-table-wrap" id="whSdelTableWrap"></div>
    <div class="wh-sdel-empty" id="whSdelEmpty" hidden>
        <span class="material-icons-round">local_shipping</span>
        <p><?php echo __t('wh_sdel_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whSdelLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-sdel-pagination" id="whSdelPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSdelPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-sdel-pagination__meta" id="whSdelPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSdelNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-sdel-toast" id="whSdelToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whSdelDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whSdelDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">local_shipping</span></div>
                <div>
                    <h3 id="whSdelDetailTitle"><?php echo __t('wms_receipt_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wh_sdel_subtitle', 'warehouse'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whSdelDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whSdelDetailBody" class="wms-detail-body wh-sdel-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

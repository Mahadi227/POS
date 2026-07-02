<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$useWmsModules = true;
$activeWhPage = 'quality_inspection';
$pageTitle = __t('wh_nav_inspection', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-quality-inspection.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_nav_inspection', 'wh_insp_subtitle', 'wh_insp_hint', 'wh_insp_stat_queue', 'wh_insp_stat_pending',
        'wh_insp_stat_inspecting', 'wh_insp_stat_passed', 'wh_insp_stat_rejected', 'wh_insp_search',
        'wh_insp_empty', 'wh_insp_hero_meta', 'wh_insp_status_breakdown', 'wh_insp_link_deliveries',
        'wh_insp_link_receive', 'wh_insp_link_grn', 'wh_all_warehouses', 'wh_migration_hint', 'loading',
        'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'save', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_pending', 'wms_status_inspecting', 'wms_col_grn',
        'wms_col_supplier', 'wms_col_items', 'wms_col_received_by', 'wms_receipt_notes', 'wms_view_details',
        'wms_start_inspection', 'wms_continue_inspection', 'wms_pass_inspection', 'wms_fail_inspection',
        'wms_confirm_inspect', 'wms_confirm_pass_inspection', 'wms_confirm_fail_inspection',
        'wms_insp_details', 'wms_insp_col_result', 'wms_insp_status_pending', 'wms_insp_status_passed',
        'wms_insp_status_failed', 'wms_insp_status_partial', 'wms_col_product', 'wms_qty_received',
        'wms_qty_damaged', 'wms_qty_ok', 'wms_nav_warehouses', 'wms_toast_inspecting', 'wms_toast_accepted',
        'wms_toast_rejected', 'wms_toast_inspection_saved',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-insp-hero" aria-labelledby="whInspHeroTitle">
    <div class="wh-insp-hero__intro">
        <h2 class="wh-insp-hero__title" id="whInspHeroTitle"><?php echo __t('wh_insp_subtitle', 'warehouse'); ?></h2>
        <p class="wh-insp-hero__meta" id="whInspHeroMeta" aria-live="polite">—</p>
        <p class="wh-insp-hero__hint"><?php echo __t('wh_insp_hint', 'warehouse'); ?></p>
        <div class="wh-insp-hero__links">
            <a class="wh-insp-hero__link" href="supplier_deliveries.php"><?php echo __t('wh_insp_link_deliveries', 'warehouse'); ?></a>
            <a class="wh-insp-hero__link" href="receive_stock.php"><?php echo __t('wh_insp_link_receive', 'warehouse'); ?></a>
            <a class="wh-insp-hero__link" href="goods_receipts.php"><?php echo __t('wh_insp_link_grn', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-insp-hero__stats" role="group">
        <article class="wh-insp-stat wh-insp-stat--primary">
            <span class="wh-insp-stat__label"><?php echo __t('wh_insp_stat_queue', 'warehouse'); ?></span>
            <strong class="wh-insp-stat__value is-loading" id="whInspStatQueue">—</strong>
        </article>
        <article class="wh-insp-stat wh-insp-stat--warn">
            <span class="wh-insp-stat__label"><?php echo __t('wh_insp_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-insp-stat__value is-loading" id="whInspStatPending">—</strong>
        </article>
        <article class="wh-insp-stat">
            <span class="wh-insp-stat__label"><?php echo __t('wh_insp_stat_inspecting', 'warehouse'); ?></span>
            <strong class="wh-insp-stat__value is-loading" id="whInspStatInspecting">—</strong>
        </article>
        <article class="wh-insp-stat wh-insp-stat--success">
            <span class="wh-insp-stat__label"><?php echo __t('wh_insp_stat_passed', 'warehouse'); ?></span>
            <strong class="wh-insp-stat__value is-loading" id="whInspStatPassed">—</strong>
        </article>
        <article class="wh-insp-stat wh-insp-stat--danger">
            <span class="wh-insp-stat__label"><?php echo __t('wh_insp_stat_rejected', 'warehouse'); ?></span>
            <strong class="wh-insp-stat__value is-loading" id="whInspStatRejected">—</strong>
        </article>
    </div>
</section>

<section class="wh-insp-breakdown" id="whInspBreakdownPanel" hidden aria-labelledby="whInspBreakdownTitle">
    <div class="wh-insp-breakdown__head">
        <h3 id="whInspBreakdownTitle"><?php echo __t('wh_insp_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-insp-status-chips" id="whInspStatusChips"></div>
</section>

<div class="wh-insp-toolbar">
    <div class="wh-insp-toolbar__row">
        <div class="wh-insp-toolbar__filters">
            <label class="wh-insp-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whInspSearch" class="wh-insp-search" placeholder="<?php echo htmlspecialchars(__t('wh_insp_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whInspWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whInspStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
                <option value="inspecting"><?php echo __t('wms_status_inspecting', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-insp-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whInspExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whInspRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-insp-panel" aria-live="polite">
    <div class="wh-insp-table-wrap" id="whInspTableWrap"></div>
    <div class="wh-insp-empty" id="whInspEmpty" hidden>
        <span class="material-icons-round">verified</span>
        <p><?php echo __t('wh_insp_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whInspLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-insp-pagination" id="whInspPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whInspPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-insp-pagination__meta" id="whInspPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whInspNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-insp-toast" id="whInspToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whInspDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide" role="dialog" aria-labelledby="whInspDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">verified</span></div>
                <div>
                    <h3 id="whInspDetailTitle"><?php echo __t('wms_insp_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wh_insp_subtitle', 'warehouse'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whInspDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whInspDetailBody" class="wms-detail-body wh-insp-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

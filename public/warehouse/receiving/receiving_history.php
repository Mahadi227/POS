<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'receiving_history';
$pageTitle = __t('wh_nav_receiving_history', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-receiving-history.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_nav_receiving_history', 'wh_rhist_subtitle', 'wh_rhist_hint', 'wh_rhist_stat_total',
        'wh_rhist_stat_completed', 'wh_rhist_stat_rejected', 'wh_rhist_stat_items', 'wh_rhist_stat_value',
        'wh_rhist_search', 'wh_rhist_empty', 'wh_rhist_hero_meta', 'wh_rhist_status_breakdown',
        'wh_rhist_inspected_at', 'wh_rhist_link_grn', 'wh_rhist_link_receive', 'wh_rhist_link_inventory',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_completed', 'wms_status_rejected', 'wms_col_grn',
        'wms_col_supplier', 'wms_col_value', 'wms_col_items', 'wms_col_received_by', 'wms_receipt_notes',
        'wms_receipt_details', 'wms_view_details', 'wms_nav_warehouses', 'wms_col_product', 'wms_col_sku',
        'wms_qty_received', 'wms_qty_damaged', 'wms_qty_ok', 'wms_unit_cost', 'wms_insp_col_result',
        'wms_insp_status_pending', 'wms_insp_status_passed', 'wms_insp_status_failed', 'wms_insp_status_partial',
        'wms_date_from', 'wms_date_to',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-rhist-hero" aria-labelledby="whRhistHeroTitle">
    <div class="wh-rhist-hero__intro">
        <h2 class="wh-rhist-hero__title" id="whRhistHeroTitle"><?php echo __t('wh_rhist_subtitle', 'warehouse'); ?></h2>
        <p class="wh-rhist-hero__meta" id="whRhistHeroMeta" aria-live="polite">—</p>
        <p class="wh-rhist-hero__hint"><?php echo __t('wh_rhist_hint', 'warehouse'); ?></p>
        <div class="wh-rhist-hero__links">
            <a class="wh-rhist-hero__link" href="goods_receipts.php"><?php echo __t('wh_rhist_link_grn', 'warehouse'); ?></a>
            <a class="wh-rhist-hero__link" href="receive_stock.php"><?php echo __t('wh_rhist_link_receive', 'warehouse'); ?></a>
            <a class="wh-rhist-hero__link" href="../inventory/warehouse_inventory.php"><?php echo __t('wh_rhist_link_inventory', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-rhist-hero__stats" role="group">
        <article class="wh-rhist-stat wh-rhist-stat--primary">
            <span class="wh-rhist-stat__label"><?php echo __t('wh_rhist_stat_total', 'warehouse'); ?></span>
            <strong class="wh-rhist-stat__value is-loading" id="whRhistStatTotal">—</strong>
        </article>
        <article class="wh-rhist-stat wh-rhist-stat--success">
            <span class="wh-rhist-stat__label"><?php echo __t('wh_rhist_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-rhist-stat__value is-loading" id="whRhistStatCompleted">—</strong>
        </article>
        <article class="wh-rhist-stat wh-rhist-stat--danger">
            <span class="wh-rhist-stat__label"><?php echo __t('wh_rhist_stat_rejected', 'warehouse'); ?></span>
            <strong class="wh-rhist-stat__value is-loading" id="whRhistStatRejected">—</strong>
        </article>
        <article class="wh-rhist-stat">
            <span class="wh-rhist-stat__label"><?php echo __t('wh_rhist_stat_items', 'warehouse'); ?></span>
            <strong class="wh-rhist-stat__value is-loading" id="whRhistStatItems">—</strong>
        </article>
        <article class="wh-rhist-stat">
            <span class="wh-rhist-stat__label"><?php echo __t('wh_rhist_stat_value', 'warehouse'); ?></span>
            <strong class="wh-rhist-stat__value is-loading" id="whRhistStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-rhist-breakdown" id="whRhistBreakdownPanel" hidden aria-labelledby="whRhistBreakdownTitle">
    <div class="wh-rhist-breakdown__head">
        <h3 id="whRhistBreakdownTitle"><?php echo __t('wh_rhist_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-rhist-status-chips" id="whRhistStatusChips"></div>
</section>

<div class="wh-rhist-toolbar">
    <div class="wh-rhist-toolbar__row">
        <div class="wh-rhist-toolbar__filters">
            <label class="wh-rhist-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whRhistSearch" class="wh-rhist-search" placeholder="<?php echo htmlspecialchars(__t('wh_rhist_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whRhistWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whRhistStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            </select>
            <label class="wh-rhist-date-wrap">
                <span><?php echo __t('wms_date_from', 'wms'); ?></span>
                <input type="date" id="whRhistDateFrom" class="wh-input wh-rhist-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-rhist-date-wrap">
                <span><?php echo __t('wms_date_to', 'wms'); ?></span>
                <input type="date" id="whRhistDateTo" class="wh-input wh-rhist-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-rhist-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whRhistExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whRhistRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-rhist-panel" aria-live="polite">
    <div class="wh-rhist-table-wrap" id="whRhistTableWrap"></div>
    <div class="wh-rhist-empty" id="whRhistEmpty" hidden>
        <span class="material-icons-round">history</span>
        <p><?php echo __t('wh_rhist_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whRhistLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-rhist-pagination" id="whRhistPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whRhistPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-rhist-pagination__meta" id="whRhistPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whRhistNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>

<div class="wms-modal-overlay" id="whRhistDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide" role="dialog" aria-labelledby="whRhistDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">history</span></div>
                <div>
                    <h3 id="whRhistDetailTitle"><?php echo __t('wms_receipt_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wh_rhist_subtitle', 'warehouse'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whRhistDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whRhistDetailBody" class="wms-detail-body wh-rhist-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'transfer_history';
$pageTitle = __t('wh_nav_transfer_history', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-transfer-history.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trh_subtitle', 'wh_trh_hint', 'wh_trh_stat_total', 'wh_trh_stat_completed', 'wh_trh_stat_rejected',
        'wh_trh_stat_items', 'wh_trh_stat_value', 'wh_trh_search', 'wh_trh_empty', 'wh_trh_hero_meta',
        'wh_trh_status_breakdown', 'wh_trh_completed_at', 'wh_trh_link_wh_transfer', 'wh_trh_link_approve',
        'wh_trh_link_outgoing', 'wh_trh_filter_all_types', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page',
        'records', 'close', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_filter_all_status', 'wms_status_completed', 'wms_status_rejected', 'wms_status_cancelled',
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_col_reason', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details',
        'wms_transfer_details', 'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh', 'wms_type_branch',
        'wms_col_requested_by', 'wms_nav_warehouses', 'wms_date_from', 'wms_date_to',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-trh-hero" aria-labelledby="whTrhHeroTitle">
    <div class="wh-trh-hero__intro">
        <h2 class="wh-trh-hero__title" id="whTrhHeroTitle"><?php echo __t('wh_trh_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trh-hero__meta" id="whTrhHeroMeta" aria-live="polite">—</p>
        <p class="wh-trh-hero__hint"><?php echo __t('wh_trh_hint', 'warehouse'); ?></p>
        <div class="wh-trh-hero__links">
            <a class="wh-trh-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_trh_link_wh_transfer', 'warehouse'); ?></a>
            <a class="wh-trh-hero__link" href="approve_transfer.php"><?php echo __t('wh_trh_link_approve', 'warehouse'); ?></a>
            <a class="wh-trh-hero__link" href="outgoing_transfers.php"><?php echo __t('wh_trh_link_outgoing', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trh-hero__stats" role="group">
        <article class="wh-trh-stat wh-trh-stat--primary">
            <span class="wh-trh-stat__label"><?php echo __t('wh_trh_stat_total', 'warehouse'); ?></span>
            <strong class="wh-trh-stat__value is-loading" id="whTrhStatTotal">—</strong>
        </article>
        <article class="wh-trh-stat wh-trh-stat--success">
            <span class="wh-trh-stat__label"><?php echo __t('wh_trh_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-trh-stat__value is-loading" id="whTrhStatCompleted">—</strong>
        </article>
        <article class="wh-trh-stat wh-trh-stat--danger">
            <span class="wh-trh-stat__label"><?php echo __t('wh_trh_stat_rejected', 'warehouse'); ?></span>
            <strong class="wh-trh-stat__value is-loading" id="whTrhStatRejected">—</strong>
        </article>
        <article class="wh-trh-stat">
            <span class="wh-trh-stat__label"><?php echo __t('wh_trh_stat_items', 'warehouse'); ?></span>
            <strong class="wh-trh-stat__value is-loading" id="whTrhStatItems">—</strong>
        </article>
        <article class="wh-trh-stat">
            <span class="wh-trh-stat__label"><?php echo __t('wh_trh_stat_value', 'warehouse'); ?></span>
            <strong class="wh-trh-stat__value is-loading" id="whTrhStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-trh-breakdown" id="whTrhBreakdownPanel" hidden aria-labelledby="whTrhBreakdownTitle">
    <div class="wh-trh-breakdown__head">
        <h3 id="whTrhBreakdownTitle"><?php echo __t('wh_trh_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trh-status-chips" id="whTrhStatusChips"></div>
</section>

<div class="wh-trh-toolbar">
    <div class="wh-trh-toolbar__row">
        <div class="wh-trh-toolbar__filters">
            <label class="wh-trh-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTrhSearch" class="wh-trh-search" placeholder="<?php echo htmlspecialchars(__t('wh_trh_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTrhWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whTrhType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_trh_filter_all_types', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_trh_filter_all_types', 'warehouse'); ?></option>
                <option value="warehouse_to_warehouse"><?php echo __t('wms_type_wh_wh', 'wms'); ?></option>
                <option value="warehouse_to_store"><?php echo __t('wms_type_wh_store', 'wms'); ?></option>
                <option value="store_to_warehouse"><?php echo __t('wms_type_store_wh', 'wms'); ?></option>
                <option value="branch_to_branch"><?php echo __t('wms_type_branch', 'wms'); ?></option>
            </select>
            <select id="whTrhStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
                <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
                <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
            </select>
            <label class="wh-trh-date-wrap">
                <span><?php echo __t('wms_date_from', 'wms'); ?></span>
                <input type="date" id="whTrhDateFrom" class="wh-input wh-trh-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-trh-date-wrap">
                <span><?php echo __t('wms_date_to', 'wms'); ?></span>
                <input type="date" id="whTrhDateTo" class="wh-input wh-trh-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-trh-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrhExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTrhRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trh-panel" aria-live="polite">
    <div class="wh-trh-table-wrap" id="whTrhTableWrap"></div>
    <div class="wh-trh-empty" id="whTrhEmpty" hidden>
        <span class="material-icons-round">history</span>
        <p><?php echo __t('wh_trh_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTrhLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trh-pagination" id="whTrhPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrhPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trh-pagination__meta" id="whTrhPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrhNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>

<div class="wms-modal-overlay" id="whTrhDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--trh" role="dialog" aria-labelledby="whTrhDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">history</span></div>
                <div>
                    <h3 id="whTrhDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whTrhDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whTrhDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whTrhDetailBody" class="wh-trh-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

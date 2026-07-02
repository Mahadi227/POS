<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'incoming_transfers';
$pageTitle = __t('wh_nav_incoming', 'warehouse');
$whCanReceive = $whCanTransfer && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-incoming-transfers.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trin_subtitle', 'wh_trin_hint', 'wh_trin_stat_pending', 'wh_trin_stat_transit', 'wh_trin_stat_completed',
        'wh_trin_stat_active', 'wh_trin_search', 'wh_trin_empty', 'wh_trin_hero_meta', 'wh_trin_status_breakdown',
        'wh_trin_link_requests', 'wh_trin_link_outgoing', 'wh_trin_link_wh_transfer', 'wh_trin_filter_active',
        'wh_trin_filter_pending', 'wh_trin_receive', 'wh_trin_toast_received', 'wh_trin_ready_badge',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_col_reason', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details',
        'wms_transfer_details', 'wms_filter_all_status', 'wms_status_approved', 'wms_status_picking',
        'wms_status_in_transit', 'wms_status_received', 'wms_status_completed', 'wms_status_rejected',
        'wms_status_cancelled', 'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh', 'wms_type_branch',
        'wms_confirm_complete_trf', 'wms_col_requested_by',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-trin-hero" aria-labelledby="whTrinHeroTitle">
    <div class="wh-trin-hero__intro">
        <h2 class="wh-trin-hero__title" id="whTrinHeroTitle"><?php echo __t('wh_trin_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trin-hero__meta" id="whTrinHeroMeta" aria-live="polite">—</p>
        <p class="wh-trin-hero__hint"><?php echo __t('wh_trin_hint', 'warehouse'); ?></p>
        <div class="wh-trin-hero__links">
            <a class="wh-trin-hero__link" href="transfer_requests.php"><?php echo __t('wh_trin_link_requests', 'warehouse'); ?></a>
            <a class="wh-trin-hero__link" href="outgoing_transfers.php"><?php echo __t('wh_trin_link_outgoing', 'warehouse'); ?></a>
            <a class="wh-trin-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_trin_link_wh_transfer', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trin-hero__stats" role="group">
        <article class="wh-trin-stat wh-trin-stat--warn">
            <span class="wh-trin-stat__label"><?php echo __t('wh_trin_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-trin-stat__value is-loading" id="whTrinStatPending">—</strong>
        </article>
        <article class="wh-trin-stat wh-trin-stat--primary">
            <span class="wh-trin-stat__label"><?php echo __t('wh_trin_stat_transit', 'warehouse'); ?></span>
            <strong class="wh-trin-stat__value is-loading" id="whTrinStatTransit">—</strong>
        </article>
        <article class="wh-trin-stat wh-trin-stat--success">
            <span class="wh-trin-stat__label"><?php echo __t('wh_trin_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-trin-stat__value is-loading" id="whTrinStatCompleted">—</strong>
        </article>
        <article class="wh-trin-stat">
            <span class="wh-trin-stat__label"><?php echo __t('wh_trin_stat_active', 'warehouse'); ?></span>
            <strong class="wh-trin-stat__value is-loading" id="whTrinStatActive">—</strong>
        </article>
    </div>
</section>

<section class="wh-trin-breakdown" id="whTrinBreakdownPanel" hidden aria-labelledby="whTrinBreakdownTitle">
    <div class="wh-trin-breakdown__head">
        <h3 id="whTrinBreakdownTitle"><?php echo __t('wh_trin_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trin-status-chips" id="whTrinStatusChips"></div>
</section>

<div class="wh-trin-toolbar">
    <div class="wh-trin-toolbar__row">
        <div class="wh-trin-toolbar__filters">
            <label class="wh-trin-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTrinSearch" class="wh-trin-search" placeholder="<?php echo htmlspecialchars(__t('wh_trin_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTrinWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whTrinStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="incoming_active"><?php echo __t('wh_trin_filter_active', 'warehouse'); ?></option>
                <option value="incoming_pending"><?php echo __t('wh_trin_filter_pending', 'warehouse'); ?></option>
                <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="received"><?php echo __t('wms_status_received', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-trin-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whTrinExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTrinRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trin-panel" aria-live="polite">
    <div class="wh-trin-table-wrap" id="whTrinTableWrap"></div>
    <div class="wh-trin-empty" id="whTrinEmpty" hidden>
        <span class="material-icons-round">call_received</span>
        <p><?php echo __t('wh_trin_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTrinLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trin-pagination" id="whTrinPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrinPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trin-pagination__meta" id="whTrinPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTrinNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-trin-toast" id="whTrinToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whTrinDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--trin" role="dialog" aria-labelledby="whTrinDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">call_received</span></div>
                <div>
                    <h3 id="whTrinDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whTrinDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whTrinDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whTrinDetailBody" class="wh-trin-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

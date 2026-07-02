<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'outgoing_transfers';
$pageTitle = __t('wh_nav_outgoing', 'warehouse');
$whCanDispatch = $whCanTransfer && !$whReadOnly;
$whCanApprove = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-outgoing-transfers.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_trout_subtitle', 'wh_trout_hint', 'wh_trout_stat_requested', 'wh_trout_stat_progress', 'wh_trout_stat_completed',
        'wh_trout_stat_active', 'wh_trout_search', 'wh_trout_empty', 'wh_trout_hero_meta', 'wh_trout_status_breakdown',
        'wh_trout_link_requests', 'wh_trout_link_incoming', 'wh_trout_link_wh_transfer', 'wh_trout_filter_active',
        'wh_trout_filter_pending', 'wh_trout_dispatch', 'wh_trout_toast_approved', 'wh_trout_toast_dispatched',
        'wh_trout_toast_rejected', 'wh_trout_dispatched_badge', 'wh_all_warehouses', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page',
        'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_col_reason', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details',
        'wms_transfer_details', 'wms_filter_all_status', 'wms_status_requested', 'wms_status_approved',
        'wms_status_picking', 'wms_status_in_transit', 'wms_status_completed', 'wms_status_rejected',
        'wms_status_cancelled', 'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh', 'wms_type_branch',
        'wms_approve', 'wms_complete', 'wms_reject', 'wms_confirm_approve_trf', 'wms_confirm_complete_trf',
        'wms_confirm_reject_trf', 'wms_col_requested_by', 'wms_nav_warehouses',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-trout-hero" aria-labelledby="whTroutHeroTitle">
    <div class="wh-trout-hero__intro">
        <h2 class="wh-trout-hero__title" id="whTroutHeroTitle"><?php echo __t('wh_trout_subtitle', 'warehouse'); ?></h2>
        <p class="wh-trout-hero__meta" id="whTroutHeroMeta" aria-live="polite">—</p>
        <p class="wh-trout-hero__hint"><?php echo __t('wh_trout_hint', 'warehouse'); ?></p>
        <div class="wh-trout-hero__links">
            <a class="wh-trout-hero__link" href="transfer_requests.php"><?php echo __t('wh_trout_link_requests', 'warehouse'); ?></a>
            <a class="wh-trout-hero__link" href="incoming_transfers.php"><?php echo __t('wh_trout_link_incoming', 'warehouse'); ?></a>
            <a class="wh-trout-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_trout_link_wh_transfer', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-trout-hero__stats" role="group">
        <article class="wh-trout-stat wh-trout-stat--warn">
            <span class="wh-trout-stat__label"><?php echo __t('wh_trout_stat_requested', 'warehouse'); ?></span>
            <strong class="wh-trout-stat__value is-loading" id="whTroutStatRequested">—</strong>
        </article>
        <article class="wh-trout-stat wh-trout-stat--primary">
            <span class="wh-trout-stat__label"><?php echo __t('wh_trout_stat_progress', 'warehouse'); ?></span>
            <strong class="wh-trout-stat__value is-loading" id="whTroutStatProgress">—</strong>
        </article>
        <article class="wh-trout-stat wh-trout-stat--success">
            <span class="wh-trout-stat__label"><?php echo __t('wh_trout_stat_completed', 'warehouse'); ?></span>
            <strong class="wh-trout-stat__value is-loading" id="whTroutStatCompleted">—</strong>
        </article>
        <article class="wh-trout-stat">
            <span class="wh-trout-stat__label"><?php echo __t('wh_trout_stat_active', 'warehouse'); ?></span>
            <strong class="wh-trout-stat__value is-loading" id="whTroutStatActive">—</strong>
        </article>
    </div>
</section>

<section class="wh-trout-breakdown" id="whTroutBreakdownPanel" hidden aria-labelledby="whTroutBreakdownTitle">
    <div class="wh-trout-breakdown__head">
        <h3 id="whTroutBreakdownTitle"><?php echo __t('wh_trout_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-trout-status-chips" id="whTroutStatusChips"></div>
</section>

<div class="wh-trout-toolbar">
    <div class="wh-trout-toolbar__row">
        <div class="wh-trout-toolbar__filters">
            <label class="wh-trout-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whTroutSearch" class="wh-trout-search" placeholder="<?php echo htmlspecialchars(__t('wh_trout_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whTroutWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whTroutStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="outgoing_active"><?php echo __t('wh_trout_filter_active', 'warehouse'); ?></option>
                <option value="outgoing_pending"><?php echo __t('wh_trout_filter_pending', 'warehouse'); ?></option>
                <option value="requested"><?php echo __t('wms_status_requested', 'wms'); ?></option>
                <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
                <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
                <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
                <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-trout-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whTroutExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whTroutRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-trout-panel" aria-live="polite">
    <div class="wh-trout-table-wrap" id="whTroutTableWrap"></div>
    <div class="wh-trout-empty" id="whTroutEmpty" hidden>
        <span class="material-icons-round">call_made</span>
        <p><?php echo __t('wh_trout_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whTroutLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-trout-pagination" id="whTroutPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTroutPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-trout-pagination__meta" id="whTroutPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whTroutNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-trout-toast" id="whTroutToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whTroutDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--trout" role="dialog" aria-labelledby="whTroutDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">call_made</span></div>
                <div>
                    <h3 id="whTroutDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whTroutDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whTroutDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whTroutDetailBody" class="wh-trout-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

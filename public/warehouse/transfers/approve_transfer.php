<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('transfers');

$useWmsModules = true;
$activeWhPage = 'approve_transfer';
$pageTitle = __t('wh_nav_approve_transfer', 'warehouse');
$whCanApprove = ($whCanManage || $whCanTransfer) && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-approve-transfers.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_apr_subtitle', 'wh_apr_hint', 'wh_apr_stat_pending', 'wh_apr_stat_warehouse', 'wh_apr_stat_branch',
        'wh_apr_stat_value', 'wh_apr_search', 'wh_apr_empty', 'wh_apr_hero_meta', 'wh_apr_type_breakdown',
        'wh_apr_link_outgoing', 'wh_apr_link_wh_transfer', 'wh_apr_link_branch', 'wh_apr_filter_all_types',
        'wh_apr_toast_approved', 'wh_apr_toast_rejected', 'wh_apr_approve_btn', 'wh_apr_reject_btn',
        'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date', 'col_status',
    ]),
    wms_i18n([
        'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
        'wms_col_reason', 'wms_col_product', 'wms_col_qty', 'wms_col_sku', 'wms_unit_cost', 'wms_view_details',
        'wms_transfer_details', 'wms_status_requested', 'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh',
        'wms_type_branch', 'wms_approve', 'wms_reject', 'wms_confirm_approve_trf', 'wms_confirm_reject_trf',
        'wms_col_requested_by', 'wms_nav_warehouses', 'wms_col_store',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-apr-hero" aria-labelledby="whAprHeroTitle">
    <div class="wh-apr-hero__intro">
        <h2 class="wh-apr-hero__title" id="whAprHeroTitle"><?php echo __t('wh_apr_subtitle', 'warehouse'); ?></h2>
        <p class="wh-apr-hero__meta" id="whAprHeroMeta" aria-live="polite">—</p>
        <p class="wh-apr-hero__hint"><?php echo __t('wh_apr_hint', 'warehouse'); ?></p>
        <div class="wh-apr-hero__links">
            <a class="wh-apr-hero__link" href="outgoing_transfers.php"><?php echo __t('wh_apr_link_outgoing', 'warehouse'); ?></a>
            <a class="wh-apr-hero__link" href="warehouse_transfer.php"><?php echo __t('wh_apr_link_wh_transfer', 'warehouse'); ?></a>
            <a class="wh-apr-hero__link" href="branch_transfer.php"><?php echo __t('wh_apr_link_branch', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-apr-hero__stats" role="group">
        <article class="wh-apr-stat wh-apr-stat--warn">
            <span class="wh-apr-stat__label"><?php echo __t('wh_apr_stat_pending', 'warehouse'); ?></span>
            <strong class="wh-apr-stat__value is-loading" id="whAprStatPending">—</strong>
        </article>
        <article class="wh-apr-stat wh-apr-stat--primary">
            <span class="wh-apr-stat__label"><?php echo __t('wh_apr_stat_warehouse', 'warehouse'); ?></span>
            <strong class="wh-apr-stat__value is-loading" id="whAprStatWarehouse">—</strong>
        </article>
        <article class="wh-apr-stat">
            <span class="wh-apr-stat__label"><?php echo __t('wh_apr_stat_branch', 'warehouse'); ?></span>
            <strong class="wh-apr-stat__value is-loading" id="whAprStatBranch">—</strong>
        </article>
        <article class="wh-apr-stat wh-apr-stat--success">
            <span class="wh-apr-stat__label"><?php echo __t('wh_apr_stat_value', 'warehouse'); ?></span>
            <strong class="wh-apr-stat__value is-loading" id="whAprStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-apr-breakdown" id="whAprBreakdownPanel" hidden aria-labelledby="whAprBreakdownTitle">
    <div class="wh-apr-breakdown__head">
        <h3 id="whAprBreakdownTitle"><?php echo __t('wh_apr_type_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-apr-type-chips" id="whAprTypeChips"></div>
</section>

<div class="wh-apr-toolbar">
    <div class="wh-apr-toolbar__row">
        <div class="wh-apr-toolbar__filters">
            <label class="wh-apr-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whAprSearch" class="wh-apr-search" placeholder="<?php echo htmlspecialchars(__t('wh_apr_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whAprWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whAprType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_apr_filter_all_types', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_apr_filter_all_types', 'warehouse'); ?></option>
                <option value="warehouse_to_warehouse"><?php echo __t('wms_type_wh_wh', 'wms'); ?></option>
                <option value="warehouse_to_store"><?php echo __t('wms_type_wh_store', 'wms'); ?></option>
                <option value="store_to_warehouse"><?php echo __t('wms_type_store_wh', 'wms'); ?></option>
                <option value="branch_to_branch"><?php echo __t('wms_type_branch', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-apr-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whAprExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whAprRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-apr-panel" aria-live="polite">
    <div class="wh-apr-table-wrap" id="whAprTableWrap"></div>
    <div class="wh-apr-empty" id="whAprEmpty" hidden>
        <span class="material-icons-round">thumb_up</span>
        <p><?php echo __t('wh_apr_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whAprLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-apr-pagination" id="whAprPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whAprPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-apr-pagination__meta" id="whAprPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whAprNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-apr-toast" id="whAprToast" role="status" aria-live="polite"></div>

<div class="wms-modal-overlay" id="whAprDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--apr" role="dialog" aria-labelledby="whAprDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">thumb_up</span></div>
                <div>
                    <h3 id="whAprDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whAprDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whAprDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whAprDetailBody" class="wh-apr-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

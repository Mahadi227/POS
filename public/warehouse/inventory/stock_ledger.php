<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('inventory');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'stock_ledger';
$pageTitle = __t('wh_nav_ledger', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stock-ledger.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_ledger_subtitle', 'wh_ledger_hint', 'wh_ledger_stat_total', 'wh_ledger_stat_in', 'wh_ledger_stat_out',
        'wh_ledger_stat_net', 'wh_ledger_stat_value', 'wh_ledger_search', 'wh_ledger_empty', 'wh_ledger_type_breakdown',
        'wh_ledger_col_date', 'wh_ledger_col_product', 'wh_ledger_col_warehouse', 'wh_ledger_col_type',
        'wh_ledger_col_qty', 'wh_ledger_col_balance', 'wh_ledger_col_value', 'wh_ledger_col_reference',
        'wh_ledger_col_notes', 'wh_ledger_col_user', 'wh_ledger_details', 'wh_ledger_link_adjustments',
        'wh_ledger_link_inventory', 'wh_ledger_link_scanner', 'wh_ledger_date_from', 'wh_ledger_date_to',
        'wh_ledger_filter_all', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'col_status',
    ]),
    wms_i18n([
        'wms_mov_purchase', 'wms_mov_sale', 'wms_mov_transfer_in', 'wms_mov_transfer_out', 'wms_mov_return_in',
        'wms_mov_return_out', 'wms_mov_adjustment', 'wms_mov_damaged', 'wms_mov_expired', 'wms_mov_lost',
        'wms_mov_manual', 'wms_mov_dispatch_out', 'wms_mov_receipt_in', 'wms_nav_warehouses', 'wms_col_product',
        'wms_date_from', 'wms_date_to', 'wms_view_details',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-ledger-hero" aria-labelledby="whLedgerHeroTitle">
    <div class="wh-ledger-hero__intro">
        <h2 class="wh-ledger-hero__title" id="whLedgerHeroTitle"><?php echo __t('wh_ledger_subtitle', 'warehouse'); ?></h2>
        <p class="wh-ledger-hero__meta" id="whLedgerHeroMeta" aria-live="polite">—</p>
        <p class="wh-ledger-hero__hint"><?php echo __t('wh_ledger_hint', 'warehouse'); ?></p>
        <div class="wh-ledger-hero__links">
            <a class="wh-ledger-hero__link" href="stock_adjustments.php"><?php echo __t('wh_ledger_link_adjustments', 'warehouse'); ?></a>
            <a class="wh-ledger-hero__link" href="warehouse_inventory.php"><?php echo __t('wh_ledger_link_inventory', 'warehouse'); ?></a>
            <a class="wh-ledger-hero__link" href="barcode_scanner.php"><?php echo __t('wh_ledger_link_scanner', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-ledger-hero__stats" role="group">
        <article class="wh-ledger-stat wh-ledger-stat--primary">
            <span class="wh-ledger-stat__label"><?php echo __t('wh_ledger_stat_total', 'warehouse'); ?></span>
            <strong class="wh-ledger-stat__value is-loading" id="whLedgerStatTotal">—</strong>
        </article>
        <article class="wh-ledger-stat wh-ledger-stat--success">
            <span class="wh-ledger-stat__label"><?php echo __t('wh_ledger_stat_in', 'warehouse'); ?></span>
            <strong class="wh-ledger-stat__value is-loading" id="whLedgerStatIn">—</strong>
        </article>
        <article class="wh-ledger-stat wh-ledger-stat--danger">
            <span class="wh-ledger-stat__label"><?php echo __t('wh_ledger_stat_out', 'warehouse'); ?></span>
            <strong class="wh-ledger-stat__value is-loading" id="whLedgerStatOut">—</strong>
        </article>
        <article class="wh-ledger-stat">
            <span class="wh-ledger-stat__label"><?php echo __t('wh_ledger_stat_net', 'warehouse'); ?></span>
            <strong class="wh-ledger-stat__value is-loading" id="whLedgerStatNet">—</strong>
        </article>
        <article class="wh-ledger-stat">
            <span class="wh-ledger-stat__label"><?php echo __t('wh_ledger_stat_value', 'warehouse'); ?></span>
            <strong class="wh-ledger-stat__value is-loading" id="whLedgerStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-ledger-breakdown" id="whLedgerBreakdownPanel" hidden aria-labelledby="whLedgerBreakdownTitle">
    <div class="wh-ledger-breakdown__head">
        <h3 id="whLedgerBreakdownTitle"><?php echo __t('wh_ledger_type_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-ledger-type-chips" id="whLedgerTypeChips"></div>
</section>

<div class="wh-ledger-toolbar">
    <div class="wh-ledger-toolbar__row">
        <div class="wh-ledger-toolbar__filters">
            <select id="whLedgerWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-ledger-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whLedgerSearch" class="wh-ledger-search" placeholder="<?php echo htmlspecialchars(__t('wh_ledger_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whLedgerType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_ledger_col_type', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="receipt_in"><?php echo __t('wms_mov_receipt_in', 'wms'); ?></option>
                <option value="dispatch_out"><?php echo __t('wms_mov_dispatch_out', 'wms'); ?></option>
                <option value="transfer_in"><?php echo __t('wms_mov_transfer_in', 'wms'); ?></option>
                <option value="transfer_out"><?php echo __t('wms_mov_transfer_out', 'wms'); ?></option>
                <option value="purchase"><?php echo __t('wms_mov_purchase', 'wms'); ?></option>
                <option value="sale"><?php echo __t('wms_mov_sale', 'wms'); ?></option>
                <option value="return_in"><?php echo __t('wms_mov_return_in', 'wms'); ?></option>
                <option value="return_out"><?php echo __t('wms_mov_return_out', 'wms'); ?></option>
                <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
                <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
                <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
                <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
            </select>
            <label class="wh-ledger-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whLedgerDateFrom" class="wh-input wh-ledger-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-ledger-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whLedgerDateTo" class="wh-input wh-ledger-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-ledger-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whLedgerExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whLedgerRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-ledger-panel" aria-live="polite">
    <div class="wh-ledger-table-wrap" id="whLedgerTableWrap"></div>
    <div class="wh-ledger-empty" id="whLedgerEmpty" hidden>
        <span class="material-icons-round">menu_book</span>
        <p><?php echo __t('wh_ledger_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whLedgerLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-ledger-pagination" id="whLedgerPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLedgerPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-ledger-pagination__meta" id="whLedgerPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLedgerNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>

<div class="wh-modal" id="whLedgerDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whLedgerDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-ledger-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whLedgerDetailTitle"><?php echo __t('wh_ledger_details', 'warehouse'); ?></h3>
                <p class="wh-modal__sub" id="whLedgerDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whLedgerDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whLedgerDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

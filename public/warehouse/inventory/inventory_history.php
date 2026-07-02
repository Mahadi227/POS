<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('inventory');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$activeWhPage = 'inventory_history';
$pageTitle = __t('wh_nav_history', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-inventory-history.js'];

$invHistoryKeys = [
    'mov_purchase', 'mov_sale', 'mov_return', 'mov_transfer_in', 'mov_transfer_out', 'mov_adjustment',
    'mov_damaged', 'mov_expired', 'mov_manual_edit', 'col_type', 'col_product', 'col_sku_barcode',
    'col_store', 'col_user', 'col_stock_in', 'col_stock_out', 'col_current_stock', 'col_notes',
    'opening_stock', 'cost_price', 'sale_price_label', 'col_estimated_profit', 'no_history',
    'data_source_logs',
];
$invI18n = [];
foreach ($invHistoryKeys as $key) {
    $invI18n[$key] = __t($key, 'inventory');
}

$pageI18n = array_merge(
    wh_i18n([
        'wh_ih_subtitle', 'wh_ih_hint', 'wh_ih_stat_entries', 'wh_ih_stat_in', 'wh_ih_stat_out',
        'wh_ih_stat_profit', 'wh_ih_search', 'wh_ih_empty', 'wh_ih_type_breakdown',
        'wh_ih_col_date', 'wh_ih_col_opening', 'wh_ih_col_balance', 'wh_ih_col_value',
        'wh_ih_details', 'wh_ih_link_ledger', 'wh_ih_link_products', 'wh_ih_link_adjustments', 'wh_ih_link_count',
        'wh_ih_date_from', 'wh_ih_date_to', 'wh_ih_filter_all', 'wh_ih_filter_sales',
        'wh_ih_filter_adjustments', 'wh_ih_filter_transfers', 'wh_ih_filter_manual',
        'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated', 'export_csv',
        'prev_page', 'next_page', 'records', 'close', 'col_status',
    ]),
    wms_i18n(['wms_date_from', 'wms_date_to', 'wms_view_details']),
    $invI18n
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-ih-hero" aria-labelledby="whIhHeroTitle">
    <div class="wh-ih-hero__intro">
        <h2 class="wh-ih-hero__title" id="whIhHeroTitle"><?php echo __t('wh_ih_subtitle', 'warehouse'); ?></h2>
        <p class="wh-ih-hero__meta" id="whIhHeroMeta" aria-live="polite">—</p>
        <p class="wh-ih-hero__hint"><?php echo __t('wh_ih_hint', 'warehouse'); ?></p>
        <div class="wh-ih-hero__links">
            <a class="wh-ih-hero__link" href="stock_ledger.php"><?php echo __t('wh_ih_link_ledger', 'warehouse'); ?></a>
            <a class="wh-ih-hero__link" href="products.php"><?php echo __t('wh_ih_link_products', 'warehouse'); ?></a>
            <a class="wh-ih-hero__link" href="stock_adjustments.php"><?php echo __t('wh_ih_link_adjustments', 'warehouse'); ?></a>
            <a class="wh-ih-hero__link" href="stock_count.php"><?php echo __t('wh_ih_link_count', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-ih-hero__stats" role="group">
        <article class="wh-ih-stat wh-ih-stat--primary">
            <span class="wh-ih-stat__label"><?php echo __t('wh_ih_stat_entries', 'warehouse'); ?></span>
            <strong class="wh-ih-stat__value is-loading" id="whIhStatEntries">—</strong>
        </article>
        <article class="wh-ih-stat wh-ih-stat--success">
            <span class="wh-ih-stat__label"><?php echo __t('wh_ih_stat_in', 'warehouse'); ?></span>
            <strong class="wh-ih-stat__value is-loading" id="whIhStatIn">—</strong>
        </article>
        <article class="wh-ih-stat wh-ih-stat--danger">
            <span class="wh-ih-stat__label"><?php echo __t('wh_ih_stat_out', 'warehouse'); ?></span>
            <strong class="wh-ih-stat__value is-loading" id="whIhStatOut">—</strong>
        </article>
        <article class="wh-ih-stat">
            <span class="wh-ih-stat__label"><?php echo __t('wh_ih_stat_profit', 'warehouse'); ?></span>
            <strong class="wh-ih-stat__value is-loading" id="whIhStatProfit">—</strong>
        </article>
    </div>
</section>

<p class="wh-ih-source" id="whIhSourceNotice" hidden>
    <span class="material-icons-round">info</span>
    <?php echo __t('data_source_logs', 'inventory'); ?>
</p>

<section class="wh-ih-breakdown" id="whIhBreakdownPanel" hidden aria-labelledby="whIhBreakdownTitle">
    <div class="wh-ih-breakdown__head">
        <h3 id="whIhBreakdownTitle"><?php echo __t('wh_ih_type_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-ih-quick-chips" id="whIhQuickChips" role="group">
        <button type="button" class="wh-ih-quick-chip active" data-quick="all"><?php echo __t('wh_ih_filter_all', 'warehouse'); ?></button>
        <button type="button" class="wh-ih-quick-chip" data-quick="sale"><?php echo __t('wh_ih_filter_sales', 'warehouse'); ?></button>
        <button type="button" class="wh-ih-quick-chip" data-quick="adjustments"><?php echo __t('wh_ih_filter_adjustments', 'warehouse'); ?></button>
        <button type="button" class="wh-ih-quick-chip" data-quick="transfer"><?php echo __t('wh_ih_filter_transfers', 'warehouse'); ?></button>
        <button type="button" class="wh-ih-quick-chip" data-quick="manual_edit"><?php echo __t('wh_ih_filter_manual', 'warehouse'); ?></button>
    </div>
    <div class="wh-ih-type-chips" id="whIhTypeChips"></div>
</section>

<div class="wh-ih-toolbar">
    <div class="wh-ih-toolbar__row">
        <div class="wh-ih-toolbar__filters">
            <label class="wh-ih-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whIhSearch" class="wh-ih-search" placeholder="<?php echo htmlspecialchars(__t('wh_ih_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whIhType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_type', 'inventory'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_ih_filter_all', 'warehouse'); ?></option>
                <option value="sale"><?php echo __t('mov_sale', 'inventory'); ?></option>
                <option value="purchase"><?php echo __t('mov_purchase', 'inventory'); ?></option>
                <option value="return"><?php echo __t('mov_return', 'inventory'); ?></option>
                <option value="transfer_in"><?php echo __t('mov_transfer_in', 'inventory'); ?></option>
                <option value="transfer_out"><?php echo __t('mov_transfer_out', 'inventory'); ?></option>
                <option value="adjustment"><?php echo __t('mov_adjustment', 'inventory'); ?></option>
                <option value="damaged"><?php echo __t('mov_damaged', 'inventory'); ?></option>
                <option value="expired"><?php echo __t('mov_expired', 'inventory'); ?></option>
                <option value="manual_edit"><?php echo __t('mov_manual_edit', 'inventory'); ?></option>
            </select>
            <label class="wh-ih-date-wrap">
                <span><?php echo __t('wh_ih_date_from', 'warehouse'); ?></span>
                <input type="date" id="whIhDateFrom" class="wh-input wh-ih-date" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-ih-date-wrap">
                <span><?php echo __t('wh_ih_date_to', 'warehouse'); ?></span>
                <input type="date" id="whIhDateTo" class="wh-input wh-ih-date" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-ih-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whIhExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whIhRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-ih-panel" aria-live="polite">
    <div class="wh-ih-table-wrap" id="whIhTableWrap"></div>
    <div class="wh-ih-empty" id="whIhEmpty" hidden>
        <span class="material-icons-round">history_toggle_off</span>
        <p><?php echo __t('wh_ih_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whIhLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-ih-pagination" id="whIhPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whIhPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-ih-pagination__meta" id="whIhPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whIhNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>

<div class="wh-modal" id="whIhDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whIhDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-ih-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whIhDetailTitle"><?php echo __t('wh_ih_details', 'warehouse'); ?></h3>
                <p class="wh-modal__sub" id="whIhDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whIhDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whIhDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

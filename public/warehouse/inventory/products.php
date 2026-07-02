<?php
require __DIR__ . '/../includes/bootstrap.php';
$useWmsModules = true;
$activeWhPage = 'products';
$pageTitle = __t('wh_nav_products', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-products.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_prod_subtitle', 'wh_prod_stat_products', 'wh_prod_stat_in_stock', 'wh_prod_stat_low',
        'wh_prod_stat_out', 'wh_prod_stat_value', 'wh_prod_search', 'wh_prod_filter_all',
        'wh_prod_filter_in_stock', 'wh_prod_filter_low', 'wh_prod_filter_out', 'wh_prod_filter_alert',
        'wh_prod_filter_no_wh', 'wh_prod_filter_category', 'wh_prod_col_product', 'wh_prod_col_category',
        'wh_prod_col_qty', 'wh_prod_col_available', 'wh_prod_col_reserved', 'wh_prod_col_value',
        'wh_prod_col_warehouses', 'wh_prod_col_price', 'wh_prod_col_status', 'wh_prod_view_details',
        'wh_prod_details', 'wh_prod_wh_breakdown', 'wh_prod_no_wh_stock', 'wh_prod_link_ledger',
        'wh_prod_link_scanner', 'wh_prod_link_inv', 'wh_prod_empty', 'wh_all_warehouses', 'wh_select_warehouse',
        'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated', 'no_data', 'prev_page',
        'next_page', 'records', 'export_csv', 'close', 'col_status',
    ]),
    wms_i18n([
        'wms_stock_ok', 'wms_stock_low', 'wms_stock_out', 'wms_stock_alert',
        'wms_col_location', 'wms_col_qty', 'wms_col_value', 'wms_nav_warehouses', 'wms_col_sku',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-prod-hero" aria-labelledby="whProdHeroTitle">
    <div class="wh-prod-hero__intro">
        <h2 class="wh-prod-hero__title" id="whProdHeroTitle"><?php echo __t('wh_prod_subtitle', 'warehouse'); ?></h2>
        <p class="wh-prod-hero__meta" id="whProdHeroMeta" aria-live="polite">—</p>
    </div>
    <div class="wh-prod-hero__stats" role="group">
        <article class="wh-prod-stat wh-prod-stat--primary">
            <span class="wh-prod-stat__label"><?php echo __t('wh_prod_stat_products', 'warehouse'); ?></span>
            <strong class="wh-prod-stat__value is-loading" id="whProdStatProducts">—</strong>
        </article>
        <article class="wh-prod-stat wh-prod-stat--success">
            <span class="wh-prod-stat__label"><?php echo __t('wh_prod_stat_in_stock', 'warehouse'); ?></span>
            <strong class="wh-prod-stat__value is-loading" id="whProdStatInStock">—</strong>
        </article>
        <article class="wh-prod-stat wh-prod-stat--warn">
            <span class="wh-prod-stat__label"><?php echo __t('wh_prod_stat_low', 'warehouse'); ?></span>
            <strong class="wh-prod-stat__value is-loading" id="whProdStatLow">—</strong>
        </article>
        <article class="wh-prod-stat wh-prod-stat--danger">
            <span class="wh-prod-stat__label"><?php echo __t('wh_prod_stat_out', 'warehouse'); ?></span>
            <strong class="wh-prod-stat__value is-loading" id="whProdStatOut">—</strong>
        </article>
        <article class="wh-prod-stat">
            <span class="wh-prod-stat__label"><?php echo __t('wh_prod_stat_value', 'warehouse'); ?></span>
            <strong class="wh-prod-stat__value is-loading" id="whProdStatValue">—</strong>
        </article>
    </div>
</section>

<div id="whMigrationHint" class="wh-migration-hint" hidden></div>

<div class="wh-prod-toolbar">
    <div class="wh-prod-toolbar__row">
        <div class="wh-prod-toolbar__filters">
            <select id="whProdWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-prod-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whProdSearch" class="wh-prod-search" placeholder="<?php echo htmlspecialchars(__t('wh_prod_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whProdFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_prod_filter_all', 'warehouse'); ?></option>
                <option value="in_stock"><?php echo __t('wh_prod_filter_in_stock', 'warehouse'); ?></option>
                <option value="low"><?php echo __t('wh_prod_filter_low', 'warehouse'); ?></option>
                <option value="out"><?php echo __t('wh_prod_filter_out', 'warehouse'); ?></option>
                <option value="alert"><?php echo __t('wh_prod_filter_alert', 'warehouse'); ?></option>
                <option value="no_wh"><?php echo __t('wh_prod_filter_no_wh', 'warehouse'); ?></option>
            </select>
            <select id="whProdCategory" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_prod_filter_category', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_prod_filter_category', 'warehouse'); ?></option>
            </select>
        </div>
        <div class="wh-prod-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whProdExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whProdRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-prod-panel" aria-live="polite">
    <div class="wh-prod-table-wrap" id="whProdTableWrap"></div>
    <div class="wh-prod-empty" id="whProdEmpty" hidden>
        <span class="material-icons-round">inventory_2</span>
        <p><?php echo __t('wh_prod_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whProdLoading" hidden><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-prod-pagination" id="whProdPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whProdPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-prod-pagination__meta" id="whProdPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whProdNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wms-modal-overlay" id="whProdDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whProdDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></div>
                <div>
                    <h3 id="whProdDetailTitle"><?php echo __t('wh_prod_details', 'warehouse'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whProdDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whProdDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whProdDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

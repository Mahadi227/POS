<?php
require __DIR__ . '/../includes/bootstrap.php';
$useWmsModules = true;
$activeWhPage = 'warehouse_inventory';
$pageTitle = __t('wh_nav_warehouse_inventory', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-inventory.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_inv_subtitle', 'wh_inv_empty', 'wh_inv_link_products', 'wh_inv_link_ledger', 'wh_inv_link_scanner',
        'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'no_data', 'export_csv', 'close', 'col_status', 'records',
    ]),
    wms_i18n([
        'wms_no_data', 'wms_select_warehouse', 'wms_inventory_title', 'wms_inventory_subtitle',
        'wms_stat_inv_skus', 'wms_stat_inv_units', 'wms_stat_inv_value', 'wms_stat_inv_low',
        'wms_search_inventory', 'wms_filter_all_stock', 'wms_stock_low', 'wms_stock_out', 'wms_stock_damaged_filter',
        'wms_col_product', 'wms_col_qty', 'wms_col_available', 'wms_col_reserved', 'wms_col_value',
        'wms_col_reorder', 'wms_col_location', 'wms_col_unit_cost', 'wms_col_batch', 'wms_col_last_movement',
        'wms_col_sku',
        'wms_inventory_details', 'wms_recent_movements', 'wms_view_details', 'wms_nav_warehouses',
        'col_date', 'wms_col_damaged', 'wms_col_expired', 'wms_stock_ok', 'wms_stock_alert',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-inv-hero" aria-labelledby="whInvHeroTitle">
    <div class="wh-inv-hero__intro">
        <h2 class="wh-inv-hero__title" id="whInvHeroTitle"><?php echo __t('wh_inv_subtitle', 'warehouse'); ?></h2>
        <p class="wh-inv-hero__meta" id="whInvHeroMeta" aria-live="polite">—</p>
    </div>
    <div class="wh-inv-hero__stats" role="group">
        <article class="wh-inv-stat wh-inv-stat--primary">
            <span class="wh-inv-stat__label"><?php echo __t('wms_stat_inv_skus', 'wms'); ?></span>
            <strong class="wh-inv-stat__value is-loading" id="whInvStatSkus">—</strong>
        </article>
        <article class="wh-inv-stat wh-inv-stat--success">
            <span class="wh-inv-stat__label"><?php echo __t('wms_stat_inv_units', 'wms'); ?></span>
            <strong class="wh-inv-stat__value is-loading" id="whInvStatUnits">—</strong>
        </article>
        <article class="wh-inv-stat">
            <span class="wh-inv-stat__label"><?php echo __t('wms_stat_inv_value', 'wms'); ?></span>
            <strong class="wh-inv-stat__value is-loading" id="whInvStatValue">—</strong>
        </article>
        <article class="wh-inv-stat wh-inv-stat--warn">
            <span class="wh-inv-stat__label"><?php echo __t('wms_stock_low', 'wms'); ?></span>
            <strong class="wh-inv-stat__value is-loading" id="whInvStatLow">—</strong>
        </article>
        <article class="wh-inv-stat wh-inv-stat--danger">
            <span class="wh-inv-stat__label"><?php echo __t('wms_stock_out', 'wms'); ?></span>
            <strong class="wh-inv-stat__value is-loading" id="whInvStatOut">—</strong>
        </article>
    </div>
</section>

<div id="whMigrationHint" class="wh-migration-hint" hidden></div>

<div class="wh-inv-toolbar">
    <div class="wh-inv-toolbar__row">
        <div class="wh-inv-toolbar__filters">
            <select id="whInvWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" required>
                <option value=""><?php echo __t('wms_select_warehouse', 'wms'); ?></option>
            </select>
            <label class="wh-inv-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whInvSearch" class="wh-inv-search" placeholder="<?php echo htmlspecialchars(__t('wms_search_inventory', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whInvFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wms_filter_all_stock', 'wms'); ?></option>
                <option value="low"><?php echo __t('wms_stock_low', 'wms'); ?></option>
                <option value="out"><?php echo __t('wms_stock_out', 'wms'); ?></option>
                <option value="damaged"><?php echo __t('wms_stock_damaged_filter', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-inv-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whInvExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whInvRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-inv-panel" aria-live="polite">
    <div class="wh-inv-table-wrap" id="whInvTableWrap">
        <div class="wh-inv-empty" id="whInvPlaceholder">
            <span class="material-icons-round">warehouse</span>
            <p><?php echo __t('wms_select_warehouse', 'wms'); ?></p>
        </div>
    </div>
    <div class="wh-inv-empty" id="whInvEmpty" hidden>
        <span class="material-icons-round">inventory_2</span>
        <p><?php echo __t('wh_inv_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whInvLoading" hidden><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<div class="wms-modal-overlay" id="whInvDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="whInvDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory</span></div>
                <div>
                    <h3 id="whInvDetailTitle"><?php echo __t('wms_inventory_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whInvDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whInvDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whInvDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

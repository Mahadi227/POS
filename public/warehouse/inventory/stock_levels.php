<?php
require __DIR__ . '/../includes/bootstrap.php';
$useWmsModules = true;
$activeWhPage = 'stock_levels';
$pageTitle = __t('wh_nav_stock_levels', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stock-levels.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_sl_subtitle', 'wh_sl_stat_skus', 'wh_sl_stat_ok', 'wh_sl_stat_low', 'wh_sl_stat_out',
        'wh_sl_stat_needs_reorder', 'wh_sl_stat_reorder_gap', 'wh_sl_search', 'wh_sl_filter_all',
        'wh_sl_filter_ok', 'wh_sl_filter_low', 'wh_sl_filter_out', 'wh_sl_filter_needs_reorder',
        'wh_sl_col_product', 'wh_sl_col_warehouse', 'wh_sl_col_on_hand', 'wh_sl_col_available',
        'wh_sl_col_reorder', 'wh_sl_col_gap', 'wh_sl_col_fill', 'wh_sl_col_status', 'wh_sl_empty',
        'wh_sl_level_ok', 'wh_sl_level_low', 'wh_sl_level_out', 'wh_sl_link_inv', 'wh_sl_link_products',
        'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error',
        'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'col_status',
    ]),
    wms_i18n(['wms_nav_warehouses', 'wms_col_qty', 'wms_col_reorder', 'wms_col_sku'])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-sl-hero" aria-labelledby="whSlHeroTitle">
    <div class="wh-sl-hero__intro">
        <h2 class="wh-sl-hero__title" id="whSlHeroTitle"><?php echo __t('wh_sl_subtitle', 'warehouse'); ?></h2>
        <p class="wh-sl-hero__meta" id="whSlHeroMeta" aria-live="polite">—</p>
    </div>
    <div class="wh-sl-hero__stats" role="group">
        <article class="wh-sl-stat wh-sl-stat--primary">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_skus', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatSkus">—</strong>
        </article>
        <article class="wh-sl-stat wh-sl-stat--success">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_ok', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatOk">—</strong>
        </article>
        <article class="wh-sl-stat wh-sl-stat--warn">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_low', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatLow">—</strong>
        </article>
        <article class="wh-sl-stat wh-sl-stat--danger">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_out', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatOut">—</strong>
        </article>
        <article class="wh-sl-stat">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_needs_reorder', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatNeeds">—</strong>
        </article>
        <article class="wh-sl-stat">
            <span class="wh-sl-stat__label"><?php echo __t('wh_sl_stat_reorder_gap', 'warehouse'); ?></span>
            <strong class="wh-sl-stat__value is-loading" id="whSlStatGap">—</strong>
        </article>
    </div>
</section>

<div id="whMigrationHint" class="wh-migration-hint" hidden></div>

<div class="wh-sl-toolbar">
    <div class="wh-sl-toolbar__row">
        <div class="wh-sl-toolbar__filters">
            <select id="whSlWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-sl-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whSlSearch" class="wh-sl-search" placeholder="<?php echo htmlspecialchars(__t('wh_sl_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whSlFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_sl_filter_all', 'warehouse'); ?></option>
                <option value="ok"><?php echo __t('wh_sl_filter_ok', 'warehouse'); ?></option>
                <option value="low"><?php echo __t('wh_sl_filter_low', 'warehouse'); ?></option>
                <option value="out"><?php echo __t('wh_sl_filter_out', 'warehouse'); ?></option>
                <option value="needs_reorder"><?php echo __t('wh_sl_filter_needs_reorder', 'warehouse'); ?></option>
            </select>
        </div>
        <div class="wh-sl-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whSlExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whSlRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-sl-panel" aria-live="polite">
    <div class="wh-sl-table-wrap" id="whSlTableWrap"></div>
    <div class="wh-sl-empty" id="whSlEmpty" hidden>
        <span class="material-icons-round">stacked_bar_chart</span>
        <p><?php echo __t('wh_sl_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whSlLoading" hidden><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-sl-pagination" id="whSlPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSlPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-sl-pagination__meta" id="whSlPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSlNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/../includes/layout-end.php';

<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'inventory_valuation';
$pageTitle = __t('wh_nav_rpt_valuation', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-inventory-valuation-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_ival_subtitle', 'wh_ival_hint', 'wh_ival_stat_cost', 'wh_ival_stat_selling', 'wh_ival_stat_profit',
        'wh_ival_stat_turnover', 'wh_ival_stat_lines', 'wh_ival_stat_qty', 'wh_ival_search', 'wh_ival_empty',
        'wh_ival_chart_value', 'wh_ival_chart_category', 'wh_ival_chart_warehouse', 'wh_ival_category_breakdown',
        'wh_ival_export_excel', 'wh_ival_export_pdf', 'wh_ival_print', 'wh_ival_offline_cached',
        'wh_ival_link_inventory', 'wh_ival_link_stock', 'wh_ival_col_unit_cost', 'wh_ival_col_unit_price',
        'wh_ival_col_margin', 'wh_ival_col_margin_pct', 'wh_ival_table_title',
        'wh_irpt_val_method', 'wh_irpt_val_fifo', 'wh_irpt_val_weighted', 'wh_irpt_val_lifo',
        'wh_nav_rpt_valuation', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'wh_prod_col_category',
        'wh_ledger_filter_all', 'loading', 'load_error', 'refresh', 'last_updated', 'export_csv',
        'prev_page', 'next_page', 'records',
    ]),
    wms_i18n([
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_value', 'wms_col_qty',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whIvalOfflineBadge" class="wh-ival-offline-badge" hidden><?php echo __t('wh_ival_offline_cached', 'warehouse'); ?></div>

<section class="wh-ival-hero" aria-labelledby="whIvalHeroTitle">
    <div class="wh-ival-hero__intro">
        <h2 class="wh-ival-hero__title" id="whIvalHeroTitle"><?php echo __t('wh_ival_subtitle', 'warehouse'); ?></h2>
        <p class="wh-ival-hero__meta" id="whIvalHeroMeta" aria-live="polite">—</p>
        <p class="wh-ival-hero__hint"><?php echo __t('wh_ival_hint', 'warehouse'); ?></p>
        <div class="wh-ival-hero__links">
            <a class="wh-ival-hero__link" href="inventory_report.php"><?php echo __t('wh_ival_link_inventory', 'warehouse'); ?></a>
            <a class="wh-ival-hero__link" href="../inventory/stock_levels.php"><?php echo __t('wh_ival_link_stock', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-ival-hero__stats" role="group">
        <article class="wh-ival-stat wh-ival-stat--primary">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_cost', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatCost">—</strong>
        </article>
        <article class="wh-ival-stat wh-ival-stat--success">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_selling', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatSelling">—</strong>
        </article>
        <article class="wh-ival-stat">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_profit', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatProfit">—</strong>
        </article>
        <article class="wh-ival-stat">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_turnover', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatTurnover">—</strong>
        </article>
        <article class="wh-ival-stat wh-ival-stat--warn">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_lines', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatLines">—</strong>
        </article>
        <article class="wh-ival-stat">
            <span class="wh-ival-stat__label"><?php echo __t('wh_ival_stat_qty', 'warehouse'); ?></span>
            <strong class="wh-ival-stat__value is-loading" id="whIvalStatQty">—</strong>
        </article>
    </div>
</section>

<section class="wh-ival-charts" aria-label="Charts">
    <div class="wh-ival-charts__grid">
        <article class="wh-ival-chart-card">
            <h4><?php echo __t('wh_ival_chart_value', 'warehouse'); ?></h4>
            <div class="wh-ival-chart-wrap"><canvas id="whIvalChartValue"></canvas></div>
        </article>
        <article class="wh-ival-chart-card">
            <h4><?php echo __t('wh_ival_chart_category', 'warehouse'); ?></h4>
            <div class="wh-ival-chart-wrap"><canvas id="whIvalChartCategory"></canvas></div>
        </article>
        <article class="wh-ival-chart-card">
            <h4><?php echo __t('wh_ival_chart_warehouse', 'warehouse'); ?></h4>
            <div class="wh-ival-chart-wrap"><canvas id="whIvalChartWarehouse"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-ival-breakdown" id="whIvalBreakdownPanel" hidden aria-labelledby="whIvalBreakdownTitle">
    <div class="wh-ival-breakdown__head">
        <h3 id="whIvalBreakdownTitle"><?php echo __t('wh_ival_category_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-ival-category-chips" id="whIvalCategoryChips"></div>
</section>

<div class="wh-ival-toolbar">
    <div class="wh-ival-toolbar__row">
        <div class="wh-ival-toolbar__filters">
            <select id="whIvalWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whIvalCategory" class="wh-select" aria-label="Category">
                <option value=""><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
            </select>
            <select id="whIvalMethod" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_irpt_val_method', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="weighted"><?php echo __t('wh_irpt_val_weighted', 'warehouse'); ?></option>
                <option value="fifo"><?php echo __t('wh_irpt_val_fifo', 'warehouse'); ?></option>
                <option value="lifo"><?php echo __t('wh_irpt_val_lifo', 'warehouse'); ?></option>
            </select>
            <label class="wh-ival-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whIvalSearch" class="wh-ival-search" placeholder="<?php echo htmlspecialchars(__t('wh_ival_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
        </div>
        <div class="wh-ival-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whIvalExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIvalExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_ival_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIvalExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_ival_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIvalPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_ival_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whIvalRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-ival-panel" aria-live="polite">
    <header class="wh-ival-panel__head">
        <h3><?php echo __t('wh_ival_table_title', 'warehouse'); ?></h3>
        <span class="wh-ival-panel__method" id="whIvalMethodLabel">—</span>
    </header>
    <div class="wh-ival-table-wrap" id="whIvalTableWrap"></div>
    <div class="wh-ival-empty" id="whIvalEmpty" hidden>
        <span class="material-icons-round">payments</span>
        <p><?php echo __t('wh_ival_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whIvalLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-ival-pagination" id="whIvalPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whIvalPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-ival-pagination__meta" id="whIvalPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whIvalNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/../includes/layout-end.php';

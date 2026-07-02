<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'damage_report';
$pageTitle = __t('wh_nav_rpt_damage', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-damage-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_dmgr_subtitle', 'wh_dmgr_hint', 'wh_dmgr_stat_incidents', 'wh_dmgr_stat_units', 'wh_dmgr_stat_loss',
        'wh_dmgr_stat_products', 'wh_dmgr_stat_warehouses', 'wh_dmgr_stat_on_hand', 'wh_dmgr_search', 'wh_dmgr_empty',
        'wh_dmgr_chart_trend', 'wh_dmgr_chart_warehouse', 'wh_dmgr_chart_type',
        'wh_dmgr_warehouse_breakdown', 'wh_dmgr_type_breakdown',
        'wh_dmgr_export_excel', 'wh_dmgr_export_pdf', 'wh_dmgr_print', 'wh_dmgr_offline_cached',
        'wh_dmgr_link_inventory', 'wh_dmgr_link_adjustments', 'wh_dmgr_col_damage_type', 'wh_dmgr_col_reported_by',
        'wh_dmgr_col_loss', 'wh_dmgr_type_unspecified', 'wh_dmgr_table_title',
        'wh_ledger_date_from', 'wh_ledger_date_to',
        'wh_nav_rpt_damage', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'col_date',
    ]),
    wms_i18n([
        'wms_nav_warehouses', 'wms_col_product', 'wms_col_value', 'wms_col_qty',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whDmgrOfflineBadge" class="wh-dmgr-offline-badge" hidden><?php echo __t('wh_dmgr_offline_cached', 'warehouse'); ?></div>

<section class="wh-dmgr-hero" aria-labelledby="whDmgrHeroTitle">
    <div class="wh-dmgr-hero__intro">
        <h2 class="wh-dmgr-hero__title" id="whDmgrHeroTitle"><?php echo __t('wh_dmgr_subtitle', 'warehouse'); ?></h2>
        <p class="wh-dmgr-hero__meta" id="whDmgrHeroMeta" aria-live="polite">—</p>
        <p class="wh-dmgr-hero__hint"><?php echo __t('wh_dmgr_hint', 'warehouse'); ?></p>
        <div class="wh-dmgr-hero__links">
            <a class="wh-dmgr-hero__link" href="inventory_report.php"><?php echo __t('wh_dmgr_link_inventory', 'warehouse'); ?></a>
            <a class="wh-dmgr-hero__link" href="../inventory/stock_adjustments.php"><?php echo __t('wh_dmgr_link_adjustments', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-dmgr-hero__stats" role="group">
        <article class="wh-dmgr-stat wh-dmgr-stat--primary">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_incidents', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatIncidents">—</strong>
        </article>
        <article class="wh-dmgr-stat wh-dmgr-stat--warn">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_units', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatUnits">—</strong>
        </article>
        <article class="wh-dmgr-stat wh-dmgr-stat--danger">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_loss', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatLoss">—</strong>
        </article>
        <article class="wh-dmgr-stat">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_products', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatProducts">—</strong>
        </article>
        <article class="wh-dmgr-stat">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_warehouses', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatWarehouses">—</strong>
        </article>
        <article class="wh-dmgr-stat">
            <span class="wh-dmgr-stat__label"><?php echo __t('wh_dmgr_stat_on_hand', 'warehouse'); ?></span>
            <strong class="wh-dmgr-stat__value is-loading" id="whDmgrStatOnHand">—</strong>
        </article>
    </div>
</section>

<section class="wh-dmgr-charts" aria-label="Charts">
    <div class="wh-dmgr-charts__grid">
        <article class="wh-dmgr-chart-card">
            <h4><?php echo __t('wh_dmgr_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-dmgr-chart-wrap"><canvas id="whDmgrChartTrend"></canvas></div>
        </article>
        <article class="wh-dmgr-chart-card">
            <h4><?php echo __t('wh_dmgr_chart_warehouse', 'warehouse'); ?></h4>
            <div class="wh-dmgr-chart-wrap"><canvas id="whDmgrChartWarehouse"></canvas></div>
        </article>
        <article class="wh-dmgr-chart-card">
            <h4><?php echo __t('wh_dmgr_chart_type', 'warehouse'); ?></h4>
            <div class="wh-dmgr-chart-wrap"><canvas id="whDmgrChartType"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-dmgr-breakdown" id="whDmgrBreakdownPanel" hidden aria-labelledby="whDmgrBreakdownTitle">
    <div class="wh-dmgr-breakdown__head">
        <h3 id="whDmgrBreakdownTitle"><?php echo __t('wh_dmgr_warehouse_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-dmgr-status-chips" id="whDmgrWarehouseChips"></div>
</section>

<div class="wh-dmgr-toolbar">
    <div class="wh-dmgr-toolbar__row">
        <div class="wh-dmgr-toolbar__filters">
            <select id="whDmgrWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-dmgr-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whDmgrSearch" class="wh-dmgr-search" placeholder="<?php echo htmlspecialchars(__t('wh_dmgr_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <label class="wh-dmgr-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whDmgrDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-dmgr-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whDmgrDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-dmgr-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_dmgr_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_dmgr_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_dmgr_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whDmgrRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-dmgr-panel" aria-live="polite">
    <header class="wh-dmgr-panel__head">
        <h3><?php echo __t('wh_dmgr_table_title', 'warehouse'); ?></h3>
    </header>
    <div class="wh-dmgr-table-wrap" id="whDmgrTableWrap"></div>
    <div class="wh-dmgr-empty" id="whDmgrEmpty" hidden>
        <span class="material-icons-round">broken_image</span>
        <p><?php echo __t('wh_dmgr_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whDmgrLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-dmgr-pagination" id="whDmgrPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-dmgr-pagination__meta" id="whDmgrPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whDmgrNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/../includes/layout-end.php';

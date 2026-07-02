<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'stock_movement_report';
$pageTitle = __t('wh_nav_rpt_movements', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stock-movement-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_smrt_subtitle', 'wh_smrt_hint', 'wh_smrt_stat_total', 'wh_smrt_stat_in', 'wh_smrt_stat_out',
        'wh_smrt_stat_value', 'wh_smrt_search', 'wh_smrt_empty', 'wh_smrt_type_breakdown', 'wh_smrt_col_prev',
        'wh_smrt_chart_trend', 'wh_smrt_chart_types', 'wh_smrt_export_excel', 'wh_smrt_export_pdf', 'wh_smrt_print',
        'wh_smrt_offline_cached', 'wh_smrt_link_inventory', 'wh_smrt_link_ledger',
        'wh_ledger_col_date', 'wh_ledger_col_product', 'wh_ledger_col_warehouse', 'wh_ledger_col_type',
        'wh_ledger_col_qty', 'wh_ledger_col_balance', 'wh_ledger_col_value', 'wh_ledger_col_reference',
        'wh_ledger_col_notes', 'wh_ledger_col_user', 'wh_ledger_details', 'wh_ledger_date_from', 'wh_ledger_date_to',
        'wh_ledger_filter_all', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'close', 'error', 'col_date',
    ]),
    wms_i18n([
        'wms_mov_purchase', 'wms_mov_sale', 'wms_mov_transfer_in', 'wms_mov_transfer_out', 'wms_mov_return_in',
        'wms_mov_return_out', 'wms_mov_adjustment', 'wms_mov_damaged', 'wms_mov_expired', 'wms_mov_lost',
        'wms_mov_manual', 'wms_mov_dispatch_out', 'wms_mov_receipt_in', 'wms_col_product', 'wms_col_reference',
        'wms_col_user', 'wms_col_movement_type', 'wms_col_qty', 'wms_col_balance', 'wms_col_value',
        'wms_date_from', 'wms_date_to', 'wms_filter_all_types', 'wms_view_details', 'wms_nav_warehouses',
        'wms_breakdown_title',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whSmrtOfflineBadge" class="wh-smrt-offline-badge" hidden><?php echo __t('wh_smrt_offline_cached', 'warehouse'); ?></div>

<section class="wh-smrt-hero" aria-labelledby="whSmrtHeroTitle">
    <div class="wh-smrt-hero__intro">
        <h2 class="wh-smrt-hero__title" id="whSmrtHeroTitle"><?php echo __t('wh_smrt_subtitle', 'warehouse'); ?></h2>
        <p class="wh-smrt-hero__meta" id="whSmrtHeroMeta" aria-live="polite">—</p>
        <p class="wh-smrt-hero__hint"><?php echo __t('wh_smrt_hint', 'warehouse'); ?></p>
        <div class="wh-smrt-hero__links">
            <a class="wh-smrt-hero__link" href="inventory_report.php"><?php echo __t('wh_smrt_link_inventory', 'warehouse'); ?></a>
            <a class="wh-smrt-hero__link" href="../inventory/stock_ledger.php"><?php echo __t('wh_smrt_link_ledger', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-smrt-hero__stats" role="group">
        <article class="wh-smrt-stat wh-smrt-stat--primary">
            <span class="wh-smrt-stat__label"><?php echo __t('wh_smrt_stat_total', 'warehouse'); ?></span>
            <strong class="wh-smrt-stat__value is-loading" id="whSmrtStatTotal">—</strong>
        </article>
        <article class="wh-smrt-stat wh-smrt-stat--success">
            <span class="wh-smrt-stat__label"><?php echo __t('wh_smrt_stat_in', 'warehouse'); ?></span>
            <strong class="wh-smrt-stat__value is-loading" id="whSmrtStatIn">—</strong>
        </article>
        <article class="wh-smrt-stat wh-smrt-stat--danger">
            <span class="wh-smrt-stat__label"><?php echo __t('wh_smrt_stat_out', 'warehouse'); ?></span>
            <strong class="wh-smrt-stat__value is-loading" id="whSmrtStatOut">—</strong>
        </article>
        <article class="wh-smrt-stat">
            <span class="wh-smrt-stat__label"><?php echo __t('wh_smrt_stat_value', 'warehouse'); ?></span>
            <strong class="wh-smrt-stat__value is-loading" id="whSmrtStatValue">—</strong>
        </article>
    </div>
</section>

<section class="wh-smrt-charts" aria-label="Charts">
    <div class="wh-smrt-charts__grid">
        <article class="wh-smrt-chart-card">
            <h4><?php echo __t('wh_smrt_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-smrt-chart-wrap"><canvas id="whSmrtChartTrend"></canvas></div>
        </article>
        <article class="wh-smrt-chart-card">
            <h4><?php echo __t('wh_smrt_chart_types', 'warehouse'); ?></h4>
            <div class="wh-smrt-chart-wrap"><canvas id="whSmrtChartTypes"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-smrt-breakdown" id="whSmrtBreakdownPanel" hidden aria-labelledby="whSmrtBreakdownTitle">
    <div class="wh-smrt-breakdown__head">
        <h3 id="whSmrtBreakdownTitle"><?php echo __t('wh_smrt_type_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-smrt-type-chips" id="whSmrtTypeChips"></div>
</section>

<div class="wh-smrt-toolbar">
    <div class="wh-smrt-toolbar__row">
        <div class="wh-smrt-toolbar__filters">
            <select id="whSmrtWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <label class="wh-smrt-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whSmrtSearch" class="wh-smrt-search" placeholder="<?php echo htmlspecialchars(__t('wh_smrt_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whSmrtType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_ledger_col_type', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_ledger_filter_all', 'warehouse'); ?></option>
                <option value="receipt_in"><?php echo __t('wms_mov_receipt_in', 'wms'); ?></option>
                <option value="purchase"><?php echo __t('wms_mov_purchase', 'wms'); ?></option>
                <option value="transfer_in"><?php echo __t('wms_mov_transfer_in', 'wms'); ?></option>
                <option value="transfer_out"><?php echo __t('wms_mov_transfer_out', 'wms'); ?></option>
                <option value="dispatch_out"><?php echo __t('wms_mov_dispatch_out', 'wms'); ?></option>
                <option value="sale"><?php echo __t('wms_mov_sale', 'wms'); ?></option>
                <option value="return_in"><?php echo __t('wms_mov_return_in', 'wms'); ?></option>
                <option value="return_out"><?php echo __t('wms_mov_return_out', 'wms'); ?></option>
                <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
                <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
                <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
                <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
            </select>
            <label class="wh-smrt-date-wrap">
                <span><?php echo __t('wh_ledger_date_from', 'warehouse'); ?></span>
                <input type="date" id="whSmrtDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-smrt-date-wrap">
                <span><?php echo __t('wh_ledger_date_to', 'warehouse'); ?></span>
                <input type="date" id="whSmrtDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="wh-smrt-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_smrt_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_smrt_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_smrt_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whSmrtRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-smrt-panel" aria-live="polite">
    <div class="wh-smrt-table-wrap" id="whSmrtTableWrap"></div>
    <div class="wh-smrt-empty" id="whSmrtEmpty" hidden>
        <span class="material-icons-round">swap_horiz</span>
        <p><?php echo __t('wh_smrt_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whSmrtLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-smrt-pagination" id="whSmrtPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-smrt-pagination__meta" id="whSmrtPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSmrtNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wh-modal" id="whSmrtDetailModal" aria-hidden="true" role="dialog" aria-labelledby="whSmrtDetailTitle">
    <div class="wh-modal__backdrop" data-close-modal></div>
    <div class="wh-modal__panel wh-smrt-modal">
        <header class="wh-modal__head">
            <div>
                <h3 id="whSmrtDetailTitle"><?php echo __t('wh_ledger_details', 'warehouse'); ?></h3>
                <p class="wh-modal__sub" id="whSmrtDetailSubtitle">—</p>
            </div>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whSmrtDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="wh-modal__body" id="whSmrtDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

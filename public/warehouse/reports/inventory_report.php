<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'inventory_report';
$pageTitle = __t('wh_nav_rpt_inventory', 'warehouse');
$dateToDefault = date('Y-m-d');
$dateFromDefault = date('Y-m-d', strtotime('-30 days'));
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-inventory-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_irpt_subtitle', 'wh_irpt_hint', 'wh_irpt_tab_overview', 'wh_irpt_tab_inventory', 'wh_irpt_tab_movements',
        'wh_irpt_tab_low_stock', 'wh_irpt_tab_out_of_stock', 'wh_irpt_tab_expiry', 'wh_irpt_tab_damaged',
        'wh_irpt_tab_valuation', 'wh_irpt_tab_performance', 'wh_irpt_filters', 'wh_irpt_apply', 'wh_irpt_reset',
        'wh_irpt_search', 'wh_irpt_empty', 'wh_irpt_kpi_products', 'wh_irpt_kpi_skus', 'wh_irpt_kpi_qty',
        'wh_irpt_kpi_available', 'wh_irpt_kpi_reserved', 'wh_irpt_kpi_damaged', 'wh_irpt_kpi_expired',
        'wh_irpt_kpi_value', 'wh_irpt_kpi_avg_cost', 'wh_irpt_kpi_avg_price', 'wh_irpt_kpi_potential',
        'wh_irpt_kpi_capacity', 'wh_irpt_kpi_low', 'wh_irpt_kpi_out', 'wh_irpt_kpi_today_mov',
        'wh_irpt_col_image', 'wh_irpt_col_location', 'wh_irpt_col_min', 'wh_irpt_col_max', 'wh_irpt_col_selling',
        'wh_irpt_col_updated', 'wh_irpt_col_prev_stock', 'wh_irpt_col_reorder', 'wh_irpt_col_days_oos',
        'wh_irpt_col_damage_type', 'wh_irpt_col_reported_by', 'wh_irpt_col_value_at_risk',
        'wh_irpt_val_method', 'wh_irpt_val_fifo', 'wh_irpt_val_weighted', 'wh_irpt_val_lifo',
        'wh_irpt_val_cost', 'wh_irpt_val_selling', 'wh_irpt_val_profit', 'wh_irpt_val_turnover',
        'wh_irpt_perf_accuracy', 'wh_irpt_perf_receiving', 'wh_irpt_perf_dispatch', 'wh_irpt_perf_transfer',
        'wh_irpt_perf_age', 'wh_irpt_perf_utilization', 'wh_irpt_chart_value_trend', 'wh_irpt_chart_movement_trend',
        'wh_irpt_chart_category', 'wh_irpt_chart_warehouse', 'wh_irpt_chart_status', 'wh_irpt_chart_top_moving',
        'wh_irpt_chart_low_stock', 'wh_irpt_chart_aging', 'wh_irpt_export_excel', 'wh_irpt_export_pdf',
        'wh_irpt_print', 'wh_irpt_schedule', 'wh_irpt_schedule_hint', 'wh_irpt_schedule_daily',
        'wh_irpt_schedule_weekly', 'wh_irpt_schedule_monthly', 'wh_irpt_schedule_quarterly', 'wh_irpt_schedule_yearly',
        'wh_irpt_audit_title', 'wh_irpt_audit_generated', 'wh_irpt_audit_filters', 'wh_irpt_alerts',
        'wh_irpt_alert_low', 'wh_irpt_alert_out', 'wh_irpt_alert_expired', 'wh_irpt_offline_cached',
        'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date',
        'col_status', 'save', 'cancel',
    ]),
    wms_i18n([
        'wms_col_product', 'wms_col_qty', 'wms_col_value', 'wms_col_batch', 'wms_col_expiry', 'wms_col_barcode',
        'wms_col_reference', 'wms_col_user', 'wms_col_movement_type', 'wms_col_warehouse', 'wms_col_reorder',
        'wms_date_from', 'wms_date_to', 'wms_filter_all_types', 'wms_mov_purchase', 'wms_mov_sale',
        'wms_mov_transfer_in', 'wms_mov_transfer_out', 'wms_mov_return_in', 'wms_mov_return_out',
        'wms_mov_adjustment', 'wms_mov_damaged', 'wms_mov_expired', 'wms_mov_lost', 'wms_mov_manual',
        'wms_mov_dispatch_out', 'wms_mov_receipt_in', 'wms_unit_cost', 'wms_nav_warehouses',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whIrptOfflineBadge" class="wh-irpt-offline-badge" hidden><?php echo __t('wh_irpt_offline_cached', 'warehouse'); ?></div>

<section class="wh-irpt-hero" aria-labelledby="whIrptHeroTitle">
    <div class="wh-irpt-hero__intro">
        <h2 class="wh-irpt-hero__title" id="whIrptHeroTitle"><?php echo __t('wh_irpt_subtitle', 'warehouse'); ?></h2>
        <p class="wh-irpt-hero__meta" id="whIrptHeroMeta" aria-live="polite">—</p>
        <p class="wh-irpt-hero__hint"><?php echo __t('wh_irpt_hint', 'warehouse'); ?></p>
    </div>
</section>

<div class="wh-irpt-alerts" id="whIrptAlerts" hidden aria-live="polite">
    <span class="material-icons-round">notifications_active</span>
    <div class="wh-irpt-alerts__body" id="whIrptAlertsBody"></div>
</div>

<div class="wh-irpt-kpi-grid" id="whIrptKpiGrid" role="group" aria-label="KPIs">
    <?php
    $kpis = [
        ['id' => 'whIrptKpiProducts', 'label' => 'wh_irpt_kpi_products', 'mod' => 'primary'],
        ['id' => 'whIrptKpiSkus', 'label' => 'wh_irpt_kpi_skus', 'mod' => ''],
        ['id' => 'whIrptKpiQty', 'label' => 'wh_irpt_kpi_qty', 'mod' => ''],
        ['id' => 'whIrptKpiAvailable', 'label' => 'wh_irpt_kpi_available', 'mod' => 'success'],
        ['id' => 'whIrptKpiReserved', 'label' => 'wh_irpt_kpi_reserved', 'mod' => ''],
        ['id' => 'whIrptKpiDamaged', 'label' => 'wh_irpt_kpi_damaged', 'mod' => 'warn'],
        ['id' => 'whIrptKpiExpired', 'label' => 'wh_irpt_kpi_expired', 'mod' => 'danger'],
        ['id' => 'whIrptKpiValue', 'label' => 'wh_irpt_kpi_value', 'mod' => 'primary'],
        ['id' => 'whIrptKpiAvgCost', 'label' => 'wh_irpt_kpi_avg_cost', 'mod' => ''],
        ['id' => 'whIrptKpiAvgPrice', 'label' => 'wh_irpt_kpi_avg_price', 'mod' => ''],
        ['id' => 'whIrptKpiPotential', 'label' => 'wh_irpt_kpi_potential', 'mod' => 'success'],
        ['id' => 'whIrptKpiCapacity', 'label' => 'wh_irpt_kpi_capacity', 'mod' => ''],
        ['id' => 'whIrptKpiLow', 'label' => 'wh_irpt_kpi_low', 'mod' => 'warn'],
        ['id' => 'whIrptKpiOut', 'label' => 'wh_irpt_kpi_out', 'mod' => 'danger'],
        ['id' => 'whIrptKpiTodayMov', 'label' => 'wh_irpt_kpi_today_mov', 'mod' => ''],
    ];
    foreach ($kpis as $kpi):
        $mod = $kpi['mod'] ? ' wh-irpt-kpi--' . $kpi['mod'] : '';
    ?>
    <article class="wh-irpt-kpi<?php echo $mod; ?>">
        <span class="wh-irpt-kpi__label"><?php echo __t($kpi['label'], 'warehouse'); ?></span>
        <strong class="wh-irpt-kpi__value is-loading" id="<?php echo $kpi['id']; ?>">—</strong>
    </article>
    <?php endforeach; ?>
</div>

<div class="wh-irpt-layout">
    <aside class="wh-irpt-filters" id="whIrptFilters" aria-label="<?php echo htmlspecialchars(__t('wh_irpt_filters', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
        <div class="wh-irpt-filters__head">
            <h3><?php echo __t('wh_irpt_filters', 'warehouse'); ?></h3>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" id="whIrptFiltersClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="wh-irpt-filters__body">
            <label class="wh-irpt-field">
                <span><?php echo __t('wh_select_warehouse', 'warehouse'); ?></span>
                <select id="whIrptWarehouse" class="wh-select"><option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option></select>
            </label>
            <label class="wh-irpt-field">
                <span>Store</span>
                <select id="whIrptStore" class="wh-select"><option value="">—</option></select>
            </label>
            <label class="wh-irpt-field">
                <span>Category</span>
                <select id="whIrptCategory" class="wh-select"><option value="">—</option></select>
            </label>
            <label class="wh-irpt-field">
                <span>Supplier</span>
                <select id="whIrptSupplier" class="wh-select"><option value="">—</option></select>
            </label>
            <label class="wh-irpt-field">
                <span><?php echo __t('col_status', 'warehouse'); ?></span>
                <select id="whIrptStockStatus" class="wh-select">
                    <option value="">—</option>
                    <option value="ok">OK</option>
                    <option value="low">Low</option>
                    <option value="out">Out</option>
                    <option value="alert">Alert</option>
                </select>
            </label>
            <label class="wh-irpt-field">
                <span><?php echo __t('wms_col_movement_type', 'wms'); ?></span>
                <select id="whIrptMovementType" class="wh-select">
                    <option value=""><?php echo __t('wms_filter_all_types', 'wms'); ?></option>
                    <option value="receipt_in"><?php echo __t('wms_mov_receipt_in', 'wms'); ?></option>
                    <option value="purchase"><?php echo __t('wms_mov_purchase', 'wms'); ?></option>
                    <option value="transfer_in"><?php echo __t('wms_mov_transfer_in', 'wms'); ?></option>
                    <option value="transfer_out"><?php echo __t('wms_mov_transfer_out', 'wms'); ?></option>
                    <option value="sale"><?php echo __t('wms_mov_sale', 'wms'); ?></option>
                    <option value="return_in"><?php echo __t('wms_mov_return_in', 'wms'); ?></option>
                    <option value="return_out"><?php echo __t('wms_mov_return_out', 'wms'); ?></option>
                    <option value="adjustment"><?php echo __t('wms_mov_adjustment', 'wms'); ?></option>
                    <option value="damaged"><?php echo __t('wms_mov_damaged', 'wms'); ?></option>
                    <option value="expired"><?php echo __t('wms_mov_expired', 'wms'); ?></option>
                    <option value="lost"><?php echo __t('wms_mov_lost', 'wms'); ?></option>
                    <option value="manual"><?php echo __t('wms_mov_manual', 'wms'); ?></option>
                    <option value="dispatch_out"><?php echo __t('wms_mov_dispatch_out', 'wms'); ?></option>
                </select>
            </label>
            <label class="wh-irpt-field">
                <span><?php echo __t('wms_date_from', 'wms'); ?></span>
                <input type="date" id="whIrptDateFrom" class="wh-input" value="<?php echo htmlspecialchars($dateFromDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-irpt-field">
                <span><?php echo __t('wms_date_to', 'wms'); ?></span>
                <input type="date" id="whIrptDateTo" class="wh-input" value="<?php echo htmlspecialchars($dateToDefault, ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="wh-irpt-field">
                <span>Zone</span>
                <input type="text" id="whIrptZone" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span>Aisle</span>
                <input type="text" id="whIrptAisle" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span>Rack</span>
                <input type="text" id="whIrptRack" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span>Shelf</span>
                <input type="text" id="whIrptShelf" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span>Bin</span>
                <input type="text" id="whIrptBin" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span><?php echo __t('wms_col_batch', 'wms'); ?></span>
                <input type="text" id="whIrptBatch" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field">
                <span>Serial</span>
                <input type="text" id="whIrptSerial" class="wh-input" autocomplete="off">
            </label>
            <label class="wh-irpt-field" id="whIrptExpiryDaysWrap" hidden>
                <span>Expiry window (days)</span>
                <select id="whIrptExpiryDays" class="wh-select">
                    <option value="7">7</option>
                    <option value="30">30</option>
                    <option value="60">60</option>
                    <option value="90" selected>90</option>
                </select>
            </label>
            <label class="wh-irpt-field" id="whIrptValMethodWrap" hidden>
                <span><?php echo __t('wh_irpt_val_method', 'warehouse'); ?></span>
                <select id="whIrptValMethod" class="wh-select">
                    <option value="weighted"><?php echo __t('wh_irpt_val_weighted', 'warehouse'); ?></option>
                    <option value="fifo"><?php echo __t('wh_irpt_val_fifo', 'warehouse'); ?></option>
                    <option value="lifo"><?php echo __t('wh_irpt_val_lifo', 'warehouse'); ?></option>
                </select>
            </label>
        </div>
        <div class="wh-irpt-filters__foot">
            <button type="button" class="wh-btn wh-btn--primary" id="whIrptApplyBtn"><?php echo __t('wh_irpt_apply', 'warehouse'); ?></button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIrptResetBtn"><?php echo __t('wh_irpt_reset', 'warehouse'); ?></button>
        </div>
    </aside>

    <div class="wh-irpt-main">
        <nav class="wh-irpt-tabs" id="whIrptTabs" role="tablist">
            <?php
            $tabs = [
                'overview' => 'wh_irpt_tab_overview',
                'inventory' => 'wh_irpt_tab_inventory',
                'movements' => 'wh_irpt_tab_movements',
                'low_stock' => 'wh_irpt_tab_low_stock',
                'out_of_stock' => 'wh_irpt_tab_out_of_stock',
                'expiry' => 'wh_irpt_tab_expiry',
                'damaged' => 'wh_irpt_tab_damaged',
                'valuation' => 'wh_irpt_tab_valuation',
                'performance' => 'wh_irpt_tab_performance',
            ];
            $first = true;
            foreach ($tabs as $tabId => $tabLabel):
            ?>
            <button type="button" class="wh-irpt-tab<?php echo $first ? ' is-active' : ''; ?>" role="tab" data-tab="<?php echo $tabId; ?>" aria-selected="<?php echo $first ? 'true' : 'false'; ?>">
                <?php echo __t($tabLabel, 'warehouse'); ?>
            </button>
            <?php $first = false; endforeach; ?>
        </nav>

        <div class="wh-irpt-toolbar">
            <div class="wh-irpt-toolbar__filters">
                <button type="button" class="wh-btn wh-btn--ghost" id="whIrptFiltersToggle">
                    <span class="material-icons-round">tune</span>
                    <span class="wh-btn-label"><?php echo __t('wh_irpt_filters', 'warehouse'); ?></span>
                </button>
                <label class="wh-irpt-search-wrap">
                    <span class="material-icons-round" aria-hidden="true">search</span>
                    <input type="search" id="whIrptSearch" class="wh-irpt-search" placeholder="<?php echo htmlspecialchars(__t('wh_irpt_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </label>
            </div>
            <div class="wh-irpt-toolbar__actions">
                <button type="button" class="wh-btn wh-btn--ghost" id="whIrptExportCsv">
                    <span class="material-icons-round">download</span>
                    <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
                </button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whIrptExportExcel">
                    <span class="material-icons-round">table_view</span>
                    <span class="wh-btn-label"><?php echo __t('wh_irpt_export_excel', 'warehouse'); ?></span>
                </button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whIrptExportPdf">
                    <span class="material-icons-round">picture_as_pdf</span>
                    <span class="wh-btn-label"><?php echo __t('wh_irpt_export_pdf', 'warehouse'); ?></span>
                </button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whIrptPrintBtn">
                    <span class="material-icons-round">print</span>
                    <span class="wh-btn-label"><?php echo __t('wh_irpt_print', 'warehouse'); ?></span>
                </button>
                <?php if (!$whReadOnly): ?>
                <button type="button" class="wh-btn" id="whIrptScheduleBtn">
                    <span class="material-icons-round">schedule</span>
                    <span class="wh-btn-label"><?php echo __t('wh_irpt_schedule', 'warehouse'); ?></span>
                </button>
                <?php endif; ?>
                <button type="button" class="wh-btn" id="whIrptRefreshBtn">
                    <span class="material-icons-round">refresh</span>
                    <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
                </button>
            </div>
        </div>

        <section class="wh-irpt-charts" id="whIrptCharts" aria-label="Charts">
            <div class="wh-irpt-charts__grid">
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_value_trend', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartValue"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_movement_trend', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartMovement"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_category', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartCategory"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_warehouse', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartWarehouse"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_status', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartStatus"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_top_moving', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartTopMoving"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_low_stock', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartLowStock"></canvas></div></article>
                <article class="wh-irpt-chart-card"><h4><?php echo __t('wh_irpt_chart_aging', 'warehouse'); ?></h4><div class="wh-irpt-chart-wrap"><canvas id="whIrptChartAging"></canvas></div></article>
            </div>
        </section>

        <section class="wh-irpt-valuation" id="whIrptValuation" hidden>
            <div class="wh-irpt-valuation__grid">
                <article class="wh-irpt-val-card"><span><?php echo __t('wh_irpt_val_cost', 'warehouse'); ?></span><strong id="whIrptValCost">—</strong></article>
                <article class="wh-irpt-val-card"><span><?php echo __t('wh_irpt_val_selling', 'warehouse'); ?></span><strong id="whIrptValSelling">—</strong></article>
                <article class="wh-irpt-val-card"><span><?php echo __t('wh_irpt_val_profit', 'warehouse'); ?></span><strong id="whIrptValProfit">—</strong></article>
                <article class="wh-irpt-val-card"><span><?php echo __t('wh_irpt_val_turnover', 'warehouse'); ?></span><strong id="whIrptValTurnover">—</strong></article>
            </div>
        </section>

        <section class="wh-irpt-performance" id="whIrptPerformance" hidden>
            <div class="wh-irpt-performance__grid">
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_accuracy', 'warehouse'); ?></span><strong id="whIrptPerfAccuracy">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_receiving', 'warehouse'); ?></span><strong id="whIrptPerfReceiving">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_dispatch', 'warehouse'); ?></span><strong id="whIrptPerfDispatch">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_transfer', 'warehouse'); ?></span><strong id="whIrptPerfTransfer">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_age', 'warehouse'); ?></span><strong id="whIrptPerfAge">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_perf_utilization', 'warehouse'); ?></span><strong id="whIrptPerfUtil">—</strong></article>
                <article class="wh-irpt-perf-card"><span><?php echo __t('wh_irpt_val_turnover', 'warehouse'); ?></span><strong id="whIrptPerfTurnover">—</strong></article>
            </div>
        </section>

        <section class="wh-irpt-panel" aria-live="polite">
            <div class="wh-irpt-table-wrap" id="whIrptTableWrap"></div>
            <div class="wh-irpt-empty" id="whIrptEmpty" hidden>
                <span class="material-icons-round">inventory_2</span>
                <p><?php echo __t('wh_irpt_empty', 'warehouse'); ?></p>
            </div>
            <div class="wh-loading" id="whIrptLoading"><?php echo __t('loading', 'warehouse'); ?></div>
        </section>

        <nav class="wh-irpt-pagination" id="whIrptPagination" aria-label="Pagination" hidden>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIrptPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
            <span class="wh-irpt-pagination__meta" id="whIrptPageMeta">—</span>
            <button type="button" class="wh-btn wh-btn--ghost" id="whIrptNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
        </nav>

        <footer class="wh-irpt-audit" id="whIrptAudit">
            <h4><?php echo __t('wh_irpt_audit_title', 'warehouse'); ?></h4>
            <dl class="wh-irpt-audit__grid">
                <div><dt><?php echo __t('wh_irpt_audit_generated', 'warehouse'); ?></dt><dd id="whIrptAuditUser">—</dd></div>
                <div><dt><?php echo __t('col_date', 'warehouse'); ?></dt><dd id="whIrptAuditDate">—</dd></div>
                <div class="wh-irpt-audit__filters"><dt><?php echo __t('wh_irpt_audit_filters', 'warehouse'); ?></dt><dd id="whIrptAuditFilters">—</dd></div>
            </dl>
        </footer>
    </div>
</div>

<div class="wh-form-modal wh-form-modal--irpt" id="whIrptScheduleModal" hidden role="dialog" aria-modal="true" aria-labelledby="whIrptScheduleTitle">
    <div class="wh-form-modal__backdrop" data-close="whIrptScheduleModal"></div>
    <div class="wh-form-modal__panel">
        <header class="wh-form-modal__head">
            <h3 id="whIrptScheduleTitle"><?php echo __t('wh_irpt_schedule', 'warehouse'); ?></h3>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--icon" data-close="whIrptScheduleModal"><span class="material-icons-round">close</span></button>
        </header>
        <p class="wh-form-modal__hint"><?php echo __t('wh_irpt_schedule_hint', 'warehouse'); ?></p>
        <form id="whIrptScheduleForm" class="wh-form-modal__body">
            <label class="wh-irpt-field">
                <span>Frequency</span>
                <select id="whIrptScheduleFreq" class="wh-select" required>
                    <option value="daily"><?php echo __t('wh_irpt_schedule_daily', 'warehouse'); ?></option>
                    <option value="weekly"><?php echo __t('wh_irpt_schedule_weekly', 'warehouse'); ?></option>
                    <option value="monthly"><?php echo __t('wh_irpt_schedule_monthly', 'warehouse'); ?></option>
                    <option value="quarterly"><?php echo __t('wh_irpt_schedule_quarterly', 'warehouse'); ?></option>
                    <option value="yearly"><?php echo __t('wh_irpt_schedule_yearly', 'warehouse'); ?></option>
                </select>
            </label>
            <label class="wh-irpt-field">
                <span>Email</span>
                <input type="email" id="whIrptScheduleEmail" class="wh-input" placeholder="user@example.com">
            </label>
        </form>
        <footer class="wh-form-modal__foot">
            <button type="button" class="wh-btn wh-btn--ghost" data-close="whIrptScheduleModal"><?php echo __t('cancel', 'warehouse'); ?></button>
            <button type="submit" form="whIrptScheduleForm" class="wh-btn wh-btn--primary"><?php echo __t('save', 'warehouse'); ?></button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

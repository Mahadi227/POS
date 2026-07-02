<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('reports');

$useWmsModules = true;
$loadChart = true;
$activeWhPage = 'expiry_report';
$pageTitle = __t('wh_nav_rpt_expiry', 'warehouse');
$extraAdminScripts = ['wms-report-export.js'];
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-expiry-report.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_expr_subtitle', 'wh_expr_hint', 'wh_expr_stat_soon', 'wh_expr_stat_past', 'wh_expr_stat_units',
        'wh_expr_stat_value', 'wh_expr_stat_batches', 'wh_expr_stat_days', 'wh_expr_search', 'wh_expr_empty',
        'wh_expr_chart_trend', 'wh_expr_chart_warehouse', 'wh_expr_chart_urgency', 'wh_expr_status_breakdown',
        'wh_expr_export_excel', 'wh_expr_export_pdf', 'wh_expr_print', 'wh_expr_offline_cached',
        'wh_expr_link_inventory', 'wh_expr_link_expiry', 'wh_expr_table_title',
        'wh_expr_urgency_expired', 'wh_expr_urgency_critical', 'wh_expr_urgency_warning', 'wh_expr_urgency_upcoming',
        'wh_nav_rpt_expiry', 'wh_all_warehouses', 'wh_select_warehouse', 'wh_migration_hint',
        'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records',
        'col_status',
    ]),
    wms_i18n([
        'wms_nav_warehouses', 'wms_col_batch', 'wms_col_product', 'wms_col_qty', 'wms_col_value',
        'wms_col_expiry', 'wms_days_to_expiry', 'wms_days_short',
        'wms_expiry_period', 'wms_period_7d', 'wms_period_14d', 'wms_period_30d', 'wms_period_60d', 'wms_period_90d',
        'wms_filter_at_risk', 'wms_filter_expiring_only', 'wms_filter_expired_only',
        'wms_status_active', 'wms_status_expired', 'wms_status_recalled', 'wms_status_depleted',
        'wms_urgency_expired', 'wms_urgency_critical', 'wms_urgency_warning',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<div id="whErrorBanner" class="ad-error-banner" hidden></div>
<div id="whMigrationHint" class="wh-migration-hint" hidden></div>
<div id="whExprOfflineBadge" class="wh-expr-offline-badge" hidden><?php echo __t('wh_expr_offline_cached', 'warehouse'); ?></div>

<section class="wh-expr-hero" aria-labelledby="whExprHeroTitle">
    <div class="wh-expr-hero__intro">
        <h2 class="wh-expr-hero__title" id="whExprHeroTitle"><?php echo __t('wh_expr_subtitle', 'warehouse'); ?></h2>
        <p class="wh-expr-hero__meta" id="whExprHeroMeta" aria-live="polite">—</p>
        <p class="wh-expr-hero__hint"><?php echo __t('wh_expr_hint', 'warehouse'); ?></p>
        <div class="wh-expr-hero__links">
            <a class="wh-expr-hero__link" href="inventory_report.php"><?php echo __t('wh_expr_link_inventory', 'warehouse'); ?></a>
            <a class="wh-expr-hero__link" href="../batch/expiry_management.php"><?php echo __t('wh_expr_link_expiry', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-expr-hero__stats" role="group">
        <article class="wh-expr-stat wh-expr-stat--warn">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_soon', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatSoon">—</strong>
        </article>
        <article class="wh-expr-stat wh-expr-stat--danger">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_past', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatPast">—</strong>
        </article>
        <article class="wh-expr-stat">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_units', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatUnits">—</strong>
        </article>
        <article class="wh-expr-stat wh-expr-stat--primary">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_value', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatValue">—</strong>
        </article>
        <article class="wh-expr-stat">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_batches', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatBatches">—</strong>
        </article>
        <article class="wh-expr-stat">
            <span class="wh-expr-stat__label"><?php echo __t('wh_expr_stat_days', 'warehouse'); ?></span>
            <strong class="wh-expr-stat__value is-loading" id="whExprStatDays">—</strong>
        </article>
    </div>
</section>

<section class="wh-expr-charts" aria-label="Charts">
    <div class="wh-expr-charts__grid">
        <article class="wh-expr-chart-card">
            <h4><?php echo __t('wh_expr_chart_trend', 'warehouse'); ?></h4>
            <div class="wh-expr-chart-wrap"><canvas id="whExprChartTrend"></canvas></div>
        </article>
        <article class="wh-expr-chart-card">
            <h4><?php echo __t('wh_expr_chart_warehouse', 'warehouse'); ?></h4>
            <div class="wh-expr-chart-wrap"><canvas id="whExprChartWarehouse"></canvas></div>
        </article>
        <article class="wh-expr-chart-card">
            <h4><?php echo __t('wh_expr_chart_urgency', 'warehouse'); ?></h4>
            <div class="wh-expr-chart-wrap"><canvas id="whExprChartUrgency"></canvas></div>
        </article>
    </div>
</section>

<section class="wh-expr-breakdown" id="whExprBreakdownPanel" hidden aria-labelledby="whExprBreakdownTitle">
    <div class="wh-expr-breakdown__head">
        <h3 id="whExprBreakdownTitle"><?php echo __t('wh_expr_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-expr-status-chips" id="whExprStatusChips"></div>
</section>

<div class="wh-expr-toolbar">
    <div class="wh-expr-toolbar__row">
        <div class="wh-expr-toolbar__filters">
            <select id="whExprWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whExprPeriod" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_expiry_period', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="7"><?php echo __t('wms_period_7d', 'wms'); ?></option>
                <option value="14"><?php echo __t('wms_period_14d', 'wms'); ?></option>
                <option value="30" selected><?php echo __t('wms_period_30d', 'wms'); ?></option>
                <option value="60"><?php echo __t('wms_period_60d', 'wms'); ?></option>
                <option value="90"><?php echo __t('wms_period_90d', 'wms'); ?></option>
            </select>
            <select id="whExprStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="at_risk"><?php echo __t('wms_filter_at_risk', 'wms'); ?></option>
                <option value="expiring_soon"><?php echo __t('wms_filter_expiring_only', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_filter_expired_only', 'wms'); ?></option>
            </select>
            <label class="wh-expr-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whExprSearch" class="wh-expr-search" placeholder="<?php echo htmlspecialchars(__t('wh_expr_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
        </div>
        <div class="wh-expr-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whExprExportCsv">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whExprExportExcel">
                <span class="material-icons-round">table_view</span>
                <span class="wh-btn-label"><?php echo __t('wh_expr_export_excel', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whExprExportPdf">
                <span class="material-icons-round">picture_as_pdf</span>
                <span class="wh-btn-label"><?php echo __t('wh_expr_export_pdf', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost" id="whExprPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="wh-btn-label"><?php echo __t('wh_expr_print', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whExprRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-expr-panel" aria-live="polite">
    <header class="wh-expr-panel__head">
        <h3><?php echo __t('wh_expr_table_title', 'warehouse'); ?></h3>
    </header>
    <div class="wh-expr-table-wrap" id="whExprTableWrap"></div>
    <div class="wh-expr-empty" id="whExprEmpty" hidden>
        <span class="material-icons-round">event_busy</span>
        <p><?php echo __t('wh_expr_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whExprLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-expr-pagination" id="whExprPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whExprPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-expr-pagination__meta" id="whExprPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whExprNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php require __DIR__ . '/../includes/layout-end.php';

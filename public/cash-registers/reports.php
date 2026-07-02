<?php
/**
 * Register session reports — closed session history with variance tracking.
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'reports';
$pageTitle = __t('cr_reports_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-report-export.js', 'cash-registers-reports.js'];
$pageI18n = cr_i18n([
    'cr_rpt_subtitle', 'cr_rpt_stat_sessions', 'cr_rpt_stat_sales', 'cr_rpt_stat_variance', 'cr_rpt_stat_today',
    'cr_rpt_search_placeholder', 'cr_rpt_table_summary',
    'cr_export_csv', 'cr_export_pdf', 'cr_export_print', 'cr_opening_balance', 'cr_counted_cash',
    'cr_col_expected', 'cr_col_difference', 'cr_col_register', 'cr_col_cashier', 'cr_branch', 'col_date',
    'cr_no_data', 'loading', 'refresh', 'clear_search', 'start_date', 'end_date', 'prev_page', 'next_page',
    'load_error', 'exporting_pdf', 'pdf_fallback_print', 'doc_page', 'last_updated', 'status_open', 'status_closed',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-data-hero" aria-labelledby="crRptHeroTitle">
    <div class="cr-data-hero__intro">
        <h2 class="cr-data-hero__title" id="crRptHeroTitle"><?php echo __t('cr_rpt_subtitle', 'admin'); ?></h2>
        <p class="cr-data-hero__count" id="crRptCount" aria-live="polite">—</p>
    </div>
    <div class="cr-data-hero__stats" id="crRptStats">
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_rpt_stat_sessions', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crRptStatSessions">—</strong>
        </div>
        <div class="cr-data-stat cr-data-stat--primary">
            <span class="cr-data-stat__label"><?php echo __t('cr_rpt_stat_sales', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crRptStatSales">—</strong>
        </div>
        <div class="cr-data-stat cr-data-stat--warn">
            <span class="cr-data-stat__label"><?php echo __t('cr_rpt_stat_variance', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crRptStatVariance">—</strong>
        </div>
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_rpt_stat_today', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crRptStatToday">—</strong>
        </div>
    </div>
</section>

<div class="cr-data-toolbar">
    <div class="cr-data-toolbar__top">
        <div class="cr-data-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="crRptSearch" placeholder="<?php echo htmlspecialchars(__t('cr_rpt_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="cr-data-search-clear" id="crRptSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="cr-data-toolbar__dates">
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crRptDateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crRptDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="cr-data-toolbar__actions">
            <button type="button" class="cr-btn cr-btn--ghost" id="crRptExportCsvBtn">
                <span class="material-icons-round">download</span>
                <?php echo __t('cr_export_csv', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn cr-btn--ghost" id="crRptExportPdfBtn">
                <span class="material-icons-round">picture_as_pdf</span>
                <?php echo __t('cr_export_pdf', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn cr-btn--ghost" id="crRptPrintBtn">
                <span class="material-icons-round">print</span>
                <?php echo __t('cr_export_print', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn" id="crRptRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <?php echo __t('refresh', 'admin'); ?>
            </button>
        </div>
    </div>
</div>

<div class="cr-data-panel">
    <div class="cr-data-panel__head">
        <span class="cr-data-panel__meta" id="crRptMeta"><?php echo __t('loading', 'admin'); ?></span>
        <div class="cr-data-pagination">
            <button type="button" class="cr-data-page-btn" id="crRptPrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>"><span class="material-icons-round">chevron_left</span></button>
            <span id="crRptPageInfo">1 / 1</span>
            <button type="button" class="cr-data-page-btn" id="crRptNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>"><span class="material-icons-round">chevron_right</span></button>
        </div>
    </div>
    <div id="crRptRoot"></div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

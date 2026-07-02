<?php
/**
 * Cash movements — ledger of register cash in/out events.
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'movements';
$pageTitle = __t('cr_movements_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-movements.js'];
$pageI18n = cr_i18n([
    'cr_mv_subtitle', 'cr_mv_stat_total', 'cr_mv_stat_volume', 'cr_mv_stat_sales', 'cr_mv_stat_today',
    'cr_mv_search_placeholder', 'cr_mv_filter_type', 'cr_mv_table_summary',
    'cr_mv_type_all', 'cr_mv_type_opening_cash', 'cr_mv_type_sale', 'cr_mv_type_refund',
    'cr_mv_type_closing_cash', 'cr_mv_type_transfer_out', 'cr_mv_type_adjustment',
    'cr_col_register', 'cr_col_cashier', 'cr_col_action', 'cr_amount', 'col_date',
    'cr_no_data', 'cr_export_csv', 'loading', 'refresh', 'clear_search', 'start_date', 'end_date',
    'prev_page', 'next_page', 'load_error', 'cr_view_details',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-data-hero" aria-labelledby="crMvHeroTitle">
    <div class="cr-data-hero__intro">
        <h2 class="cr-data-hero__title" id="crMvHeroTitle"><?php echo __t('cr_mv_subtitle', 'admin'); ?></h2>
        <p class="cr-data-hero__count" id="crMvCount" aria-live="polite">—</p>
    </div>
    <div class="cr-data-hero__stats" id="crMvStats">
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_mv_stat_total', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crMvStatTotal">—</strong>
        </div>
        <div class="cr-data-stat cr-data-stat--primary">
            <span class="cr-data-stat__label"><?php echo __t('cr_mv_stat_volume', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crMvStatVolume">—</strong>
        </div>
        <div class="cr-data-stat cr-data-stat--success">
            <span class="cr-data-stat__label"><?php echo __t('cr_mv_stat_sales', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crMvStatSales">—</strong>
        </div>
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_mv_stat_today', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crMvStatToday">—</strong>
        </div>
    </div>
</section>

<div class="cr-data-toolbar">
    <div class="cr-data-toolbar__top">
        <div class="cr-data-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="crMvSearch" placeholder="<?php echo htmlspecialchars(__t('cr_mv_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="cr-data-search-clear" id="crMvSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="cr-data-toolbar__dates">
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crMvDateFrom" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crMvDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="cr-data-toolbar__actions">
            <button type="button" class="cr-btn cr-btn--ghost" id="crMvExportBtn">
                <span class="material-icons-round">download</span>
                <?php echo __t('cr_export_csv', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn" id="crMvRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <?php echo __t('refresh', 'admin'); ?>
            </button>
        </div>
    </div>
    <div class="cr-data-toolbar__filters" id="crMvTypeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('cr_mv_filter_type', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip is-active" data-type="all" role="tab"><?php echo __t('cr_mv_type_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-type="opening_cash" role="tab"><?php echo __t('cr_mv_type_opening_cash', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-type="sale" role="tab"><?php echo __t('cr_mv_type_sale', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-type="refund" role="tab"><?php echo __t('cr_mv_type_refund', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-type="closing_cash" role="tab"><?php echo __t('cr_mv_type_closing_cash', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-type="transfer_out" role="tab"><?php echo __t('cr_mv_type_transfer_out', 'admin'); ?></button>
    </div>
</div>

<div class="cr-data-panel">
    <div class="cr-data-panel__head">
        <span class="cr-data-panel__meta" id="crMvMeta"><?php echo __t('loading', 'admin'); ?></span>
        <div class="cr-data-pagination">
            <button type="button" class="cr-data-page-btn" id="crMvPrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>"><span class="material-icons-round">chevron_left</span></button>
            <span id="crMvPageInfo">1 / 1</span>
            <button type="button" class="cr-data-page-btn" id="crMvNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>"><span class="material-icons-round">chevron_right</span></button>
        </div>
    </div>
    <div id="crMvRoot"></div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

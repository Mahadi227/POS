<?php
/**
 * Shift management — register sessions by shift type (morning / afternoon / evening / night).
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'shifts';
$pageTitle = __t('cr_shifts_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-shifts.js'];
$pageI18n = cr_i18n([
    'cr_shift_subtitle', 'cr_shift_stat_open', 'cr_shift_stat_closed', 'cr_shift_stat_sales', 'cr_shift_stat_today',
    'cr_shift_search_placeholder', 'cr_shift_filter_shift', 'cr_shift_table_summary', 'cr_shift_col_shift',
    'cr_shift_detail_title', 'cr_shift_duration', 'cr_shift_opening', 'cr_shift_closing', 'cr_shift_variance',
    'cr_shift_morning', 'cr_shift_afternoon', 'cr_shift_evening', 'cr_shift_night', 'cr_shift_type_all',
    'cr_filter_all', 'cr_filter_session_open', 'cr_filter_session_closed',
    'cr_col_register', 'cr_col_cashier', 'cr_branch', 'cr_col_opened', 'cr_col_closed',
    'cr_stat_sales_today', 'col_status', 'col_date', 'cr_no_data', 'cr_export_csv', 'cr_view_details',
    'loading', 'refresh', 'clear_search', 'start_date', 'end_date', 'prev_page', 'next_page',
    'close', 'load_error', 'cr_opening_balance', 'cr_counted_cash', 'cr_col_expected', 'cr_col_difference',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-data-hero" aria-labelledby="crShiftHeroTitle">
    <div class="cr-data-hero__intro">
        <div class="cr-data-hero__title-row">
            <h2 class="cr-data-hero__title" id="crShiftHeroTitle"><?php echo __t('cr_shift_subtitle', 'admin'); ?></h2>
            <span class="cr-data-hero__badge" id="crShiftOpenBadge" hidden aria-live="polite">0</span>
        </div>
        <p class="cr-data-hero__count" id="crShiftCount" aria-live="polite">—</p>
    </div>
    <div class="cr-data-hero__stats" id="crShiftStats">
        <button type="button" class="cr-data-stat cr-data-stat--success cr-data-stat--click" data-stat-filter="open">
            <span class="cr-data-stat__label"><?php echo __t('cr_shift_stat_open', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crShiftStatOpen">—</strong>
        </button>
        <button type="button" class="cr-data-stat cr-data-stat--click" data-stat-filter="closed">
            <span class="cr-data-stat__label"><?php echo __t('cr_shift_stat_closed', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crShiftStatClosed">—</strong>
        </button>
        <div class="cr-data-stat cr-data-stat--primary">
            <span class="cr-data-stat__label"><?php echo __t('cr_shift_stat_sales', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crShiftStatSales">—</strong>
        </div>
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_shift_stat_today', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crShiftStatToday">—</strong>
        </div>
    </div>
</section>

<div class="cr-data-toolbar">
    <div class="cr-data-toolbar__top">
        <div class="cr-data-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="crShiftSearch" placeholder="<?php echo htmlspecialchars(__t('cr_shift_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="cr-data-search-clear" id="crShiftSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="cr-data-toolbar__dates">
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crShiftDateFrom" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crShiftDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="cr-data-toolbar__actions">
            <button type="button" class="cr-btn cr-btn--ghost" id="crShiftExportBtn">
                <span class="material-icons-round">download</span>
                <?php echo __t('cr_export_csv', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn" id="crShiftRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <?php echo __t('refresh', 'admin'); ?>
            </button>
        </div>
    </div>
    <div class="cr-data-toolbar__filters" id="crShiftStatusFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip is-active" data-status="all" role="tab"><?php echo __t('cr_filter_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-status="open" role="tab"><?php echo __t('cr_filter_session_open', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-status="closed" role="tab"><?php echo __t('cr_filter_session_closed', 'admin'); ?></button>
    </div>
    <div class="cr-data-toolbar__filters" id="crShiftTypeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('cr_shift_filter_shift', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip is-active" data-shift="all" role="tab"><?php echo __t('cr_shift_type_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-shift="morning" role="tab"><?php echo __t('cr_shift_morning', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-shift="afternoon" role="tab"><?php echo __t('cr_shift_afternoon', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-shift="evening" role="tab"><?php echo __t('cr_shift_evening', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-shift="night" role="tab"><?php echo __t('cr_shift_night', 'admin'); ?></button>
    </div>
</div>

<div class="cr-data-panel">
    <div class="cr-data-panel__head">
        <span class="cr-data-panel__meta" id="crShiftMeta"><?php echo __t('loading', 'admin'); ?></span>
        <div class="cr-data-pagination">
            <button type="button" class="cr-data-page-btn" id="crShiftPrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>"><span class="material-icons-round">chevron_left</span></button>
            <span id="crShiftPageInfo">1 / 1</span>
            <button type="button" class="cr-data-page-btn" id="crShiftNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>"><span class="material-icons-round">chevron_right</span></button>
        </div>
    </div>
    <div id="crShiftRoot"></div>
</div>

<div class="cr-modal" id="crShiftDetailModal" hidden role="dialog" aria-modal="true" aria-labelledby="crShiftDetailTitle">
    <div class="cr-modal__backdrop" data-close-shift-modal></div>
    <div class="cr-modal__dialog cr-modal__dialog--wide">
        <header class="cr-modal__head">
            <h3 id="crShiftDetailTitle"><?php echo __t('cr_shift_detail_title', 'admin'); ?></h3>
            <button type="button" class="cr-modal__close" data-close-shift-modal aria-label="<?php echo __t('close', 'admin'); ?>"><span class="material-icons-round">close</span></button>
        </header>
        <div class="cr-modal__body" id="crShiftDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

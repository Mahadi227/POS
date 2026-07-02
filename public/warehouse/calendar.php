<?php
require __DIR__ . '/includes/bootstrap.php';
$activeWhPage = 'calendar';
$pageTitle = __t('wh_nav_calendar', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-calendar.js'];
$pageI18n = wh_i18n([
    'wh_cal_subtitle', 'wh_cal_stat_total', 'wh_cal_stat_tasks', 'wh_cal_stat_receiving', 'wh_cal_stat_dispatch', 'wh_cal_stat_expiry',
    'wh_cal_today', 'wh_cal_prev', 'wh_cal_next', 'wh_cal_day_detail', 'wh_cal_no_events', 'wh_cal_legend',
    'wh_cal_filter_all', 'wh_cal_type_task', 'wh_cal_type_receiving', 'wh_cal_type_dispatch', 'wh_cal_type_transfer', 'wh_cal_type_expiry', 'wh_cal_type_count',
    'wh_cal_weekday_mon', 'wh_cal_weekday_tue', 'wh_cal_weekday_wed', 'wh_cal_weekday_thu', 'wh_cal_weekday_fri', 'wh_cal_weekday_sat', 'wh_cal_weekday_sun',
    'wh_cal_open_module', 'loading', 'load_error', 'refresh', 'no_data',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="wh-cal-hero" aria-labelledby="whCalHeroTitle">
    <div class="wh-cal-hero__intro">
        <h2 class="wh-cal-hero__title" id="whCalHeroTitle"><?php echo __t('wh_cal_subtitle', 'warehouse'); ?></h2>
        <p class="wh-cal-hero__meta" id="whCalHeroMeta" aria-live="polite">—</p>
    </div>
    <div class="wh-cal-hero__stats" role="group">
        <article class="wh-cal-stat wh-cal-stat--primary">
            <span class="wh-cal-stat__label"><?php echo __t('wh_cal_stat_total', 'warehouse'); ?></span>
            <strong class="wh-cal-stat__value is-loading" id="whCalStatTotal">—</strong>
        </article>
        <article class="wh-cal-stat">
            <span class="wh-cal-stat__label"><?php echo __t('wh_cal_stat_tasks', 'warehouse'); ?></span>
            <strong class="wh-cal-stat__value is-loading" id="whCalStatTasks">—</strong>
        </article>
        <article class="wh-cal-stat wh-cal-stat--success">
            <span class="wh-cal-stat__label"><?php echo __t('wh_cal_stat_receiving', 'warehouse'); ?></span>
            <strong class="wh-cal-stat__value is-loading" id="whCalStatReceiving">—</strong>
        </article>
        <article class="wh-cal-stat">
            <span class="wh-cal-stat__label"><?php echo __t('wh_cal_stat_dispatch', 'warehouse'); ?></span>
            <strong class="wh-cal-stat__value is-loading" id="whCalStatDispatch">—</strong>
        </article>
        <article class="wh-cal-stat wh-cal-stat--warn">
            <span class="wh-cal-stat__label"><?php echo __t('wh_cal_stat_expiry', 'warehouse'); ?></span>
            <strong class="wh-cal-stat__value is-loading" id="whCalStatExpiry">—</strong>
        </article>
    </div>
</section>

<div class="wh-cal-toolbar">
    <div class="wh-cal-toolbar__nav">
        <button type="button" class="wh-btn wh-btn--ghost" id="whCalPrev" aria-label="<?php echo htmlspecialchars(__t('wh_cal_prev', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="material-icons-round">chevron_left</span>
        </button>
        <strong class="wh-cal-toolbar__label" id="whCalLabel">—</strong>
        <button type="button" class="wh-btn wh-btn--ghost" id="whCalNext" aria-label="<?php echo htmlspecialchars(__t('wh_cal_next', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="material-icons-round">chevron_right</span>
        </button>
        <button type="button" class="wh-btn" id="whCalToday"><?php echo __t('wh_cal_today', 'warehouse'); ?></button>
    </div>
    <div class="wh-cal-filters" id="whCalFilters" role="group" aria-label="<?php echo htmlspecialchars(__t('wh_cal_filter_all', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="wh-cal-chip is-active" data-type=""><?php echo __t('wh_cal_filter_all', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="task"><?php echo __t('wh_cal_type_task', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="receiving"><?php echo __t('wh_cal_type_receiving', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="dispatch"><?php echo __t('wh_cal_type_dispatch', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="transfer"><?php echo __t('wh_cal_type_transfer', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="expiry"><?php echo __t('wh_cal_type_expiry', 'warehouse'); ?></button>
        <button type="button" class="wh-cal-chip" data-type="count"><?php echo __t('wh_cal_type_count', 'warehouse'); ?></button>
    </div>
</div>

<div class="wh-cal-layout">
    <section class="wh-cal-panel" aria-label="Calendar">
        <div class="wh-cal-weekdays" id="whCalWeekdays"></div>
        <div id="whCalGrid" class="wh-calendar-grid"></div>
        <div class="wh-loading" id="whCalLoading" hidden><?php echo __t('loading', 'warehouse'); ?></div>
    </section>
    <aside class="wh-cal-side" aria-labelledby="whCalSideTitle">
        <header class="wh-cal-side__head">
            <h3 id="whCalSideTitle"><?php echo __t('wh_cal_day_detail', 'warehouse'); ?></h3>
            <span class="wh-cal-side__date" id="whCalSelectedDate">—</span>
        </header>
        <ul class="wh-cal-event-list" id="whCalDayEvents"></ul>
        <p class="wh-cal-empty" id="whCalDayEmpty" hidden><?php echo __t('wh_cal_no_events', 'warehouse'); ?></p>
    </aside>
</div>

<div class="wh-cal-legend" aria-label="<?php echo htmlspecialchars(__t('wh_cal_legend', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--task"></i><?php echo __t('wh_cal_type_task', 'warehouse'); ?></span>
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--receiving"></i><?php echo __t('wh_cal_type_receiving', 'warehouse'); ?></span>
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--dispatch"></i><?php echo __t('wh_cal_type_dispatch', 'warehouse'); ?></span>
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--transfer"></i><?php echo __t('wh_cal_type_transfer', 'warehouse'); ?></span>
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--expiry"></i><?php echo __t('wh_cal_type_expiry', 'warehouse'); ?></span>
    <span class="wh-cal-legend__item"><i class="wh-cal-dot wh-cal-dot--count"></i><?php echo __t('wh_cal_type_count', 'warehouse'); ?></span>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

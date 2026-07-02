<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'audit';
$pageTitle = __t('nav_audit', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-audit-logs.js'];
$pageI18n = acc_i18n([
    'al_subtitle', 'al_stat_total', 'al_stat_journal', 'al_stat_expense', 'al_stat_treasury',
    'al_insight_events', 'al_insight_users', 'al_insight_actions', 'al_insight_top_action',
    'al_chart_actions', 'al_chart_entities', 'al_chart_trend', 'al_search_placeholder',
    'al_filter_category', 'al_filter_action', 'al_filter_entity', 'al_filter_all',
    'al_cat_journal', 'al_cat_expense', 'al_cat_treasury', 'al_cat_accounts',
    'al_col_time', 'al_col_action', 'al_col_entity', 'al_col_entity_id', 'al_col_user', 'al_col_ip',
    'al_view_details', 'al_detail_title', 'al_detail_payload', 'al_entity_other',
    'al_action_expense_created', 'al_action_expense_approved', 'al_action_expense_rejected',
    'al_action_journal_posted', 'al_action_auto_post_sale', 'al_action_account_created',
    'al_action_cash_transaction', 'al_action_cash_account_created', 'al_action_bank_account_created',
    'al_action_mobile_wallet_created', 'al_entity_expense', 'al_entity_journal_entry', 'al_entity_sale',
    'al_entity_account', 'al_entity_cash_transaction', 'al_entity_cash_account', 'al_entity_bank_account',
    'al_entity_mobile_account', 'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh',
    'loading', 'no_data', 'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page',
    'next_page', 'records', 'close',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-al-hero" aria-labelledby="accAlHeroTitle">
    <div class="acc-al-hero__intro">
        <h2 class="acc-al-hero__title" id="accAlHeroTitle"><?php echo __t('al_subtitle', 'accounting'); ?></h2>
        <p class="acc-al-hero__count" id="accAlCount" aria-live="polite">—</p>
    </div>
    <div class="acc-al-hero__stats" id="accAlStats" role="group">
        <button type="button" class="acc-al-stat acc-al-stat--primary acc-al-stat--click" data-stat-filter="all">
            <span class="acc-al-stat__label"><?php echo __t('al_stat_total', 'accounting'); ?></span>
            <strong class="acc-al-stat__value is-loading" id="accAlStatTotal">—</strong>
        </button>
        <button type="button" class="acc-al-stat acc-al-stat--click" data-stat-filter="journal">
            <span class="acc-al-stat__label"><?php echo __t('al_stat_journal', 'accounting'); ?></span>
            <strong class="acc-al-stat__value is-loading" id="accAlStatJournal">—</strong>
        </button>
        <button type="button" class="acc-al-stat acc-al-stat--warn acc-al-stat--click" data-stat-filter="expense">
            <span class="acc-al-stat__label"><?php echo __t('al_stat_expense', 'accounting'); ?></span>
            <strong class="acc-al-stat__value is-loading" id="accAlStatExpense">—</strong>
        </button>
        <button type="button" class="acc-al-stat acc-al-stat--success acc-al-stat--click" data-stat-filter="treasury">
            <span class="acc-al-stat__label"><?php echo __t('al_stat_treasury', 'accounting'); ?></span>
            <strong class="acc-al-stat__value is-loading" id="accAlStatTreasury">—</strong>
        </button>
    </div>
</section>

<div class="acc-al-insights" id="accAlInsights">
    <article class="acc-al-insight">
        <span class="acc-al-insight__label"><?php echo __t('al_insight_events', 'accounting'); ?></span>
        <strong class="acc-al-insight__value is-loading" id="accAlInsightEvents">—</strong>
    </article>
    <article class="acc-al-insight">
        <span class="acc-al-insight__label"><?php echo __t('al_insight_users', 'accounting'); ?></span>
        <strong class="acc-al-insight__value is-loading" id="accAlInsightUsers">—</strong>
    </article>
    <article class="acc-al-insight">
        <span class="acc-al-insight__label"><?php echo __t('al_insight_actions', 'accounting'); ?></span>
        <strong class="acc-al-insight__value is-loading" id="accAlInsightActions">—</strong>
    </article>
    <article class="acc-al-insight">
        <span class="acc-al-insight__label"><?php echo __t('al_insight_top_action', 'accounting'); ?></span>
        <strong class="acc-al-insight__value is-loading acc-al-insight__value--truncate" id="accAlInsightTop">—</strong>
    </article>
</div>

<div class="acc-al-charts" id="accAlCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('al_chart_actions', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accAlActions"></canvas>
                <p class="acc-chart-empty" id="accAlActionsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accAlActionsLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('al_chart_entities', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accAlEntities"></canvas>
                <p class="acc-chart-empty" id="accAlEntitiesEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('al_chart_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accAlTrend"></canvas>
                <p class="acc-chart-empty" id="accAlTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-al-toolbar">
    <div class="acc-al-toolbar__top">
        <div class="acc-al-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accAlSearch" placeholder="<?php echo htmlspecialchars(__t('al_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-al-search-clear" id="accAlSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-al-toolbar__dates">
            <label class="acc-al-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accAlDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-al-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accAlDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-al-toolbar__actions">
            <button type="button" class="acc-btn acc-btn--ghost" id="accAlExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-al-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accAlPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-al-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accAlRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-al-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-al-toolbar__filters">
        <div class="acc-al-toolbar__filters-group" id="accAlEntityFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('al_filter_entity', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-al-chip is-active" data-entity="all" role="tab" aria-selected="true"><?php echo __t('al_filter_all', 'accounting'); ?></button>
        </div>
        <div class="acc-al-toolbar__filters-group acc-al-toolbar__filters-group--actions" id="accAlActionFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('al_filter_action', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-al-chip is-active" data-action="all" role="tab" aria-selected="true"><?php echo __t('al_filter_all', 'accounting'); ?></button>
        </div>
    </div>
</div>

<div class="acc-al-panel" id="accAlPrintArea">
    <div class="acc-al-panel__head">
        <span class="acc-al-panel__meta" id="accAlMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-al-pagination">
            <button type="button" class="acc-al-page-btn" id="accAlPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accAlPageInfo">1 / 1</span>
            <button type="button" class="acc-al-page-btn" id="accAlNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accAlRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-al-modal-overlay" id="accAlDetailModal" hidden>
    <div class="acc-al-modal" role="dialog" aria-labelledby="accAlDetailTitle">
        <header class="acc-al-modal__head">
            <h2 id="accAlDetailTitle"><?php echo __t('al_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-al-modal__close" id="accAlDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-al-modal__body" id="accAlDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

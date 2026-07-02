<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'logs';
$pageTitle = __t('cr_logs_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-logs.js'];
$pageI18n = cr_i18n([
    'cr_logs_subtitle', 'cr_logs_search_placeholder', 'cr_logs_filter_action', 'cr_logs_all_actions',
    'cr_logs_export_csv', 'cr_logs_view_details', 'cr_logs_detail_title', 'cr_logs_col_ip',
    'cr_logs_col_entity', 'cr_logs_col_details', 'cr_logs_stat_total', 'cr_logs_stat_today',
    'cr_logs_stat_users', 'cr_logs_stat_alerts', 'cr_logs_table_summary', 'cr_logs_no_details',
    'cr_col_action', 'cr_col_register', 'col_date', 'cr_col_user', 'cr_no_data', 'cr_export_csv',
    'loading', 'refresh', 'clear_search', 'start_date', 'end_date', 'prev_page', 'next_page',
    'close', 'load_error', 'connection_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-logs-intro"><?php echo __t('cr_logs_subtitle', 'admin'); ?></p>

<div class="cr-logs-stats" id="crLogsStats" aria-live="polite">
    <article class="cr-logs-stat is-loading">
        <span class="cr-logs-stat__label"><?php echo __t('cr_logs_stat_total', 'admin'); ?></span>
        <strong class="cr-logs-stat__value" id="crLogsStatTotal">—</strong>
    </article>
    <article class="cr-logs-stat is-loading">
        <span class="cr-logs-stat__label"><?php echo __t('cr_logs_stat_today', 'admin'); ?></span>
        <strong class="cr-logs-stat__value" id="crLogsStatToday">—</strong>
    </article>
    <article class="cr-logs-stat is-loading">
        <span class="cr-logs-stat__label"><?php echo __t('cr_logs_stat_users', 'admin'); ?></span>
        <strong class="cr-logs-stat__value" id="crLogsStatUsers">—</strong>
    </article>
    <article class="cr-logs-stat is-loading">
        <span class="cr-logs-stat__label"><?php echo __t('cr_logs_stat_alerts', 'admin'); ?></span>
        <strong class="cr-logs-stat__value" id="crLogsStatAlerts">—</strong>
    </article>
</div>

<div class="cr-toolbar cr-filter cr-logs-toolbar">
    <div class="cr-logs-search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="crLogsSearch" placeholder="<?php echo __t('cr_logs_search_placeholder', 'admin'); ?>" autocomplete="off">
        <button type="button" class="cr-logs-search-clear" id="crLogsSearchClear" aria-label="<?php echo __t('clear_search', 'admin'); ?>">
            <span class="material-icons-round">close</span>
        </button>
    </div>
    <select id="crLogsAction" class="cr-logs-select" aria-label="<?php echo __t('cr_logs_filter_action', 'admin'); ?>">
        <option value="all"><?php echo __t('cr_logs_all_actions', 'admin'); ?></option>
    </select>
    <label class="cr-logs-date">
        <span class="material-icons-round">calendar_today</span>
        <input type="date" id="crLogsDateFrom" value="<?php echo date('Y-m-d', strtotime('-7 days')); ?>" aria-label="<?php echo __t('start_date', 'admin'); ?>">
    </label>
    <label class="cr-logs-date">
        <span class="material-icons-round">calendar_today</span>
        <input type="date" id="crLogsDateTo" value="<?php echo $today; ?>" aria-label="<?php echo __t('end_date', 'admin'); ?>">
    </label>
    <button type="button" class="cr-btn" id="crLogsFilterBtn">
        <span class="material-icons-round">filter_alt</span>
        <?php echo __t('refresh', 'admin'); ?>
    </button>
    <button type="button" class="cr-btn cr-btn--ghost" id="crLogsExportBtn">
        <span class="material-icons-round">download</span>
        <?php echo __t('cr_export_csv', 'admin'); ?>
    </button>
</div>

<div class="cr-panel cr-logs-panel">
    <div class="cr-logs-meta" id="crLogsMeta"><?php echo __t('loading', 'admin'); ?></div>
    <div class="cr-logs-pagination">
        <button type="button" class="cr-logs-page-btn" id="crLogsPrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>">
            <span class="material-icons-round">chevron_left</span>
        </button>
        <span id="crLogsPageInfo">1 / 1</span>
        <button type="button" class="cr-logs-page-btn" id="crLogsNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>">
            <span class="material-icons-round">chevron_right</span>
        </button>
    </div>
    <div id="crLogsRoot"></div>
</div>

<div class="cr-modal-overlay" id="crLogDetailModal" hidden>
    <div class="cr-modal cr-logs-modal" role="dialog" aria-labelledby="crLogDetailTitle">
        <header class="cr-logs-modal__head">
            <h2 id="crLogDetailTitle"><?php echo __t('cr_logs_detail_title', 'admin'); ?></h2>
            <button type="button" class="cr-logs-modal__close" id="crLogDetailClose" aria-label="<?php echo __t('close', 'admin'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="cr-logs-modal__body" id="crLogDetailBody"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

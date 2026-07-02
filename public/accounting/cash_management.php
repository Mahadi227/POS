<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'cash';
$pageTitle = __t('nav_cash', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-cash.js'];
$pageI18n = acc_i18n([
    'cm_subtitle', 'cm_stat_balance', 'cm_stat_in', 'cm_stat_out', 'cm_stat_net',
    'cm_insight_registers', 'cm_insight_avg_balance', 'cm_insight_in_out_ratio', 'cm_insight_top_register',
    'cm_chart_types', 'cm_chart_trend', 'cm_chart_registers', 'cm_registers_title',
    'cm_search_placeholder', 'cm_filter_type', 'cm_filter_all', 'cm_type_deposit', 'cm_type_withdrawal',
    'cm_type_opening', 'cm_type_closing', 'cm_type_transfer', 'cm_type_sale', 'cm_type_expense',
    'cm_col_date', 'cm_col_register', 'cm_col_type', 'cm_col_amount', 'cm_col_balance_after',
    'cm_col_reference', 'cm_col_notes', 'cm_col_by', 'cm_view_details', 'cm_detail_title',
    'cm_add_register', 'cm_add_transaction', 'cm_register_modal_title', 'cm_tx_modal_title',
    'cm_form_register_name', 'cm_form_opening_balance', 'cm_form_register', 'cm_form_type',
    'cm_form_amount', 'cm_form_date', 'cm_form_reference', 'cm_form_notes', 'cm_register_balance', 'cm_tx_count',
    'cash_in', 'cash_out', 'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading',
    'no_data', 'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records',
    'close', 'cancel', 'save',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-cm-hero" aria-labelledby="accCmHeroTitle">
    <div class="acc-cm-hero__intro">
        <h2 class="acc-cm-hero__title" id="accCmHeroTitle"><?php echo __t('cm_subtitle', 'accounting'); ?></h2>
        <p class="acc-cm-hero__count" id="accCmCount" aria-live="polite">—</p>
    </div>
    <div class="acc-cm-hero__stats" id="accCmStats" role="group">
        <button type="button" class="acc-cm-stat acc-cm-stat--primary acc-cm-stat--click" data-stat-filter="all">
            <span class="acc-cm-stat__label"><?php echo __t('cm_stat_balance', 'accounting'); ?></span>
            <strong class="acc-cm-stat__value is-loading" id="accCmStatBalance">—</strong>
        </button>
        <button type="button" class="acc-cm-stat acc-cm-stat--success acc-cm-stat--click" data-stat-filter="in">
            <span class="acc-cm-stat__label"><?php echo __t('cm_stat_in', 'accounting'); ?></span>
            <strong class="acc-cm-stat__value is-loading" id="accCmStatIn">—</strong>
        </button>
        <button type="button" class="acc-cm-stat acc-cm-stat--warn acc-cm-stat--click" data-stat-filter="out">
            <span class="acc-cm-stat__label"><?php echo __t('cm_stat_out', 'accounting'); ?></span>
            <strong class="acc-cm-stat__value is-loading" id="accCmStatOut">—</strong>
        </button>
        <div class="acc-cm-stat acc-cm-stat--net">
            <span class="acc-cm-stat__label"><?php echo __t('cm_stat_net', 'accounting'); ?></span>
            <strong class="acc-cm-stat__value is-loading" id="accCmStatNet">—</strong>
        </div>
    </div>
</section>

<div class="acc-cm-insights" id="accCmInsights">
    <article class="acc-cm-insight">
        <span class="acc-cm-insight__label"><?php echo __t('cm_insight_registers', 'accounting'); ?></span>
        <strong class="acc-cm-insight__value is-loading" id="accCmRegisters">—</strong>
    </article>
    <article class="acc-cm-insight">
        <span class="acc-cm-insight__label"><?php echo __t('cm_insight_avg_balance', 'accounting'); ?></span>
        <strong class="acc-cm-insight__value is-loading" id="accCmAvgBalance">—</strong>
    </article>
    <article class="acc-cm-insight">
        <span class="acc-cm-insight__label"><?php echo __t('cm_insight_in_out_ratio', 'accounting'); ?></span>
        <strong class="acc-cm-insight__value is-loading" id="accCmInOutRatio">—</strong>
    </article>
    <article class="acc-cm-insight">
        <span class="acc-cm-insight__label"><?php echo __t('cm_insight_top_register', 'accounting'); ?></span>
        <strong class="acc-cm-insight__value is-loading" id="accCmTopRegister">—</strong>
    </article>
</div>

<div class="acc-cm-charts" id="accCmCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('cm_chart_types', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accCmTypes"></canvas>
                <p class="acc-chart-empty" id="accCmTypesEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accCmTypesLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('cm_chart_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accCmTrend"></canvas>
                <p class="acc-chart-empty" id="accCmTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('cm_chart_registers', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accCmRegistersChart"></canvas>
                <p class="acc-chart-empty" id="accCmRegistersEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="acc-cm-registers-section" id="accCmRegistersSection" hidden>
    <header class="acc-cm-registers-section__head">
        <h3><?php echo __t('cm_registers_title', 'accounting'); ?></h3>
    </header>
    <div class="acc-cm-registers" id="accCmRegisterCards"></div>
</section>

<div class="acc-cm-toolbar">
    <div class="acc-cm-toolbar__top">
        <div class="acc-cm-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accCmSearch" placeholder="<?php echo htmlspecialchars(__t('cm_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-cm-search-clear" id="accCmSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-cm-toolbar__dates">
            <label class="acc-cm-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCmDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-cm-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCmDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-cm-toolbar__actions">
            <?php if ($canManageAccounting): ?>
            <button type="button" class="acc-btn" id="accCmAddRegisterBtn">
                <span class="material-icons-round">point_of_sale</span>
                <span class="acc-cm-btn-label"><?php echo __t('cm_add_register', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accCmAddTxBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-cm-btn-label"><?php echo __t('cm_add_transaction', 'accounting'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCmExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-cm-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCmPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-cm-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCmRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-cm-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-cm-toolbar__filters" id="accCmTypeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('cm_filter_type', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-cm-chip is-active" data-type="all" role="tab" aria-selected="true"><?php echo __t('cm_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="deposit" role="tab"><?php echo __t('cm_type_deposit', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="withdrawal" role="tab"><?php echo __t('cm_type_withdrawal', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="sale" role="tab"><?php echo __t('cm_type_sale', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="expense" role="tab"><?php echo __t('cm_type_expense', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="transfer" role="tab"><?php echo __t('cm_type_transfer', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="opening" role="tab"><?php echo __t('cm_type_opening', 'accounting'); ?></button>
        <button type="button" class="acc-cm-chip" data-type="closing" role="tab"><?php echo __t('cm_type_closing', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-cm-panel" id="accCmPrintArea">
    <div class="acc-cm-panel__head">
        <span class="acc-cm-panel__meta" id="accCmMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-cm-pagination">
            <button type="button" class="acc-cm-page-btn" id="accCmPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accCmPageInfo">1 / 1</span>
            <button type="button" class="acc-cm-page-btn" id="accCmNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accCmRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-cm-modal-overlay" id="accCmDetailModal" hidden>
    <div class="acc-cm-modal" role="dialog" aria-labelledby="accCmDetailTitle">
        <header class="acc-cm-modal__head">
            <h2 id="accCmDetailTitle"><?php echo __t('cm_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-cm-modal__close" id="accCmDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-cm-modal__body" id="accCmDetailBody"></div>
    </div>
</div>

<?php if ($canManageAccounting): ?>
<div class="acc-cm-modal-overlay" id="accCmRegisterModal" hidden>
    <div class="acc-cm-modal" role="dialog" aria-labelledby="accCmRegisterTitle">
        <header class="acc-cm-modal__head">
            <h2 id="accCmRegisterTitle"><?php echo __t('cm_register_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-cm-modal__close" id="accCmRegisterClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-cm-form" id="accCmRegisterForm">
            <div class="acc-cm-form__body">
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_register_name', 'accounting'); ?></span>
                    <input type="text" name="name" required maxlength="100" placeholder="…">
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_opening_balance', 'accounting'); ?></span>
                    <input type="number" name="opening_balance" min="0" step="0.01" value="0">
                </label>
            </div>
            <footer class="acc-cm-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accCmRegisterCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accCmRegisterSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="acc-cm-modal-overlay" id="accCmTxModal" hidden>
    <div class="acc-cm-modal" role="dialog" aria-labelledby="accCmTxTitle">
        <header class="acc-cm-modal__head">
            <h2 id="accCmTxTitle"><?php echo __t('cm_tx_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-cm-modal__close" id="accCmTxClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-cm-form" id="accCmTxForm">
            <div class="acc-cm-form__body">
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_register', 'accounting'); ?></span>
                    <select name="cash_account_id" id="accCmTxRegisterSelect" required>
                        <option value="">—</option>
                    </select>
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_type', 'accounting'); ?></span>
                    <select name="transaction_type" required>
                        <option value="deposit"><?php echo __t('cm_type_deposit', 'accounting'); ?></option>
                        <option value="withdrawal"><?php echo __t('cm_type_withdrawal', 'accounting'); ?></option>
                        <option value="transfer"><?php echo __t('cm_type_transfer', 'accounting'); ?></option>
                        <option value="opening"><?php echo __t('cm_type_opening', 'accounting'); ?></option>
                        <option value="closing"><?php echo __t('cm_type_closing', 'accounting'); ?></option>
                    </select>
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_amount', 'accounting'); ?></span>
                    <input type="number" name="amount" min="0.01" step="0.01" required>
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_date', 'accounting'); ?></span>
                    <input type="date" name="transaction_date" value="<?php echo $today; ?>" required>
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_reference', 'accounting'); ?></span>
                    <input type="text" name="reference" maxlength="100" placeholder="…">
                </label>
                <label class="acc-cm-field">
                    <span><?php echo __t('cm_form_notes', 'accounting'); ?></span>
                    <textarea name="notes" rows="2" maxlength="500" placeholder="…"></textarea>
                </label>
            </div>
            <footer class="acc-cm-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accCmTxCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accCmTxSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

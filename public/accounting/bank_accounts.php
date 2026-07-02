<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'banks';
$pageTitle = __t('nav_banks', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-banks.js'];
$pageI18n = acc_i18n([
    'bk_subtitle', 'bk_stat_balance', 'bk_stat_deposits', 'bk_stat_withdrawals', 'bk_stat_net',
    'bk_insight_accounts', 'bk_insight_avg_balance', 'bk_insight_in_out_ratio', 'bk_insight_top_bank',
    'bk_chart_banks', 'bk_chart_trend', 'bk_chart_accounts', 'bk_accounts_title',
    'bk_search_placeholder', 'bk_filter_type', 'bk_filter_all', 'bk_type_deposit', 'bk_type_withdrawal',
    'bk_type_transfer', 'bk_type_fee', 'bk_type_reconciliation', 'bk_col_date', 'bk_col_account',
    'bk_col_bank', 'bk_col_type', 'bk_col_amount', 'bk_col_reference', 'bk_col_reconciled', 'bk_col_by',
    'bk_view_details', 'bk_detail_title', 'bk_add_account', 'bk_add_transaction',
    'bk_account_modal_title', 'bk_tx_modal_title', 'bk_form_bank_name', 'bk_form_account_name',
    'bk_form_account_number', 'bk_form_currency', 'bk_form_opening_balance', 'bk_form_account',
    'bk_form_type', 'bk_form_amount', 'bk_form_date', 'bk_form_reference', 'bk_form_reconciled',
    'bk_account_balance', 'bk_tx_count', 'bk_reconciled_yes', 'bk_reconciled_no',
    'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data',
    'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records',
    'close', 'cancel', 'save',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-bk-hero" aria-labelledby="accBkHeroTitle">
    <div class="acc-bk-hero__intro">
        <h2 class="acc-bk-hero__title" id="accBkHeroTitle"><?php echo __t('bk_subtitle', 'accounting'); ?></h2>
        <p class="acc-bk-hero__count" id="accBkCount" aria-live="polite">—</p>
    </div>
    <div class="acc-bk-hero__stats" id="accBkStats" role="group">
        <button type="button" class="acc-bk-stat acc-bk-stat--primary acc-bk-stat--click" data-stat-filter="all">
            <span class="acc-bk-stat__label"><?php echo __t('bk_stat_balance', 'accounting'); ?></span>
            <strong class="acc-bk-stat__value is-loading" id="accBkStatBalance">—</strong>
        </button>
        <button type="button" class="acc-bk-stat acc-bk-stat--success acc-bk-stat--click" data-stat-filter="in">
            <span class="acc-bk-stat__label"><?php echo __t('bk_stat_deposits', 'accounting'); ?></span>
            <strong class="acc-bk-stat__value is-loading" id="accBkStatDeposits">—</strong>
        </button>
        <button type="button" class="acc-bk-stat acc-bk-stat--warn acc-bk-stat--click" data-stat-filter="out">
            <span class="acc-bk-stat__label"><?php echo __t('bk_stat_withdrawals', 'accounting'); ?></span>
            <strong class="acc-bk-stat__value is-loading" id="accBkStatWithdrawals">—</strong>
        </button>
        <div class="acc-bk-stat acc-bk-stat--net">
            <span class="acc-bk-stat__label"><?php echo __t('bk_stat_net', 'accounting'); ?></span>
            <strong class="acc-bk-stat__value is-loading" id="accBkStatNet">—</strong>
        </div>
    </div>
</section>

<div class="acc-bk-insights" id="accBkInsights">
    <article class="acc-bk-insight">
        <span class="acc-bk-insight__label"><?php echo __t('bk_insight_accounts', 'accounting'); ?></span>
        <strong class="acc-bk-insight__value is-loading" id="accBkAccounts">—</strong>
    </article>
    <article class="acc-bk-insight">
        <span class="acc-bk-insight__label"><?php echo __t('bk_insight_avg_balance', 'accounting'); ?></span>
        <strong class="acc-bk-insight__value is-loading" id="accBkAvgBalance">—</strong>
    </article>
    <article class="acc-bk-insight">
        <span class="acc-bk-insight__label"><?php echo __t('bk_insight_in_out_ratio', 'accounting'); ?></span>
        <strong class="acc-bk-insight__value is-loading" id="accBkInOutRatio">—</strong>
    </article>
    <article class="acc-bk-insight">
        <span class="acc-bk-insight__label"><?php echo __t('bk_insight_top_bank', 'accounting'); ?></span>
        <strong class="acc-bk-insight__value is-loading" id="accBkTopBank">—</strong>
    </article>
</div>

<div class="acc-bk-charts" id="accBkCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('bk_chart_banks', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accBkBanks"></canvas>
                <p class="acc-chart-empty" id="accBkBanksEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accBkBanksLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('bk_chart_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accBkTrend"></canvas>
                <p class="acc-chart-empty" id="accBkTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('bk_chart_accounts', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accBkAccountsChart"></canvas>
                <p class="acc-chart-empty" id="accBkAccountsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="acc-bk-accounts-section" id="accBkAccountsSection" hidden>
    <header class="acc-bk-accounts-section__head">
        <h3><?php echo __t('bk_accounts_title', 'accounting'); ?></h3>
    </header>
    <div class="acc-bk-accounts" id="accBkAccountCards"></div>
</section>

<div class="acc-bk-toolbar">
    <div class="acc-bk-toolbar__top">
        <div class="acc-bk-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accBkSearch" placeholder="<?php echo htmlspecialchars(__t('bk_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-bk-search-clear" id="accBkSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-bk-toolbar__dates">
            <label class="acc-bk-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accBkDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-bk-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accBkDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-bk-toolbar__actions">
            <?php if ($canManageAccounting): ?>
            <button type="button" class="acc-btn" id="accBkAddAccountBtn">
                <span class="material-icons-round">account_balance</span>
                <span class="acc-bk-btn-label"><?php echo __t('bk_add_account', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accBkAddTxBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-bk-btn-label"><?php echo __t('bk_add_transaction', 'accounting'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="acc-btn acc-btn--ghost" id="accBkExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-bk-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accBkPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-bk-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accBkRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-bk-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-bk-toolbar__filters" id="accBkTypeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('bk_filter_type', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-bk-chip is-active" data-type="all" role="tab" aria-selected="true"><?php echo __t('bk_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-bk-chip" data-type="deposit" role="tab"><?php echo __t('bk_type_deposit', 'accounting'); ?></button>
        <button type="button" class="acc-bk-chip" data-type="withdrawal" role="tab"><?php echo __t('bk_type_withdrawal', 'accounting'); ?></button>
        <button type="button" class="acc-bk-chip" data-type="transfer" role="tab"><?php echo __t('bk_type_transfer', 'accounting'); ?></button>
        <button type="button" class="acc-bk-chip" data-type="fee" role="tab"><?php echo __t('bk_type_fee', 'accounting'); ?></button>
        <button type="button" class="acc-bk-chip" data-type="reconciliation" role="tab"><?php echo __t('bk_type_reconciliation', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-bk-panel" id="accBkPrintArea">
    <div class="acc-bk-panel__head">
        <span class="acc-bk-panel__meta" id="accBkMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-bk-pagination">
            <button type="button" class="acc-bk-page-btn" id="accBkPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accBkPageInfo">1 / 1</span>
            <button type="button" class="acc-bk-page-btn" id="accBkNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accBkRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-bk-modal-overlay" id="accBkDetailModal" hidden>
    <div class="acc-bk-modal" role="dialog" aria-labelledby="accBkDetailTitle">
        <header class="acc-bk-modal__head">
            <h2 id="accBkDetailTitle"><?php echo __t('bk_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-bk-modal__close" id="accBkDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-bk-modal__body" id="accBkDetailBody"></div>
    </div>
</div>

<?php if ($canManageAccounting): ?>
<div class="acc-bk-modal-overlay" id="accBkAccountModal" hidden>
    <div class="acc-bk-modal" role="dialog" aria-labelledby="accBkAccountTitle">
        <header class="acc-bk-modal__head">
            <h2 id="accBkAccountTitle"><?php echo __t('bk_account_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-bk-modal__close" id="accBkAccountClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-bk-form" id="accBkAccountForm">
            <div class="acc-bk-form__body">
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_bank_name', 'accounting'); ?></span>
                    <input type="text" name="bank_name" required maxlength="100" placeholder="…">
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_account_name', 'accounting'); ?></span>
                    <input type="text" name="account_name" required maxlength="100" placeholder="…">
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_account_number', 'accounting'); ?></span>
                    <input type="text" name="account_number" maxlength="50" placeholder="…">
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_currency', 'accounting'); ?></span>
                    <input type="text" name="currency" value="FCFA" maxlength="10">
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_opening_balance', 'accounting'); ?></span>
                    <input type="number" name="opening_balance" min="0" step="0.01" value="0">
                </label>
            </div>
            <footer class="acc-bk-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accBkAccountCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accBkAccountSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="acc-bk-modal-overlay" id="accBkTxModal" hidden>
    <div class="acc-bk-modal" role="dialog" aria-labelledby="accBkTxTitle">
        <header class="acc-bk-modal__head">
            <h2 id="accBkTxTitle"><?php echo __t('bk_tx_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-bk-modal__close" id="accBkTxClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-bk-form" id="accBkTxForm">
            <div class="acc-bk-form__body">
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_account', 'accounting'); ?></span>
                    <select name="bank_account_id" id="accBkTxAccountSelect" required>
                        <option value="">—</option>
                    </select>
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_type', 'accounting'); ?></span>
                    <select name="transaction_type" required>
                        <option value="deposit"><?php echo __t('bk_type_deposit', 'accounting'); ?></option>
                        <option value="withdrawal"><?php echo __t('bk_type_withdrawal', 'accounting'); ?></option>
                        <option value="transfer"><?php echo __t('bk_type_transfer', 'accounting'); ?></option>
                        <option value="fee"><?php echo __t('bk_type_fee', 'accounting'); ?></option>
                        <option value="reconciliation"><?php echo __t('bk_type_reconciliation', 'accounting'); ?></option>
                    </select>
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_amount', 'accounting'); ?></span>
                    <input type="number" name="amount" min="0.01" step="0.01" required>
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_date', 'accounting'); ?></span>
                    <input type="date" name="transaction_date" value="<?php echo $today; ?>" required>
                </label>
                <label class="acc-bk-field">
                    <span><?php echo __t('bk_form_reference', 'accounting'); ?></span>
                    <input type="text" name="reference" maxlength="100" placeholder="…">
                </label>
                <label class="acc-bk-field acc-bk-field--check">
                    <input type="checkbox" name="reconciled" value="1" id="accBkTxReconciled">
                    <span><?php echo __t('bk_form_reconciled', 'accounting'); ?></span>
                </label>
            </div>
            <footer class="acc-bk-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accBkTxCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accBkTxSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

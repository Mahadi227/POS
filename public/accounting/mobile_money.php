<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'mobile';
$pageTitle = __t('nav_mobile_money', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-mobile-money.js'];
$pageI18n = acc_i18n([
    'mm_subtitle', 'mm_stat_balance', 'mm_stat_in', 'mm_stat_out', 'mm_stat_net',
    'mm_insight_wallets', 'mm_insight_avg_balance', 'mm_insight_in_out_ratio', 'mm_insight_top_provider',
    'mm_chart_provider', 'mm_chart_trend', 'mm_chart_wallets', 'mm_wallets_title',
    'mm_search_placeholder', 'mm_filter_direction', 'mm_filter_provider', 'mm_filter_all',
    'mm_direction_in', 'mm_direction_out', 'mm_col_date', 'mm_col_wallet', 'mm_col_provider',
    'mm_col_direction', 'mm_col_amount', 'mm_col_reference', 'mm_col_external_ref', 'mm_col_by',
    'mm_view_details', 'mm_detail_title', 'mm_add_wallet', 'mm_add_transaction',
    'mm_wallet_modal_title', 'mm_tx_modal_title', 'mm_form_provider', 'mm_form_label',
    'mm_form_phone', 'mm_form_account_id', 'mm_form_opening_balance', 'mm_form_wallet',
    'mm_form_direction', 'mm_form_amount', 'mm_form_date', 'mm_form_reference', 'mm_form_external_ref',
    'mm_provider_mtn', 'mm_provider_orange', 'mm_provider_moov', 'mm_provider_airtel',
    'mm_provider_vodafone', 'mm_provider_other', 'mm_wallet_balance', 'mm_tx_count',
    'dash_all_stores', 'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data',
    'load_error', 'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records',
    'close', 'cancel', 'save',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-mm-hero" aria-labelledby="accMmHeroTitle">
    <div class="acc-mm-hero__intro">
        <h2 class="acc-mm-hero__title" id="accMmHeroTitle"><?php echo __t('mm_subtitle', 'accounting'); ?></h2>
        <p class="acc-mm-hero__count" id="accMmCount" aria-live="polite">—</p>
    </div>
    <div class="acc-mm-hero__stats" id="accMmStats" role="group">
        <button type="button" class="acc-mm-stat acc-mm-stat--primary acc-mm-stat--click" data-stat-filter="all">
            <span class="acc-mm-stat__label"><?php echo __t('mm_stat_balance', 'accounting'); ?></span>
            <strong class="acc-mm-stat__value is-loading" id="accMmStatBalance">—</strong>
        </button>
        <button type="button" class="acc-mm-stat acc-mm-stat--success acc-mm-stat--click" data-stat-filter="in">
            <span class="acc-mm-stat__label"><?php echo __t('mm_stat_in', 'accounting'); ?></span>
            <strong class="acc-mm-stat__value is-loading" id="accMmStatIn">—</strong>
        </button>
        <button type="button" class="acc-mm-stat acc-mm-stat--warn acc-mm-stat--click" data-stat-filter="out">
            <span class="acc-mm-stat__label"><?php echo __t('mm_stat_out', 'accounting'); ?></span>
            <strong class="acc-mm-stat__value is-loading" id="accMmStatOut">—</strong>
        </button>
        <div class="acc-mm-stat acc-mm-stat--net">
            <span class="acc-mm-stat__label"><?php echo __t('mm_stat_net', 'accounting'); ?></span>
            <strong class="acc-mm-stat__value is-loading" id="accMmStatNet">—</strong>
        </div>
    </div>
</section>

<div class="acc-mm-insights" id="accMmInsights">
    <article class="acc-mm-insight">
        <span class="acc-mm-insight__label"><?php echo __t('mm_insight_wallets', 'accounting'); ?></span>
        <strong class="acc-mm-insight__value is-loading" id="accMmWallets">—</strong>
    </article>
    <article class="acc-mm-insight">
        <span class="acc-mm-insight__label"><?php echo __t('mm_insight_avg_balance', 'accounting'); ?></span>
        <strong class="acc-mm-insight__value is-loading" id="accMmAvgBalance">—</strong>
    </article>
    <article class="acc-mm-insight">
        <span class="acc-mm-insight__label"><?php echo __t('mm_insight_in_out_ratio', 'accounting'); ?></span>
        <strong class="acc-mm-insight__value is-loading" id="accMmInOutRatio">—</strong>
    </article>
    <article class="acc-mm-insight">
        <span class="acc-mm-insight__label"><?php echo __t('mm_insight_top_provider', 'accounting'); ?></span>
        <strong class="acc-mm-insight__value is-loading" id="accMmTopProvider">—</strong>
    </article>
</div>

<div class="acc-mm-charts" id="accMmCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('mm_chart_provider', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accMmProvider"></canvas>
                <p class="acc-chart-empty" id="accMmProviderEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accMmProviderLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('mm_chart_trend', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accMmTrend"></canvas>
                <p class="acc-chart-empty" id="accMmTrendEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('mm_chart_wallets', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accMmWalletsChart"></canvas>
                <p class="acc-chart-empty" id="accMmWalletsEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="acc-mm-wallets-section" id="accMmWalletsSection" hidden>
    <header class="acc-mm-wallets-section__head">
        <h3><?php echo __t('mm_wallets_title', 'accounting'); ?></h3>
    </header>
    <div class="acc-mm-wallets" id="accMmWalletCards"></div>
</section>

<div class="acc-mm-toolbar">
    <div class="acc-mm-toolbar__top">
        <div class="acc-mm-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accMmSearch" placeholder="<?php echo htmlspecialchars(__t('mm_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-mm-search-clear" id="accMmSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-mm-toolbar__dates">
            <label class="acc-mm-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accMmDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-mm-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accMmDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-mm-toolbar__actions">
            <?php if ($canManageAccounting): ?>
            <button type="button" class="acc-btn" id="accMmAddWalletBtn">
                <span class="material-icons-round">account_balance_wallet</span>
                <span class="acc-mm-btn-label"><?php echo __t('mm_add_wallet', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn" id="accMmAddTxBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-mm-btn-label"><?php echo __t('mm_add_transaction', 'accounting'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="acc-btn acc-btn--ghost" id="accMmExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-mm-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accMmPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-mm-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accMmRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-mm-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-mm-toolbar__filters">
        <div class="acc-mm-toolbar__filters-group" id="accMmDirectionFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('mm_filter_direction', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-mm-chip is-active" data-direction="all" role="tab" aria-selected="true"><?php echo __t('mm_filter_all', 'accounting'); ?></button>
            <button type="button" class="acc-mm-chip" data-direction="in" role="tab"><?php echo __t('mm_direction_in', 'accounting'); ?></button>
            <button type="button" class="acc-mm-chip" data-direction="out" role="tab"><?php echo __t('mm_direction_out', 'accounting'); ?></button>
        </div>
        <div class="acc-mm-toolbar__filters-group" id="accMmProviderFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('mm_filter_provider', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-mm-chip is-active" data-provider="all" role="tab" aria-selected="true"><?php echo __t('mm_filter_all', 'accounting'); ?></button>
            <button type="button" class="acc-mm-chip" data-provider="mtn" role="tab">MTN</button>
            <button type="button" class="acc-mm-chip" data-provider="orange" role="tab">Orange</button>
            <button type="button" class="acc-mm-chip" data-provider="moov" role="tab">Moov</button>
            <button type="button" class="acc-mm-chip" data-provider="airtel" role="tab">Airtel</button>
            <button type="button" class="acc-mm-chip" data-provider="vodafone" role="tab">Vodafone</button>
            <button type="button" class="acc-mm-chip" data-provider="other" role="tab"><?php echo __t('mm_provider_other', 'accounting'); ?></button>
        </div>
    </div>
</div>

<div class="acc-mm-panel" id="accMmPrintArea">
    <div class="acc-mm-panel__head">
        <span class="acc-mm-panel__meta" id="accMmMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-mm-pagination">
            <button type="button" class="acc-mm-page-btn" id="accMmPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accMmPageInfo">1 / 1</span>
            <button type="button" class="acc-mm-page-btn" id="accMmNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accMmRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-mm-modal-overlay" id="accMmDetailModal" hidden>
    <div class="acc-mm-modal" role="dialog" aria-labelledby="accMmDetailTitle">
        <header class="acc-mm-modal__head">
            <h2 id="accMmDetailTitle"><?php echo __t('mm_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-mm-modal__close" id="accMmDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-mm-modal__body" id="accMmDetailBody"></div>
    </div>
</div>

<?php if ($canManageAccounting): ?>
<div class="acc-mm-modal-overlay" id="accMmWalletModal" hidden>
    <div class="acc-mm-modal" role="dialog" aria-labelledby="accMmWalletTitle">
        <header class="acc-mm-modal__head">
            <h2 id="accMmWalletTitle"><?php echo __t('mm_wallet_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-mm-modal__close" id="accMmWalletClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-mm-form" id="accMmWalletForm">
            <div class="acc-mm-form__body">
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_provider', 'accounting'); ?></span>
                    <select name="provider" required>
                        <option value="mtn">MTN</option>
                        <option value="orange">Orange</option>
                        <option value="moov">Moov</option>
                        <option value="airtel">Airtel</option>
                        <option value="vodafone">Vodafone</option>
                        <option value="other"><?php echo __t('mm_provider_other', 'accounting'); ?></option>
                    </select>
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_label', 'accounting'); ?></span>
                    <input type="text" name="label" required maxlength="120" placeholder="…">
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_phone', 'accounting'); ?></span>
                    <input type="tel" name="phone_number" maxlength="30" placeholder="…">
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_account_id', 'accounting'); ?></span>
                    <input type="text" name="account_id" maxlength="80" placeholder="…">
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_opening_balance', 'accounting'); ?></span>
                    <input type="number" name="current_balance" min="0" step="0.01" value="0">
                </label>
            </div>
            <footer class="acc-mm-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accMmWalletCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accMmWalletSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="acc-mm-modal-overlay" id="accMmTxModal" hidden>
    <div class="acc-mm-modal" role="dialog" aria-labelledby="accMmTxTitle">
        <header class="acc-mm-modal__head">
            <h2 id="accMmTxTitle"><?php echo __t('mm_tx_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-mm-modal__close" id="accMmTxClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-mm-form" id="accMmTxForm">
            <div class="acc-mm-form__body">
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_wallet', 'accounting'); ?></span>
                    <select name="mobile_account_id" id="accMmTxWalletSelect" required>
                        <option value="">—</option>
                    </select>
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_direction', 'accounting'); ?></span>
                    <select name="direction" required>
                        <option value="in"><?php echo __t('mm_direction_in', 'accounting'); ?></option>
                        <option value="out"><?php echo __t('mm_direction_out', 'accounting'); ?></option>
                    </select>
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_amount', 'accounting'); ?></span>
                    <input type="number" name="amount" min="0.01" step="0.01" required>
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_date', 'accounting'); ?></span>
                    <input type="date" name="transaction_date" value="<?php echo $today; ?>" required>
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_reference', 'accounting'); ?></span>
                    <input type="text" name="reference" maxlength="120" placeholder="…">
                </label>
                <label class="acc-mm-field">
                    <span><?php echo __t('mm_form_external_ref', 'accounting'); ?></span>
                    <input type="text" name="external_ref" maxlength="80" placeholder="…">
                </label>
            </div>
            <footer class="acc-mm-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accMmTxCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accMmTxSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

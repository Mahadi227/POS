<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'accounts';
$pageTitle = __t('nav_chart_of_accounts', 'accounting');
$loadChart = true;
$today = date('Y-m-d');
$yearStart = date('Y-01-01');
$extraScripts = ['accounting-common.js', 'accounting-chart-of-accounts.js'];
$pageI18n = acc_i18n([
    'coa_subtitle', 'coa_stat_accounts', 'coa_stat_assets', 'coa_stat_liabilities', 'coa_stat_equity',
    'coa_insight_accounts', 'coa_insight_system', 'coa_insight_custom', 'coa_insight_top_account',
    'coa_chart_balances', 'coa_chart_top', 'coa_chart_count', 'coa_search_placeholder',
    'coa_filter_type', 'coa_filter_scope', 'coa_filter_all', 'coa_scope_system', 'coa_scope_custom',
    'coa_type_asset', 'coa_type_liability', 'coa_type_equity', 'coa_type_revenue', 'coa_type_expense',
    'coa_col_code', 'coa_col_name', 'coa_col_type', 'coa_col_subtype', 'coa_col_normal', 'coa_col_balance',
    'coa_col_system', 'coa_view_details', 'coa_detail_title', 'coa_add_account', 'coa_modal_title',
    'coa_form_code', 'coa_form_name', 'coa_form_type', 'coa_form_subtype', 'coa_form_normal',
    'coa_form_parent', 'coa_form_description', 'coa_normal_debit', 'coa_normal_credit',
    'coa_system_yes', 'coa_system_no', 'coa_balance_as_of', 'coa_no_parent',
    'report_assets', 'report_liabilities', 'report_equity', 'dash_all_stores',
    'cr_export_csv', 'rpt_export_print', 'refresh', 'loading', 'no_data', 'load_error',
    'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records', 'close', 'cancel', 'save',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-coa-hero" aria-labelledby="accCoaHeroTitle">
    <div class="acc-coa-hero__intro">
        <h2 class="acc-coa-hero__title" id="accCoaHeroTitle"><?php echo __t('coa_subtitle', 'accounting'); ?></h2>
        <p class="acc-coa-hero__count" id="accCoaCount" aria-live="polite">—</p>
    </div>
    <div class="acc-coa-hero__stats" id="accCoaStats" role="group">
        <button type="button" class="acc-coa-stat acc-coa-stat--primary acc-coa-stat--click" data-stat-filter="all">
            <span class="acc-coa-stat__label"><?php echo __t('coa_stat_accounts', 'accounting'); ?></span>
            <strong class="acc-coa-stat__value is-loading" id="accCoaStatAccounts">—</strong>
        </button>
        <button type="button" class="acc-coa-stat acc-coa-stat--success acc-coa-stat--click" data-stat-filter="asset">
            <span class="acc-coa-stat__label"><?php echo __t('coa_stat_assets', 'accounting'); ?></span>
            <strong class="acc-coa-stat__value is-loading" id="accCoaStatAssets">—</strong>
        </button>
        <button type="button" class="acc-coa-stat acc-coa-stat--warn acc-coa-stat--click" data-stat-filter="liability">
            <span class="acc-coa-stat__label"><?php echo __t('coa_stat_liabilities', 'accounting'); ?></span>
            <strong class="acc-coa-stat__value is-loading" id="accCoaStatLiabilities">—</strong>
        </button>
        <button type="button" class="acc-coa-stat acc-coa-stat--click" data-stat-filter="equity">
            <span class="acc-coa-stat__label"><?php echo __t('coa_stat_equity', 'accounting'); ?></span>
            <strong class="acc-coa-stat__value is-loading" id="accCoaStatEquity">—</strong>
        </button>
    </div>
</section>

<div class="acc-coa-insights" id="accCoaInsights">
    <article class="acc-coa-insight">
        <span class="acc-coa-insight__label"><?php echo __t('coa_insight_accounts', 'accounting'); ?></span>
        <strong class="acc-coa-insight__value is-loading" id="accCoaInsightAccounts">—</strong>
    </article>
    <article class="acc-coa-insight">
        <span class="acc-coa-insight__label"><?php echo __t('coa_insight_system', 'accounting'); ?></span>
        <strong class="acc-coa-insight__value is-loading" id="accCoaInsightSystem">—</strong>
    </article>
    <article class="acc-coa-insight">
        <span class="acc-coa-insight__label"><?php echo __t('coa_insight_custom', 'accounting'); ?></span>
        <strong class="acc-coa-insight__value is-loading" id="accCoaInsightCustom">—</strong>
    </article>
    <article class="acc-coa-insight">
        <span class="acc-coa-insight__label"><?php echo __t('coa_insight_top_account', 'accounting'); ?></span>
        <strong class="acc-coa-insight__value is-loading" id="accCoaInsightTop">—</strong>
    </article>
</div>

<div class="acc-coa-charts" id="accCoaCharts">
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('coa_chart_balances', 'accounting'); ?></h3></header>
        <div class="acc-panel__body acc-panel__body--donut">
            <div class="acc-chart-wrap acc-chart-wrap--donut">
                <canvas id="accCoaByType"></canvas>
                <p class="acc-chart-empty" id="accCoaByTypeEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
            <ul class="acc-chart-legend" id="accCoaByTypeLegend"></ul>
        </div>
    </section>
    <section class="acc-panel">
        <header class="acc-panel__head"><h3><?php echo __t('coa_chart_count', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap">
                <canvas id="accCoaCount"></canvas>
                <p class="acc-chart-empty" id="accCoaCountEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
    <section class="acc-panel acc-panel--wide">
        <header class="acc-panel__head"><h3><?php echo __t('coa_chart_top', 'accounting'); ?></h3></header>
        <div class="acc-panel__body">
            <div class="acc-chart-wrap acc-chart-wrap--tall">
                <canvas id="accCoaTop"></canvas>
                <p class="acc-chart-empty" id="accCoaTopEmpty" hidden><?php echo __t('no_data', 'accounting'); ?></p>
            </div>
        </div>
    </section>
</div>

<div class="acc-coa-toolbar">
    <div class="acc-coa-toolbar__top">
        <div class="acc-coa-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accCoaSearch" placeholder="<?php echo htmlspecialchars(__t('coa_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-coa-search-clear" id="accCoaSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-coa-toolbar__dates">
            <label class="acc-coa-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCoaDateFrom" value="<?php echo $yearStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-coa-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accCoaDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-coa-toolbar__actions">
            <?php if ($canManageAccounting): ?>
            <button type="button" class="acc-btn" id="accCoaAddBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-coa-btn-label"><?php echo __t('coa_add_account', 'accounting'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCoaExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-coa-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCoaPrintBtn">
                <span class="material-icons-round">print</span>
                <span class="acc-coa-btn-label"><?php echo __t('rpt_export_print', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accCoaRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-coa-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-coa-toolbar__filters">
        <div class="acc-coa-toolbar__filters-group" id="accCoaTypeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('coa_filter_type', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-coa-chip is-active" data-type="all" role="tab" aria-selected="true"><?php echo __t('coa_filter_all', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-type="asset" role="tab"><?php echo __t('coa_type_asset', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-type="liability" role="tab"><?php echo __t('coa_type_liability', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-type="equity" role="tab"><?php echo __t('coa_type_equity', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-type="revenue" role="tab"><?php echo __t('coa_type_revenue', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-type="expense" role="tab"><?php echo __t('coa_type_expense', 'accounting'); ?></button>
        </div>
        <div class="acc-coa-toolbar__filters-group" id="accCoaScopeFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('coa_filter_scope', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="acc-coa-chip is-active" data-scope="all" role="tab" aria-selected="true"><?php echo __t('coa_filter_all', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-scope="system" role="tab"><?php echo __t('coa_scope_system', 'accounting'); ?></button>
            <button type="button" class="acc-coa-chip" data-scope="custom" role="tab"><?php echo __t('coa_scope_custom', 'accounting'); ?></button>
        </div>
    </div>
</div>

<div class="acc-coa-panel" id="accCoaPrintArea">
    <div class="acc-coa-panel__head">
        <span class="acc-coa-panel__meta" id="accCoaMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-coa-pagination">
            <button type="button" class="acc-coa-page-btn" id="accCoaPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accCoaPageInfo">1 / 1</span>
            <button type="button" class="acc-coa-page-btn" id="accCoaNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accCoaRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-coa-modal-overlay" id="accCoaDetailModal" hidden>
    <div class="acc-coa-modal" role="dialog" aria-labelledby="accCoaDetailTitle">
        <header class="acc-coa-modal__head">
            <h2 id="accCoaDetailTitle"><?php echo __t('coa_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-coa-modal__close" id="accCoaDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-coa-modal__body" id="accCoaDetailBody"></div>
    </div>
</div>

<?php if ($canManageAccounting): ?>
<div class="acc-coa-modal-overlay" id="accCoaFormModal" hidden>
    <div class="acc-coa-modal acc-coa-modal--wide" role="dialog" aria-labelledby="accCoaFormTitle">
        <header class="acc-coa-modal__head">
            <h2 id="accCoaFormTitle"><?php echo __t('coa_modal_title', 'accounting'); ?></h2>
            <button type="button" class="acc-coa-modal__close" id="accCoaFormClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-coa-form" id="accCoaForm">
            <div class="acc-coa-form__body">
                <div class="acc-coa-form__row">
                    <label class="acc-coa-field">
                        <span><?php echo __t('coa_form_code', 'accounting'); ?></span>
                        <input type="text" name="code" required maxlength="20" placeholder="5100">
                    </label>
                    <label class="acc-coa-field acc-coa-field--grow">
                        <span><?php echo __t('coa_form_name', 'accounting'); ?></span>
                        <input type="text" name="name" required maxlength="150" placeholder="…">
                    </label>
                </div>
                <div class="acc-coa-form__row">
                    <label class="acc-coa-field">
                        <span><?php echo __t('coa_form_type', 'accounting'); ?></span>
                        <select name="account_type" id="accCoaFormType" required>
                            <option value="asset"><?php echo __t('coa_type_asset', 'accounting'); ?></option>
                            <option value="liability"><?php echo __t('coa_type_liability', 'accounting'); ?></option>
                            <option value="equity"><?php echo __t('coa_type_equity', 'accounting'); ?></option>
                            <option value="revenue"><?php echo __t('coa_type_revenue', 'accounting'); ?></option>
                            <option value="expense"><?php echo __t('coa_type_expense', 'accounting'); ?></option>
                        </select>
                    </label>
                    <label class="acc-coa-field">
                        <span><?php echo __t('coa_form_subtype', 'accounting'); ?></span>
                        <input type="text" name="account_subtype" maxlength="50" placeholder="…">
                    </label>
                    <label class="acc-coa-field">
                        <span><?php echo __t('coa_form_normal', 'accounting'); ?></span>
                        <select name="normal_balance" id="accCoaFormNormal">
                            <option value="debit"><?php echo __t('coa_normal_debit', 'accounting'); ?></option>
                            <option value="credit"><?php echo __t('coa_normal_credit', 'accounting'); ?></option>
                        </select>
                    </label>
                </div>
                <label class="acc-coa-field">
                    <span><?php echo __t('coa_form_parent', 'accounting'); ?></span>
                    <select name="parent_id" id="accCoaFormParent">
                        <option value=""><?php echo __t('coa_no_parent', 'accounting'); ?></option>
                    </select>
                </label>
                <label class="acc-coa-field">
                    <span><?php echo __t('coa_form_description', 'accounting'); ?></span>
                    <textarea name="description" rows="2" maxlength="500" placeholder="…"></textarea>
                </label>
            </div>
            <footer class="acc-coa-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accCoaFormCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accCoaFormSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('save', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

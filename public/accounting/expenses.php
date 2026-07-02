<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'expenses';
$pageTitle = __t('nav_expenses', 'accounting');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-expenses.js'];
$pageI18n = acc_i18n([
    'exp_subtitle', 'exp_stat_total', 'exp_stat_pending', 'exp_stat_approved', 'exp_stat_rejected',
    'exp_search_placeholder', 'exp_filter_status', 'exp_filter_category', 'exp_filter_all',
    'exp_new_btn', 'exp_table_summary', 'exp_col_date', 'exp_col_category', 'exp_col_amount',
    'exp_col_payment', 'exp_col_status', 'exp_col_submitted_by', 'exp_col_description',
    'exp_view_details', 'exp_detail_title', 'exp_modal_new_title', 'exp_form_category',
    'exp_form_amount', 'exp_form_date', 'exp_form_payment', 'exp_form_description',
    'exp_form_submit', 'exp_approve', 'exp_reject', 'exp_approved_by', 'exp_journal_ref',
    'exp_payment_cash', 'exp_payment_bank', 'exp_payment_mobile', 'exp_cat_rent', 'exp_cat_utilities',
    'exp_cat_salaries', 'exp_cat_supplies', 'exp_cat_transport', 'exp_cat_marketing',
    'exp_cat_maintenance', 'exp_cat_misc', 'exp_create_success', 'exp_action_success',
    'status_pending', 'status_approved', 'status_rejected', 'confirm', 'save', 'cancel', 'close',
    'cr_export_csv', 'refresh', 'loading', 'no_data', 'cr_no_data', 'load_error', 'error',
    'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-exp-hero" aria-labelledby="accExpHeroTitle">
    <div class="acc-exp-hero__intro">
        <h2 class="acc-exp-hero__title" id="accExpHeroTitle"><?php echo __t('exp_subtitle', 'accounting'); ?></h2>
        <p class="acc-exp-hero__count" id="accExpCount" aria-live="polite">—</p>
    </div>
    <div class="acc-exp-hero__stats" id="accExpStats" role="group">
        <button type="button" class="acc-exp-stat acc-exp-stat--click" data-stat-filter="all">
            <span class="acc-exp-stat__label"><?php echo __t('exp_stat_total', 'accounting'); ?></span>
            <strong class="acc-exp-stat__value is-loading" id="accExpStatTotal">—</strong>
        </button>
        <button type="button" class="acc-exp-stat acc-exp-stat--warn acc-exp-stat--click" data-stat-filter="pending">
            <span class="acc-exp-stat__label"><?php echo __t('exp_stat_pending', 'accounting'); ?></span>
            <strong class="acc-exp-stat__value is-loading" id="accExpStatPending">—</strong>
        </button>
        <button type="button" class="acc-exp-stat acc-exp-stat--success acc-exp-stat--click" data-stat-filter="approved">
            <span class="acc-exp-stat__label"><?php echo __t('exp_stat_approved', 'accounting'); ?></span>
            <strong class="acc-exp-stat__value is-loading" id="accExpStatApproved">—</strong>
        </button>
        <button type="button" class="acc-exp-stat acc-exp-stat--danger acc-exp-stat--click" data-stat-filter="rejected">
            <span class="acc-exp-stat__label"><?php echo __t('exp_stat_rejected', 'accounting'); ?></span>
            <strong class="acc-exp-stat__value is-loading" id="accExpStatRejected">—</strong>
        </button>
    </div>
</section>

<div class="acc-exp-toolbar">
    <div class="acc-exp-toolbar__top">
        <div class="acc-exp-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accExpSearch" placeholder="<?php echo htmlspecialchars(__t('exp_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-exp-search-clear" id="accExpSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-exp-toolbar__dates">
            <label class="acc-exp-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accExpDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-exp-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accExpDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-exp-toolbar__actions">
            <button type="button" class="acc-btn" id="accExpNewBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-exp-btn-label"><?php echo __t('exp_new_btn', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accExpExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-exp-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accExpRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-exp-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-exp-toolbar__filters" id="accExpStatusFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('exp_filter_status', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-exp-chip is-active" data-status="all" role="tab" aria-selected="true"><?php echo __t('exp_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-exp-chip" data-status="pending" role="tab"><?php echo __t('status_pending', 'accounting'); ?></button>
        <button type="button" class="acc-exp-chip" data-status="approved" role="tab"><?php echo __t('status_approved', 'accounting'); ?></button>
        <button type="button" class="acc-exp-chip" data-status="rejected" role="tab"><?php echo __t('status_rejected', 'accounting'); ?></button>
    </div>
    <div class="acc-exp-toolbar__filters acc-exp-toolbar__filters--cats" id="accExpCategoryFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('exp_filter_category', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" hidden>
        <button type="button" class="acc-exp-chip is-active" data-category="" role="tab" aria-selected="true"><?php echo __t('exp_filter_all', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-exp-panel">
    <div class="acc-exp-panel__head">
        <span class="acc-exp-panel__meta" id="accExpMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-exp-pagination">
            <button type="button" class="acc-exp-page-btn" id="accExpPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accExpPageInfo">1 / 1</span>
            <button type="button" class="acc-exp-page-btn" id="accExpNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accExpRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<div class="acc-exp-modal-overlay" id="accExpFormModal" hidden>
    <div class="acc-exp-modal" role="dialog" aria-labelledby="accExpFormTitle">
        <header class="acc-exp-modal__head">
            <h2 id="accExpFormTitle"><?php echo __t('exp_modal_new_title', 'accounting'); ?></h2>
            <button type="button" class="acc-exp-modal__close" id="accExpFormClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-exp-form" id="accExpForm">
            <label class="acc-exp-field">
                <span><?php echo __t('exp_form_category', 'accounting'); ?></span>
                <select name="category" required id="accExpFormCategory">
                    <option value="rent"><?php echo __t('exp_cat_rent', 'accounting'); ?></option>
                    <option value="utilities"><?php echo __t('exp_cat_utilities', 'accounting'); ?></option>
                    <option value="salaries"><?php echo __t('exp_cat_salaries', 'accounting'); ?></option>
                    <option value="supplies"><?php echo __t('exp_cat_supplies', 'accounting'); ?></option>
                    <option value="transport"><?php echo __t('exp_cat_transport', 'accounting'); ?></option>
                    <option value="marketing"><?php echo __t('exp_cat_marketing', 'accounting'); ?></option>
                    <option value="maintenance"><?php echo __t('exp_cat_maintenance', 'accounting'); ?></option>
                    <option value="misc" selected><?php echo __t('exp_cat_misc', 'accounting'); ?></option>
                </select>
            </label>
            <label class="acc-exp-field">
                <span><?php echo __t('exp_form_amount', 'accounting'); ?></span>
                <input type="number" name="amount" min="0.01" step="0.01" required placeholder="0.00">
            </label>
            <label class="acc-exp-field">
                <span><?php echo __t('exp_form_date', 'accounting'); ?></span>
                <input type="date" name="expense_date" value="<?php echo $today; ?>" required>
            </label>
            <label class="acc-exp-field">
                <span><?php echo __t('exp_form_payment', 'accounting'); ?></span>
                <select name="payment_method" required>
                    <option value="cash"><?php echo __t('exp_payment_cash', 'accounting'); ?></option>
                    <option value="bank"><?php echo __t('exp_payment_bank', 'accounting'); ?></option>
                    <option value="mobile_money"><?php echo __t('exp_payment_mobile', 'accounting'); ?></option>
                </select>
            </label>
            <label class="acc-exp-field acc-exp-field--full">
                <span><?php echo __t('exp_form_description', 'accounting'); ?></span>
                <textarea name="description" rows="3" placeholder="…"></textarea>
            </label>
            <footer class="acc-exp-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accExpFormCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accExpFormSubmit">
                    <span class="material-icons-round">save</span>
                    <?php echo __t('exp_form_submit', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>

<div class="acc-exp-modal-overlay" id="accExpDetailModal" hidden>
    <div class="acc-exp-modal acc-exp-modal--wide" role="dialog" aria-labelledby="accExpDetailTitle">
        <header class="acc-exp-modal__head">
            <h2 id="accExpDetailTitle"><?php echo __t('exp_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-exp-modal__close" id="accExpDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-exp-detail" id="accExpDetailBody"></div>
        <footer class="acc-exp-modal__foot" id="accExpDetailActions" hidden>
            <button type="button" class="acc-btn acc-btn--ghost acc-btn--danger" id="accExpRejectBtn">
                <span class="material-icons-round">close</span>
                <?php echo __t('exp_reject', 'accounting'); ?>
            </button>
            <button type="button" class="acc-btn" id="accExpApproveBtn">
                <span class="material-icons-round">check_circle</span>
                <?php echo __t('exp_approve', 'accounting'); ?>
            </button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

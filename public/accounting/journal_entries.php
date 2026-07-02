<?php
require __DIR__ . '/includes/bootstrap.php';
$activeAccPage = 'journal';
$pageTitle = __t('nav_journal', 'accounting');
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$extraScripts = ['accounting-common.js', 'accounting-journal.js'];
$pageI18n = acc_i18n([
    'je_subtitle', 'je_stat_volume', 'je_stat_entries', 'je_stat_manual', 'je_stat_auto',
    'je_search_placeholder', 'je_filter_status', 'je_filter_source', 'je_filter_all',
    'je_new_btn', 'je_table_summary', 'je_col_date', 'je_col_entry_no', 'je_col_description',
    'je_col_reference', 'je_col_debit', 'je_col_credit', 'je_col_status', 'je_col_created_by',
    'je_view_details', 'je_detail_title', 'je_modal_new_title', 'je_form_date', 'je_form_description',
    'je_form_submit', 'je_form_add_line', 'je_form_remove_line', 'je_form_account', 'je_form_debit',
    'je_form_credit', 'je_form_memo', 'je_balance_ok', 'je_balance_error', 'je_lines_title',
    'je_ref_manual', 'je_ref_sale', 'je_ref_expense', 'je_ref_payment', 'je_ref_purchase', 'je_ref_inventory',
    'je_status_posted', 'je_status_draft', 'je_status_void', 'je_post_success',
    'status_pending', 'confirm', 'save', 'cancel', 'close',
    'cr_export_csv', 'refresh', 'loading', 'no_data', 'cr_no_data', 'load_error', 'error',
    'start_date', 'end_date', 'clear_search', 'prev_page', 'next_page', 'records',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="acc-je-hero" aria-labelledby="accJeHeroTitle">
    <div class="acc-je-hero__intro">
        <h2 class="acc-je-hero__title" id="accJeHeroTitle"><?php echo __t('je_subtitle', 'accounting'); ?></h2>
        <p class="acc-je-hero__count" id="accJeCount" aria-live="polite">—</p>
    </div>
    <div class="acc-je-hero__stats" id="accJeStats" role="group">
        <button type="button" class="acc-je-stat acc-je-stat--primary acc-je-stat--click" data-stat-filter="all">
            <span class="acc-je-stat__label"><?php echo __t('je_stat_volume', 'accounting'); ?></span>
            <strong class="acc-je-stat__value is-loading" id="accJeStatVolume">—</strong>
        </button>
        <button type="button" class="acc-je-stat acc-je-stat--click" data-stat-filter="all">
            <span class="acc-je-stat__label"><?php echo __t('je_stat_entries', 'accounting'); ?></span>
            <strong class="acc-je-stat__value is-loading" id="accJeStatEntries">—</strong>
        </button>
        <button type="button" class="acc-je-stat acc-je-stat--click" data-stat-filter="manual">
            <span class="acc-je-stat__label"><?php echo __t('je_stat_manual', 'accounting'); ?></span>
            <strong class="acc-je-stat__value is-loading" id="accJeStatManual">—</strong>
        </button>
        <button type="button" class="acc-je-stat acc-je-stat--success acc-je-stat--click" data-stat-filter="auto">
            <span class="acc-je-stat__label"><?php echo __t('je_stat_auto', 'accounting'); ?></span>
            <strong class="acc-je-stat__value is-loading" id="accJeStatAuto">—</strong>
        </button>
    </div>
</section>

<div class="acc-je-toolbar">
    <div class="acc-je-toolbar__top">
        <div class="acc-je-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="accJeSearch" placeholder="<?php echo htmlspecialchars(__t('je_search_placeholder', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="acc-je-search-clear" id="accJeSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="acc-je-toolbar__dates">
            <label class="acc-je-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accJeDateFrom" value="<?php echo $monthStart; ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="acc-je-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="accJeDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="acc-je-toolbar__actions">
            <?php if ($canManageAccounting): ?>
            <button type="button" class="acc-btn" id="accJeNewBtn">
                <span class="material-icons-round">add</span>
                <span class="acc-je-btn-label"><?php echo __t('je_new_btn', 'accounting'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="acc-btn acc-btn--ghost" id="accJeExportBtn">
                <span class="material-icons-round">download</span>
                <span class="acc-je-btn-label"><?php echo __t('cr_export_csv', 'accounting'); ?></span>
            </button>
            <button type="button" class="acc-btn acc-btn--ghost" id="accJeRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="acc-je-btn-label"><?php echo __t('refresh', 'accounting'); ?></span>
            </button>
        </div>
    </div>
    <div class="acc-je-toolbar__filters" id="accJeStatusFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('je_filter_status', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-je-chip is-active" data-status="all" role="tab" aria-selected="true"><?php echo __t('je_filter_all', 'accounting'); ?></button>
        <button type="button" class="acc-je-chip" data-status="posted" role="tab"><?php echo __t('je_status_posted', 'accounting'); ?></button>
        <button type="button" class="acc-je-chip" data-status="draft" role="tab"><?php echo __t('je_status_draft', 'accounting'); ?></button>
        <button type="button" class="acc-je-chip" data-status="void" role="tab"><?php echo __t('je_status_void', 'accounting'); ?></button>
    </div>
    <div class="acc-je-toolbar__filters acc-je-toolbar__filters--refs" id="accJeRefFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('je_filter_source', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>" hidden>
        <button type="button" class="acc-je-chip is-active" data-reference="" role="tab" aria-selected="true"><?php echo __t('je_filter_all', 'accounting'); ?></button>
    </div>
</div>

<div class="acc-je-panel">
    <div class="acc-je-panel__head">
        <span class="acc-je-panel__meta" id="accJeMeta"><?php echo __t('loading', 'accounting'); ?></span>
        <div class="acc-je-pagination">
            <button type="button" class="acc-je-page-btn" id="accJePrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_left</span>
            </button>
            <span id="accJePageInfo">1 / 1</span>
            <button type="button" class="acc-je-page-btn" id="accJeNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">chevron_right</span>
            </button>
        </div>
    </div>
    <div id="accJeRoot">
        <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
    </div>
</div>

<?php if ($canManageAccounting): ?>
<div class="acc-je-modal-overlay" id="accJeFormModal" hidden>
    <div class="acc-je-modal acc-je-modal--wide" role="dialog" aria-labelledby="accJeFormTitle">
        <header class="acc-je-modal__head">
            <h2 id="accJeFormTitle"><?php echo __t('je_modal_new_title', 'accounting'); ?></h2>
            <button type="button" class="acc-je-modal__close" id="accJeFormClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form class="acc-je-form" id="accJeForm">
            <div class="acc-je-form__row">
                <label class="acc-je-field">
                    <span><?php echo __t('je_form_date', 'accounting'); ?></span>
                    <input type="date" name="entry_date" value="<?php echo $today; ?>" required>
                </label>
                <label class="acc-je-field acc-je-field--grow">
                    <span><?php echo __t('je_form_description', 'accounting'); ?></span>
                    <input type="text" name="description" required maxlength="255" placeholder="…">
                </label>
            </div>
            <div class="acc-je-lines-head">
                <h3><?php echo __t('je_lines_title', 'accounting'); ?></h3>
                <button type="button" class="acc-btn acc-btn--ghost" id="accJeAddLine">
                    <span class="material-icons-round">add</span>
                    <?php echo __t('je_form_add_line', 'accounting'); ?>
                </button>
            </div>
            <div class="acc-je-lines" id="accJeLines"></div>
            <div class="acc-je-balance" id="accJeBalance" data-state="error">
                <span class="material-icons-round">info</span>
                <span id="accJeBalanceText">—</span>
            </div>
            <footer class="acc-je-modal__foot">
                <button type="button" class="acc-btn acc-btn--ghost" id="accJeFormCancel"><?php echo __t('cancel', 'accounting'); ?></button>
                <button type="submit" class="acc-btn" id="accJeFormSubmit" disabled>
                    <span class="material-icons-round">save</span>
                    <?php echo __t('je_form_submit', 'accounting'); ?>
                </button>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="acc-je-modal-overlay" id="accJeDetailModal" hidden>
    <div class="acc-je-modal acc-je-modal--wide" role="dialog" aria-labelledby="accJeDetailTitle">
        <header class="acc-je-modal__head">
            <h2 id="accJeDetailTitle"><?php echo __t('je_detail_title', 'accounting'); ?></h2>
            <button type="button" class="acc-je-modal__close" id="accJeDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'accounting'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="acc-je-detail" id="accJeDetailBody"></div>
        <footer class="acc-je-modal__foot">
            <button type="button" class="acc-btn acc-btn--ghost" id="accJeDetailOk"><?php echo __t('close', 'accounting'); ?></button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

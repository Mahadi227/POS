<?php
/**
 * Réconciliation caisse — review session close-out variances (approve / reject).
 * @see docs/cash-registers/reconciliation.md
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'reconciliation';
$pageTitle = __t('cr_recon_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-reconciliation.js'];
$pageI18n = cr_i18n([
    'cr_recon_subtitle', 'cr_recon_approve', 'cr_recon_reject', 'cr_col_expected', 'cr_col_physical', 'cr_col_difference',
    'cr_col_register', 'cr_col_cashier', 'cr_branch', 'cr_no_data', 'cr_no_recon_hint', 'cr_recon_search_placeholder',
    'cr_filter_all', 'cr_filter_pending', 'cr_filter_approved', 'cr_filter_rejected', 'cr_recon_count',
    'cr_recon_pending', 'cr_recon_approved', 'cr_recon_rejected', 'cr_recon_variance_total', 'cr_recon_review_title',
    'cr_recon_note_label', 'cr_recon_note_optional', 'cr_recon_note_required', 'cr_recon_modal_approve', 'cr_recon_modal_reject',
    'cr_recon_table_summary', 'cr_recon_detail_title', 'cr_recon_reviewed_by', 'cr_recon_session', 'cr_recon_auto_approved',
    'cr_recon_high_variance', 'cr_recon_tolerance_hint', 'cr_recon_stat_today', 'cr_view_grid', 'cr_view_table',
    'col_status', 'col_date', 'status_pending', 'status_approved', 'status_rejected', 'cr_view_details', 'cr_register_details',
    'cancel', 'confirm', 'error', 'loading', 'refresh', 'clear_search', 'start_date', 'end_date', 'prev_page', 'next_page',
    'close', 'load_error', 'cr_export_csv',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-recon-hero" aria-labelledby="crReconHeroTitle">
    <div class="cr-recon-hero__intro">
        <div class="cr-recon-hero__title-row">
            <h2 class="cr-recon-hero__title" id="crReconHeroTitle"><?php echo __t('cr_recon_subtitle', 'admin'); ?></h2>
            <span class="cr-recon-hero__badge" id="crReconPendingBadge" hidden aria-live="polite">0</span>
        </div>
        <p class="cr-recon-hero__hint"><?php echo __t('cr_recon_tolerance_hint', 'admin'); ?></p>
        <p class="cr-recon-hero__count" id="crReconCount" aria-live="polite">—</p>
    </div>
    <div class="cr-recon-hero__stats" id="crReconStats" role="group" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-recon-stat cr-recon-stat--warn cr-recon-stat--click" data-stat-filter="pending">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_pending', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatPending">—</strong>
        </button>
        <button type="button" class="cr-recon-stat cr-recon-stat--success cr-recon-stat--click" data-stat-filter="approved">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_approved', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatApproved">—</strong>
        </button>
        <button type="button" class="cr-recon-stat cr-recon-stat--danger cr-recon-stat--click" data-stat-filter="rejected">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_rejected', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatRejected">—</strong>
        </button>
        <div class="cr-recon-stat">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_variance_total', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatVariance">—</strong>
        </div>
        <div class="cr-recon-stat">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_stat_today', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatToday">—</strong>
        </div>
    </div>
</section>

<div class="cr-recon-toolbar">
    <div class="cr-recon-toolbar__top">
        <div class="cr-recon-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="crReconSearch" placeholder="<?php echo htmlspecialchars(__t('cr_recon_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="cr-recon-search-clear" id="crReconSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="cr-recon-toolbar__dates">
            <label class="cr-recon-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crReconDateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="cr-recon-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crReconDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="cr-recon-toolbar__actions">
            <div class="cr-reg-view-toggle" role="group">
                <button type="button" class="cr-reg-view-btn is-active" data-view="grid" title="<?php echo htmlspecialchars(__t('cr_view_grid', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">grid_view</span></button>
                <button type="button" class="cr-reg-view-btn" data-view="table" title="<?php echo htmlspecialchars(__t('cr_view_table', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">table_rows</span></button>
            </div>
            <button type="button" class="cr-btn cr-btn--ghost" id="crReconExportBtn">
                <span class="material-icons-round">download</span>
                <span class="cr-recon-btn-label"><?php echo __t('cr_export_csv', 'admin'); ?></span>
            </button>
            <button type="button" class="cr-btn" id="crReconRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="cr-recon-btn-label"><?php echo __t('refresh', 'admin'); ?></span>
            </button>
        </div>
    </div>
    <div class="cr-recon-toolbar__filters cr-reg-filters" id="crReconFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip is-active" data-filter="all" role="tab" aria-selected="true"><?php echo __t('cr_filter_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="pending" role="tab"><?php echo __t('cr_filter_pending', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="approved" role="tab"><?php echo __t('cr_filter_approved', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="rejected" role="tab"><?php echo __t('cr_filter_rejected', 'admin'); ?></button>
    </div>
</div>

<div class="cr-panel cr-recon-panel">
    <div class="cr-recon-meta" id="crReconMeta"><?php echo __t('loading', 'admin'); ?></div>
    <div class="cr-recon-pagination">
        <button type="button" class="cr-recon-page-btn" id="crReconPrev" disabled aria-label="<?php echo htmlspecialchars(__t('prev_page', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="material-icons-round">chevron_left</span>
        </button>
        <span id="crReconPageInfo">1 / 1</span>
        <button type="button" class="cr-recon-page-btn" id="crReconNext" disabled aria-label="<?php echo htmlspecialchars(__t('next_page', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            <span class="material-icons-round">chevron_right</span>
        </button>
    </div>
    <div id="crReconRoot" class="cr-recon-root">
        <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
    </div>
</div>

<div class="cr-modal-overlay" id="crReconDetailModal" hidden>
    <div class="cr-modal cr-recon-detail-modal" role="dialog" aria-labelledby="crReconDetailTitle">
        <header class="cr-recon-detail-modal__head">
            <h2 id="crReconDetailTitle"><?php echo __t('cr_recon_detail_title', 'admin'); ?></h2>
            <button type="button" class="cr-recon-detail-modal__close" id="crReconDetailClose" aria-label="<?php echo htmlspecialchars(__t('close', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div class="cr-recon-detail-modal__body" id="crReconDetailBody"></div>
    </div>
</div>

<div class="cr-modal" id="crReconModal" hidden aria-hidden="true">
    <div class="cr-modal__backdrop" data-close-recon-modal></div>
    <div class="cr-modal__dialog cr-modal__dialog--wide" role="dialog" aria-modal="true" aria-labelledby="crReconModalTitle">
        <header class="cr-modal__head">
            <h3 id="crReconModalTitle"><?php echo __t('cr_recon_review_title', 'admin'); ?></h3>
            <button type="button" class="cr-modal__close" data-close-recon-modal aria-label="<?php echo htmlspecialchars(__t('close', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">close</span></button>
        </header>
        <div class="cr-modal__body cr-recon-modal__body">
            <div class="cr-recon-modal__summary" id="crReconModalSummary"></div>
            <form class="cr-form" id="crReconModalForm">
                <input type="hidden" name="recon_id" id="crReconModalId">
                <input type="hidden" name="decision" id="crReconModalDecision">
                <label for="crReconModalNote" id="crReconNoteLabel">
                    <span id="crReconNoteLabelText"><?php echo __t('cr_recon_note_label', 'admin'); ?></span>
                    <textarea name="note" id="crReconModalNote" rows="3" placeholder=""></textarea>
                </label>
                <div class="cr-form-actions">
                    <button type="button" class="cr-btn cr-btn--ghost" data-close-recon-modal><?php echo __t('cancel', 'admin'); ?></button>
                    <button type="submit" class="cr-btn" id="crReconModalSubmit"><?php echo __t('confirm', 'admin'); ?></button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

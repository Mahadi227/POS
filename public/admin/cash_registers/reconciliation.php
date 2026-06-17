<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'reconciliation';
$pageTitle = __t('cr_recon_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-reconciliation.js'];
$pageI18n = cr_i18n([
    'cr_recon_subtitle', 'cr_recon_approve', 'cr_recon_reject', 'cr_col_expected', 'cr_col_physical', 'cr_col_difference',
    'cr_col_register', 'cr_col_cashier', 'cr_branch', 'cr_no_data', 'cr_no_recon_hint', 'cr_recon_search_placeholder',
    'cr_filter_all', 'cr_filter_pending', 'cr_filter_approved', 'cr_filter_rejected', 'cr_recon_count',
    'cr_recon_pending', 'cr_recon_approved', 'cr_recon_rejected', 'cr_recon_variance_total', 'cr_recon_review_title',
    'cr_recon_note_label', 'cr_recon_note_optional', 'cr_recon_note_required', 'cr_recon_modal_approve', 'cr_recon_modal_reject',
    'col_status', 'col_date', 'status_pending', 'status_approved', 'status_rejected', 'cr_view_details', 'cancel', 'confirm', 'error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-recon-hero" aria-labelledby="crReconHeroTitle">
    <div class="cr-recon-hero__intro">
        <h2 class="cr-recon-hero__title" id="crReconHeroTitle"><?php echo __t('cr_recon_subtitle', 'admin'); ?></h2>
        <p class="cr-recon-hero__count" id="crReconCount" aria-live="polite">—</p>
    </div>
    <div class="cr-recon-hero__stats">
        <div class="cr-recon-stat cr-recon-stat--warn">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_pending', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatPending">—</strong>
        </div>
        <div class="cr-recon-stat cr-recon-stat--success">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_approved', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatApproved">—</strong>
        </div>
        <div class="cr-recon-stat cr-recon-stat--danger">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_rejected', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatRejected">—</strong>
        </div>
        <div class="cr-recon-stat">
            <span class="cr-recon-stat__label"><?php echo __t('cr_recon_variance_total', 'admin'); ?></span>
            <strong class="cr-recon-stat__value is-loading" id="crReconStatVariance">—</strong>
        </div>
    </div>
</section>

<div class="cr-reg-toolbar cr-recon-toolbar">
    <div class="cr-reg-toolbar__search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="crReconSearch" class="cr-reg-search" placeholder="<?php echo htmlspecialchars(__t('cr_recon_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
    </div>
    <div class="cr-reg-filters" id="crReconFilters" role="tablist">
        <button type="button" class="cr-reg-chip is-active" data-filter="all" role="tab" aria-selected="true"><?php echo __t('cr_filter_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="pending" role="tab"><?php echo __t('cr_filter_pending', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="approved" role="tab"><?php echo __t('cr_filter_approved', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="rejected" role="tab"><?php echo __t('cr_filter_rejected', 'admin'); ?></button>
    </div>
</div>

<div id="crReconRoot" class="cr-recon-root">
    <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
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

<?php
/**
 * Cash transfers — request, approve, and complete register-to-safe transfers.
 */
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'transfers';
$pageTitle = __t('cr_transfers_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-transfers.js'];
$pageI18n = cr_i18n([
    'cr_tr_subtitle', 'cr_tr_stat_pending', 'cr_tr_stat_approved', 'cr_tr_stat_completed', 'cr_tr_stat_amount',
    'cr_tr_search_placeholder', 'cr_tr_new', 'cr_tr_complete', 'cr_tr_table_summary', 'cr_tr_modal_title',
    'cr_tr_modal_submit', 'cr_transfer_type', 'cr_tr_type_register_to_safe', 'cr_amount', 'cr_reason',
    'cr_filter_all', 'cr_filter_pending', 'cr_filter_approved', 'status_completed', 'col_status', 'col_date',
    'cr_col_register', 'cr_col_cashier', 'cr_recon_approve', 'cr_no_data', 'loading', 'refresh', 'clear_search',
    'start_date', 'end_date', 'prev_page', 'next_page', 'cancel', 'confirm', 'load_error', 'error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-data-hero" aria-labelledby="crTrHeroTitle">
    <div class="cr-data-hero__intro">
        <div class="cr-data-hero__title-row">
            <h2 class="cr-data-hero__title" id="crTrHeroTitle"><?php echo __t('cr_tr_subtitle', 'admin'); ?></h2>
            <span class="cr-data-hero__badge" id="crTrPendingBadge" hidden aria-live="polite">0</span>
        </div>
        <p class="cr-data-hero__count" id="crTrCount" aria-live="polite">—</p>
    </div>
    <div class="cr-data-hero__stats" id="crTrStats">
        <button type="button" class="cr-data-stat cr-data-stat--warn cr-data-stat--click" data-stat-filter="pending">
            <span class="cr-data-stat__label"><?php echo __t('cr_tr_stat_pending', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crTrStatPending">—</strong>
        </button>
        <button type="button" class="cr-data-stat cr-data-stat--primary cr-data-stat--click" data-stat-filter="approved">
            <span class="cr-data-stat__label"><?php echo __t('cr_tr_stat_approved', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crTrStatApproved">—</strong>
        </button>
        <button type="button" class="cr-data-stat cr-data-stat--success cr-data-stat--click" data-stat-filter="completed">
            <span class="cr-data-stat__label"><?php echo __t('cr_tr_stat_completed', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crTrStatCompleted">—</strong>
        </button>
        <div class="cr-data-stat">
            <span class="cr-data-stat__label"><?php echo __t('cr_tr_stat_amount', 'admin'); ?></span>
            <strong class="cr-data-stat__value is-loading" id="crTrStatAmount">—</strong>
        </div>
    </div>
</section>

<div class="cr-data-toolbar">
    <div class="cr-data-toolbar__top">
        <div class="cr-data-search">
            <span class="material-icons-round" aria-hidden="true">search</span>
            <input type="search" id="crTrSearch" placeholder="<?php echo htmlspecialchars(__t('cr_tr_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            <button type="button" class="cr-data-search-clear" id="crTrSearchClear" aria-label="<?php echo htmlspecialchars(__t('clear_search', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="cr-data-toolbar__dates">
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crTrDateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>" aria-label="<?php echo htmlspecialchars(__t('start_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
            <label class="cr-data-date">
                <span class="material-icons-round">calendar_today</span>
                <input type="date" id="crTrDateTo" value="<?php echo $today; ?>" aria-label="<?php echo htmlspecialchars(__t('end_date', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            </label>
        </div>
        <div class="cr-data-toolbar__actions">
            <button type="button" class="cr-btn" id="crTrNewBtn">
                <span class="material-icons-round">add</span>
                <?php echo __t('cr_tr_new', 'admin'); ?>
            </button>
            <button type="button" class="cr-btn" id="crTrRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <?php echo __t('refresh', 'admin'); ?>
            </button>
        </div>
    </div>
    <div class="cr-data-toolbar__filters" id="crTrStatusFilters" role="tablist">
        <button type="button" class="cr-reg-chip is-active" data-status="all" role="tab"><?php echo __t('cr_filter_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-status="pending" role="tab"><?php echo __t('cr_filter_pending', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-status="approved" role="tab"><?php echo __t('cr_filter_approved', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-status="completed" role="tab"><?php echo __t('status_completed', 'admin'); ?></button>
    </div>
</div>

<div class="cr-data-panel">
    <div class="cr-data-panel__head">
        <span class="cr-data-panel__meta" id="crTrMeta"><?php echo __t('loading', 'admin'); ?></span>
        <div class="cr-data-pagination">
            <button type="button" class="cr-data-page-btn" id="crTrPrev" disabled aria-label="<?php echo __t('prev_page', 'admin'); ?>"><span class="material-icons-round">chevron_left</span></button>
            <span id="crTrPageInfo">1 / 1</span>
            <button type="button" class="cr-data-page-btn" id="crTrNext" disabled aria-label="<?php echo __t('next_page', 'admin'); ?>"><span class="material-icons-round">chevron_right</span></button>
        </div>
    </div>
    <div id="crTrRoot"></div>
</div>

<div class="cr-modal" id="crTrModal" hidden role="dialog" aria-modal="true" aria-labelledby="crTrModalTitle">
    <div class="cr-modal__backdrop" data-close-modal></div>
    <div class="cr-modal__dialog">
        <header class="cr-modal__head">
            <h3 id="crTrModalTitle"><?php echo __t('cr_tr_modal_title', 'admin'); ?></h3>
            <button type="button" class="cr-modal__close" data-close-modal aria-label="<?php echo __t('cancel', 'admin'); ?>"><span class="material-icons-round">close</span></button>
        </header>
        <form id="crTrModalForm" class="cr-modal__body">
            <label class="cr-field">
                <span><?php echo __t('cr_transfer_type', 'admin'); ?></span>
                <select id="crTrType" required>
                    <option value="register_to_safe"><?php echo __t('cr_tr_type_register_to_safe', 'admin'); ?></option>
                </select>
            </label>
            <label class="cr-field">
                <span><?php echo __t('cr_amount', 'admin'); ?></span>
                <input type="number" id="crTrAmount" min="1" step="0.01" required placeholder="0">
            </label>
            <label class="cr-field">
                <span><?php echo __t('cr_reason', 'admin'); ?></span>
                <textarea id="crTrReason" rows="3" placeholder="—"></textarea>
            </label>
            <footer class="cr-modal__foot">
                <button type="button" class="cr-btn cr-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
                <button type="submit" class="cr-btn" id="crTrModalSubmit"><?php echo __t('cr_tr_modal_submit', 'admin'); ?></button>
            </footer>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

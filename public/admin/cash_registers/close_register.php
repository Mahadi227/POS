<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_close_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-close.js'];
$pageI18n = cr_i18n([
    'cr_close_subtitle', 'cr_close_register', 'cr_close_select_register', 'cr_close_open_sessions',
    'cr_close_summary', 'cr_close_confirm', 'cr_close_notes', 'cr_close_notes_placeholder',
    'cr_counted_cash', 'cr_stat_expected', 'cr_col_difference', 'cr_stat_cash_balance',
    'cr_register_name', 'cr_register_code', 'cr_branch', 'cr_assigned_cashier', 'cr_session_open',
    'cr_variance_within', 'cr_variance_alert_short', 'cr_no_open_sessions', 'cr_open_register',
    'cr_view_registers', 'cr_use_expected', 'cancel', 'error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-session-hero cr-close-hero" aria-labelledby="crCloseHeroTitle">
    <div class="cr-session-hero__body">
        <h2 class="cr-session-hero__title" id="crCloseHeroTitle"><?php echo __t('cr_close_subtitle', 'admin'); ?></h2>
        <p class="cr-session-hero__hint" id="crCloseHeroHint">—</p>
    </div>
    <div class="cr-session-hero__stats">
        <div class="cr-session-stat cr-session-stat--primary">
            <span class="cr-session-stat__label"><?php echo __t('cr_close_open_sessions', 'admin'); ?></span>
            <strong class="cr-session-stat__value is-loading" id="crCloseStatOpen">—</strong>
        </div>
        <div class="cr-session-stat cr-session-stat--warn">
            <span class="cr-session-stat__label"><?php echo __t('cr_stat_expected', 'admin'); ?></span>
            <strong class="cr-session-stat__value is-loading" id="crCloseStatExpected">—</strong>
        </div>
    </div>
</section>

<div class="cr-open-layout cr-close-layout">
    <aside class="cr-open-picker" aria-labelledby="crClosePickerTitle">
        <header class="cr-open-picker__head">
            <h3 id="crClosePickerTitle"><?php echo __t('cr_close_select_register', 'admin'); ?></h3>
            <a href="registers.php" class="cr-panel__link"><?php echo __t('cr_view_registers', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>
        </header>
        <div class="cr-open-picker__list" id="crCloseRegisterList">
            <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
        </div>
    </aside>

    <section class="cr-open-form-panel" aria-labelledby="crCloseFormTitle">
        <header class="cr-open-form-panel__head">
            <h3 id="crCloseFormTitle"><?php echo __t('cr_close_summary', 'admin'); ?></h3>
        </header>
        <div class="cr-open-summary" id="crCloseSummary">
            <p class="cr-open-summary__placeholder"><?php echo __t('cr_close_select_register', 'admin'); ?></p>
        </div>
        <form class="cr-form cr-open-form cr-close-form" id="crCloseForm" hidden>
            <input type="hidden" name="session_id" id="crCloseSessionId">

            <div class="cr-close-expected" id="crCloseExpectedBox" role="status">
                <span class="cr-close-expected__label"><?php echo __t('cr_stat_expected', 'admin'); ?></span>
                <strong class="cr-close-expected__value" id="crCloseExpectedValue">—</strong>
                <button type="button" class="cr-btn cr-btn--ghost cr-btn--sm" id="crCloseUseExpected">
                    <span class="material-icons-round">content_copy</span>
                    <?php echo __t('cr_use_expected', 'admin'); ?>
                </button>
            </div>

            <label for="crCloseCounted"><?php echo __t('cr_counted_cash', 'admin'); ?></label>
            <div class="cr-open-amount-row">
                <input type="number" name="counted_cash" id="crCloseCounted" min="0" step="0.01" value="0" required>
            </div>

            <div class="cr-close-variance" id="crCloseVarianceBox" aria-live="polite">
                <span class="cr-close-variance__label"><?php echo __t('cr_col_difference', 'admin'); ?></span>
                <strong class="cr-close-variance__value" id="crCloseVarianceValue">—</strong>
                <span class="cr-close-variance__hint" id="crCloseVarianceHint"></span>
            </div>

            <label for="crCloseNotes"><?php echo __t('cr_close_notes', 'admin'); ?>
                <textarea name="notes" id="crCloseNotes" rows="2" placeholder="<?php echo htmlspecialchars(__t('cr_close_notes_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
            </label>

            <div class="cr-form-actions">
                <a href="registers.php" class="cr-btn cr-btn--ghost"><?php echo __t('cancel', 'admin'); ?></a>
                <button type="submit" class="cr-btn cr-btn--warn" id="crCloseSubmitBtn">
                    <span class="material-icons-round">lock</span>
                    <?php echo __t('cr_close_confirm', 'admin'); ?>
                </button>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

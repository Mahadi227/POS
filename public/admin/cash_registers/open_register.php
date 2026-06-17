<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_open_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-open.js'];
$pageI18n = cr_i18n([
    'cr_open_subtitle', 'cr_open_register', 'cr_opening_balance', 'cr_shift_morning', 'cr_shift_afternoon',
    'cr_shift_evening', 'cr_shift_night', 'cr_register_name', 'cr_register_code', 'cr_branch', 'cr_assigned_cashier',
    'cr_open_select_register', 'cr_open_available', 'cr_open_already_open', 'cr_open_notes', 'cr_open_notes_placeholder',
    'cr_open_summary', 'cr_open_confirm', 'cr_stat_cash_balance', 'cr_no_registers', 'cr_no_open_available',
    'cr_view_registers', 'cr_quick_amount', 'cr_session_open', 'cr_close_register', 'cancel', 'save', 'error', 'load_error', 'cr_nav_shifts',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-session-hero cr-open-hero" aria-labelledby="crOpenHeroTitle">
    <div class="cr-session-hero__body">
        <h2 class="cr-session-hero__title" id="crOpenHeroTitle"><?php echo __t('cr_open_subtitle', 'admin'); ?></h2>
        <p class="cr-session-hero__hint" id="crOpenHeroHint">—</p>
    </div>
    <div class="cr-session-hero__stats">
        <div class="cr-session-stat cr-session-stat--primary">
            <span class="cr-session-stat__label"><?php echo __t('cr_open_available', 'admin'); ?></span>
            <strong class="cr-session-stat__value is-loading" id="crOpenStatAvailable">—</strong>
        </div>
        <div class="cr-session-stat">
            <span class="cr-session-stat__label"><?php echo __t('cr_open_already_open', 'admin'); ?></span>
            <strong class="cr-session-stat__value is-loading" id="crOpenStatBusy">—</strong>
        </div>
    </div>
</section>

<div class="cr-open-layout">
    <aside class="cr-open-picker" aria-labelledby="crOpenPickerTitle">
        <header class="cr-open-picker__head">
            <h3 id="crOpenPickerTitle"><?php echo __t('cr_open_select_register', 'admin'); ?></h3>
            <a href="registers.php" class="cr-panel__link"><?php echo __t('cr_view_registers', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>
        </header>
        <div class="cr-open-picker__list" id="crOpenRegisterList">
            <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
        </div>
    </aside>

    <section class="cr-open-form-panel" aria-labelledby="crOpenFormTitle">
        <header class="cr-open-form-panel__head">
            <h3 id="crOpenFormTitle"><?php echo __t('cr_open_summary', 'admin'); ?></h3>
        </header>
        <div class="cr-open-summary" id="crOpenSummary">
            <p class="cr-open-summary__placeholder"><?php echo __t('cr_open_select_register', 'admin'); ?></p>
        </div>
        <form class="cr-form cr-open-form" id="crOpenForm" hidden>
            <input type="hidden" name="register_id" id="crOpenRegisterId">

            <label for="crOpenBalance"><?php echo __t('cr_opening_balance', 'admin'); ?></label>
            <div class="cr-open-amount-row">
                <input type="number" name="opening_balance" id="crOpenBalance" min="0" step="0.01" value="0" required>
            </div>
            <div class="cr-open-presets" role="group" aria-label="<?php echo htmlspecialchars(__t('cr_quick_amount', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
                <button type="button" class="cr-reg-chip" data-preset="0">0</button>
                <button type="button" class="cr-reg-chip" data-preset="25000">25 000</button>
                <button type="button" class="cr-reg-chip" data-preset="50000">50 000</button>
                <button type="button" class="cr-reg-chip" data-preset="100000">100 000</button>
            </div>

            <fieldset class="cr-open-shifts">
                <legend><?php echo __t('cr_nav_shifts', 'admin'); ?></legend>
                <div class="cr-open-shift-grid">
                    <label class="cr-open-shift"><input type="radio" name="shift_type" value="morning" checked><span><?php echo __t('cr_shift_morning', 'admin'); ?></span></label>
                    <label class="cr-open-shift"><input type="radio" name="shift_type" value="afternoon"><span><?php echo __t('cr_shift_afternoon', 'admin'); ?></span></label>
                    <label class="cr-open-shift"><input type="radio" name="shift_type" value="evening"><span><?php echo __t('cr_shift_evening', 'admin'); ?></span></label>
                    <label class="cr-open-shift"><input type="radio" name="shift_type" value="night"><span><?php echo __t('cr_shift_night', 'admin'); ?></span></label>
                </div>
            </fieldset>

            <label for="crOpenNotes"><?php echo __t('cr_open_notes', 'admin'); ?>
                <textarea name="notes" id="crOpenNotes" rows="2" placeholder="<?php echo htmlspecialchars(__t('cr_open_notes_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"></textarea>
            </label>

            <div class="cr-form-actions">
                <a href="registers.php" class="cr-btn cr-btn--ghost"><?php echo __t('cancel', 'admin'); ?></a>
                <button type="submit" class="cr-btn" id="crOpenSubmitBtn">
                    <span class="material-icons-round">lock_open</span>
                    <?php echo __t('cr_open_confirm', 'admin'); ?>
                </button>
            </div>
        </form>
    </section>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

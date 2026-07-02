<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_registers_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-registers.js'];
$pageI18n = cr_i18n([
    'cr_registers_subtitle', 'cr_new_register', 'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier',
    'cr_opening_balance', 'cr_open_register', 'cr_close_register', 'cr_no_registers', 'cr_saved', 'cr_view_details',
    'cr_session_open', 'cr_session_closed', 'col_status', 'cr_stat_cash_balance', 'cr_counted_cash', 'cr_stat_expected',
    'cr_registers_search_placeholder', 'cr_filter_all', 'cr_filter_session_open', 'cr_filter_session_closed',
    'cr_filter_active', 'cr_filter_inactive', 'cr_view_grid', 'cr_view_table', 'cr_edit_register',
    'cr_status_active', 'cr_status_inactive', 'cr_status_maintenance', 'cr_modal_open_title', 'cr_modal_close_title',
    'cr_shift_morning', 'cr_shift_afternoon', 'cr_shift_evening', 'cr_shift_night', 'cr_registers_count',
    'cr_no_registers_hint', 'cancel', 'save', 'error', 'confirm',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="cr-reg-hero" aria-labelledby="crRegHeroTitle">
    <div class="cr-reg-hero__intro">
        <h2 class="cr-reg-hero__title" id="crRegHeroTitle"><?php echo __t('cr_registers_subtitle', 'admin'); ?></h2>
        <p class="cr-reg-hero__count" id="crRegCount" aria-live="polite">—</p>
    </div>
    <div class="cr-reg-hero__stats">
        <div class="cr-reg-stat">
            <span class="cr-reg-stat__label"><?php echo __t('cr_stat_total_registers', 'admin'); ?></span>
            <strong class="cr-reg-stat__value is-loading" id="crRegStatTotal">—</strong>
        </div>
        <div class="cr-reg-stat cr-reg-stat--success">
            <span class="cr-reg-stat__label"><?php echo __t('cr_stat_open', 'admin'); ?></span>
            <strong class="cr-reg-stat__value is-loading" id="crRegStatOpen">—</strong>
        </div>
        <div class="cr-reg-stat">
            <span class="cr-reg-stat__label"><?php echo __t('cr_stat_closed', 'admin'); ?></span>
            <strong class="cr-reg-stat__value is-loading" id="crRegStatClosed">—</strong>
        </div>
        <div class="cr-reg-stat cr-reg-stat--primary">
            <span class="cr-reg-stat__label"><?php echo __t('cr_stat_cash_balance', 'admin'); ?></span>
            <strong class="cr-reg-stat__value is-loading" id="crRegStatBalance">—</strong>
        </div>
    </div>
</section>

<div class="cr-reg-toolbar">
    <div class="cr-reg-toolbar__search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="crRegSearch" class="cr-reg-search" placeholder="<?php echo htmlspecialchars(__t('cr_registers_search_placeholder', 'admin'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
    </div>
    <div class="cr-reg-filters" id="crRegFilters" role="tablist" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="cr-reg-chip is-active" data-filter="all" role="tab" aria-selected="true"><?php echo __t('cr_filter_all', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="session_open" role="tab"><?php echo __t('cr_filter_session_open', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="session_closed" role="tab"><?php echo __t('cr_filter_session_closed', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="active" role="tab"><?php echo __t('cr_filter_active', 'admin'); ?></button>
        <button type="button" class="cr-reg-chip" data-filter="inactive" role="tab"><?php echo __t('cr_filter_inactive', 'admin'); ?></button>
    </div>
    <div class="cr-reg-toolbar__actions">
        <div class="cr-reg-view-toggle" role="group" aria-label="<?php echo htmlspecialchars(__t('cr_view_grid', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
            <button type="button" class="cr-reg-view-btn is-active" data-view="grid" title="<?php echo htmlspecialchars(__t('cr_view_grid', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">grid_view</span></button>
            <button type="button" class="cr-reg-view-btn" data-view="table" title="<?php echo htmlspecialchars(__t('cr_view_table', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">table_rows</span></button>
        </div>
        <?php if ($canManageRegisters): ?>
        <a href="create_register.php" class="cr-btn"><span class="material-icons-round">add</span><?php echo __t('cr_new_register', 'admin'); ?></a>
        <?php endif; ?>
    </div>
</div>

<?php if ($canManageRegisters): ?>
<div class="cr-reg-quick">
    <a href="open_register.php" class="cr-btn cr-btn--ghost"><span class="material-icons-round">lock_open</span><?php echo __t('cr_open_register', 'admin'); ?></a>
    <a href="close_register.php" class="cr-btn cr-btn--ghost"><span class="material-icons-round">lock</span><?php echo __t('cr_close_register', 'admin'); ?></a>
</div>
<?php endif; ?>

<div id="crRegistersRoot" class="cr-reg-root">
    <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
</div>

<div class="cr-modal" id="crOpenModal" hidden aria-hidden="true">
    <div class="cr-modal__backdrop" data-close-modal></div>
    <div class="cr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="crOpenModalTitle">
        <header class="cr-modal__head">
            <h3 id="crOpenModalTitle"><?php echo __t('cr_modal_open_title', 'admin'); ?></h3>
            <button type="button" class="cr-modal__close" data-close-modal aria-label="<?php echo htmlspecialchars(__t('close', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">close</span></button>
        </header>
        <form class="cr-form cr-modal__body" id="crOpenModalForm">
            <input type="hidden" name="register_id" id="crOpenRegisterId">
            <p class="cr-modal__register-name" id="crOpenRegisterName"></p>
            <label><?php echo __t('cr_opening_balance', 'admin'); ?>
                <input type="number" name="opening_balance" min="0" step="0.01" value="0" required>
            </label>
            <label><?php echo __t('cr_nav_shifts', 'admin'); ?>
                <select name="shift_type">
                    <option value="morning"><?php echo __t('cr_shift_morning', 'admin'); ?></option>
                    <option value="afternoon"><?php echo __t('cr_shift_afternoon', 'admin'); ?></option>
                    <option value="evening"><?php echo __t('cr_shift_evening', 'admin'); ?></option>
                    <option value="night"><?php echo __t('cr_shift_night', 'admin'); ?></option>
                </select>
            </label>
            <div class="cr-form-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
                <button type="submit" class="cr-btn"><span class="material-icons-round">lock_open</span><?php echo __t('cr_open_register', 'admin'); ?></button>
            </div>
        </form>
    </div>
</div>

<div class="cr-modal" id="crCloseModal" hidden aria-hidden="true">
    <div class="cr-modal__backdrop" data-close-modal></div>
    <div class="cr-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="crCloseModalTitle">
        <header class="cr-modal__head">
            <h3 id="crCloseModalTitle"><?php echo __t('cr_modal_close_title', 'admin'); ?></h3>
            <button type="button" class="cr-modal__close" data-close-modal aria-label="<?php echo htmlspecialchars(__t('close', 'admin'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">close</span></button>
        </header>
        <form class="cr-form cr-modal__body" id="crCloseModalForm">
            <input type="hidden" name="session_id" id="crCloseSessionId">
            <p class="cr-modal__register-name" id="crCloseRegisterName"></p>
            <p class="cr-modal__hint" id="crCloseExpectedHint"></p>
            <label><?php echo __t('cr_counted_cash', 'admin'); ?>
                <input type="number" name="counted_cash" id="crCloseCountedCash" min="0" step="0.01" required>
            </label>
            <div class="cr-form-actions">
                <button type="button" class="cr-btn cr-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
                <button type="submit" class="cr-btn cr-btn--warn"><span class="material-icons-round">lock</span><?php echo __t('cr_close_register', 'admin'); ?></button>
            </div>
        </form>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

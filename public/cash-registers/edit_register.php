<?php

require __DIR__ . '/includes/bootstrap.php';

if (!$canManageRegisters) {

    header('Location: registers.php');

    exit;

}

$id = (int) ($_GET['id'] ?? 0);

if (!$id) {

    header('Location: registers.php');

    exit;

}

$activeCrPage = 'registers';

$pageTitle = __t('cr_edit_form_title', 'admin');

$extraScripts = ['cash-registers-common.js', 'cash-registers-edit.js'];

$pageI18n = cr_i18n([

    'cr_edit_subtitle', 'cr_edit_preview', 'cr_edit_form_title', 'cr_edit_confirm', 'cr_edit_branch_hint', 'cr_edit_code_locked',

    'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier', 'cr_opening_balance',

    'cr_status_active', 'cr_status_inactive', 'cr_status_maintenance', 'cr_session_open', 'cr_session_closed',

    'cr_create_step_identity', 'cr_create_step_assignment', 'cr_create_step_balance',

    'cr_stat_cash_balance', 'cr_view_details', 'cr_view_registers', 'cr_quick_amount', 'col_status',

    'save', 'cancel', 'error', 'load_error',

]);

require __DIR__ . '/includes/layout-start.php';

?>



<section class="cr-session-hero cr-create-hero cr-edit-hero" aria-labelledby="crEditHeroTitle">

    <div class="cr-session-hero__body">

        <h2 class="cr-session-hero__title" id="crEditHeroTitle"><?php echo __t('cr_edit_subtitle', 'admin'); ?></h2>

        <p class="cr-session-hero__hint" id="crEditHeroHint">—</p>

    </div>

    <div class="cr-session-hero__stats">

        <div class="cr-session-stat">

            <span class="cr-session-stat__label"><?php echo __t('cr_branch', 'admin'); ?></span>

            <strong class="cr-session-stat__value" id="crEditStatBranch"><?php echo htmlspecialchars($storeName ?: '—', ENT_QUOTES, 'UTF-8'); ?></strong>

        </div>

        <div class="cr-session-stat cr-session-stat--primary">

            <span class="cr-session-stat__label"><?php echo __t('col_status', 'admin'); ?></span>

            <strong class="cr-session-stat__value is-loading" id="crEditStatSession">—</strong>

        </div>

        <div class="cr-session-stat">

            <span class="cr-session-stat__label"><?php echo __t('cr_stat_cash_balance', 'admin'); ?></span>

            <strong class="cr-session-stat__value is-loading" id="crEditStatBalance">—</strong>

        </div>

    </div>

</section>



<div class="cr-open-layout cr-create-layout">

    <aside class="cr-create-preview" aria-labelledby="crEditPreviewTitle">

        <header class="cr-open-picker__head">

            <h3 id="crEditPreviewTitle"><?php echo __t('cr_edit_preview', 'admin'); ?></h3>

            <a href="register_details.php?id=<?php echo $id; ?>" class="cr-panel__link"><?php echo __t('cr_view_details', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>

        </header>

        <div class="cr-create-preview__body">

            <div class="cr-create-preview__card" id="crEditPreviewCard">

                <div class="cr-create-preview__icon"><span class="material-icons-round">storefront</span></div>

                <div class="cr-create-preview__main">

                    <strong id="crPreviewName"><?php echo __t('cr_register_name', 'admin'); ?></strong>

                    <span class="cr-muted" id="crPreviewCode">—</span>

                </div>

                <span class="cr-badge cr-badge--ok" id="crPreviewStatus"><?php echo __t('cr_status_active', 'admin'); ?></span>

            </div>

            <dl class="cr-open-summary__dl cr-create-preview__dl">

                <div><dt><?php echo __t('cr_branch', 'admin'); ?></dt><dd id="crPreviewBranch"><?php echo htmlspecialchars($storeName ?: '—', ENT_QUOTES, 'UTF-8'); ?></dd></div>

                <div><dt><?php echo __t('cr_assigned_cashier', 'admin'); ?></dt><dd id="crPreviewCashier">—</dd></div>

                <div><dt><?php echo __t('cr_stat_cash_balance', 'admin'); ?></dt><dd id="crPreviewBalance">—</dd></div>

            </dl>

            <p class="cr-edit-session-badge" id="crEditSessionBadge" hidden></p>

            <ol class="cr-create-steps" aria-label="<?php echo htmlspecialchars(__t('cr_edit_form_title', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                <li class="cr-create-step is-done" data-step="identity"><span class="material-icons-round">badge</span><?php echo __t('cr_create_step_identity', 'admin'); ?></li>

                <li class="cr-create-step" data-step="assignment"><span class="material-icons-round">person</span><?php echo __t('cr_create_step_assignment', 'admin'); ?></li>

                <li class="cr-create-step" data-step="balance"><span class="material-icons-round">payments</span><?php echo __t('cr_create_step_balance', 'admin'); ?></li>

            </ol>

        </div>

    </aside>



    <section class="cr-open-form-panel" aria-labelledby="crEditFormTitle">

        <header class="cr-open-form-panel__head">

            <h3 id="crEditFormTitle"><?php echo __t('cr_edit_form_title', 'admin'); ?></h3>

        </header>

        <div class="cr-loading" id="crEditFormLoading"><?php echo __t('loading', 'admin'); ?></div>

        <form class="cr-form cr-open-form cr-create-form" id="crEditForm" data-register-id="<?php echo $id; ?>" hidden>

            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_identity', 'admin'); ?></legend>

                <div class="cr-form-grid">

                    <label for="crEditCode">

                        <?php echo __t('cr_register_code', 'admin'); ?>

                        <input type="text" name="register_code" id="crEditCode" readonly class="cr-input-readonly">

                        <span class="cr-create-hint"><?php echo __t('cr_edit_code_locked', 'admin'); ?></span>

                    </label>

                    <label for="crEditName"><?php echo __t('cr_register_name', 'admin'); ?>

                        <input type="text" name="name" id="crEditName" required maxlength="120" autocomplete="off">

                    </label>

                </div>

            </fieldset>



            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_assignment', 'admin'); ?></legend>

                <label for="crEditCashier"><?php echo __t('cr_assigned_cashier', 'admin'); ?>

                    <select name="assigned_user_id" id="crEditCashier">

                        <option value="">—</option>

                    </select>

                </label>

                <div class="cr-create-status">

                    <span class="cr-create-status__label"><?php echo __t('col_status', 'admin'); ?></span>

                    <div class="cr-create-status-grid cr-create-status-grid--3" role="radiogroup" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                        <label class="cr-open-shift"><input type="radio" name="status" value="active"><span><?php echo __t('cr_status_active', 'admin'); ?></span></label>

                        <label class="cr-open-shift"><input type="radio" name="status" value="inactive"><span><?php echo __t('cr_status_inactive', 'admin'); ?></span></label>

                        <label class="cr-open-shift"><input type="radio" name="status" value="maintenance"><span><?php echo __t('cr_status_maintenance', 'admin'); ?></span></label>

                    </div>

                </div>

            </fieldset>



            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_balance', 'admin'); ?></legend>

                <label for="crEditBalance"><?php echo __t('cr_opening_balance', 'admin'); ?></label>

                <div class="cr-open-amount-row">

                    <input type="number" name="opening_balance" id="crEditBalance" min="0" step="0.01" value="0" required>

                </div>

                <div class="cr-open-presets" role="group" aria-label="<?php echo htmlspecialchars(__t('cr_quick_amount', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                    <button type="button" class="cr-reg-chip" data-preset="0">0</button>

                    <button type="button" class="cr-reg-chip" data-preset="25000">25 000</button>

                    <button type="button" class="cr-reg-chip" data-preset="50000">50 000</button>

                    <button type="button" class="cr-reg-chip" data-preset="100000">100 000</button>

                </div>

            </fieldset>



            <div class="cr-form-actions">

                <a href="register_details.php?id=<?php echo $id; ?>" class="cr-btn cr-btn--ghost"><?php echo __t('cancel', 'admin'); ?></a>

                <button type="submit" class="cr-btn" id="crEditSubmitBtn">

                    <span class="material-icons-round">save</span>

                    <?php echo __t('cr_edit_confirm', 'admin'); ?>

                </button>

            </div>

        </form>

    </section>

</div>



<?php require __DIR__ . '/includes/layout-end.php'; ?>


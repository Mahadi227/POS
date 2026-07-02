<?php

require __DIR__ . '/includes/bootstrap.php';

if (!$canManageRegisters) {

    header('Location: registers.php');

    exit;

}

$activeCrPage = 'registers';

$pageTitle = __t('cr_new_register', 'admin');

$extraScripts = ['cash-registers-common.js', 'cash-registers-create.js'];

$pageI18n = cr_i18n([

    'cr_create_subtitle', 'cr_new_register', 'cr_create_preview', 'cr_create_form_title',

    'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier', 'cr_opening_balance',

    'cr_status_active', 'cr_status_inactive', 'cr_create_code_hint', 'cr_create_suggest_code',

    'cr_create_step_identity', 'cr_create_step_assignment', 'cr_create_step_balance',

    'cr_create_confirm', 'cr_create_cashiers', 'cr_create_branch_hint', 'cr_stat_total_registers', 'cr_stat_cash_balance',

    'cr_view_registers', 'cr_quick_amount', 'col_status', 'cr_no_registers_hint',

    'save', 'cancel', 'error', 'load_error',

]);

require __DIR__ . '/includes/layout-start.php';

?>



<section class="cr-session-hero cr-create-hero" aria-labelledby="crCreateHeroTitle">

    <div class="cr-session-hero__body">

        <h2 class="cr-session-hero__title" id="crCreateHeroTitle"><?php echo __t('cr_create_subtitle', 'admin'); ?></h2>

        <p class="cr-session-hero__hint" id="crCreateHeroHint">—</p>

    </div>

    <div class="cr-session-hero__stats">

        <div class="cr-session-stat">

            <span class="cr-session-stat__label"><?php echo __t('cr_branch', 'admin'); ?></span>

            <strong class="cr-session-stat__value" id="crCreateStatBranch"><?php echo htmlspecialchars($storeName ?: '—', ENT_QUOTES, 'UTF-8'); ?></strong>

        </div>

        <div class="cr-session-stat cr-session-stat--primary">

            <span class="cr-session-stat__label"><?php echo __t('cr_stat_total_registers', 'admin'); ?></span>

            <strong class="cr-session-stat__value is-loading" id="crCreateStatRegisters">—</strong>

        </div>

        <div class="cr-session-stat">

            <span class="cr-session-stat__label"><?php echo __t('cr_create_cashiers', 'admin'); ?></span>

            <strong class="cr-session-stat__value is-loading" id="crCreateStatCashiers">—</strong>

        </div>

    </div>

</section>



<div class="cr-open-layout cr-create-layout">

    <aside class="cr-create-preview" aria-labelledby="crCreatePreviewTitle">

        <header class="cr-open-picker__head">

            <h3 id="crCreatePreviewTitle"><?php echo __t('cr_create_preview', 'admin'); ?></h3>

            <a href="registers.php" class="cr-panel__link"><?php echo __t('cr_view_registers', 'admin'); ?> <span class="material-icons-round">arrow_forward</span></a>

        </header>

        <div class="cr-create-preview__body">

            <div class="cr-create-preview__card" id="crCreatePreviewCard">

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

                <div><dt><?php echo __t('cr_stat_cash_balance', 'admin'); ?></dt><dd id="crPreviewBalance">0</dd></div>

            </dl>

            <ol class="cr-create-steps" aria-label="<?php echo htmlspecialchars(__t('cr_create_form_title', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                <li class="cr-create-step is-done" data-step="identity"><span class="material-icons-round">badge</span><?php echo __t('cr_create_step_identity', 'admin'); ?></li>

                <li class="cr-create-step" data-step="assignment"><span class="material-icons-round">person</span><?php echo __t('cr_create_step_assignment', 'admin'); ?></li>

                <li class="cr-create-step" data-step="balance"><span class="material-icons-round">payments</span><?php echo __t('cr_create_step_balance', 'admin'); ?></li>

            </ol>

        </div>

    </aside>



    <section class="cr-open-form-panel" aria-labelledby="crCreateFormTitle">

        <header class="cr-open-form-panel__head">

            <h3 id="crCreateFormTitle"><?php echo __t('cr_create_form_title', 'admin'); ?></h3>

        </header>

        <form class="cr-form cr-open-form cr-create-form" id="crCreateForm">

            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_identity', 'admin'); ?></legend>

                <div class="cr-form-grid">

                    <label for="crCreateCode">

                        <?php echo __t('cr_register_code', 'admin'); ?>

                        <div class="cr-create-code-row">

                            <input type="text" name="register_code" id="crCreateCode" required maxlength="32" autocomplete="off" placeholder="CR1-0001">

                            <button type="button" class="cr-btn cr-btn--ghost cr-btn--sm" id="crCreateSuggestCode">

                                <span class="material-icons-round">auto_fix_high</span>

                                <?php echo __t('cr_create_suggest_code', 'admin'); ?>

                            </button>

                        </div>

                        <span class="cr-create-hint"><?php echo __t('cr_create_code_hint', 'admin'); ?></span>

                    </label>

                    <label for="crCreateName"><?php echo __t('cr_register_name', 'admin'); ?>

                        <input type="text" name="name" id="crCreateName" required maxlength="120" autocomplete="off">

                    </label>

                </div>

            </fieldset>



            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_assignment', 'admin'); ?></legend>

                <label for="crCreateCashier"><?php echo __t('cr_assigned_cashier', 'admin'); ?>

                    <select name="assigned_user_id" id="crCreateCashier">

                        <option value="">—</option>

                    </select>

                </label>

                <div class="cr-create-status">

                    <span class="cr-create-status__label"><?php echo __t('col_status', 'admin'); ?></span>

                    <div class="cr-create-status-grid" role="radiogroup" aria-label="<?php echo htmlspecialchars(__t('col_status', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                        <label class="cr-open-shift"><input type="radio" name="status" value="active" checked><span><?php echo __t('cr_status_active', 'admin'); ?></span></label>

                        <label class="cr-open-shift"><input type="radio" name="status" value="inactive"><span><?php echo __t('cr_status_inactive', 'admin'); ?></span></label>

                    </div>

                </div>

            </fieldset>



            <fieldset class="cr-create-fieldset">

                <legend><?php echo __t('cr_create_step_balance', 'admin'); ?></legend>

                <label for="crCreateBalance"><?php echo __t('cr_opening_balance', 'admin'); ?></label>

                <div class="cr-open-amount-row">

                    <input type="number" name="opening_balance" id="crCreateBalance" min="0" step="0.01" value="0" required>

                </div>

                <div class="cr-open-presets" role="group" aria-label="<?php echo htmlspecialchars(__t('cr_quick_amount', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">

                    <button type="button" class="cr-reg-chip" data-preset="0">0</button>

                    <button type="button" class="cr-reg-chip" data-preset="25000">25 000</button>

                    <button type="button" class="cr-reg-chip" data-preset="50000">50 000</button>

                    <button type="button" class="cr-reg-chip" data-preset="100000">100 000</button>

                </div>

            </fieldset>



            <div class="cr-form-actions">

                <a href="registers.php" class="cr-btn cr-btn--ghost"><?php echo __t('cancel', 'admin'); ?></a>

                <button type="submit" class="cr-btn" id="crCreateSubmitBtn">

                    <span class="material-icons-round">add</span>

                    <?php echo __t('cr_create_confirm', 'admin'); ?>

                </button>

            </div>

        </form>

    </section>

</div>



<?php require __DIR__ . '/includes/layout-end.php'; ?>


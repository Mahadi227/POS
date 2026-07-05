<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'support';
$pageTitle = __t('plat_nav_support', 'platform');
$extraStyles = ['platform-support.css'];
$extraScripts = ['platform-common.js', 'platform-support.js'];
$pageI18n = plat_i18n([
    'plat_nav_support', 'plat_nav_tickets', 'plat_nav_companies', 'plat_col_name', 'plat_col_status',
    'plat_no_data', 'plat_search', 'plat_clear_filters', 'loading', 'load_error', 'plat_view_detail',
    'action_success', 'action_error',
    'plat_support_subtitle', 'plat_support_badge', 'plat_support_load_error', 'plat_support_count',
    'plat_support_kpi_open', 'plat_support_kpi_progress', 'plat_support_kpi_waiting',
    'plat_support_kpi_resolved', 'plat_support_kpi_attention', 'plat_support_view_tickets',
    'plat_support_view_companies', 'plat_support_add_ticket', 'plat_support_add_title',
    'plat_support_add_submit', 'plat_support_add_cancel', 'plat_support_field_subject',
    'plat_support_field_description', 'plat_support_field_tenant', 'plat_support_field_priority',
    'plat_support_field_category', 'plat_support_field_assignee', 'plat_support_tenant_none',
    'plat_support_assignee_none', 'plat_support_attention', 'plat_support_recent_tickets',
    'plat_support_recent_actions', 'plat_support_col_ticket', 'plat_support_col_priority',
    'plat_support_col_category', 'plat_support_col_assignee', 'plat_support_col_updated',
    'plat_support_col_action', 'plat_support_col_reason', 'plat_support_filter_all_status',
    'plat_support_filter_all_priority', 'plat_support_status_open', 'plat_support_status_in_progress',
    'plat_support_status_waiting', 'plat_support_status_resolved', 'plat_support_status_closed',
    'plat_support_priority_low', 'plat_support_priority_normal', 'plat_support_priority_high',
    'plat_support_priority_urgent', 'plat_support_cat_billing', 'plat_support_cat_technical',
    'plat_support_cat_onboarding', 'plat_support_cat_account', 'plat_support_cat_other',
    'plat_support_reason_suspended', 'plat_support_reason_trial_ending', 'plat_support_reason_past_due',
    'plat_support_action_impersonate_start', 'plat_support_action_impersonate_end',
    'plat_support_action_suspend', 'plat_support_action_restore', 'plat_support_action_extend_trial',
    'plat_support_action_change_plan', 'plat_support_change_status', 'plat_support_assign',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-support">
    <div class="plat-support-error" id="platSupportError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platSupportErrorText"></span>
    </div>
    <div class="plat-support-alert" id="platSupportAlert" hidden role="status"></div>

    <section class="plat-support-hero" aria-labelledby="platSupportHeroTitle">
        <div class="plat-support-hero__intro">
            <div class="plat-support-badge">
                <span class="material-icons-round" aria-hidden="true">support_agent</span>
                <?php echo __t('plat_support_badge', 'platform'); ?>
            </div>
            <h2 class="plat-support-hero__title" id="platSupportHeroTitle"><?php echo __t('plat_nav_support', 'platform'); ?></h2>
            <p class="plat-support-hero__desc"><?php echo __t('plat_support_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-support-hero__actions">
            <p class="plat-support-count" id="platSupportCount" aria-live="polite"></p>
            <div class="plat-support-hero__btns">
                <a href="<?php echo htmlspecialchars(plat_href('companies/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-support-link-btn">
                    <span class="material-icons-round" aria-hidden="true">business</span>
                    <?php echo __t('plat_support_view_companies', 'platform'); ?>
                </a>
                <button type="button" class="plat-support-add-btn" id="platSupportAddOpen">
                    <span class="material-icons-round" aria-hidden="true">add</span>
                    <?php echo __t('plat_support_add_ticket', 'platform'); ?>
                </button>
            </div>
        </div>
    </section>

    <section class="plat-kpi-grid plat-support-kpi-grid" id="platSupportKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">inbox</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_support_kpi_open', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSupKpiOpen">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">pending</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_support_kpi_progress', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSupKpiProgress">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hourglass_empty</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_support_kpi_waiting', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSupKpiWaiting">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">task_alt</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_support_kpi_resolved', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSupKpiResolved">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--warn is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">warning_amber</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_support_kpi_attention', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSupKpiAttention">—</strong>
        </article>
    </section>

    <div class="plat-support-mid">
        <section class="plat-panel plat-support-attention-panel">
            <header class="plat-support-panel-head">
                <h3>
                    <span class="material-icons-round" aria-hidden="true">priority_high</span>
                    <?php echo __t('plat_support_attention', 'platform'); ?>
                </h3>
            </header>
            <div class="plat-table-wrap">
                <table class="plat-table plat-support-table">
                    <thead>
                        <tr>
                            <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                            <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                            <th><?php echo __t('plat_support_col_reason', 'platform'); ?></th>
                            <th class="plat-col-action"></th>
                        </tr>
                    </thead>
                    <tbody id="platSupportAttention">
                        <tr><td colspan="4" class="plat-support-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="plat-panel plat-support-actions-panel">
            <header class="plat-support-panel-head">
                <h3>
                    <span class="material-icons-round" aria-hidden="true">history</span>
                    <?php echo __t('plat_support_recent_actions', 'platform'); ?>
                </h3>
            </header>
            <div class="plat-support-actions-list" id="platSupportActions" aria-live="polite">
                <p class="plat-support-muted"><?php echo __t('loading', 'platform'); ?>…</p>
            </div>
        </section>
    </div>

    <section class="plat-panel plat-support-tickets-panel">
        <header class="plat-support-panel-head plat-support-tickets-head">
            <h3>
                <span class="material-icons-round" aria-hidden="true">confirmation_number</span>
                <?php echo __t('plat_support_recent_tickets', 'platform'); ?>
            </h3>
            <a href="<?php echo htmlspecialchars(plat_href('tickets/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-support-tickets-link">
                <?php echo __t('plat_support_view_tickets', 'platform'); ?>
                <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
            </a>
        </header>

        <div class="plat-support-toolbar">
            <div class="plat-support-search-wrap">
                <span class="material-icons-round plat-support-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platSupportSearch" class="plat-search plat-support-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platSupportStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_support_filter_all_status', 'platform'); ?></option>
                <option value="open"><?php echo __t('plat_support_status_open', 'platform'); ?></option>
                <option value="in_progress"><?php echo __t('plat_support_status_in_progress', 'platform'); ?></option>
                <option value="waiting"><?php echo __t('plat_support_status_waiting', 'platform'); ?></option>
                <option value="resolved"><?php echo __t('plat_support_status_resolved', 'platform'); ?></option>
                <option value="closed"><?php echo __t('plat_support_status_closed', 'platform'); ?></option>
            </select>
            <select id="platSupportPriorityFilter" class="plat-select" aria-label="<?php echo __t('plat_support_col_priority', 'platform'); ?>">
                <option value=""><?php echo __t('plat_support_filter_all_priority', 'platform'); ?></option>
                <option value="urgent"><?php echo __t('plat_support_priority_urgent', 'platform'); ?></option>
                <option value="high"><?php echo __t('plat_support_priority_high', 'platform'); ?></option>
                <option value="normal"><?php echo __t('plat_support_priority_normal', 'platform'); ?></option>
                <option value="low"><?php echo __t('plat_support_priority_low', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-support-clear-btn" id="platSupportClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap">
            <table class="plat-table plat-support-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_support_col_ticket', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_support_col_priority', 'platform'); ?></th>
                        <th><?php echo __t('plat_support_col_assignee', 'platform'); ?></th>
                        <th><?php echo __t('plat_support_col_updated', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platSupportTickets">
                    <tr><td colspan="7" class="plat-support-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="plat-support-modal" id="platSupportModal" hidden role="dialog" aria-modal="true" aria-labelledby="platSupportModalTitle">
    <div class="plat-support-modal__backdrop" id="platSupportModalBackdrop"></div>
    <form class="plat-support-modal__panel" id="platSupportForm">
        <header class="plat-support-modal__head">
            <h3 id="platSupportModalTitle"><?php echo __t('plat_support_add_title', 'platform'); ?></h3>
            <button type="button" class="plat-support-modal__close" id="platSupportModalClose" aria-label="<?php echo __t('plat_support_add_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-support-modal__body">
            <label class="plat-support-field">
                <span><?php echo __t('plat_support_field_subject', 'platform'); ?></span>
                <input type="text" name="subject" id="platSupportSubject" required maxlength="255">
            </label>
            <label class="plat-support-field">
                <span><?php echo __t('plat_support_field_tenant', 'platform'); ?></span>
                <select name="tenant_id" id="platSupportTenant">
                    <option value=""><?php echo __t('plat_support_tenant_none', 'platform'); ?></option>
                </select>
            </label>
            <div class="plat-support-field-row">
                <label class="plat-support-field">
                    <span><?php echo __t('plat_support_field_priority', 'platform'); ?></span>
                    <select name="priority" id="platSupportPriority">
                        <option value="normal"><?php echo __t('plat_support_priority_normal', 'platform'); ?></option>
                        <option value="low"><?php echo __t('plat_support_priority_low', 'platform'); ?></option>
                        <option value="high"><?php echo __t('plat_support_priority_high', 'platform'); ?></option>
                        <option value="urgent"><?php echo __t('plat_support_priority_urgent', 'platform'); ?></option>
                    </select>
                </label>
                <label class="plat-support-field">
                    <span><?php echo __t('plat_support_field_category', 'platform'); ?></span>
                    <select name="category" id="platSupportCategory">
                        <option value="other"><?php echo __t('plat_support_cat_other', 'platform'); ?></option>
                        <option value="billing"><?php echo __t('plat_support_cat_billing', 'platform'); ?></option>
                        <option value="technical"><?php echo __t('plat_support_cat_technical', 'platform'); ?></option>
                        <option value="onboarding"><?php echo __t('plat_support_cat_onboarding', 'platform'); ?></option>
                        <option value="account"><?php echo __t('plat_support_cat_account', 'platform'); ?></option>
                    </select>
                </label>
            </div>
            <label class="plat-support-field">
                <span><?php echo __t('plat_support_field_assignee', 'platform'); ?></span>
                <select name="assigned_to" id="platSupportAssignee">
                    <option value=""><?php echo __t('plat_support_assignee_none', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-support-field">
                <span><?php echo __t('plat_support_field_description', 'platform'); ?></span>
                <textarea name="description" id="platSupportDescription" rows="4"></textarea>
            </label>
        </div>
        <footer class="plat-support-modal__foot">
            <button type="button" class="plat-support-btn" id="platSupportModalCancel"><?php echo __t('plat_support_add_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-support-btn plat-support-btn--primary"><?php echo __t('plat_support_add_submit', 'platform'); ?></button>
        </footer>
    </form>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

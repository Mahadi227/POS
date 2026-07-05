<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'tickets';
$pageTitle = __t('plat_nav_tickets', 'platform');
$extraStyles = ['platform-tickets.css'];
$extraScripts = ['platform-common.js', 'platform-tickets.js'];
$pageI18n = plat_i18n([
    'plat_nav_tickets', 'plat_nav_support', 'plat_col_name', 'plat_col_status', 'plat_no_data',
    'plat_search', 'plat_clear_filters', 'loading', 'load_error', 'plat_view_detail',
    'action_success', 'action_error',
    'plat_tickets_subtitle', 'plat_tickets_badge', 'plat_tickets_count', 'plat_tickets_load_error',
    'plat_tickets_empty', 'plat_tickets_empty_hint', 'plat_tickets_kpi_total', 'plat_tickets_kpi_open',
    'plat_tickets_kpi_progress', 'plat_tickets_kpi_waiting', 'plat_tickets_kpi_resolved',
    'plat_tickets_view_support', 'plat_tickets_add', 'plat_tickets_add_title', 'plat_tickets_add_submit',
    'plat_tickets_add_cancel', 'plat_tickets_col_ticket', 'plat_tickets_col_priority',
    'plat_tickets_col_category', 'plat_tickets_col_assignee', 'plat_tickets_col_updated',
    'plat_tickets_filter_all_status', 'plat_tickets_filter_all_priority', 'plat_tickets_detail_title',
    'plat_tickets_detail_close', 'plat_tickets_detail_description', 'plat_tickets_detail_replies',
    'plat_tickets_reply_placeholder', 'plat_tickets_reply_submit', 'plat_tickets_reply_internal',
    'plat_tickets_assign', 'plat_tickets_change_status', 'plat_tickets_created', 'plat_tickets_creator',
    'plat_tickets_no_replies', 'plat_tickets_open_detail',
    'plat_support_field_subject', 'plat_support_field_description', 'plat_support_field_tenant',
    'plat_support_field_priority', 'plat_support_field_category', 'plat_support_field_assignee',
    'plat_support_tenant_none', 'plat_support_assignee_none',
    'plat_support_status_open', 'plat_support_status_in_progress', 'plat_support_status_waiting',
    'plat_support_status_resolved', 'plat_support_status_closed',
    'plat_support_priority_low', 'plat_support_priority_normal', 'plat_support_priority_high',
    'plat_support_priority_urgent', 'plat_support_cat_billing', 'plat_support_cat_technical',
    'plat_support_cat_onboarding', 'plat_support_cat_account', 'plat_support_cat_other',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-tickets">
    <div class="plat-tickets-error" id="platTicketsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platTicketsErrorText"></span>
    </div>
    <div class="plat-tickets-alert" id="platTicketsAlert" hidden role="status"></div>

    <section class="plat-tickets-hero" aria-labelledby="platTicketsHeroTitle">
        <div class="plat-tickets-hero__intro">
            <div class="plat-tickets-badge">
                <span class="material-icons-round" aria-hidden="true">confirmation_number</span>
                <?php echo __t('plat_tickets_badge', 'platform'); ?>
            </div>
            <h2 class="plat-tickets-hero__title" id="platTicketsHeroTitle"><?php echo __t('plat_nav_tickets', 'platform'); ?></h2>
            <p class="plat-tickets-hero__desc"><?php echo __t('plat_tickets_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-tickets-hero__actions">
            <p class="plat-tickets-count" id="platTicketsCount" aria-live="polite"></p>
            <div class="plat-tickets-hero__btns">
                <a href="<?php echo htmlspecialchars(plat_href('support/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-tickets-link-btn">
                    <span class="material-icons-round" aria-hidden="true">support_agent</span>
                    <?php echo __t('plat_tickets_view_support', 'platform'); ?>
                </a>
                <button type="button" class="plat-tickets-add-btn" id="platTicketsAddOpen">
                    <span class="material-icons-round" aria-hidden="true">add</span>
                    <?php echo __t('plat_tickets_add', 'platform'); ?>
                </button>
            </div>
        </div>
    </section>

    <section class="plat-kpi-grid plat-tickets-kpi-grid" id="platTicketsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">confirmation_number</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_tickets_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platTktKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">inbox</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_tickets_kpi_open', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platTktKpiOpen">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">pending</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_tickets_kpi_progress', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platTktKpiProgress">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hourglass_empty</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_tickets_kpi_waiting', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platTktKpiWaiting">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">task_alt</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_tickets_kpi_resolved', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platTktKpiResolved">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-tickets-panel">
        <div class="plat-tickets-toolbar">
            <div class="plat-tickets-search-wrap">
                <span class="material-icons-round plat-tickets-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platTicketsSearch" class="plat-search plat-tickets-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platTicketsStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_tickets_filter_all_status', 'platform'); ?></option>
                <option value="open"><?php echo __t('plat_support_status_open', 'platform'); ?></option>
                <option value="in_progress"><?php echo __t('plat_support_status_in_progress', 'platform'); ?></option>
                <option value="waiting"><?php echo __t('plat_support_status_waiting', 'platform'); ?></option>
                <option value="resolved"><?php echo __t('plat_support_status_resolved', 'platform'); ?></option>
                <option value="closed"><?php echo __t('plat_support_status_closed', 'platform'); ?></option>
            </select>
            <select id="platTicketsPriorityFilter" class="plat-select" aria-label="<?php echo __t('plat_tickets_col_priority', 'platform'); ?>">
                <option value=""><?php echo __t('plat_tickets_filter_all_priority', 'platform'); ?></option>
                <option value="urgent"><?php echo __t('plat_support_priority_urgent', 'platform'); ?></option>
                <option value="high"><?php echo __t('plat_support_priority_high', 'platform'); ?></option>
                <option value="normal"><?php echo __t('plat_support_priority_normal', 'platform'); ?></option>
                <option value="low"><?php echo __t('plat_support_priority_low', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-tickets-clear-btn" id="platTicketsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-tickets-table-wrap">
            <table class="plat-table plat-tickets-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_tickets_col_ticket', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_tickets_col_priority', 'platform'); ?></th>
                        <th><?php echo __t('plat_tickets_col_category', 'platform'); ?></th>
                        <th><?php echo __t('plat_tickets_col_assignee', 'platform'); ?></th>
                        <th><?php echo __t('plat_tickets_col_updated', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platTicketsBody">
                    <tr><td colspan="8" class="plat-tickets-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>

        <div class="plat-tickets-empty" id="platTicketsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">confirmation_number</span>
            <h3><?php echo __t('plat_tickets_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_tickets_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<div class="plat-tickets-modal" id="platTicketsCreateModal" hidden role="dialog" aria-modal="true" aria-labelledby="platTicketsCreateTitle">
    <div class="plat-tickets-modal__backdrop" data-close-modal></div>
    <form class="plat-tickets-modal__panel" id="platTicketsCreateForm">
        <header class="plat-tickets-modal__head">
            <h3 id="platTicketsCreateTitle"><?php echo __t('plat_tickets_add_title', 'platform'); ?></h3>
            <button type="button" class="plat-tickets-modal__close" data-close-modal aria-label="<?php echo __t('plat_tickets_add_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-tickets-modal__body">
            <label class="plat-tickets-field">
                <span><?php echo __t('plat_support_field_subject', 'platform'); ?></span>
                <input type="text" name="subject" required maxlength="255">
            </label>
            <label class="plat-tickets-field">
                <span><?php echo __t('plat_support_field_tenant', 'platform'); ?></span>
                <select name="tenant_id" id="platTicketsCreateTenant">
                    <option value=""><?php echo __t('plat_support_tenant_none', 'platform'); ?></option>
                </select>
            </label>
            <div class="plat-tickets-field-row">
                <label class="plat-tickets-field">
                    <span><?php echo __t('plat_support_field_priority', 'platform'); ?></span>
                    <select name="priority">
                        <option value="normal"><?php echo __t('plat_support_priority_normal', 'platform'); ?></option>
                        <option value="low"><?php echo __t('plat_support_priority_low', 'platform'); ?></option>
                        <option value="high"><?php echo __t('plat_support_priority_high', 'platform'); ?></option>
                        <option value="urgent"><?php echo __t('plat_support_priority_urgent', 'platform'); ?></option>
                    </select>
                </label>
                <label class="plat-tickets-field">
                    <span><?php echo __t('plat_support_field_category', 'platform'); ?></span>
                    <select name="category">
                        <option value="other"><?php echo __t('plat_support_cat_other', 'platform'); ?></option>
                        <option value="billing"><?php echo __t('plat_support_cat_billing', 'platform'); ?></option>
                        <option value="technical"><?php echo __t('plat_support_cat_technical', 'platform'); ?></option>
                        <option value="onboarding"><?php echo __t('plat_support_cat_onboarding', 'platform'); ?></option>
                        <option value="account"><?php echo __t('plat_support_cat_account', 'platform'); ?></option>
                    </select>
                </label>
            </div>
            <label class="plat-tickets-field">
                <span><?php echo __t('plat_support_field_assignee', 'platform'); ?></span>
                <select name="assigned_to" id="platTicketsCreateAssignee">
                    <option value=""><?php echo __t('plat_support_assignee_none', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-tickets-field">
                <span><?php echo __t('plat_support_field_description', 'platform'); ?></span>
                <textarea name="description" rows="4"></textarea>
            </label>
        </div>
        <footer class="plat-tickets-modal__foot">
            <button type="button" class="plat-tickets-btn" data-close-modal><?php echo __t('plat_tickets_add_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-tickets-btn plat-tickets-btn--primary"><?php echo __t('plat_tickets_add_submit', 'platform'); ?></button>
        </footer>
    </form>
</div>

<div class="plat-tickets-drawer" id="platTicketsDrawer" hidden role="dialog" aria-modal="true" aria-labelledby="platTicketsDrawerTitle">
    <div class="plat-tickets-drawer__backdrop" data-close-drawer></div>
    <aside class="plat-tickets-drawer__panel">
        <header class="plat-tickets-drawer__head">
            <div>
                <p class="plat-tickets-drawer__number" id="platTicketsDrawerNumber"></p>
                <h3 id="platTicketsDrawerTitle"><?php echo __t('plat_tickets_detail_title', 'platform'); ?></h3>
                <p class="plat-tickets-drawer__meta" id="platTicketsDrawerMeta"></p>
            </div>
            <button type="button" class="plat-tickets-modal__close" data-close-drawer aria-label="<?php echo __t('plat_tickets_detail_close', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-tickets-drawer__controls">
            <label class="plat-tickets-control">
                <span><?php echo __t('plat_tickets_change_status', 'platform'); ?></span>
                <select id="platTicketsDrawerStatus"></select>
            </label>
            <label class="plat-tickets-control">
                <span><?php echo __t('plat_tickets_assign', 'platform'); ?></span>
                <select id="platTicketsDrawerAssignee"></select>
            </label>
        </div>
        <div class="plat-tickets-drawer__body">
            <section class="plat-tickets-detail-block">
                <h4><?php echo __t('plat_tickets_detail_description', 'platform'); ?></h4>
                <p id="platTicketsDrawerDesc" class="plat-tickets-muted">—</p>
            </section>
            <section class="plat-tickets-detail-block">
                <h4><?php echo __t('plat_tickets_detail_replies', 'platform'); ?></h4>
                <div class="plat-tickets-replies" id="platTicketsReplies"></div>
                <form id="platTicketsReplyForm" class="plat-tickets-reply-form">
                    <textarea id="platTicketsReplyMessage" rows="3" placeholder="<?php echo __t('plat_tickets_reply_placeholder', 'platform'); ?>" required></textarea>
                    <label class="plat-tickets-internal">
                        <input type="checkbox" id="platTicketsReplyInternal">
                        <?php echo __t('plat_tickets_reply_internal', 'platform'); ?>
                    </label>
                    <button type="submit" class="plat-tickets-btn plat-tickets-btn--primary">
                        <span class="material-icons-round" aria-hidden="true">send</span>
                        <?php echo __t('plat_tickets_reply_submit', 'platform'); ?>
                    </button>
                </form>
            </section>
        </div>
    </aside>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

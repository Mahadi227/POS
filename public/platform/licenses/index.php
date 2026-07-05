<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'licenses';
$pageTitle = __t('plat_nav_licenses', 'platform');
$extraStyles = ['platform-licenses.css'];
$extraScripts = ['platform-common.js', 'platform-licenses.js'];
$pageI18n = plat_i18n([
    'plat_nav_licenses', 'plat_col_name', 'plat_col_plan', 'plat_col_status', 'plat_no_data',
    'plat_search', 'plat_clear_filters', 'loading', 'load_error', 'action_success', 'action_error',
    'plat_licenses_subtitle', 'plat_licenses_badge', 'plat_licenses_count', 'plat_licenses_load_error',
    'plat_licenses_empty', 'plat_licenses_empty_hint', 'plat_licenses_kpi_total', 'plat_licenses_kpi_active',
    'plat_licenses_kpi_expiring', 'plat_licenses_kpi_revoked', 'plat_licenses_filter_all_status',
    'plat_licenses_filter_all_types', 'plat_licenses_col_key', 'plat_licenses_col_type', 'plat_licenses_col_seats',
    'plat_licenses_col_expires', 'plat_licenses_col_issued', 'plat_licenses_status_active',
    'plat_licenses_status_revoked', 'plat_licenses_status_expired', 'plat_licenses_type_cloud',
    'plat_licenses_type_on_prem', 'plat_licenses_type_partner', 'plat_licenses_type_trial',
    'plat_licenses_issue', 'plat_licenses_revoke', 'plat_licenses_confirm_revoke', 'plat_licenses_view_org',
    'plat_licenses_unassigned', 'plat_licenses_issue_title', 'plat_licenses_issue_submit',
    'plat_licenses_issue_cancel', 'plat_licenses_field_tenant', 'plat_licenses_field_type',
    'plat_licenses_field_plan', 'plat_licenses_field_seats', 'plat_licenses_field_expires',
    'plat_licenses_field_notes', 'plat_licenses_tenant_none', 'plat_licenses_key_reveal_title',
    'plat_licenses_key_reveal_hint', 'plat_licenses_key_copy', 'plat_licenses_key_copied',
    'plat_view_detail',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-licenses">
    <div class="plat-licenses-error" id="platLicensesError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platLicensesErrorText"></span>
    </div>
    <div class="plat-licenses-alert" id="platLicensesAlert" hidden role="status"></div>

    <section class="plat-licenses-hero" aria-labelledby="platLicensesHeroTitle">
        <div class="plat-licenses-hero__intro">
            <div class="plat-licenses-badge">
                <span class="material-icons-round" aria-hidden="true">verified_user</span>
                <?php echo __t('plat_licenses_badge', 'platform'); ?>
            </div>
            <h2 class="plat-licenses-hero__title" id="platLicensesHeroTitle"><?php echo __t('plat_nav_licenses', 'platform'); ?></h2>
            <p class="plat-licenses-hero__desc"><?php echo __t('plat_licenses_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-licenses-hero__actions">
            <p class="plat-licenses-count" id="platLicensesCount" aria-live="polite"></p>
            <button type="button" class="plat-licenses-issue-btn" id="platLicensesIssueOpen">
                <span class="material-icons-round" aria-hidden="true">add</span>
                <?php echo __t('plat_licenses_issue', 'platform'); ?>
            </button>
        </div>
    </section>

    <section class="plat-kpi-grid plat-licenses-kpi-grid" id="platLicensesKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">vpn_key</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_licenses_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLicKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_licenses_kpi_active', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLicKpiActive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">schedule</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_licenses_kpi_expiring', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLicKpiExpiring">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">block</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_licenses_kpi_revoked', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLicKpiRevoked">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-licenses-panel">
        <div class="plat-licenses-toolbar" id="platLicensesFilters">
            <div class="plat-licenses-search-wrap">
                <span class="material-icons-round plat-licenses-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platLicensesSearch" class="plat-search plat-licenses-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platLicensesStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_licenses_filter_all_status', 'platform'); ?></option>
                <option value="active"><?php echo __t('plat_licenses_status_active', 'platform'); ?></option>
                <option value="revoked"><?php echo __t('plat_licenses_status_revoked', 'platform'); ?></option>
                <option value="expired"><?php echo __t('plat_licenses_status_expired', 'platform'); ?></option>
            </select>
            <select id="platLicensesTypeFilter" class="plat-select" aria-label="<?php echo __t('plat_licenses_col_type', 'platform'); ?>">
                <option value=""><?php echo __t('plat_licenses_filter_all_types', 'platform'); ?></option>
                <option value="cloud"><?php echo __t('plat_licenses_type_cloud', 'platform'); ?></option>
                <option value="on_prem"><?php echo __t('plat_licenses_type_on_prem', 'platform'); ?></option>
                <option value="partner"><?php echo __t('plat_licenses_type_partner', 'platform'); ?></option>
                <option value="trial"><?php echo __t('plat_licenses_type_trial', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-licenses-clear-btn" id="platLicensesClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-licenses-table-wrap">
            <table class="plat-table plat-licenses-table" id="platLicensesTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_licenses_col_key', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_licenses_col_type', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_plan', 'platform'); ?></th>
                        <th><?php echo __t('plat_licenses_col_seats', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_licenses_col_expires', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platLicensesBody">
                    <tr class="plat-licenses-loading-row">
                        <td colspan="8">
                            <span class="plat-licenses-loading">
                                <span class="plat-licenses-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-licenses-empty" id="platLicensesEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">vpn_key_off</span>
            <h3><?php echo __t('plat_licenses_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_licenses_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<dialog class="plat-licenses-dialog" id="platLicensesIssueDialog">
    <form method="dialog" class="plat-licenses-dialog__inner" id="platLicensesIssueForm">
        <header class="plat-licenses-dialog__head">
            <h3><?php echo __t('plat_licenses_issue_title', 'platform'); ?></h3>
            <button type="button" class="plat-licenses-dialog__close" id="platLicensesIssueClose" aria-label="<?php echo __t('plat_licenses_issue_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-licenses-dialog__body">
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_tenant', 'platform'); ?></span>
                <select id="platLicIssueTenant" class="plat-select">
                    <option value=""><?php echo __t('plat_licenses_tenant_none', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_type', 'platform'); ?></span>
                <select id="platLicIssueType" class="plat-select" required>
                    <option value="cloud"><?php echo __t('plat_licenses_type_cloud', 'platform'); ?></option>
                    <option value="on_prem"><?php echo __t('plat_licenses_type_on_prem', 'platform'); ?></option>
                    <option value="partner"><?php echo __t('plat_licenses_type_partner', 'platform'); ?></option>
                    <option value="trial"><?php echo __t('plat_licenses_type_trial', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_plan', 'platform'); ?></span>
                <select id="platLicIssuePlan" class="plat-select">
                    <option value="">—</option>
                </select>
            </label>
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_seats', 'platform'); ?></span>
                <input type="number" id="platLicIssueSeats" class="plat-search" min="1" placeholder="—">
            </label>
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_expires', 'platform'); ?></span>
                <input type="date" id="platLicIssueExpires" class="plat-search">
            </label>
            <label class="plat-licenses-field">
                <span><?php echo __t('plat_licenses_field_notes', 'platform'); ?></span>
                <textarea id="platLicIssueNotes" class="plat-licenses-textarea" rows="3"></textarea>
            </label>
        </div>
        <footer class="plat-licenses-dialog__foot">
            <button type="button" class="plat-licenses-btn" id="platLicensesIssueCancel"><?php echo __t('plat_licenses_issue_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-licenses-btn plat-licenses-btn--primary" id="platLicensesIssueSubmit"><?php echo __t('plat_licenses_issue_submit', 'platform'); ?></button>
        </footer>
    </form>
</dialog>

<dialog class="plat-licenses-dialog plat-licenses-dialog--reveal" id="platLicensesRevealDialog">
    <div class="plat-licenses-dialog__inner">
        <header class="plat-licenses-dialog__head">
            <h3><?php echo __t('plat_licenses_key_reveal_title', 'platform'); ?></h3>
        </header>
        <div class="plat-licenses-dialog__body">
            <p class="plat-licenses-reveal-hint"><?php echo __t('plat_licenses_key_reveal_hint', 'platform'); ?></p>
            <div class="plat-licenses-key-box">
                <code id="platLicensesRevealKey"></code>
                <button type="button" class="plat-licenses-btn" id="platLicensesCopyKey">
                    <span class="material-icons-round" aria-hidden="true">content_copy</span>
                    <?php echo __t('plat_licenses_key_copy', 'platform'); ?>
                </button>
            </div>
        </div>
        <footer class="plat-licenses-dialog__foot">
            <button type="button" class="plat-licenses-btn plat-licenses-btn--primary" id="platLicensesRevealClose">OK</button>
        </footer>
    </div>
</dialog>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

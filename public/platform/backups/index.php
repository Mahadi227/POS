<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'backups';
$pageTitle = __t('plat_nav_backups', 'platform');
$extraStyles = ['platform-governance.css', 'platform-backups.css'];
$extraScripts = ['platform-common.js', 'platform-backups.js'];
$canManageBackups = ($_SESSION['platform_role'] ?? '') === 'platform_admin';
$pageI18n = plat_i18n([
    'plat_nav_backups', 'plat_backups_badge', 'plat_backups_subtitle', 'plat_backups_load_error',
    'plat_backups_kpi_total', 'plat_backups_kpi_completed', 'plat_backups_kpi_failed',
    'plat_backups_kpi_today', 'plat_backups_kpi_storage', 'plat_backups_count',
    'plat_backups_create', 'plat_backups_create_title', 'plat_backups_run', 'plat_backups_cancel',
    'plat_backups_field_label', 'plat_backups_field_scope', 'plat_backups_field_tenant',
    'plat_backups_scope_full', 'plat_backups_scope_schema', 'plat_backups_scope_tenant',
    'plat_backups_col_label', 'plat_backups_col_scope', 'plat_backups_col_status', 'plat_backups_col_size',
    'plat_backups_col_tenant', 'plat_backups_col_created', 'plat_backups_col_by', 'plat_col_status',
    'plat_backups_status_pending', 'plat_backups_status_running', 'plat_backups_status_completed',
    'plat_backups_status_failed', 'plat_backups_download', 'plat_backups_delete', 'plat_backups_admin_only',
    'plat_backups_create_success', 'plat_backups_create_error', 'plat_backups_confirm_delete',
    'plat_backups_select_tenant', 'plat_clear_filters', 'plat_search', 'loading', 'load_error',
    'plat_no_data', 'action_success', 'action_error',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-backups">
    <div class="plat-gov-error" id="platBackupsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platBackupsErrorText"></span>
    </div>
    <div class="plat-backups-alert" id="platBackupsAlert" hidden role="status"></div>

    <section class="plat-gov-hero plat-backups-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge plat-backups-badge">
                <span class="material-icons-round" aria-hidden="true">backup</span>
                <?php echo __t('plat_backups_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_backups', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_backups_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-gov-hero__actions">
            <p class="plat-gov-count" id="platBackupsCount" aria-live="polite"></p>
            <?php if ($canManageBackups): ?>
            <button type="button" class="plat-backups-create-btn" id="platBackupsCreateOpen">
                <span class="material-icons-round" aria-hidden="true">add</span>
                <?php echo __t('plat_backups_create', 'platform'); ?>
            </button>
            <?php else: ?>
            <p class="plat-backups-admin-hint"><?php echo __t('plat_backups_admin_only', 'platform'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platBackupsKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_backups_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBkKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_backups_kpi_completed', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBkKpiCompleted">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--warn is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">error</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_backups_kpi_failed', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBkKpiFailed">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">today</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_backups_kpi_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBkKpiToday">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">sd_storage</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_backups_kpi_storage', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platBkKpiStorage">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-backups-panel">
        <div class="plat-backups-toolbar">
            <div class="plat-backups-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="platBackupsSearch" class="plat-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platBackupsStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_col_status', 'platform'); ?></option>
                <option value="completed"><?php echo __t('plat_backups_status_completed', 'platform'); ?></option>
                <option value="failed"><?php echo __t('plat_backups_status_failed', 'platform'); ?></option>
                <option value="running"><?php echo __t('plat_backups_status_running', 'platform'); ?></option>
                <option value="pending"><?php echo __t('plat_backups_status_pending', 'platform'); ?></option>
            </select>
            <select id="platBackupsScopeFilter" class="plat-select" aria-label="<?php echo __t('plat_backups_col_scope', 'platform'); ?>">
                <option value=""><?php echo __t('plat_backups_col_scope', 'platform'); ?></option>
                <option value="full"><?php echo __t('plat_backups_scope_full', 'platform'); ?></option>
                <option value="schema"><?php echo __t('plat_backups_scope_schema', 'platform'); ?></option>
                <option value="tenant"><?php echo __t('plat_backups_scope_tenant', 'platform'); ?></option>
            </select>
        </div>

        <div class="plat-table-wrap">
            <table class="plat-table plat-backups-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_backups_col_label', 'platform'); ?></th>
                        <th><?php echo __t('plat_backups_col_scope', 'platform'); ?></th>
                        <th><?php echo __t('plat_backups_col_tenant', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_backups_col_size', 'platform'); ?></th>
                        <th><?php echo __t('plat_backups_col_by', 'platform'); ?></th>
                        <th><?php echo __t('plat_backups_col_created', 'platform'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="platBackupsBody">
                    <tr><td colspan="8" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($canManageBackups): ?>
<dialog class="plat-backups-dialog" id="platBackupsDialog">
    <form method="dialog" class="plat-backups-dialog__inner" id="platBackupsForm">
        <header class="plat-backups-dialog__head">
            <h3><?php echo __t('plat_backups_create_title', 'platform'); ?></h3>
            <button type="button" class="plat-backups-dialog__close" id="platBackupsDialogClose" aria-label="<?php echo __t('plat_backups_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-backups-dialog__body">
            <label class="plat-backups-field">
                <span><?php echo __t('plat_backups_field_label', 'platform'); ?></span>
                <input type="text" id="platBkLabel" class="plat-input" placeholder="<?php echo __t('plat_backups_create', 'platform'); ?>">
            </label>
            <label class="plat-backups-field">
                <span><?php echo __t('plat_backups_field_scope', 'platform'); ?></span>
                <select id="platBkScope" class="plat-select">
                    <option value="full"><?php echo __t('plat_backups_scope_full', 'platform'); ?></option>
                    <option value="schema"><?php echo __t('plat_backups_scope_schema', 'platform'); ?></option>
                    <option value="tenant"><?php echo __t('plat_backups_scope_tenant', 'platform'); ?></option>
                </select>
            </label>
            <label class="plat-backups-field" id="platBkTenantWrap" hidden>
                <span><?php echo __t('plat_backups_field_tenant', 'platform'); ?></span>
                <select id="platBkTenant" class="plat-select">
                    <option value=""><?php echo __t('plat_backups_select_tenant', 'platform'); ?></option>
                </select>
            </label>
        </div>
        <footer class="plat-backups-dialog__foot">
            <button type="button" class="plat-backups-dialog__cancel" id="platBackupsDialogCancel"><?php echo __t('plat_backups_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-backups-dialog__submit" id="platBackupsRunBtn">
                <span class="material-icons-round" aria-hidden="true">backup</span>
                <?php echo __t('plat_backups_run', 'platform'); ?>
            </button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<script>window.PLATFORM_BACKUPS = { canManage: <?php echo $canManageBackups ? 'true' : 'false'; ?> };</script>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

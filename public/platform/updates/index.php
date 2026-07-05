<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'updates';
$pageTitle = __t('plat_nav_updates', 'platform');
$extraStyles = ['platform-governance.css', 'platform-updates.css'];
$extraScripts = ['platform-common.js', 'platform-updates.js'];
$canManage = ($_SESSION['platform_role'] ?? '') === 'platform_admin';
$pageI18n = plat_i18n([
    'plat_nav_updates', 'plat_upd_badge', 'plat_upd_subtitle', 'plat_upd_load_error',
    'plat_upd_count', 'plat_upd_current', 'plat_upd_kpi_total', 'plat_upd_kpi_released',
    'plat_upd_kpi_scheduled', 'plat_upd_kpi_draft', 'plat_upd_kpi_migrations', 'plat_upd_kpi_maintenance',
    'plat_upd_tab_releases', 'plat_upd_tab_migrations', 'plat_upd_create', 'plat_upd_create_title',
    'plat_upd_edit_title', 'plat_upd_edit', 'plat_upd_save', 'plat_upd_cancel', 'plat_upd_publish', 'plat_upd_delete',
    'plat_upd_view', 'plat_upd_close', 'plat_upd_field_version', 'plat_upd_field_title', 'plat_upd_field_summary',
    'plat_upd_field_changelog', 'plat_upd_field_type', 'plat_upd_field_status', 'plat_upd_field_migration',
    'plat_upd_field_maintenance', 'plat_upd_col_version', 'plat_upd_col_title', 'plat_upd_col_type',
    'plat_upd_col_migration', 'plat_upd_col_published', 'plat_upd_col_by', 'plat_upd_col_applied',
    'plat_upd_col_release', 'plat_upd_type_major', 'plat_upd_type_minor', 'plat_upd_type_patch',
    'plat_upd_type_hotfix', 'plat_upd_type_migration', 'plat_upd_status_draft', 'plat_upd_status_scheduled',
    'plat_upd_status_released', 'plat_upd_status_rolled_back', 'plat_upd_maintenance_yes', 'plat_upd_maintenance_no',
    'plat_upd_admin_only', 'plat_upd_confirm_delete', 'plat_upd_confirm_publish', 'plat_upd_create_success',
    'plat_upd_publish_success', 'plat_upd_filter_all_types', 'plat_upd_filter_all_status', 'plat_col_status',
    'plat_search', 'loading', 'load_error', 'plat_no_data', 'action_success', 'action_error',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-upd">
    <div class="plat-gov-error" id="platUpdError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platUpdErrorText"></span>
    </div>
    <div class="plat-upd-alert" id="platUpdAlert" hidden role="status"></div>

    <section class="plat-gov-hero plat-upd-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge plat-upd-badge">
                <span class="material-icons-round" aria-hidden="true">system_update</span>
                <?php echo __t('plat_upd_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_updates', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_upd_subtitle', 'platform'); ?></p>
            <p class="plat-upd-current" id="platUpdCurrent"></p>
        </div>
        <div class="plat-gov-hero__actions">
            <p class="plat-gov-count" id="platUpdCount" aria-live="polite"></p>
            <?php if ($canManage): ?>
            <button type="button" class="plat-upd-create-btn" id="platUpdCreateOpen">
                <span class="material-icons-round" aria-hidden="true">add</span>
                <?php echo __t('plat_upd_create', 'platform'); ?>
            </button>
            <?php else: ?>
            <p class="plat-upd-admin-hint"><?php echo __t('plat_upd_admin_only', 'platform'); ?></p>
            <?php endif; ?>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platUpdKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">new_releases</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_upd_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUpdKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_upd_kpi_released', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUpdKpiReleased">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">schedule</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_upd_kpi_scheduled', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUpdKpiScheduled">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">edit_note</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_upd_kpi_draft', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUpdKpiDraft">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">storage</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_upd_kpi_migrations', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUpdKpiMigrations">—</strong>
        </article>
    </section>

    <div class="plat-upd-tabs" role="tablist">
        <button type="button" class="plat-upd-tab is-active" role="tab" aria-selected="true" data-tab="releases">
            <?php echo __t('plat_upd_tab_releases', 'platform'); ?>
        </button>
        <button type="button" class="plat-upd-tab" role="tab" aria-selected="false" data-tab="migrations">
            <?php echo __t('plat_upd_tab_migrations', 'platform'); ?>
        </button>
    </div>

    <section class="plat-panel plat-upd-panel" id="platUpdReleasesPanel" role="tabpanel">
        <div class="plat-upd-toolbar">
            <div class="plat-upd-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="platUpdSearch" class="plat-search" placeholder="<?php echo __t('plat_search', 'platform'); ?>">
            </div>
            <select id="platUpdTypeFilter" class="plat-select" aria-label="<?php echo __t('plat_upd_col_type', 'platform'); ?>">
                <option value=""><?php echo __t('plat_upd_filter_all_types', 'platform'); ?></option>
                <option value="major"><?php echo __t('plat_upd_type_major', 'platform'); ?></option>
                <option value="minor"><?php echo __t('plat_upd_type_minor', 'platform'); ?></option>
                <option value="patch"><?php echo __t('plat_upd_type_patch', 'platform'); ?></option>
                <option value="hotfix"><?php echo __t('plat_upd_type_hotfix', 'platform'); ?></option>
                <option value="migration"><?php echo __t('plat_upd_type_migration', 'platform'); ?></option>
            </select>
            <select id="platUpdStatusFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_upd_filter_all_status', 'platform'); ?></option>
                <option value="released"><?php echo __t('plat_upd_status_released', 'platform'); ?></option>
                <option value="scheduled"><?php echo __t('plat_upd_status_scheduled', 'platform'); ?></option>
                <option value="draft"><?php echo __t('plat_upd_status_draft', 'platform'); ?></option>
                <option value="rolled_back"><?php echo __t('plat_upd_status_rolled_back', 'platform'); ?></option>
            </select>
        </div>
        <div class="plat-table-wrap">
            <table class="plat-table plat-upd-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_upd_col_version', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_title', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_type', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_migration', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_published', 'platform'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="platUpdBody">
                    <tr><td colspan="7" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <section class="plat-panel plat-upd-panel" id="platUpdMigrationsPanel" role="tabpanel" hidden>
        <div class="plat-table-wrap">
            <table class="plat-table plat-upd-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_upd_col_migration', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_applied', 'platform'); ?></th>
                        <th><?php echo __t('plat_upd_col_release', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platUpdMigBody">
                    <tr><td colspan="4" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php if ($canManage): ?>
<dialog class="plat-upd-dialog" id="platUpdFormDialog">
    <form method="dialog" class="plat-upd-dialog__inner" id="platUpdForm">
        <header class="plat-upd-dialog__head">
            <h3 id="platUpdFormTitle"><?php echo __t('plat_upd_create_title', 'platform'); ?></h3>
            <button type="button" class="plat-upd-dialog__close" id="platUpdFormClose" aria-label="<?php echo __t('plat_upd_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-upd-dialog__body">
            <input type="hidden" id="platUpdEditId" value="">
            <label class="plat-upd-field">
                <span><?php echo __t('plat_upd_field_version', 'platform'); ?></span>
                <input type="text" id="platUpdVersion" class="plat-input" placeholder="3.1.0" required>
            </label>
            <label class="plat-upd-field">
                <span><?php echo __t('plat_upd_field_title', 'platform'); ?></span>
                <input type="text" id="platUpdTitle" class="plat-input" required>
            </label>
            <label class="plat-upd-field">
                <span><?php echo __t('plat_upd_field_summary', 'platform'); ?></span>
                <input type="text" id="platUpdSummary" class="plat-input">
            </label>
            <label class="plat-upd-field">
                <span><?php echo __t('plat_upd_field_changelog', 'platform'); ?></span>
                <textarea id="platUpdChangelog" class="plat-input plat-upd-textarea" rows="5"></textarea>
            </label>
            <div class="plat-upd-field-row">
                <label class="plat-upd-field">
                    <span><?php echo __t('plat_upd_field_type', 'platform'); ?></span>
                    <select id="platUpdType" class="plat-select">
                        <option value="minor"><?php echo __t('plat_upd_type_minor', 'platform'); ?></option>
                        <option value="major"><?php echo __t('plat_upd_type_major', 'platform'); ?></option>
                        <option value="patch"><?php echo __t('plat_upd_type_patch', 'platform'); ?></option>
                        <option value="hotfix"><?php echo __t('plat_upd_type_hotfix', 'platform'); ?></option>
                        <option value="migration"><?php echo __t('plat_upd_type_migration', 'platform'); ?></option>
                    </select>
                </label>
                <label class="plat-upd-field">
                    <span><?php echo __t('plat_upd_field_status', 'platform'); ?></span>
                    <select id="platUpdStatus" class="plat-select">
                        <option value="draft"><?php echo __t('plat_upd_status_draft', 'platform'); ?></option>
                        <option value="scheduled"><?php echo __t('plat_upd_status_scheduled', 'platform'); ?></option>
                    </select>
                </label>
            </div>
            <label class="plat-upd-field">
                <span><?php echo __t('plat_upd_field_migration', 'platform'); ?></span>
                <input type="text" id="platUpdMigration" class="plat-input" placeholder="036_platform_updates">
            </label>
            <label class="plat-upd-check">
                <input type="checkbox" id="platUpdMaintenance">
                <span><?php echo __t('plat_upd_field_maintenance', 'platform'); ?></span>
            </label>
        </div>
        <footer class="plat-upd-dialog__foot">
            <button type="button" class="plat-upd-dialog__cancel" id="platUpdFormCancel"><?php echo __t('plat_upd_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-upd-dialog__submit"><?php echo __t('plat_upd_save', 'platform'); ?></button>
        </footer>
    </form>
</dialog>
<?php endif; ?>

<dialog class="plat-upd-dialog plat-upd-dialog--view" id="platUpdViewDialog">
    <div class="plat-upd-dialog__inner">
        <header class="plat-upd-dialog__head">
            <h3 id="platUpdViewTitle"></h3>
            <button type="button" class="plat-upd-dialog__close" id="platUpdViewClose" aria-label="<?php echo __t('plat_upd_close', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-upd-dialog__body" id="platUpdViewBody"></div>
        <footer class="plat-upd-dialog__foot" id="platUpdViewFoot"></footer>
    </div>
</dialog>

<script>window.PLATFORM_UPDATES = { canManage: <?php echo $canManage ? 'true' : 'false'; ?> };</script>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

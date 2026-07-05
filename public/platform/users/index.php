<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'users';
$pageTitle = __t('plat_nav_users', 'platform');
$extraStyles = ['platform-users.css'];
$extraScripts = ['platform-common.js', 'platform-users.js'];
$pageI18n = plat_i18n([
    'plat_nav_users', 'plat_col_status', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error', 'action_success', 'action_error',
    'plat_users_subtitle', 'plat_users_badge', 'plat_users_count', 'plat_users_load_error',
    'plat_users_empty', 'plat_users_empty_hint', 'plat_users_kpi_total', 'plat_users_kpi_active',
    'plat_users_kpi_inactive', 'plat_users_kpi_admins', 'plat_users_col_email', 'plat_users_col_name',
    'plat_users_col_role', 'plat_users_col_last_login', 'plat_users_col_created',
    'plat_users_filter_all_roles', 'plat_users_filter_all_status', 'plat_users_filter_active',
    'plat_users_filter_inactive', 'plat_users_role_platform_admin', 'plat_users_role_support',
    'plat_users_status_active', 'plat_users_status_inactive', 'plat_users_add', 'plat_users_add_title',
    'plat_users_add_submit', 'plat_users_add_cancel', 'plat_users_field_name', 'plat_users_field_email',
    'plat_users_field_password', 'plat_users_field_role', 'plat_users_activate', 'plat_users_deactivate',
    'plat_users_confirm_deactivate', 'plat_users_confirm_activate', 'plat_users_you',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-users">
    <div class="plat-users-error" id="platUsersError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platUsersErrorText"></span>
    </div>
    <div class="plat-users-alert" id="platUsersAlert" hidden role="status"></div>

    <section class="plat-users-hero" aria-labelledby="platUsersHeroTitle">
        <div class="plat-users-hero__intro">
            <div class="plat-users-badge">
                <span class="material-icons-round" aria-hidden="true">admin_panel_settings</span>
                <?php echo __t('plat_users_badge', 'platform'); ?>
            </div>
            <h2 class="plat-users-hero__title" id="platUsersHeroTitle"><?php echo __t('plat_nav_users', 'platform'); ?></h2>
            <p class="plat-users-hero__desc"><?php echo __t('plat_users_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-users-hero__actions">
            <p class="plat-users-count" id="platUsersCount" aria-live="polite"></p>
            <button type="button" class="plat-users-add-btn" id="platUsersAddOpen">
                <span class="material-icons-round" aria-hidden="true">person_add</span>
                <?php echo __t('plat_users_add', 'platform'); ?>
            </button>
        </div>
    </section>

    <section class="plat-kpi-grid plat-users-kpi-grid" id="platUsersKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">group</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_users_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUsrKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_users_kpi_active', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUsrKpiActive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">person_off</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_users_kpi_inactive', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUsrKpiInactive">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">shield</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_users_kpi_admins', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platUsrKpiAdmins">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-users-panel">
        <div class="plat-users-toolbar">
            <div class="plat-users-search-wrap">
                <span class="material-icons-round plat-users-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platUsersSearch" class="plat-search plat-users-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platUsersRoleFilter" class="plat-select" aria-label="<?php echo __t('plat_users_col_role', 'platform'); ?>">
                <option value=""><?php echo __t('plat_users_filter_all_roles', 'platform'); ?></option>
                <option value="platform_admin"><?php echo __t('plat_users_role_platform_admin', 'platform'); ?></option>
                <option value="support"><?php echo __t('plat_users_role_support', 'platform'); ?></option>
            </select>
            <select id="platUsersActiveFilter" class="plat-select" aria-label="<?php echo __t('plat_col_status', 'platform'); ?>">
                <option value=""><?php echo __t('plat_users_filter_all_status', 'platform'); ?></option>
                <option value="yes"><?php echo __t('plat_users_filter_active', 'platform'); ?></option>
                <option value="no"><?php echo __t('plat_users_filter_inactive', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-users-clear-btn" id="platUsersClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-users-table-wrap">
            <table class="plat-table plat-users-table" id="platUsersTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_users_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_users_col_email', 'platform'); ?></th>
                        <th><?php echo __t('plat_users_col_role', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_status', 'platform'); ?></th>
                        <th><?php echo __t('plat_users_col_last_login', 'platform'); ?></th>
                        <th><?php echo __t('plat_users_col_created', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platUsersBody">
                    <tr class="plat-users-loading-row">
                        <td colspan="7">
                            <span class="plat-users-loading">
                                <span class="plat-users-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-users-empty" id="platUsersEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">group_off</span>
            <h3><?php echo __t('plat_users_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_users_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<dialog class="plat-users-dialog" id="platUsersAddDialog">
    <form method="dialog" class="plat-users-dialog__inner" id="platUsersAddForm">
        <header class="plat-users-dialog__head">
            <h3><?php echo __t('plat_users_add_title', 'platform'); ?></h3>
            <button type="button" class="plat-users-dialog__close" id="platUsersAddClose" aria-label="<?php echo __t('plat_users_add_cancel', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-users-dialog__body">
            <label class="plat-users-field">
                <span><?php echo __t('plat_users_field_name', 'platform'); ?></span>
                <input type="text" id="platUsrAddName" class="plat-input" required minlength="2" autocomplete="name">
            </label>
            <label class="plat-users-field">
                <span><?php echo __t('plat_users_field_email', 'platform'); ?></span>
                <input type="email" id="platUsrAddEmail" class="plat-input" required autocomplete="email">
            </label>
            <label class="plat-users-field">
                <span><?php echo __t('plat_users_field_password', 'platform'); ?></span>
                <input type="password" id="platUsrAddPassword" class="plat-input" required minlength="8" autocomplete="new-password">
            </label>
            <label class="plat-users-field">
                <span><?php echo __t('plat_users_field_role', 'platform'); ?></span>
                <select id="platUsrAddRole" class="plat-select" required>
                    <option value="platform_admin"><?php echo __t('plat_users_role_platform_admin', 'platform'); ?></option>
                    <option value="support"><?php echo __t('plat_users_role_support', 'platform'); ?></option>
                </select>
            </label>
        </div>
        <footer class="plat-users-dialog__foot">
            <button type="button" class="plat-users-dialog__cancel" id="platUsersAddCancel"><?php echo __t('plat_users_add_cancel', 'platform'); ?></button>
            <button type="submit" class="plat-users-dialog__submit" id="platUsersAddSubmit"><?php echo __t('plat_users_add_submit', 'platform'); ?></button>
        </footer>
    </form>
</dialog>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

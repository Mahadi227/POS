<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('../login.php');

$activePlatPage = 'profile';
$pageTitle = __t('plat_nav_profile', 'platform');
$extraStyles = ['platform-governance.css', 'platform-profile.css'];
$extraScripts = ['platform-common.js', 'platform-profile.js'];
$pageI18n = plat_i18n([
    'plat_nav_profile', 'plat_profile_badge', 'plat_profile_subtitle', 'plat_profile_load_error',
    'plat_profile_account', 'plat_profile_edit', 'plat_profile_password', 'plat_profile_save',
    'plat_profile_field_name', 'plat_profile_field_email', 'plat_profile_field_role',
    'plat_profile_field_status', 'plat_profile_field_member_since', 'plat_profile_field_last_login',
    'plat_profile_field_session', 'plat_profile_status_active', 'plat_profile_status_inactive',
    'plat_profile_current_password', 'plat_profile_new_password', 'plat_profile_confirm_password',
    'plat_profile_update_success', 'plat_profile_password_success', 'plat_profile_password_mismatch',
    'plat_profile_recent_activity', 'plat_profile_no_activity', 'plat_users_role_platform_admin',
    'plat_users_role_support', 'plat_profile_action_update', 'plat_profile_action_password', 'loading', 'load_error', 'action_success', 'action_error', 'plat_no_data',
    'plat_audit_col_date', 'plat_audit_col_action', 'plat_audit_col_org', 'plat_audit_col_ip',
    'plat_audit_action_platform_login_success', 'plat_audit_action_platform_login_failed',
    'plat_audit_action_platform_logout', 'plat_audit_action_tenant_impersonate_start',
    'plat_audit_action_tenant_impersonate_end', 'plat_audit_action_platform_settings_update',
]);
$initial = strtoupper(substr($_SESSION['platform_name'] ?? 'P', 0, 1));
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-profile plat-gov">
    <div class="plat-gov-error" id="platProfileError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platProfileErrorText"></span>
    </div>
    <div class="plat-profile-alert" id="platProfileAlert" hidden role="status"></div>

    <section class="plat-gov-hero plat-profile-hero" aria-labelledby="platProfileHeroTitle">
        <div class="plat-profile-hero__main">
            <div class="plat-profile-avatar" id="platProfileAvatar" aria-hidden="true"><?php echo htmlspecialchars($initial); ?></div>
            <div class="plat-gov-hero__intro">
                <div class="plat-gov-badge plat-profile-badge">
                    <span class="material-icons-round" aria-hidden="true">account_circle</span>
                    <?php echo __t('plat_profile_badge', 'platform'); ?>
                </div>
                <h2 class="plat-gov-hero__title" id="platProfileHeroTitle"><?php echo __t('plat_nav_profile', 'platform'); ?></h2>
                <p class="plat-gov-hero__desc"><?php echo __t('plat_profile_subtitle', 'platform'); ?></p>
                <p class="plat-profile-meta" id="platProfileMeta" aria-live="polite">—</p>
            </div>
        </div>
    </section>

    <div class="plat-profile-grid">
        <section class="plat-panel plat-profile-card" aria-labelledby="platProfileAccountTitle">
            <h3 id="platProfileAccountTitle">
                <span class="material-icons-round" aria-hidden="true">badge</span>
                <?php echo __t('plat_profile_account', 'platform'); ?>
            </h3>
            <dl class="plat-profile-dl" id="platProfileAccountDl">
                <div><dt><?php echo __t('plat_profile_field_role', 'platform'); ?></dt><dd id="platProfRole">—</dd></div>
                <div><dt><?php echo __t('plat_profile_field_status', 'platform'); ?></dt><dd id="platProfStatus">—</dd></div>
                <div><dt><?php echo __t('plat_profile_field_member_since', 'platform'); ?></dt><dd id="platProfCreated">—</dd></div>
                <div><dt><?php echo __t('plat_profile_field_last_login', 'platform'); ?></dt><dd id="platProfLastLogin">—</dd></div>
                <div><dt><?php echo __t('plat_profile_field_session', 'platform'); ?></dt><dd id="platProfSession">—</dd></div>
            </dl>
        </section>

        <section class="plat-panel plat-profile-card" aria-labelledby="platProfileEditTitle">
            <h3 id="platProfileEditTitle">
                <span class="material-icons-round" aria-hidden="true">edit</span>
                <?php echo __t('plat_profile_edit', 'platform'); ?>
            </h3>
            <form id="platProfileForm" class="plat-profile-form" novalidate>
                <label class="plat-profile-field">
                    <span><?php echo __t('plat_profile_field_name', 'platform'); ?></span>
                    <input type="text" id="platProfName" class="plat-input" required minlength="2" autocomplete="name">
                </label>
                <label class="plat-profile-field">
                    <span><?php echo __t('plat_profile_field_email', 'platform'); ?></span>
                    <input type="email" id="platProfEmail" class="plat-input" required autocomplete="email">
                </label>
                <button type="submit" class="plat-profile-save-btn" id="platProfileSaveBtn">
                    <span class="material-icons-round" aria-hidden="true">save</span>
                    <?php echo __t('plat_profile_save', 'platform'); ?>
                </button>
            </form>
        </section>

        <section class="plat-panel plat-profile-card" aria-labelledby="platProfilePasswordTitle">
            <h3 id="platProfilePasswordTitle">
                <span class="material-icons-round" aria-hidden="true">lock</span>
                <?php echo __t('plat_profile_password', 'platform'); ?>
            </h3>
            <form id="platPasswordForm" class="plat-profile-form" novalidate>
                <label class="plat-profile-field">
                    <span><?php echo __t('plat_profile_current_password', 'platform'); ?></span>
                    <input type="password" id="platProfCurrentPw" class="plat-input" required autocomplete="current-password">
                </label>
                <label class="plat-profile-field">
                    <span><?php echo __t('plat_profile_new_password', 'platform'); ?></span>
                    <input type="password" id="platProfNewPw" class="plat-input" required minlength="8" autocomplete="new-password">
                </label>
                <label class="plat-profile-field">
                    <span><?php echo __t('plat_profile_confirm_password', 'platform'); ?></span>
                    <input type="password" id="platProfConfirmPw" class="plat-input" required minlength="8" autocomplete="new-password">
                </label>
                <button type="submit" class="plat-profile-save-btn plat-profile-save-btn--secondary" id="platPasswordSaveBtn">
                    <span class="material-icons-round" aria-hidden="true">vpn_key</span>
                    <?php echo __t('plat_profile_password', 'platform'); ?>
                </button>
            </form>
        </section>
    </div>

    <section class="plat-panel plat-profile-activity" aria-labelledby="platProfileActivityTitle">
        <h3 id="platProfileActivityTitle">
            <span class="material-icons-round" aria-hidden="true">history</span>
            <?php echo __t('plat_profile_recent_activity', 'platform'); ?>
        </h3>
        <div class="plat-table-wrap">
            <table class="plat-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_audit_col_date', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_action', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_org', 'platform'); ?></th>
                        <th><?php echo __t('plat_audit_col_ip', 'platform'); ?></th>
                    </tr>
                </thead>
                <tbody id="platProfileActivityBody">
                    <tr><td colspan="4" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

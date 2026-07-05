<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'security';
$pageTitle = __t('plat_nav_security', 'platform');
$extraStyles = ['platform-governance.css'];
$extraScripts = ['platform-common.js', 'platform-security.js'];
$pageI18n = plat_i18n([
    'plat_nav_security', 'plat_nav_audit', 'plat_nav_users', 'plat_no_data',
    'plat_search', 'plat_clear_filters', 'loading', 'load_error',
    'plat_security_badge', 'plat_security_subtitle', 'plat_security_load_error',
    'plat_security_kpi_failed_today', 'plat_security_kpi_failed_total', 'plat_security_kpi_success_today',
    'plat_security_kpi_events', 'plat_security_kpi_active_users',
    'plat_security_attempts_title', 'plat_security_events_title',
    'plat_security_col_email', 'plat_security_col_status', 'plat_security_col_ip',
    'plat_security_col_user', 'plat_security_col_date', 'plat_security_col_event',
    'plat_security_col_severity', 'plat_security_filter_all_status', 'plat_security_filter_all_severity',
    'plat_security_status_success', 'plat_security_status_failed', 'plat_security_status_locked',
    'plat_security_severity_low', 'plat_security_severity_medium', 'plat_security_severity_high',
    'plat_security_severity_critical', 'plat_security_event_login_lockout',
    'plat_security_view_audit', 'plat_security_view_users',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-gov-security">
    <div class="plat-gov-error" id="platSecurityError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platSecurityErrorText"></span>
    </div>

    <section class="plat-gov-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge">
                <span class="material-icons-round" aria-hidden="true">security</span>
                <?php echo __t('plat_security_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_security', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_security_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-gov-hero__actions">
            <a href="<?php echo htmlspecialchars(plat_href('audit/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-gov-link-btn">
                <span class="material-icons-round" aria-hidden="true">fact_check</span>
                <?php echo __t('plat_security_view_audit', 'platform'); ?>
            </a>
            <a href="<?php echo htmlspecialchars(plat_href('users/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-gov-link-btn">
                <span class="material-icons-round" aria-hidden="true">group</span>
                <?php echo __t('plat_security_view_users', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platSecurityKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--warn is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">block</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_security_kpi_failed_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSecKpiFailedToday">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">warning</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_security_kpi_failed_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSecKpiFailedTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">login</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_security_kpi_success_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSecKpiSuccessToday">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">report</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_security_kpi_events', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSecKpiEvents">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">verified_user</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_security_kpi_active_users', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platSecKpiActiveUsers">—</strong>
        </article>
    </section>

    <div class="plat-gov-mid">
        <section class="plat-panel plat-gov-panel">
            <header class="plat-gov-panel-head">
                <h3><span class="material-icons-round" aria-hidden="true">login</span><?php echo __t('plat_security_attempts_title', 'platform'); ?></h3>
            </header>
            <div class="plat-gov-toolbar">
                <div class="plat-gov-search-wrap">
                    <span class="material-icons-round plat-gov-search-icon" aria-hidden="true">search</span>
                    <input type="search" id="platSecAttemptSearch" class="plat-search plat-gov-search"
                           placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
                </div>
                <select id="platSecAttemptStatus" class="plat-select">
                    <option value=""><?php echo __t('plat_security_filter_all_status', 'platform'); ?></option>
                    <option value="success"><?php echo __t('plat_security_status_success', 'platform'); ?></option>
                    <option value="failed"><?php echo __t('plat_security_status_failed', 'platform'); ?></option>
                    <option value="locked"><?php echo __t('plat_security_status_locked', 'platform'); ?></option>
                </select>
            </div>
            <div class="plat-table-wrap plat-gov-table-wrap">
                <table class="plat-table plat-gov-table">
                    <thead>
                        <tr>
                            <th><?php echo __t('plat_security_col_email', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_status', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_ip', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_date', 'platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="platSecAttempts">
                        <tr><td colspan="4" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="plat-panel plat-gov-panel">
            <header class="plat-gov-panel-head">
                <h3><span class="material-icons-round" aria-hidden="true">shield</span><?php echo __t('plat_security_events_title', 'platform'); ?></h3>
            </header>
            <div class="plat-gov-toolbar">
                <div class="plat-gov-search-wrap">
                    <span class="material-icons-round plat-gov-search-icon" aria-hidden="true">search</span>
                    <input type="search" id="platSecEventSearch" class="plat-search plat-gov-search"
                           placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
                </div>
                <select id="platSecEventSeverity" class="plat-select">
                    <option value=""><?php echo __t('plat_security_filter_all_severity', 'platform'); ?></option>
                    <option value="low"><?php echo __t('plat_security_severity_low', 'platform'); ?></option>
                    <option value="medium"><?php echo __t('plat_security_severity_medium', 'platform'); ?></option>
                    <option value="high"><?php echo __t('plat_security_severity_high', 'platform'); ?></option>
                    <option value="critical"><?php echo __t('plat_security_severity_critical', 'platform'); ?></option>
                </select>
            </div>
            <div class="plat-table-wrap plat-gov-table-wrap">
                <table class="plat-table plat-gov-table">
                    <thead>
                        <tr>
                            <th><?php echo __t('plat_security_col_event', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_severity', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_email', 'platform'); ?></th>
                            <th><?php echo __t('plat_security_col_date', 'platform'); ?></th>
                        </tr>
                    </thead>
                    <tbody id="platSecEvents">
                        <tr><td colspan="4" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

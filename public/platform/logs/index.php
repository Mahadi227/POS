<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'logs';
$pageTitle = __t('plat_nav_logs', 'platform');
$extraStyles = ['platform-governance.css', 'platform-settings.css'];
$extraScripts = ['platform-common.js', 'platform-logs.js'];
$pageI18n = plat_i18n([
    'plat_nav_logs', 'plat_nav_audit', 'plat_nav_security', 'plat_nav_monitoring', 'plat_col_name',
    'plat_no_data', 'plat_search', 'plat_clear_filters', 'loading', 'load_error', 'plat_view_detail',
    'plat_logs_badge', 'plat_logs_subtitle', 'plat_logs_load_error', 'plat_logs_count',
    'plat_logs_kpi_total', 'plat_logs_kpi_today', 'plat_logs_kpi_errors', 'plat_logs_kpi_email',
    'plat_logs_kpi_webhook_failed', 'plat_logs_entries_title', 'plat_logs_col_channel',
    'plat_logs_col_level', 'plat_logs_col_message', 'plat_logs_col_org', 'plat_logs_col_date',
    'plat_logs_filter_all_channels', 'plat_logs_filter_all_levels', 'plat_logs_channel_application',
    'plat_logs_channel_email', 'plat_logs_channel_sms', 'plat_logs_channel_webhook',
    'plat_logs_level_debug', 'plat_logs_level_info', 'plat_logs_level_warning',
    'plat_logs_level_error', 'plat_logs_level_critical', 'plat_logs_detail_title', 'plat_logs_detail_close',
    'plat_logs_detail_context', 'plat_logs_view_audit', 'plat_logs_view_security',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-gov plat-logs">
    <div class="plat-gov-error" id="platLogsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platLogsErrorText"></span>
    </div>

    <section class="plat-gov-hero">
        <div class="plat-gov-hero__intro">
            <div class="plat-gov-badge">
                <span class="material-icons-round" aria-hidden="true">article</span>
                <?php echo __t('plat_logs_badge', 'platform'); ?>
            </div>
            <h2 class="plat-gov-hero__title"><?php echo __t('plat_nav_logs', 'platform'); ?></h2>
            <p class="plat-gov-hero__desc"><?php echo __t('plat_logs_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-gov-hero__actions">
            <p class="plat-gov-count" id="platLogsCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('audit/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-gov-link-btn">
                <span class="material-icons-round" aria-hidden="true">fact_check</span>
                <?php echo __t('plat_logs_view_audit', 'platform'); ?>
            </a>
            <a href="<?php echo htmlspecialchars(plat_href('security/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-gov-link-btn">
                <span class="material-icons-round" aria-hidden="true">security</span>
                <?php echo __t('plat_logs_view_security', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-gov-kpi-grid" id="platLogsKpiGrid">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">layers</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_logs_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLogsKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">today</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_logs_kpi_today', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLogsKpiToday">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--warn is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">error_outline</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_logs_kpi_errors', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLogsKpiErrors">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">mail</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_logs_kpi_email', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLogsKpiEmail">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hub</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_logs_kpi_webhook_failed', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platLogsKpiWebhook">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-gov-panel">
        <header class="plat-gov-panel-head">
            <h3><span class="material-icons-round" aria-hidden="true">list_alt</span><?php echo __t('plat_logs_entries_title', 'platform'); ?></h3>
        </header>
        <div class="plat-gov-toolbar">
            <div class="plat-gov-search-wrap">
                <span class="material-icons-round plat-gov-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platLogsSearch" class="plat-search plat-gov-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platLogsChannel" class="plat-select">
                <option value=""><?php echo __t('plat_logs_filter_all_channels', 'platform'); ?></option>
                <option value="application"><?php echo __t('plat_logs_channel_application', 'platform'); ?></option>
                <option value="email"><?php echo __t('plat_logs_channel_email', 'platform'); ?></option>
                <option value="sms"><?php echo __t('plat_logs_channel_sms', 'platform'); ?></option>
                <option value="webhook"><?php echo __t('plat_logs_channel_webhook', 'platform'); ?></option>
            </select>
            <select id="platLogsLevel" class="plat-select">
                <option value=""><?php echo __t('plat_logs_filter_all_levels', 'platform'); ?></option>
                <option value="debug"><?php echo __t('plat_logs_level_debug', 'platform'); ?></option>
                <option value="info"><?php echo __t('plat_logs_level_info', 'platform'); ?></option>
                <option value="warning"><?php echo __t('plat_logs_level_warning', 'platform'); ?></option>
                <option value="error"><?php echo __t('plat_logs_level_error', 'platform'); ?></option>
                <option value="critical"><?php echo __t('plat_logs_level_critical', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-gov-btn" id="platLogsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>
        <div class="plat-table-wrap plat-gov-table-wrap">
            <table class="plat-table plat-gov-table">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_logs_col_channel', 'platform'); ?></th>
                        <th><?php echo __t('plat_logs_col_level', 'platform'); ?></th>
                        <th><?php echo __t('plat_logs_col_message', 'platform'); ?></th>
                        <th><?php echo __t('plat_logs_col_org', 'platform'); ?></th>
                        <th><?php echo __t('plat_logs_col_date', 'platform'); ?></th>
                        <th></th>
                    </tr>
                </thead>
                <tbody id="platLogsEntries">
                    <tr><td colspan="6" class="plat-gov-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</div>

<div class="plat-gov-modal" id="platLogsModal" hidden>
    <div class="plat-gov-modal__backdrop" data-close-logs-modal></div>
    <div class="plat-gov-modal__panel">
        <header class="plat-gov-modal__head"><h3><?php echo __t('plat_logs_detail_title', 'platform'); ?></h3></header>
        <div class="plat-gov-modal__body" id="platLogsDetail"></div>
        <footer class="plat-gov-modal__foot">
            <button type="button" class="plat-gov-btn" data-close-logs-modal><?php echo __t('plat_logs_detail_close', 'platform'); ?></button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

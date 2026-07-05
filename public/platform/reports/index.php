<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'reports';
$pageTitle = __t('plat_nav_reports', 'platform');
$extraStyles = ['platform-reports.css'];
$extraScripts = ['platform-common.js', 'platform-reports.js'];
$pageI18n = plat_i18n([
    'plat_nav_reports', 'plat_nav_analytics', 'plat_no_data', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error', 'plat_view_detail',
    'plat_reports_subtitle', 'plat_reports_badge', 'plat_reports_count', 'plat_reports_load_error',
    'plat_reports_empty', 'plat_reports_empty_hint', 'plat_reports_kpi_total', 'plat_reports_kpi_categories',
    'plat_reports_kpi_rows', 'plat_reports_kpi_formats', 'plat_reports_view_analytics',
    'plat_reports_filter_all_categories', 'plat_reports_col_rows', 'plat_reports_col_format',
    'plat_reports_preview', 'plat_reports_export', 'plat_reports_unavailable', 'plat_reports_preview_title',
    'plat_reports_preview_rows', 'plat_reports_close', 'plat_reports_format_csv',
    'plat_reports_cat_core', 'plat_reports_cat_billing', 'plat_reports_cat_operations',
    'plat_reports_cat_product', 'plat_reports_cat_security',
    'plat_report_tenants', 'plat_report_subscriptions', 'plat_report_billing',
    'plat_report_revenue_monthly', 'plat_report_usage', 'plat_report_licenses', 'plat_report_audit',
    'plat_report_desc_tenants', 'plat_report_desc_subscriptions', 'plat_report_desc_billing',
    'plat_report_desc_revenue_monthly', 'plat_report_desc_usage', 'plat_report_desc_licenses',
    'plat_report_desc_audit',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-reports">
    <div class="plat-reports-error" id="platReportsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platReportsErrorText"></span>
    </div>

    <section class="plat-reports-hero" aria-labelledby="platReportsHeroTitle">
        <div class="plat-reports-hero__intro">
            <div class="plat-reports-badge">
                <span class="material-icons-round" aria-hidden="true">assessment</span>
                <?php echo __t('plat_reports_badge', 'platform'); ?>
            </div>
            <h2 class="plat-reports-hero__title" id="platReportsHeroTitle"><?php echo __t('plat_nav_reports', 'platform'); ?></h2>
            <p class="plat-reports-hero__desc"><?php echo __t('plat_reports_subtitle', 'platform'); ?></p>
        </div>
        <div class="plat-reports-hero__actions">
            <p class="plat-reports-count" id="platReportsCount" aria-live="polite"></p>
            <a href="<?php echo htmlspecialchars(plat_href('analytics/index.php'), ENT_QUOTES, 'UTF-8'); ?>" class="plat-reports-link-btn">
                <span class="material-icons-round" aria-hidden="true">insights</span>
                <?php echo __t('plat_reports_view_analytics', 'platform'); ?>
            </a>
        </div>
    </section>

    <section class="plat-kpi-grid plat-reports-kpi-grid" id="platReportsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">assessment</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_reports_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRepKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">category</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_reports_kpi_categories', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRepKpiCats">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">table_rows</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_reports_kpi_rows', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRepKpiRows">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">download</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_reports_kpi_formats', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platRepKpiFormats">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-reports-panel">
        <div class="plat-reports-toolbar">
            <div class="plat-reports-search-wrap">
                <span class="material-icons-round plat-reports-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platReportsSearch" class="plat-search plat-reports-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platReportsCategory" class="plat-reports-filter" aria-label="<?php echo __t('plat_reports_filter_all_categories', 'platform'); ?>">
                <option value=""><?php echo __t('plat_reports_filter_all_categories', 'platform'); ?></option>
                <option value="core"><?php echo __t('plat_reports_cat_core', 'platform'); ?></option>
                <option value="billing"><?php echo __t('plat_reports_cat_billing', 'platform'); ?></option>
                <option value="operations"><?php echo __t('plat_reports_cat_operations', 'platform'); ?></option>
                <option value="product"><?php echo __t('plat_reports_cat_product', 'platform'); ?></option>
                <option value="security"><?php echo __t('plat_reports_cat_security', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-reports-clear-btn" id="platReportsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-reports-grid" id="platReportsGrid" aria-live="polite">
            <div class="plat-reports-loading">
                <span class="plat-reports-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>

        <div class="plat-reports-empty" id="platReportsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">find_in_page</span>
            <h3><?php echo __t('plat_reports_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_reports_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<div class="plat-reports-modal" id="platReportsModal" hidden role="dialog" aria-modal="true" aria-labelledby="platReportsModalTitle">
    <div class="plat-reports-modal__backdrop" id="platReportsModalBackdrop"></div>
    <div class="plat-reports-modal__panel">
        <header class="plat-reports-modal__head">
            <div>
                <h3 id="platReportsModalTitle"><?php echo __t('plat_reports_preview_title', 'platform'); ?></h3>
                <p class="plat-reports-modal__meta" id="platReportsModalMeta"></p>
            </div>
            <button type="button" class="plat-reports-modal__close" id="platReportsModalClose" aria-label="<?php echo __t('plat_reports_close', 'platform'); ?>">
                <span class="material-icons-round" aria-hidden="true">close</span>
            </button>
        </header>
        <div class="plat-reports-modal__body">
            <div class="plat-table-wrap">
                <table class="plat-table plat-reports-preview-table">
                    <thead id="platReportsPreviewHead"></thead>
                    <tbody id="platReportsPreviewBody">
                        <tr><td class="plat-reports-muted"><?php echo __t('loading', 'platform'); ?>…</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        <footer class="plat-reports-modal__foot">
            <button type="button" class="plat-reports-modal__export" id="platReportsModalExport">
                <span class="material-icons-round" aria-hidden="true">download</span>
                <?php echo __t('plat_reports_export', 'platform'); ?>
            </button>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

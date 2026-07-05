<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'marketplace';
$pageTitle = __t('plat_nav_marketplace', 'platform');
$extraStyles = ['platform-marketplace.css'];
$extraScripts = ['platform-common.js', 'platform-marketplace.js'];
$pageI18n = plat_i18n([
    'plat_nav_marketplace', 'plat_no_data', 'plat_search', 'plat_clear_filters', 'loading', 'load_error',
    'plat_market_subtitle', 'plat_market_badge', 'plat_market_count', 'plat_market_load_error',
    'plat_market_empty', 'plat_market_empty_hint', 'plat_market_kpi_total', 'plat_market_kpi_published',
    'plat_market_kpi_official', 'plat_market_kpi_installs', 'plat_market_filter_all_categories',
    'plat_market_filter_all_status', 'plat_market_col_vendor', 'plat_market_col_installs',
    'plat_market_pricing_free', 'plat_market_pricing_paid', 'plat_market_pricing_contact',
    'plat_market_official', 'plat_market_view_docs', 'plat_market_view_site', 'plat_market_modules_req',
    'plat_market_cat_payments', 'plat_market_cat_developer', 'plat_market_cat_branding',
    'plat_market_cat_analytics', 'plat_market_cat_shipping', 'plat_market_cat_other',
    'plat_market_status_published', 'plat_market_status_draft', 'plat_market_status_deprecated',
    'plat_modules_view_plans',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-market">
    <div class="plat-market-error" id="platMarketError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platMarketErrorText"></span>
    </div>

    <section class="plat-market-hero" aria-labelledby="platMarketHeroTitle">
        <div class="plat-market-hero__intro">
            <div class="plat-market-badge">
                <span class="material-icons-round" aria-hidden="true">storefront</span>
                <?php echo __t('plat_market_badge', 'platform'); ?>
            </div>
            <h2 class="plat-market-hero__title" id="platMarketHeroTitle"><?php echo __t('plat_nav_marketplace', 'platform'); ?></h2>
            <p class="plat-market-hero__desc"><?php echo __t('plat_market_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-market-count" id="platMarketCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-market-kpi-grid" id="platMarketKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">apps</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_market_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platMarketKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon plat-kpi-card--success is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">check_circle</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_market_kpi_published', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platMarketKpiPublished">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">verified</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_market_kpi_official', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platMarketKpiOfficial">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">download</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_market_kpi_installs', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platMarketKpiInstalls">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-market-panel">
        <div class="plat-market-toolbar">
            <div class="plat-market-search-wrap">
                <span class="material-icons-round plat-market-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platMarketSearch" class="plat-search plat-market-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platMarketCategoryFilter" class="plat-select" aria-label="<?php echo __t('plat_market_filter_all_categories', 'platform'); ?>">
                <option value=""><?php echo __t('plat_market_filter_all_categories', 'platform'); ?></option>
                <option value="payments"><?php echo __t('plat_market_cat_payments', 'platform'); ?></option>
                <option value="developer"><?php echo __t('plat_market_cat_developer', 'platform'); ?></option>
                <option value="branding"><?php echo __t('plat_market_cat_branding', 'platform'); ?></option>
                <option value="analytics"><?php echo __t('plat_market_cat_analytics', 'platform'); ?></option>
                <option value="shipping"><?php echo __t('plat_market_cat_shipping', 'platform'); ?></option>
                <option value="other"><?php echo __t('plat_market_cat_other', 'platform'); ?></option>
            </select>
            <select id="platMarketStatusFilter" class="plat-select">
                <option value=""><?php echo __t('plat_market_status_published', 'platform'); ?></option>
                <option value="all"><?php echo __t('plat_market_filter_all_status', 'platform'); ?></option>
                <option value="draft"><?php echo __t('plat_market_status_draft', 'platform'); ?></option>
                <option value="deprecated"><?php echo __t('plat_market_status_deprecated', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-market-clear-btn" id="platMarketClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-market-grid" id="platMarketGrid" aria-live="polite">
            <div class="plat-market-loading">
                <span class="plat-market-spinner" aria-hidden="true"></span>
                <?php echo __t('loading', 'platform'); ?>…
            </div>
        </div>

        <div class="plat-market-empty" id="platMarketEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">storefront</span>
            <h3><?php echo __t('plat_market_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_market_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

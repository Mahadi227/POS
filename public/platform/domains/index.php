<?php
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'domains';
$pageTitle = __t('plat_nav_domains', 'platform');
$extraStyles = ['platform-domains.css'];
$extraScripts = ['platform-common.js', 'platform-domains.js'];
$pageI18n = plat_i18n([
    'plat_nav_domains', 'plat_col_name', 'plat_no_data', 'plat_search', 'plat_clear_filters',
    'loading', 'load_error', 'action_success', 'action_error',
    'plat_domains_subtitle', 'plat_domains_badge', 'plat_domains_count', 'plat_domains_load_error',
    'plat_domains_empty', 'plat_domains_empty_hint', 'plat_domains_kpi_total', 'plat_domains_kpi_subdomain',
    'plat_domains_kpi_custom', 'plat_domains_kpi_pending', 'plat_domains_col_hostname', 'plat_domains_col_kind',
    'plat_domains_col_verified', 'plat_domains_col_created', 'plat_domains_view_org', 'plat_domains_verify',
    'plat_domains_confirm_verify', 'plat_domains_filter_all_kinds', 'plat_domains_filter_all_verified',
    'plat_domains_filter_verified', 'plat_domains_filter_pending', 'plat_domains_kind_subdomain',
    'plat_domains_kind_custom', 'plat_domains_status_verified', 'plat_domains_status_pending',
]);
require __DIR__ . '/../includes/layout-start.php';
?>

<div class="plat-domains">
    <div class="plat-domains-error" id="platDomainsError" hidden role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <span id="platDomainsErrorText"></span>
    </div>
    <div class="plat-domains-alert" id="platDomainsAlert" hidden role="status"></div>

    <section class="plat-domains-hero" aria-labelledby="platDomainsHeroTitle">
        <div class="plat-domains-hero__intro">
            <div class="plat-domains-badge">
                <span class="material-icons-round" aria-hidden="true">language</span>
                <?php echo __t('plat_domains_badge', 'platform'); ?>
            </div>
            <h2 class="plat-domains-hero__title" id="platDomainsHeroTitle"><?php echo __t('plat_nav_domains', 'platform'); ?></h2>
            <p class="plat-domains-hero__desc"><?php echo __t('plat_domains_subtitle', 'platform'); ?></p>
        </div>
        <p class="plat-domains-count" id="platDomainsCount" aria-live="polite"></p>
    </section>

    <section class="plat-kpi-grid plat-domains-kpi-grid" id="platDomainsKpiGrid" aria-live="polite">
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">language</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_domains_kpi_total', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platDomKpiTotal">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">link</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_domains_kpi_subdomain', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platDomKpiSub">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">public</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_domains_kpi_custom', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platDomKpiCustom">—</strong>
        </article>
        <article class="plat-kpi-card plat-kpi-card--icon is-loading">
            <span class="plat-kpi-card__icon" aria-hidden="true"><span class="material-icons-round">hourglass_top</span></span>
            <span class="plat-kpi-card__label"><?php echo __t('plat_domains_kpi_pending', 'platform'); ?></span>
            <strong class="plat-kpi-card__value" id="platDomKpiPending">—</strong>
        </article>
    </section>

    <section class="plat-panel plat-domains-panel">
        <div class="plat-domains-toolbar">
            <div class="plat-domains-search-wrap">
                <span class="material-icons-round plat-domains-search-icon" aria-hidden="true">search</span>
                <input type="search" id="platDomainsSearch" class="plat-search plat-domains-search"
                       placeholder="<?php echo __t('plat_search', 'platform'); ?>" autocomplete="off">
            </div>
            <select id="platDomainsKindFilter" class="plat-select" aria-label="<?php echo __t('plat_domains_col_kind', 'platform'); ?>">
                <option value=""><?php echo __t('plat_domains_filter_all_kinds', 'platform'); ?></option>
                <option value="subdomain"><?php echo __t('plat_domains_kind_subdomain', 'platform'); ?></option>
                <option value="custom"><?php echo __t('plat_domains_kind_custom', 'platform'); ?></option>
            </select>
            <select id="platDomainsVerifiedFilter" class="plat-select" aria-label="<?php echo __t('plat_domains_col_verified', 'platform'); ?>">
                <option value=""><?php echo __t('plat_domains_filter_all_verified', 'platform'); ?></option>
                <option value="yes"><?php echo __t('plat_domains_filter_verified', 'platform'); ?></option>
                <option value="no"><?php echo __t('plat_domains_filter_pending', 'platform'); ?></option>
            </select>
            <button type="button" class="plat-domains-clear-btn" id="platDomainsClearFilters" hidden>
                <span class="material-icons-round" aria-hidden="true">filter_alt_off</span>
                <?php echo __t('plat_clear_filters', 'platform'); ?>
            </button>
        </div>

        <div class="plat-table-wrap plat-domains-table-wrap">
            <table class="plat-table plat-domains-table" id="platDomainsTable">
                <thead>
                    <tr>
                        <th><?php echo __t('plat_domains_col_hostname', 'platform'); ?></th>
                        <th><?php echo __t('plat_col_name', 'platform'); ?></th>
                        <th><?php echo __t('plat_domains_col_kind', 'platform'); ?></th>
                        <th><?php echo __t('plat_domains_col_verified', 'platform'); ?></th>
                        <th><?php echo __t('plat_domains_col_created', 'platform'); ?></th>
                        <th class="plat-col-action"></th>
                    </tr>
                </thead>
                <tbody id="platDomainsBody">
                    <tr class="plat-domains-loading-row">
                        <td colspan="6">
                            <span class="plat-domains-loading">
                                <span class="plat-domains-spinner" aria-hidden="true"></span>
                                <?php echo __t('loading', 'platform'); ?>…
                            </span>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div class="plat-domains-empty" id="platDomainsEmpty" hidden>
            <span class="material-icons-round" aria-hidden="true">language_off</span>
            <h3><?php echo __t('plat_domains_empty', 'platform'); ?></h3>
            <p><?php echo __t('plat_domains_empty_hint', 'platform'); ?></p>
        </div>
    </section>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

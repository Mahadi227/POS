<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('stores');

$useWmsModules = true;
$activeWhPage = 'stores_mgmt';
$pageTitle = __t('wh_nav_stores', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-stores.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_stn_subtitle', 'wh_stn_stat_branches', 'wh_stn_stat_active', 'wh_stn_stat_warehouses',
        'wh_stn_stat_units', 'wh_stn_stat_currencies', 'wh_stn_stat_countries', 'wh_stn_search',
        'wh_stn_filter_all', 'wh_stn_filter_active', 'wh_stn_filter_inactive', 'wh_stn_view_table',
        'wh_stn_view_cards', 'wh_stn_col_branch', 'wh_stn_col_location', 'wh_stn_col_currency',
        'wh_stn_col_warehouses', 'wh_stn_col_wms_units', 'wh_stn_col_wms_value', 'wh_stn_col_staff',
        'wh_stn_col_products', 'wh_stn_col_regions', 'wh_stn_empty', 'wh_stn_currency_title',
        'wh_stn_currency_multi', 'wh_stn_details', 'wh_stn_linked_wh', 'wh_stn_no_warehouses',
        'wh_stn_manage_admin', 'wh_stn_open_warehouses', 'wh_stn_active', 'wh_stn_inactive',
        'wh_stn_wh_active', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'col_status', 'dash_all_stores',
    ]),
    wms_i18n([
        'wms_wh_code', 'wms_wh_name', 'wms_wh_type', 'wms_col_units', 'wms_stat_inv_value',
        'wms_status_active', 'wms_status_inactive', 'wms_wh_type_central', 'wms_wh_type_regional',
        'wms_wh_type_store', 'wms_wh_type_distribution', 'wms_wh_type_cold_storage', 'wms_wh_type_temporary',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-stn-hero" aria-labelledby="whStnHeroTitle">
    <div class="wh-stn-hero__intro">
        <h2 class="wh-stn-hero__title" id="whStnHeroTitle"><?php echo __t('wh_stn_subtitle', 'warehouse'); ?></h2>
        <p class="wh-stn-hero__meta" id="whStnHeroMeta" aria-live="polite">—</p>
        <div class="wh-stn-hero__links">
            <a class="wh-stn-hero__link" href="warehouses.php"><?php echo __t('wh_stn_open_warehouses', 'warehouse'); ?></a>
            <a class="wh-stn-hero__link" href="../../admin/stores.php" target="_blank" rel="noopener"><?php echo __t('wh_stn_manage_admin', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-stn-hero__stats" role="group">
        <article class="wh-stn-stat wh-stn-stat--primary">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_branches', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading" id="whStnStatBranches">—</strong>
        </article>
        <article class="wh-stn-stat wh-stn-stat--success">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_active', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading" id="whStnStatActive">—</strong>
        </article>
        <article class="wh-stn-stat">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_warehouses', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading" id="whStnStatWarehouses">—</strong>
        </article>
        <article class="wh-stn-stat">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_units', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading" id="whStnStatUnits">—</strong>
        </article>
        <article class="wh-stn-stat">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_currencies', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading" id="whStnStatCurrencies">—</strong>
        </article>
        <article class="wh-stn-stat wh-stn-stat--muted">
            <span class="wh-stn-stat__label"><?php echo __t('wh_stn_stat_countries', 'warehouse'); ?></span>
            <strong class="wh-stn-stat__value is-loading wh-stn-stat__value--text" id="whStnStatCountries">—</strong>
        </article>
    </div>
</section>

<section class="wh-stn-currency" id="whStnCurrencyPanel" hidden aria-labelledby="whStnCurrencyTitle">
    <div class="wh-stn-currency__head">
        <h3 id="whStnCurrencyTitle"><?php echo __t('wh_stn_currency_title', 'warehouse'); ?></h3>
        <p class="wh-stn-currency__hint" id="whStnCurrencyHint" hidden><?php echo __t('wh_stn_currency_multi', 'warehouse'); ?></p>
    </div>
    <div class="wh-stn-currency__grid" id="whStnCurrencyGrid"></div>
</section>

<div class="wh-stn-toolbar">
    <div class="wh-stn-toolbar__row">
        <div class="wh-stn-toolbar__filters">
            <label class="wh-stn-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whStnSearch" class="wh-stn-search" placeholder="<?php echo htmlspecialchars(__t('wh_stn_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whStnStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_stn_filter_all', 'warehouse'); ?></option>
                <option value="active"><?php echo __t('wh_stn_filter_active', 'warehouse'); ?></option>
                <option value="inactive"><?php echo __t('wh_stn_filter_inactive', 'warehouse'); ?></option>
            </select>
            <div class="wh-stn-view-toggle" role="group" aria-label="View mode">
                <button type="button" class="wh-stn-view-btn is-active" data-view="table" id="whStnViewTable"><?php echo __t('wh_stn_view_table', 'warehouse'); ?></button>
                <button type="button" class="wh-stn-view-btn" data-view="cards" id="whStnViewCards"><?php echo __t('wh_stn_view_cards', 'warehouse'); ?></button>
            </div>
        </div>
        <div class="wh-stn-toolbar__actions">
            <button type="button" class="wh-btn wh-btn--ghost" id="whStnExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whStnRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-stn-panel" aria-live="polite">
    <div class="wh-stn-table-wrap" id="whStnTableWrap"></div>
    <div class="wh-stn-cards" id="whStnCardsWrap" hidden></div>
    <div class="wh-stn-empty" id="whStnEmpty" hidden>
        <span class="material-icons-round">storefront</span>
        <p><?php echo __t('wh_stn_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whStnLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-stn-pagination" id="whStnPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whStnPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-stn-pagination__meta" id="whStnPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whStnNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wms-modal-overlay" id="whStnDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wh-stn-modal" role="dialog" aria-labelledby="whStnDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">storefront</span></div>
                <div>
                    <h3 id="whStnDetailTitle"><?php echo __t('wh_stn_details', 'warehouse'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whStnDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whStnDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whStnDetailBody" class="wms-detail-body wh-stn-detail-body"></div>
        <footer class="wms-grn-modal__footer">
            <div class="wms-grn-modal__actions">
                <a class="wh-btn wh-btn--ghost" id="whStnDetailWhLink" href="warehouses.php"><?php echo __t('wh_stn_open_warehouses', 'warehouse'); ?></a>
                <button type="button" class="wh-btn wh-btn--ghost" id="whStnDetailCloseBtn"><?php echo __t('close', 'warehouse'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

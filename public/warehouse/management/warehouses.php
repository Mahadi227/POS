<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('management');

$useWmsModules = true;
$activeWhPage = 'warehouses';
$pageTitle = __t('wms_warehouses_title', 'wms');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-warehouses.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_wh_subtitle', 'wh_wh_stat_total', 'wh_wh_stat_active', 'wh_wh_stat_inactive', 'wh_wh_stat_units',
        'wh_wh_stat_value', 'wh_wh_stat_capacity', 'wh_wh_stat_countries', 'wh_wh_search', 'wh_wh_filter_all_status',
        'wh_wh_filter_all_types', 'wh_wh_filter_all_stores', 'wh_wh_view_table', 'wh_wh_view_cards', 'wh_wh_col_code',
        'wh_wh_col_name', 'wh_wh_col_type', 'wh_wh_col_branch', 'wh_wh_col_manager', 'wh_wh_col_location',
        'wh_wh_col_units', 'wh_wh_col_skus', 'wh_wh_col_locations', 'wh_wh_col_capacity', 'wh_wh_col_value',
        'wh_wh_empty', 'wh_wh_type_breakdown', 'wh_wh_store_breakdown', 'wh_wh_details', 'wh_wh_new', 'wh_wh_edit',
        'wh_wh_link_inventory', 'wh_wh_link_locations', 'wh_wh_link_stores', 'wh_wh_contact', 'wh_wh_notes',
        'wh_stn_currency_multi', 'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated',
        'export_csv', 'prev_page', 'next_page', 'records', 'close', 'col_status', 'dash_all_stores',
    ]),
    wms_i18n([
        'wms_wh_code', 'wms_wh_name', 'wms_wh_type', 'wms_wh_manager', 'wms_wh_city', 'wms_wh_address',
        'wms_wh_capacity', 'wms_col_store', 'wms_col_units', 'wms_stat_inv_value', 'wms_status_active',
        'wms_status_inactive', 'wms_wh_type_central', 'wms_wh_type_regional', 'wms_wh_type_store',
        'wms_wh_type_distribution', 'wms_wh_type_cold_storage', 'wms_wh_type_temporary',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-wh-hero" aria-labelledby="whWhHeroTitle">
    <div class="wh-wh-hero__intro">
        <h2 class="wh-wh-hero__title" id="whWhHeroTitle"><?php echo __t('wh_wh_subtitle', 'warehouse'); ?></h2>
        <p class="wh-wh-hero__meta" id="whWhHeroMeta" aria-live="polite">—</p>
        <div class="wh-wh-hero__links">
            <a class="wh-wh-hero__link" href="stores.php"><?php echo __t('wh_wh_link_stores', 'warehouse'); ?></a>
            <a class="wh-wh-hero__link" href="locations.php"><?php echo __t('wh_wh_link_locations', 'warehouse'); ?></a>
            <?php if ($canManageWms): ?>
            <a class="wh-wh-hero__link" href="create_warehouse.php"><?php echo __t('wh_wh_new', 'warehouse'); ?></a>
            <?php endif; ?>
        </div>
    </div>
    <div class="wh-wh-hero__stats" role="group">
        <article class="wh-wh-stat wh-wh-stat--primary">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_total', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatTotal">—</strong>
        </article>
        <article class="wh-wh-stat wh-wh-stat--success">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_active', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatActive">—</strong>
        </article>
        <article class="wh-wh-stat wh-wh-stat--warn">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_inactive', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatInactive">—</strong>
        </article>
        <article class="wh-wh-stat">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_units', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatUnits">—</strong>
        </article>
        <article class="wh-wh-stat">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_value', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatValue">—</strong>
        </article>
        <article class="wh-wh-stat">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_capacity', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading" id="whWhStatCapacity">—</strong>
        </article>
        <article class="wh-wh-stat wh-wh-stat--muted">
            <span class="wh-wh-stat__label"><?php echo __t('wh_wh_stat_countries', 'warehouse'); ?></span>
            <strong class="wh-wh-stat__value is-loading wh-wh-stat__value--text" id="whWhStatCountries">—</strong>
        </article>
    </div>
</section>

<div class="wh-wh-breakdown" id="whWhBreakdownRow" hidden>
    <section class="wh-wh-breakdown__panel" id="whWhTypePanel" hidden aria-labelledby="whWhTypeTitle">
        <h3 id="whWhTypeTitle"><?php echo __t('wh_wh_type_breakdown', 'warehouse'); ?></h3>
        <div class="wh-wh-type-chips" id="whWhTypeChips"></div>
    </section>
    <section class="wh-wh-breakdown__panel" id="whWhStorePanel" hidden aria-labelledby="whWhStoreTitle">
        <div class="wh-wh-breakdown__head">
            <h3 id="whWhStoreTitle"><?php echo __t('wh_wh_store_breakdown', 'warehouse'); ?></h3>
            <p class="wh-wh-breakdown__hint" id="whWhStoreHint" hidden><?php echo __t('wh_stn_currency_multi', 'warehouse'); ?></p>
        </div>
        <div class="wh-wh-store-grid" id="whWhStoreGrid"></div>
    </section>
</div>

<div class="wh-wh-toolbar">
    <div class="wh-wh-toolbar__row">
        <div class="wh-wh-toolbar__filters">
            <label class="wh-wh-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whWhSearch" class="wh-wh-search" placeholder="<?php echo htmlspecialchars(__t('wh_wh_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whWhStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_wh_filter_all_status', 'warehouse'); ?></option>
                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                <option value="inactive"><?php echo __t('wms_status_inactive', 'wms'); ?></option>
            </select>
            <select id="whWhType" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_wh_col_type', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_wh_filter_all_types', 'warehouse'); ?></option>
                <option value="central"><?php echo __t('wms_wh_type_central', 'wms'); ?></option>
                <option value="regional"><?php echo __t('wms_wh_type_regional', 'wms'); ?></option>
                <option value="store"><?php echo __t('wms_wh_type_store', 'wms'); ?></option>
                <option value="distribution"><?php echo __t('wms_wh_type_distribution', 'wms'); ?></option>
                <option value="cold_storage"><?php echo __t('wms_wh_type_cold_storage', 'wms'); ?></option>
                <option value="temporary"><?php echo __t('wms_wh_type_temporary', 'wms'); ?></option>
            </select>
            <select id="whWhStore" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_wh_col_branch', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_wh_filter_all_stores', 'warehouse'); ?></option>
            </select>
            <div class="wh-wh-view-toggle" role="group" aria-label="View mode">
                <button type="button" class="wh-wh-view-btn is-active" id="whWhViewTable"><?php echo __t('wh_wh_view_table', 'warehouse'); ?></button>
                <button type="button" class="wh-wh-view-btn" id="whWhViewCards"><?php echo __t('wh_wh_view_cards', 'warehouse'); ?></button>
            </div>
        </div>
        <div class="wh-wh-toolbar__actions">
            <?php if ($canManageWms): ?>
            <a href="create_warehouse.php" class="wh-btn wh-btn--primary">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_wh_new', 'warehouse'); ?></span>
            </a>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whWhExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whWhRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-wh-panel" aria-live="polite">
    <div class="wh-wh-table-wrap" id="whWhTableWrap"></div>
    <div class="wh-wh-cards" id="whWhCardsWrap" hidden></div>
    <div class="wh-wh-empty" id="whWhEmpty" hidden>
        <span class="material-icons-round">warehouse</span>
        <p><?php echo __t('wh_wh_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whWhLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-wh-pagination" id="whWhPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whWhPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-wh-pagination__meta" id="whWhPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whWhNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<div class="wms-modal-overlay" id="whWhDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wh-wh-modal" role="dialog" aria-labelledby="whWhDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">warehouse</span></div>
                <div>
                    <h3 id="whWhDetailTitle"><?php echo __t('wh_wh_details', 'warehouse'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whWhDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whWhDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whWhDetailBody" class="wms-detail-body wh-wh-detail-body"></div>
        <footer class="wms-grn-modal__footer">
            <div class="wms-grn-modal__actions">
                <a class="wh-btn wh-btn--ghost" id="whWhDetailInvLink" href="../inventory/warehouse_inventory.php"><?php echo __t('wh_wh_link_inventory', 'warehouse'); ?></a>
                <a class="wh-btn wh-btn--ghost" id="whWhDetailLocLink" href="locations.php"><?php echo __t('wh_wh_link_locations', 'warehouse'); ?></a>
                <?php if ($canManageWms): ?>
                <a class="wh-btn" id="whWhDetailEditLink" href="../../admin/warehouse/edit_warehouse.php"><?php echo __t('wh_wh_edit', 'warehouse'); ?></a>
                <?php endif; ?>
                <button type="button" class="wh-btn wh-btn--ghost" id="whWhDetailCloseBtn"><?php echo __t('close', 'warehouse'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

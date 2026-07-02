<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('locations');

$useWmsModules = true;
$activeWhPage = 'locations';
$pageTitle = __t('wms_locations_title', 'wms');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-locations.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_loc_subtitle', 'wh_loc_stat_total', 'wh_loc_stat_active', 'wh_loc_stat_full',
        'wh_loc_stat_capacity', 'wh_loc_stat_zones', 'wh_loc_search', 'wh_loc_filter_all_status',
        'wh_loc_filter_all_zones', 'wh_loc_view_table', 'wh_loc_view_cards', 'wh_loc_zone_breakdown',
        'wh_loc_select_prompt', 'wh_loc_empty', 'wh_loc_new', 'wh_loc_link_warehouses',
        'wh_loc_link_inventory', 'wh_select_warehouse', 'wh_migration_hint', 'loading', 'load_error',
        'refresh', 'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close',
        'cancel', 'save', 'error', 'col_status',
    ]),
    wms_i18n([
        'wms_locations_title', 'wms_location_form_subtitle', 'wms_location_section_placement',
        'wms_location_section_details', 'wms_col_zone', 'wms_col_aisle', 'wms_col_rack',
        'wms_col_shelf', 'wms_col_bin', 'wms_col_code', 'wms_location_capacity',
        'wms_location_code_optional', 'wms_location_code_preview', 'wms_new_location',
        'wms_nav_warehouses', 'wms_status_active', 'wms_status_inactive', 'wms_status_full',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-loc-hero" aria-labelledby="whLocHeroTitle">
    <div class="wh-loc-hero__intro">
        <h2 class="wh-loc-hero__title" id="whLocHeroTitle"><?php echo __t('wh_loc_subtitle', 'warehouse'); ?></h2>
        <p class="wh-loc-hero__meta" id="whLocHeroMeta" aria-live="polite">—</p>
        <div class="wh-loc-hero__links">
            <a class="wh-loc-hero__link" href="warehouses.php"><?php echo __t('wh_loc_link_warehouses', 'warehouse'); ?></a>
            <a class="wh-loc-hero__link" href="../inventory/warehouse_inventory.php"><?php echo __t('wh_loc_link_inventory', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-loc-hero__stats" role="group">
        <article class="wh-loc-stat wh-loc-stat--primary">
            <span class="wh-loc-stat__label"><?php echo __t('wh_loc_stat_total', 'warehouse'); ?></span>
            <strong class="wh-loc-stat__value is-loading" id="whLocStatTotal">—</strong>
        </article>
        <article class="wh-loc-stat wh-loc-stat--success">
            <span class="wh-loc-stat__label"><?php echo __t('wh_loc_stat_active', 'warehouse'); ?></span>
            <strong class="wh-loc-stat__value is-loading" id="whLocStatActive">—</strong>
        </article>
        <article class="wh-loc-stat wh-loc-stat--warn">
            <span class="wh-loc-stat__label"><?php echo __t('wh_loc_stat_full', 'warehouse'); ?></span>
            <strong class="wh-loc-stat__value is-loading" id="whLocStatFull">—</strong>
        </article>
        <article class="wh-loc-stat">
            <span class="wh-loc-stat__label"><?php echo __t('wh_loc_stat_capacity', 'warehouse'); ?></span>
            <strong class="wh-loc-stat__value is-loading" id="whLocStatCapacity">—</strong>
        </article>
        <article class="wh-loc-stat wh-loc-stat--muted">
            <span class="wh-loc-stat__label"><?php echo __t('wh_loc_stat_zones', 'warehouse'); ?></span>
            <strong class="wh-loc-stat__value is-loading" id="whLocStatZones">—</strong>
        </article>
    </div>
</section>

<section class="wh-loc-zone-panel" id="whLocZonePanel" hidden aria-labelledby="whLocZoneTitle">
    <div class="wh-loc-zone-panel__head">
        <h3 id="whLocZoneTitle"><?php echo __t('wh_loc_zone_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-loc-zone-chips" id="whLocZoneChips"></div>
</section>

<div class="wh-loc-toolbar">
    <div class="wh-loc-toolbar__row">
        <div class="wh-loc-toolbar__filters">
            <select id="whLocWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" required>
                <option value=""><?php echo __t('wh_select_warehouse', 'warehouse'); ?></option>
            </select>
            <label class="wh-loc-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whLocSearch" class="wh-loc-search" placeholder="<?php echo htmlspecialchars(__t('wh_loc_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whLocStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_loc_filter_all_status', 'warehouse'); ?></option>
                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                <option value="inactive"><?php echo __t('wms_status_inactive', 'wms'); ?></option>
                <option value="full"><?php echo __t('wms_status_full', 'wms'); ?></option>
            </select>
            <select id="whLocZoneFilter" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wms_col_zone', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value="all"><?php echo __t('wh_loc_filter_all_zones', 'warehouse'); ?></option>
            </select>
            <div class="wh-loc-view-toggle" role="group" aria-label="View mode">
                <button type="button" class="wh-loc-view-btn is-active" id="whLocViewTable"><?php echo __t('wh_loc_view_table', 'warehouse'); ?></button>
                <button type="button" class="wh-loc-view-btn" id="whLocViewCards"><?php echo __t('wh_loc_view_cards', 'warehouse'); ?></button>
            </div>
        </div>
        <div class="wh-loc-toolbar__actions">
            <?php if ($canManageWms): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whLocNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_loc_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whLocExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whLocRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-loc-panel" aria-live="polite">
    <div class="wh-loc-table-wrap" id="whLocTableWrap"></div>
    <div class="wh-loc-cards" id="whLocCardsWrap" hidden></div>
    <div class="wh-loc-empty" id="whLocEmpty" hidden>
        <span class="material-icons-round">place</span>
        <p><?php echo __t('wh_loc_select_prompt', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whLocLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-loc-pagination" id="whLocPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLocPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-loc-pagination__meta" id="whLocPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whLocNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="whLocModal" aria-hidden="true">
    <div class="wms-modal wms-modal--location" role="dialog" aria-labelledby="whLocModalTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">place</span></div>
                <div>
                    <h3 id="whLocModalTitle"><?php echo __t('wms_new_location', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_location_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whLocModalClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="whLocForm" class="wms-grn-form wms-grn-form--location">
            <div class="wms-loc-form__body">
                <section class="wms-grn-section wms-loc-section">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">warehouse</span><?php echo __t('wms_location_section_details', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--loc">
                        <label class="wms-grn-field wms-grn-field--full">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whLocFormWarehouse" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_location_code_optional', 'wms'); ?></span>
                            <input type="text" name="location_code" id="whLocCodeInput" placeholder="A-01-R1-S1-B1" autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_location_capacity', 'wms'); ?></span>
                            <input type="number" name="capacity_units" min="0" value="0" placeholder="0">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('col_status', 'warehouse'); ?></span>
                            <select name="status">
                                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                                <option value="inactive"><?php echo __t('wms_status_inactive', 'wms'); ?></option>
                                <option value="full"><?php echo __t('wms_status_full', 'wms'); ?></option>
                            </select>
                        </label>
                        <div class="wms-grn-field wms-grn-field--full wms-loc-code-preview">
                            <span><?php echo __t('wms_location_code_preview', 'wms'); ?></span>
                            <strong id="whLocCodePreview">A</strong>
                        </div>
                    </div>
                </section>

                <section class="wms-grn-section wms-loc-section">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">grid_view</span><?php echo __t('wms_location_section_placement', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--loc-placement">
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_zone', 'wms'); ?></span>
                            <input type="text" name="zone" value="A" required maxlength="50" placeholder="A">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_aisle', 'wms'); ?></span>
                            <input type="text" name="aisle" maxlength="50" placeholder="01">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_rack', 'wms'); ?></span>
                            <input type="text" name="rack" maxlength="50" placeholder="R1">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_shelf', 'wms'); ?></span>
                            <input type="text" name="shelf" maxlength="50" placeholder="S1">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_bin', 'wms'); ?></span>
                            <input type="text" name="bin" maxlength="50" placeholder="B1">
                        </label>
                    </div>
                </section>
            </div>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-modal__actions">
                    <button type="button" class="wh-btn wh-btn--ghost" id="whLocFormCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                    <button type="submit" class="wh-btn wh-btn--primary">
                        <span class="material-icons-round">save</span>
                        <?php echo __t('save', 'warehouse'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

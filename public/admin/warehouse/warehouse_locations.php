<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'warehouse_locations';
$pageTitle = __t('wms_locations_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-locations.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_select_warehouse',
    'wms_new_location', 'wms_locations_subtitle', 'wms_location_form_subtitle', 'wms_location_section_placement',
    'wms_location_section_details', 'wms_col_zone', 'wms_col_aisle', 'wms_col_rack', 'wms_col_shelf', 'wms_col_bin',
    'wms_col_code', 'wms_location_capacity', 'wms_location_code_optional', 'wms_location_code_preview',
    'wms_stat_loc_total', 'wms_stat_loc_active', 'wms_stat_loc_capacity', 'wms_search_location', 'wms_location_saved',
    'wms_status_active', 'wms_status_inactive', 'wms_status_full', 'col_status', 'wms_nav_warehouses', 'close',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_locations_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <select id="wmsLocWarehouse" class="form-input" style="max-width:240px;" required>
            <option value=""><?php echo __t('wms_select_warehouse', 'wms'); ?></option>
        </select>
        <input id="wmsLocSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_location', 'wms')); ?>" style="max-width:300px;">
        <button type="button" class="cr-btn" id="wmsLocRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsLocNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_location', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">place</span></div><div class="card-info"><h3><?php echo __t('wms_stat_loc_total', 'wms'); ?></h3><h2 id="wmsLocTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_loc_active', 'wms'); ?></h3><h2 id="wmsLocActive">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">inventory_2</span></div><div class="card-info"><h3><?php echo __t('wms_stat_loc_capacity', 'wms'); ?></h3><h2 id="wmsLocCapacity">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">place</span><?php echo __t('wms_locations_title', 'wms'); ?></h3>
    <div id="wmsLocRoot"><div class="cr-empty"><?php echo __t('wms_select_warehouse', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsLocCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--location" role="dialog" aria-labelledby="wmsLocCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">place</span></div>
                <div>
                    <h3 id="wmsLocCreateTitle"><?php echo __t('wms_new_location', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_location_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsLocCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsLocCreateForm" class="wms-grn-form wms-grn-form--location">
            <div class="wms-loc-form__body">
                <section class="wms-grn-section wms-loc-section">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">warehouse</span><?php echo __t('wms_location_section_details', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--loc">
                        <label class="wms-grn-field wms-grn-field--full">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="wmsLocFormWarehouse" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_location_code_optional', 'wms'); ?></span>
                            <input type="text" name="location_code" id="wmsLocCodeInput" placeholder="A-01-R1-S1-B1" autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_location_capacity', 'wms'); ?></span>
                            <input type="number" name="capacity_units" min="0" value="0" placeholder="0">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('col_status', 'wms'); ?></span>
                            <select name="status">
                                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                                <option value="inactive"><?php echo __t('wms_status_inactive', 'wms'); ?></option>
                                <option value="full"><?php echo __t('wms_status_full', 'wms'); ?></option>
                            </select>
                        </label>
                        <div class="wms-grn-field wms-grn-field--full wms-loc-code-preview">
                            <span><?php echo __t('wms_location_code_preview', 'wms'); ?></span>
                            <strong id="wmsLocCodePreview">A</strong>
                        </div>
                    </div>
                </section>

                <section class="wms-grn-section wms-loc-section">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">grid_view</span><?php echo __t('wms_location_section_placement', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--loc-placement">
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_zone', 'wms'); ?></span>
                            <input type="text" name="zone" id="wmsLocZone" value="A" required maxlength="50" placeholder="A">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_aisle', 'wms'); ?></span>
                            <input type="text" name="aisle" id="wmsLocAisle" maxlength="50" placeholder="01">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_rack', 'wms'); ?></span>
                            <input type="text" name="rack" id="wmsLocRack" maxlength="50" placeholder="R1">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_shelf', 'wms'); ?></span>
                            <input type="text" name="shelf" id="wmsLocShelf" maxlength="50" placeholder="S1">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_bin', 'wms'); ?></span>
                            <input type="text" name="bin" id="wmsLocBin" maxlength="50" placeholder="B1">
                        </label>
                    </div>
                </section>
            </div>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-summary">
                    <span id="wmsLocFormHint"><?php echo __t('wms_location_form_subtitle', 'wms'); ?></span>
                </div>
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsLocCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

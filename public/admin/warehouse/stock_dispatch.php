<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'stock_dispatch';
$pageTitle = __t('wms_dispatch_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-dispatch.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_new_dispatch', 'wms_dispatch_subtitle', 'wms_stat_dsp_total', 'wms_stat_dsp_outgoing',
    'wms_stat_dsp_delivered', 'wms_stat_dsp_draft', 'wms_filter_all_status', 'wms_search_dispatch',
    'wms_col_dispatch', 'wms_col_driver', 'wms_col_vehicle', 'wms_col_destination', 'wms_col_items',
    'wms_col_value', 'wms_col_delivery_date', 'wms_dispatch_details', 'wms_dispatch_btn', 'wms_view_details',
    'wms_dispatch_form_subtitle', 'wms_dispatch_section_info', 'wms_dispatch_section_lines',
    'wms_dispatch_lines_hint', 'wms_add_line', 'wms_select_product', 'wms_qty_short', 'wms_unit_cost',
    'wms_line_subtotal', 'wms_grn_lines_count', 'wms_grn_estimated_total', 'wms_product_filter',
    'wms_remove_line', 'wms_dest_store', 'wms_dest_warehouse', 'wms_select_store', 'wms_select_dest_type',
    'wms_select_warehouse', 'wms_nav_warehouses', 'wms_col_store', 'wms_confirm_dispatch', 'wms_receipt_notes',
    'wms_driver_placeholder', 'wms_vehicle_placeholder', 'close', 'col_status', 'col_date', 'wms_col_product',
    'wms_status_draft', 'wms_status_picking', 'wms_status_packed', 'wms_status_dispatched',
    'wms_status_in_transit', 'wms_status_delivered', 'wms_status_cancelled',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_dispatch_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsDspSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_dispatch', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsDspWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsDspStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
            <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
            <option value="packed"><?php echo __t('wms_status_packed', 'wms'); ?></option>
            <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
            <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
            <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
            <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsDspRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsDspNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_dispatch', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">local_shipping</span></div><div class="card-info"><h3><?php echo __t('wms_stat_dsp_total', 'wms'); ?></h3><h2 id="wmsDspTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_dsp_draft', 'wms'); ?></h3><h2 id="wmsDspDraft">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">airport_shuttle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_dsp_outgoing', 'wms'); ?></h3><h2 id="wmsDspOutgoing">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_dsp_delivered', 'wms'); ?></h3><h2 id="wmsDspDelivered">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">local_shipping</span><?php echo __t('wms_dispatch_title', 'wms'); ?></h3>
    <div id="wmsDspRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsDspCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--grn-create" role="dialog" aria-labelledby="wmsDspCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">local_shipping</span></div>
                <div>
                    <h3 id="wmsDspCreateTitle"><?php echo __t('wms_new_dispatch', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_dispatch_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsDspCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsDspCreateForm" class="wms-grn-form wms-grn-form--create">
            <div class="wms-grn-form__body">
                <section class="wms-grn-section wms-grn-section--info">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_dispatch_section_info', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--dsp-info">
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?> (<?php echo strtolower(__t('wms_dispatch_btn', 'wms')); ?>)</span>
                            <select name="from_warehouse_id" id="wmsDspFormWarehouse" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_select_dest_type', 'wms'); ?></span>
                            <select name="dest_type" id="wmsDspDestType" required>
                                <option value="store"><?php echo __t('wms_dest_store', 'wms'); ?></option>
                                <option value="warehouse"><?php echo __t('wms_dest_warehouse', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wms-grn-field wms-grn-field--full" id="wmsDspStoreField">
                            <span><?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="to_store_id" id="wmsDspFormStore"></select>
                        </label>
                        <label class="wms-grn-field wms-grn-field--full" id="wmsDspWhDestField" hidden>
                            <span><?php echo __t('wms_dest_warehouse', 'wms'); ?></span>
                            <select name="to_warehouse_id" id="wmsDspFormWhDest"></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_driver', 'wms'); ?></span>
                            <input type="text" name="driver_name" placeholder="<?php echo htmlspecialchars(__t('wms_driver_placeholder', 'wms')); ?>">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_vehicle', 'wms'); ?></span>
                            <input type="text" name="vehicle_number" placeholder="<?php echo htmlspecialchars(__t('wms_vehicle_placeholder', 'wms')); ?>">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_delivery_date', 'wms'); ?></span>
                            <input type="date" name="delivery_date">
                        </label>
                        <label class="wms-grn-field wms-grn-field--full">
                            <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                            <textarea name="notes" rows="2" placeholder="—"></textarea>
                        </label>
                    </div>
                </section>

                <section class="wms-grn-section wms-grn-section--lines">
                    <div class="wms-grn-section__top">
                        <div>
                            <h4 class="wms-grn-section__title"><span class="material-icons-round">list_alt</span><?php echo __t('wms_dispatch_section_lines', 'wms'); ?></h4>
                            <p class="wms-grn-lines-hint"><?php echo __t('wms_dispatch_lines_hint', 'wms'); ?></p>
                        </div>
                        <button type="button" class="cr-btn" id="wmsDspAddLine">
                            <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                        </button>
                    </div>
                    <div class="wms-grn-lines-toolbar">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="wmsDspProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms')); ?>" autocomplete="off">
                    </div>
                    <div class="wms-grn-lines-panel">
                        <div class="wms-grn-lines__header wms-grn-lines__header--dispatch" aria-hidden="true">
                            <span>#</span>
                            <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                            <span><?php echo __t('wms_qty_short', 'wms'); ?></span>
                            <span><?php echo __t('wms_unit_cost', 'wms'); ?></span>
                            <span><?php echo __t('wms_line_subtotal', 'wms'); ?></span>
                            <span></span>
                        </div>
                        <div id="wmsDspLineItems" class="wms-grn-lines__body"></div>
                    </div>
                </section>
            </div>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-summary">
                    <span id="wmsDspLineCount">0 <?php echo __t('wms_col_items', 'wms'); ?></span>
                    <div class="wms-grn-summary__total">
                        <span><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                        <strong id="wmsDspEstTotal">0</strong>
                    </div>
                </div>
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsDspCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsDspDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsDspDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsDspDetailTitle"><?php echo __t('wms_dispatch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsDspDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsDspDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsDspDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

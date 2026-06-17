<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'stock_transfers';
$pageTitle = __t('wms_transfers_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-transfers.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_new_transfer', 'wms_transfers_subtitle', 'wms_stat_trf_total', 'wms_stat_trf_requested',
    'wms_stat_trf_progress', 'wms_stat_trf_completed', 'wms_filter_all_status', 'wms_search_transfer',
    'wms_col_transfer', 'wms_col_type', 'wms_col_from', 'wms_col_to', 'wms_col_items', 'wms_col_value',
    'wms_col_reason', 'wms_transfer_details', 'wms_view_details', 'wms_approve', 'wms_complete', 'wms_reject',
    'wms_transfer_form_subtitle', 'wms_transfer_section_info', 'wms_transfer_section_lines', 'wms_transfer_lines_hint',
    'wms_add_line', 'wms_select_product', 'wms_qty_short', 'wms_unit_cost', 'wms_line_subtotal',
    'wms_grn_lines_count', 'wms_grn_estimated_total', 'wms_product_filter', 'wms_remove_line',
    'wms_select_store', 'wms_select_warehouse', 'wms_nav_warehouses', 'wms_col_store', 'wms_col_product',
    'wms_type_wh_wh', 'wms_type_wh_store', 'wms_type_store_wh', 'wms_type_branch', 'wms_reason_placeholder',
    'close', 'col_status', 'col_date', 'wms_status_requested', 'wms_status_approved', 'wms_status_picking',
    'wms_status_in_transit', 'wms_status_received', 'wms_status_completed', 'wms_status_rejected',
    'wms_status_cancelled', 'wms_confirm_approve_trf', 'wms_confirm_complete_trf', 'wms_confirm_reject_trf',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_transfers_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsTrfSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_transfer', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsTrfWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsTrfStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="requested"><?php echo __t('wms_status_requested', 'wms'); ?></option>
            <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
            <option value="picking"><?php echo __t('wms_status_picking', 'wms'); ?></option>
            <option value="in_transit"><?php echo __t('wms_status_in_transit', 'wms'); ?></option>
            <option value="received"><?php echo __t('wms_status_received', 'wms'); ?></option>
            <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
            <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsTrfRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsTrfNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_transfer', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">swap_horiz</span></div><div class="card-info"><h3><?php echo __t('wms_stat_trf_total', 'wms'); ?></h3><h2 id="wmsTrfTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_trf_requested', 'wms'); ?></h3><h2 id="wmsTrfRequested">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">local_shipping</span></div><div class="card-info"><h3><?php echo __t('wms_stat_trf_progress', 'wms'); ?></h3><h2 id="wmsTrfProgress">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_trf_completed', 'wms'); ?></h3><h2 id="wmsTrfCompleted">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">swap_horiz</span><?php echo __t('wms_transfers_title', 'wms'); ?></h3>
    <div id="wmsTrfRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsTrfCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--grn-create" role="dialog" aria-labelledby="wmsTrfCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">swap_horiz</span></div>
                <div>
                    <h3 id="wmsTrfCreateTitle"><?php echo __t('wms_new_transfer', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_transfer_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsTrfCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsTrfCreateForm" class="wms-grn-form wms-grn-form--create">
            <div class="wms-grn-form__body">
                <section class="wms-grn-section wms-grn-section--info">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_transfer_section_info', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--trf-info">
                        <label class="wms-grn-field wms-grn-field--full">
                            <span><?php echo __t('wms_col_type', 'wms'); ?></span>
                            <select name="transfer_type" id="wmsTrfType" required>
                                <option value="warehouse_to_warehouse"><?php echo __t('wms_type_wh_wh', 'wms'); ?></option>
                                <option value="warehouse_to_store"><?php echo __t('wms_type_wh_store', 'wms'); ?></option>
                                <option value="store_to_warehouse"><?php echo __t('wms_type_store_wh', 'wms'); ?></option>
                                <option value="branch_to_branch"><?php echo __t('wms_type_branch', 'wms'); ?></option>
                            </select>
                        </label>
                        <label class="wms-grn-field" id="wmsTrfFromWhField">
                            <span><?php echo __t('wms_col_from', 'wms'); ?> — <?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="from_warehouse_id" id="wmsTrfFromWh"></select>
                        </label>
                        <label class="wms-grn-field" id="wmsTrfFromStoreField" hidden>
                            <span><?php echo __t('wms_col_from', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="from_store_id" id="wmsTrfFromStore"></select>
                        </label>
                        <label class="wms-grn-field" id="wmsTrfToWhField">
                            <span><?php echo __t('wms_col_to', 'wms'); ?> — <?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="to_warehouse_id" id="wmsTrfToWh"></select>
                        </label>
                        <label class="wms-grn-field" id="wmsTrfToStoreField" hidden>
                            <span><?php echo __t('wms_col_to', 'wms'); ?> — <?php echo __t('wms_col_store', 'wms'); ?></span>
                            <select name="to_store_id" id="wmsTrfToStore"></select>
                        </label>
                        <label class="wms-grn-field wms-grn-field--full">
                            <span><?php echo __t('wms_col_reason', 'wms'); ?></span>
                            <textarea name="reason" rows="2" placeholder="<?php echo htmlspecialchars(__t('wms_reason_placeholder', 'wms')); ?>"></textarea>
                        </label>
                    </div>
                </section>

                <section class="wms-grn-section wms-grn-section--lines">
                    <div class="wms-grn-section__top">
                        <div>
                            <h4 class="wms-grn-section__title"><span class="material-icons-round">list_alt</span><?php echo __t('wms_transfer_section_lines', 'wms'); ?></h4>
                            <p class="wms-grn-lines-hint"><?php echo __t('wms_transfer_lines_hint', 'wms'); ?></p>
                        </div>
                        <button type="button" class="cr-btn" id="wmsTrfAddLine">
                            <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                        </button>
                    </div>
                    <div class="wms-grn-lines-toolbar">
                        <span class="material-icons-round">search</span>
                        <input type="search" id="wmsTrfProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms')); ?>" autocomplete="off">
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
                        <div id="wmsTrfLineItems" class="wms-grn-lines__body"></div>
                    </div>
                </section>
            </div>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-summary">
                    <span id="wmsTrfLineCount">0 <?php echo __t('wms_col_items', 'wms'); ?></span>
                    <div class="wms-grn-summary__total">
                        <span><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                        <strong id="wmsTrfEstTotal">0</strong>
                    </div>
                </div>
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsTrfCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsTrfDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsTrfDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsTrfDetailTitle"><?php echo __t('wms_transfer_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsTrfDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsTrfDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsTrfDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

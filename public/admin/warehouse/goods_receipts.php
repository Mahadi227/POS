<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'goods_receipts';
$pageTitle = __t('wms_receipts_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-goods-receipts.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_new_receipt', 'wms_receipts_subtitle', 'wms_stat_grn_total', 'wms_stat_grn_pending', 'wms_stat_grn_value',
    'wms_filter_all_status', 'wms_status_pending', 'wms_status_inspecting', 'wms_status_accepted',
    'wms_status_completed', 'wms_status_rejected', 'wms_col_grn', 'wms_col_supplier', 'wms_col_value',
    'wms_col_items', 'wms_col_received_by', 'wms_receipt_notes', 'wms_receipt_details', 'wms_add_line',
    'wms_select_product', 'wms_qty_received', 'wms_unit_cost', 'wms_batch_optional', 'wms_expiry_optional',
    'wms_search_grn', 'wms_confirm_complete', 'wms_receipt_saved', 'wms_complete_success', 'wms_view_details',
    'wms_complete', 'col_status', 'col_date', 'wms_nav_warehouses', 'wms_col_product', 'close',
    'wms_grn_form_subtitle', 'wms_grn_section_info', 'wms_grn_section_lines', 'wms_grn_estimated_total',
    'wms_grn_lines_count', 'wms_supplier_placeholder',
    'wms_grn_lines_hint', 'wms_line_subtotal', 'wms_line_tracking', 'wms_product_filter', 'wms_qty_short', 'wms_remove_line',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_receipts_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsGrnSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_grn', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsGrnWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsGrnStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
            <option value="inspecting"><?php echo __t('wms_status_inspecting', 'wms'); ?></option>
            <option value="accepted"><?php echo __t('wms_status_accepted', 'wms'); ?></option>
            <option value="completed"><?php echo __t('wms_status_completed', 'wms'); ?></option>
            <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsGrnRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsGrnNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_receipt', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">move_to_inbox</span></div><div class="card-info"><h3><?php echo __t('wms_stat_grn_total', 'wms'); ?></h3><h2 id="wmsGrnTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_grn_pending', 'wms'); ?></h3><h2 id="wmsGrnPending">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">payments</span></div><div class="card-info"><h3><?php echo __t('wms_stat_grn_value', 'wms'); ?></h3><h2 id="wmsGrnValue">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">move_to_inbox</span><?php echo __t('wms_receipts_title', 'wms'); ?></h3>
    <div id="wmsGrnRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsGrnCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsGrnCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory</span></div>
                <div>
                    <h3 id="wmsGrnCreateTitle"><?php echo __t('wms_new_receipt', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_grn_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsGrnCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsGrnCreateForm" class="wms-grn-form">
            <section class="wms-grn-section">
                <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_grn_section_info', 'wms'); ?></h4>
                <div class="wms-grn-fields">
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                        <select name="warehouse_id" id="wmsGrnFormWarehouse" required></select>
                    </label>
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_col_supplier', 'wms'); ?></span>
                        <input type="text" name="supplier_name" placeholder="<?php echo htmlspecialchars(__t('wms_supplier_placeholder', 'wms')); ?>">
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
                        <h4 class="wms-grn-section__title"><span class="material-icons-round">list_alt</span><?php echo __t('wms_grn_section_lines', 'wms'); ?></h4>
                        <p class="wms-grn-lines-hint"><?php echo __t('wms_grn_lines_hint', 'wms'); ?></p>
                    </div>
                    <button type="button" class="cr-btn" id="wmsGrnAddLine">
                        <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                    </button>
                </div>
                <div class="wms-grn-lines-toolbar">
                    <span class="material-icons-round">search</span>
                    <input type="search" id="wmsGrnProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms')); ?>" autocomplete="off">
                </div>
                <div class="wms-grn-lines-panel">
                    <div class="wms-grn-lines__header" aria-hidden="true">
                        <span>#</span>
                        <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                        <span><?php echo __t('wms_qty_short', 'wms'); ?></span>
                        <span><?php echo __t('wms_unit_cost', 'wms'); ?></span>
                        <span><?php echo __t('wms_line_subtotal', 'wms'); ?></span>
                        <span class="wms-grn-lines__header-icon" title="<?php echo htmlspecialchars(__t('wms_line_tracking', 'wms')); ?>"><span class="material-icons-round">inventory_2</span></span>
                        <span></span>
                    </div>
                    <div id="wmsGrnLineItems" class="wms-grn-lines__body"></div>
                    <p class="wms-grn-lines__empty" id="wmsGrnLinesEmpty" hidden><?php echo __t('wms_add_line', 'wms'); ?></p>
                </div>
            </section>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-summary">
                    <span id="wmsGrnLineCount">0 <?php echo __t('wms_col_items', 'wms'); ?></span>
                    <div class="wms-grn-summary__total">
                        <span><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                        <strong id="wmsGrnEstTotal">0</strong>
                    </div>
                </div>
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsGrnCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsGrnDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsGrnDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsGrnDetailTitle"><?php echo __t('wms_receipt_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_receipts_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsGrnDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsGrnDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'stock_requests';
$pageTitle = __t('wms_requests_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-requests.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses', 'wms_all_stores',
    'wms_new_request', 'wms_requests_subtitle', 'wms_stat_req_total', 'wms_stat_req_pending', 'wms_stat_req_approved',
    'wms_stat_req_urgent', 'wms_filter_all_status', 'wms_search_request', 'wms_col_request', 'wms_col_store',
    'wms_col_priority', 'wms_col_items', 'wms_col_qty', 'wms_request_details', 'wms_view_details', 'wms_approve',
    'wms_approve_warehouse', 'wms_reject', 'wms_request_form_subtitle', 'wms_request_section_info',
    'wms_request_section_lines', 'wms_request_lines_hint', 'wms_add_line', 'wms_select_product', 'wms_qty_short',
    'wms_product_filter', 'wms_remove_line', 'wms_grn_lines_count', 'wms_select_store', 'wms_select_warehouse',
    'wms_nav_warehouses', 'wms_col_product', 'wms_col_requested_by', 'wms_receipt_notes', 'close', 'col_status',
    'col_date', 'wms_status_pending', 'wms_status_manager_approved', 'wms_status_warehouse_approved',
    'wms_status_dispatched', 'wms_status_delivered', 'wms_status_rejected', 'wms_status_cancelled',
    'wms_priority_low', 'wms_priority_normal', 'wms_priority_high', 'wms_priority_urgent',
    'wms_confirm_approve_mgr', 'wms_confirm_approve_wh', 'wms_confirm_reject',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_requests_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsReqSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_request', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsReqStore" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_stores', 'wms'); ?></option>
        </select>
        <select id="wmsReqWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsReqStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="pending"><?php echo __t('wms_status_pending', 'wms'); ?></option>
            <option value="manager_approved"><?php echo __t('wms_status_manager_approved', 'wms'); ?></option>
            <option value="warehouse_approved"><?php echo __t('wms_status_warehouse_approved', 'wms'); ?></option>
            <option value="dispatched"><?php echo __t('wms_status_dispatched', 'wms'); ?></option>
            <option value="delivered"><?php echo __t('wms_status_delivered', 'wms'); ?></option>
            <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
            <option value="cancelled"><?php echo __t('wms_status_cancelled', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsReqRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsReqNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_request', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">assignment</span></div><div class="card-info"><h3><?php echo __t('wms_stat_req_total', 'wms'); ?></h3><h2 id="wmsReqTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_req_pending', 'wms'); ?></h3><h2 id="wmsReqPending">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">thumb_up</span></div><div class="card-info"><h3><?php echo __t('wms_stat_req_approved', 'wms'); ?></h3><h2 id="wmsReqApproved">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">priority_high</span></div><div class="card-info"><h3><?php echo __t('wms_stat_req_urgent', 'wms'); ?></h3><h2 id="wmsReqUrgent">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">assignment</span><?php echo __t('wms_requests_title', 'wms'); ?></h3>
    <div id="wmsReqRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsReqCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsReqCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">assignment</span></div>
                <div>
                    <h3 id="wmsReqCreateTitle"><?php echo __t('wms_new_request', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_request_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsReqCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsReqCreateForm" class="wms-grn-form">
            <section class="wms-grn-section">
                <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_request_section_info', 'wms'); ?></h4>
                <div class="wms-grn-fields">
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_col_store', 'wms'); ?></span>
                        <select name="store_id" id="wmsReqFormStore" required></select>
                    </label>
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                        <select name="warehouse_id" id="wmsReqFormWarehouse" required></select>
                    </label>
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_col_priority', 'wms'); ?></span>
                        <select name="priority" required>
                            <option value="low"><?php echo __t('wms_priority_low', 'wms'); ?></option>
                            <option value="normal" selected><?php echo __t('wms_priority_normal', 'wms'); ?></option>
                            <option value="high"><?php echo __t('wms_priority_high', 'wms'); ?></option>
                            <option value="urgent"><?php echo __t('wms_priority_urgent', 'wms'); ?></option>
                        </select>
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
                        <h4 class="wms-grn-section__title"><span class="material-icons-round">list_alt</span><?php echo __t('wms_request_section_lines', 'wms'); ?></h4>
                        <p class="wms-grn-lines-hint"><?php echo __t('wms_request_lines_hint', 'wms'); ?></p>
                    </div>
                    <button type="button" class="cr-btn" id="wmsReqAddLine">
                        <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                    </button>
                </div>
                <div class="wms-grn-lines-toolbar">
                    <span class="material-icons-round">search</span>
                    <input type="search" id="wmsReqProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms')); ?>" autocomplete="off">
                </div>
                <div class="wms-grn-lines-panel">
                    <div class="wms-grn-lines__header wms-grn-lines__header--request" aria-hidden="true">
                        <span>#</span>
                        <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                        <span><?php echo __t('wms_qty_short', 'wms'); ?></span>
                        <span></span>
                    </div>
                    <div id="wmsReqLineItems" class="wms-grn-lines__body"></div>
                </div>
            </section>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-summary">
                    <span id="wmsReqLineCount">0 <?php echo __t('wms_col_items', 'wms'); ?></span>
                </div>
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsReqCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsReqDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsReqDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsReqDetailTitle"><?php echo __t('wms_request_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsReqDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsReqDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsReqDetailBody" class="wms-detail-body"></div>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

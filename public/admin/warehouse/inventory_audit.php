<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'inventory_audit';
$pageTitle = __t('wms_audit_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-audits.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_audit_subtitle', 'wms_stat_audit_total', 'wms_stat_audit_open', 'wms_stat_audit_variance', 'wms_stat_audit_completed',
    'wms_search_audit', 'wms_new_audit', 'wms_filter_all_status', 'wms_filter_all_types', 'wms_col_audit_type', 'wms_col_variance',
    'wms_col_items', 'wms_col_value', 'wms_col_expected', 'wms_col_counted_value', 'wms_col_system_qty', 'wms_col_counted_qty',
    'wms_audit_details', 'wms_view_details', 'wms_audit_form_subtitle', 'wms_audit_section_info', 'wms_audit_section_lines',
    'wms_audit_lines_hint', 'wms_add_line', 'wms_select_product', 'wms_qty_short', 'wms_product_filter', 'wms_remove_line',
    'wms_select_warehouse', 'wms_nav_warehouses', 'wms_col_product', 'wms_receipt_notes', 'wms_type_cycle_count',
    'wms_type_physical_count', 'wms_type_spot_check', 'wms_submit_audit', 'wms_approve', 'wms_reject',
    'wms_confirm_submit_audit', 'wms_confirm_approve_audit', 'wms_confirm_reject_audit',
    'wms_status_draft', 'wms_status_in_progress', 'wms_status_pending_approval', 'wms_status_approved', 'wms_status_rejected',
    'close', 'col_status', 'col_date', 'wms_export_csv',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_audit_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsAudSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_audit', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsAudWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsAudType" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_types', 'wms'); ?></option>
            <option value="cycle_count"><?php echo __t('wms_type_cycle_count', 'wms'); ?></option>
            <option value="physical_count"><?php echo __t('wms_type_physical_count', 'wms'); ?></option>
            <option value="spot_check"><?php echo __t('wms_type_spot_check', 'wms'); ?></option>
        </select>
        <select id="wmsAudStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="draft"><?php echo __t('wms_status_draft', 'wms'); ?></option>
            <option value="in_progress"><?php echo __t('wms_status_in_progress', 'wms'); ?></option>
            <option value="pending_approval"><?php echo __t('wms_status_pending_approval', 'wms'); ?></option>
            <option value="approved"><?php echo __t('wms_status_approved', 'wms'); ?></option>
            <option value="rejected"><?php echo __t('wms_status_rejected', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsAudRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsAudNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_audit', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">fact_check</span></div><div class="card-info"><h3><?php echo __t('wms_stat_audit_total', 'wms'); ?></h3><h2 id="wmsAudTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">pending_actions</span></div><div class="card-info"><h3><?php echo __t('wms_stat_audit_open', 'wms'); ?></h3><h2 id="wmsAudOpen">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">difference</span></div><div class="card-info"><h3><?php echo __t('wms_stat_audit_variance', 'wms'); ?></h3><h2 id="wmsAudVariance">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_audit_completed', 'wms'); ?></h3><h2 id="wmsAudCompleted">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">fact_check</span><?php echo __t('wms_audit_title', 'wms'); ?></h3>
    <div id="wmsAudRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsAudCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsAudCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">fact_check</span></div>
                <div>
                    <h3 id="wmsAudCreateTitle"><?php echo __t('wms_new_audit', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_audit_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsAudCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsAudCreateForm" class="wms-grn-form">
            <section class="wms-grn-section">
                <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_audit_section_info', 'wms'); ?></h4>
                <div class="wms-grn-fields">
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                        <select name="warehouse_id" id="wmsAudFormWarehouse" required></select>
                    </label>
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_col_audit_type', 'wms'); ?></span>
                        <select name="audit_type" required>
                            <option value="cycle_count"><?php echo __t('wms_type_cycle_count', 'wms'); ?></option>
                            <option value="physical_count"><?php echo __t('wms_type_physical_count', 'wms'); ?></option>
                            <option value="spot_check"><?php echo __t('wms_type_spot_check', 'wms'); ?></option>
                        </select>
                    </label>
                    <label class="wms-grn-field wms-grn-field--full">
                        <span><?php echo __t('wms_receipt_notes', 'wms'); ?></span>
                        <textarea name="notes" rows="2"></textarea>
                    </label>
                </div>
            </section>

            <section class="wms-grn-section wms-grn-section--lines">
                <div class="wms-grn-section__top">
                    <div>
                        <h4 class="wms-grn-section__title"><span class="material-icons-round">list_alt</span><?php echo __t('wms_audit_section_lines', 'wms'); ?></h4>
                        <p class="wms-grn-lines-hint"><?php echo __t('wms_audit_lines_hint', 'wms'); ?></p>
                    </div>
                    <button type="button" class="cr-btn" id="wmsAudAddLine">
                        <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                    </button>
                </div>
                <div class="wms-grn-lines-toolbar">
                    <span class="material-icons-round">search</span>
                    <input type="search" id="wmsAudProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms')); ?>" autocomplete="off">
                </div>
                <div class="wms-grn-lines-panel">
                    <div class="wms-grn-lines__header wms-grn-lines__header--request" aria-hidden="true">
                        <span>#</span>
                        <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                        <span><?php echo __t('wms_col_counted_qty', 'wms'); ?></span>
                        <span></span>
                    </div>
                    <div id="wmsAudLineItems" class="wms-grn-lines__body"></div>
                </div>
            </section>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsAudCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsAudDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsAudDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsAudDetailTitle"><?php echo __t('wms_audit_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsAudDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsAudDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsAudDetailBody" class="wms-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="wmsAudDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="cr-btn" id="wmsAudSubmitBtn"><?php echo __t('wms_submit_audit', 'wms'); ?></button>
                <button type="button" class="cr-btn" id="wmsAudApproveBtn"><?php echo __t('wms_approve', 'wms'); ?></button>
                <button type="button" class="cr-btn cr-btn--warn" id="wmsAudRejectBtn"><?php echo __t('wms_reject', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

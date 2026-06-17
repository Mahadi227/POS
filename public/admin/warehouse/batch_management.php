<?php
require __DIR__ . '/includes/bootstrap.php';

$activeWmsPage = 'batch_management';
$pageTitle = __t('wms_batches_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-batches.js'];
$pageI18n = wms_i18n([
    'loading', 'refresh', 'save', 'cancel', 'error', 'load_error', 'wms_no_data', 'wms_all_warehouses',
    'wms_new_batch', 'wms_batches_subtitle', 'wms_stat_batch_total', 'wms_stat_batch_active',
    'wms_stat_batch_expiring', 'wms_stat_batch_expired', 'wms_filter_all_status', 'wms_filter_expiring_soon',
    'wms_search_batch', 'wms_col_batch', 'wms_col_product', 'wms_col_expiry', 'wms_col_mfg', 'wms_col_qty',
    'wms_col_value', 'wms_col_barcode', 'wms_col_serial', 'wms_batch_details', 'wms_view_details',
    'wms_batch_form_subtitle', 'wms_batch_section_info', 'wms_select_product', 'wms_select_warehouse',
    'wms_nav_warehouses', 'wms_unit_cost', 'wms_days_to_expiry', 'wms_mark_recalled', 'wms_mark_depleted',
    'wms_mark_expired', 'wms_confirm_recall', 'wms_confirm_deplete', 'wms_confirm_mark_expired',
    'wms_status_active', 'wms_status_expired', 'wms_status_recalled', 'wms_status_depleted',
    'close', 'col_status', 'col_date',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_batches_subtitle', 'wms'); ?></p>

<div class="cr-toolbar">
    <div style="display:flex; gap:10px; flex-wrap:wrap; width:100%; align-items:center;">
        <input id="wmsBatSearch" type="search" class="form-input" placeholder="<?php echo htmlspecialchars(__t('wms_search_batch', 'wms')); ?>" style="max-width:300px;">
        <select id="wmsBatWarehouse" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_all_warehouses', 'wms'); ?></option>
        </select>
        <select id="wmsBatStatus" class="form-input" style="max-width:200px;">
            <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
            <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
            <option value="expiring_soon"><?php echo __t('wms_filter_expiring_soon', 'wms'); ?></option>
            <option value="expired"><?php echo __t('wms_status_expired', 'wms'); ?></option>
            <option value="recalled"><?php echo __t('wms_status_recalled', 'wms'); ?></option>
            <option value="depleted"><?php echo __t('wms_status_depleted', 'wms'); ?></option>
        </select>
        <button type="button" class="cr-btn" id="wmsBatRefresh"><span class="material-icons-round">refresh</span><?php echo __t('refresh', 'wms'); ?></button>
        <?php if ($canManageWms): ?>
        <button type="button" class="cr-btn" id="wmsBatNewBtn"><span class="material-icons-round">add</span><?php echo __t('wms_new_batch', 'wms'); ?></button>
        <?php endif; ?>
    </div>
</div>

<div class="cr-kpi-grid">
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon primary"><span class="material-icons-round">inventory_2</span></div><div class="card-info"><h3><?php echo __t('wms_stat_batch_total', 'wms'); ?></h3><h2 id="wmsBatTotal">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon success"><span class="material-icons-round">check_circle</span></div><div class="card-info"><h3><?php echo __t('wms_stat_batch_active', 'wms'); ?></h3><h2 id="wmsBatActive">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon warning"><span class="material-icons-round">schedule</span></div><div class="card-info"><h3><?php echo __t('wms_stat_batch_expiring', 'wms'); ?></h3><h2 id="wmsBatExpiring">—</h2></div></div>
    <div class="card stat-card cr-kpi-card is-loading"><div class="card-icon danger"><span class="material-icons-round">event_busy</span></div><div class="card-info"><h3><?php echo __t('wms_stat_batch_expired', 'wms'); ?></h3><h2 id="wmsBatExpired">—</h2></div></div>
</div>

<section class="cr-panel">
    <h3><span class="material-icons-round">inventory_2</span><?php echo __t('wms_batches_title', 'wms'); ?></h3>
    <div id="wmsBatRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php if ($canManageWms): ?>
<div class="wms-modal-overlay" id="wmsBatCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--batch" role="dialog" aria-labelledby="wmsBatCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></div>
                <div>
                    <h3 id="wmsBatCreateTitle"><?php echo __t('wms_new_batch', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_batch_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsBatCreateClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>

        <form id="wmsBatCreateForm" class="wms-grn-form wms-grn-form--compact">
            <div class="wms-grn-form__body">
                <section class="wms-grn-section wms-grn-section--info">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_batch_section_info', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--bat-info">
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="wmsBatFormWarehouse" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                            <select name="product_id" id="wmsBatFormProduct" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_batch', 'wms'); ?></span>
                            <input type="text" name="batch_number" required autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_barcode', 'wms'); ?></span>
                            <input type="text" name="barcode" autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_serial', 'wms'); ?></span>
                            <input type="text" name="serial_number" autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_mfg', 'wms'); ?></span>
                            <input type="date" name="manufacturing_date">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_expiry', 'wms'); ?></span>
                            <input type="date" name="expiry_date">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_qty', 'wms'); ?></span>
                            <input type="number" name="quantity" min="0" step="1" value="0">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_unit_cost', 'wms'); ?></span>
                            <input type="number" name="unit_cost" min="0" step="0.01" value="0">
                        </label>
                    </div>
                </section>
            </div>

            <footer class="wms-grn-modal__footer">
                <div class="wms-grn-modal__actions">
                    <button type="button" class="cr-btn cr-btn--ghost" id="wmsBatCreateCancel"><?php echo __t('cancel', 'wms'); ?></button>
                    <button type="submit" class="cr-btn wms-grn-submit">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'wms'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="wmsBatDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn" role="dialog" aria-labelledby="wmsBatDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="wmsBatDetailTitle"><?php echo __t('wms_batch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="wmsBatDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="wmsBatDetailClose" aria-label="<?php echo __t('close', 'wms'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="wmsBatDetailBody" class="wms-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="wmsBatDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="cr-btn cr-btn--warn" id="wmsBatRecallBtn"><?php echo __t('wms_mark_recalled', 'wms'); ?></button>
                <button type="button" class="cr-btn" id="wmsBatDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
                <button type="button" class="cr-btn cr-btn--ghost" id="wmsBatExpiredBtn"><?php echo __t('wms_mark_expired', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

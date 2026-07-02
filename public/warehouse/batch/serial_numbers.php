<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('batch');

$useWmsModules = true;
$activeWhPage = 'serial_numbers';
$pageTitle = __t('wh_nav_serial', 'warehouse');
$whCanCreate = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-serial-numbers.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_ser_subtitle', 'wh_ser_hint', 'wh_ser_stat_total', 'wh_ser_stat_active', 'wh_ser_stat_inactive',
        'wh_ser_stat_products', 'wh_ser_search', 'wh_ser_empty', 'wh_ser_hero_meta', 'wh_ser_status_breakdown',
        'wh_ser_link_batch', 'wh_ser_link_expiry', 'wh_ser_link_fifo', 'wh_ser_new', 'wh_ser_toast_created',
        'wh_ser_serial_required', 'wh_all_warehouses', 'wh_migration_hint', 'loading', 'load_error', 'refresh',
        'last_updated', 'export_csv', 'prev_page', 'next_page', 'records', 'close', 'error', 'col_date',
        'col_status', 'save', 'cancel',
    ]),
    wms_i18n([
        'wms_new_batch', 'wms_batch_form_subtitle', 'wms_batch_section_info', 'wms_select_product',
        'wms_select_warehouse', 'wms_nav_warehouses', 'wms_col_batch', 'wms_col_product', 'wms_col_expiry',
        'wms_col_mfg', 'wms_col_qty', 'wms_col_value', 'wms_col_barcode', 'wms_col_serial', 'wms_batch_details',
        'wms_view_details', 'wms_unit_cost', 'wms_days_to_expiry', 'wms_days_short', 'wms_mark_recalled',
        'wms_mark_depleted', 'wms_mark_expired', 'wms_confirm_recall', 'wms_confirm_deplete',
        'wms_confirm_mark_expired', 'wms_filter_all_status', 'wms_filter_expiring_soon', 'wms_status_active',
        'wms_status_expired', 'wms_status_recalled', 'wms_status_depleted',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-ser-hero" aria-labelledby="whSerHeroTitle">
    <div class="wh-ser-hero__intro">
        <h2 class="wh-ser-hero__title" id="whSerHeroTitle"><?php echo __t('wh_ser_subtitle', 'warehouse'); ?></h2>
        <p class="wh-ser-hero__meta" id="whSerHeroMeta" aria-live="polite">—</p>
        <p class="wh-ser-hero__hint"><?php echo __t('wh_ser_hint', 'warehouse'); ?></p>
        <div class="wh-ser-hero__links">
            <a class="wh-ser-hero__link" href="batch_tracking.php"><?php echo __t('wh_ser_link_batch', 'warehouse'); ?></a>
            <a class="wh-ser-hero__link" href="expiry_management.php"><?php echo __t('wh_ser_link_expiry', 'warehouse'); ?></a>
            <a class="wh-ser-hero__link" href="fifo_fefo.php"><?php echo __t('wh_ser_link_fifo', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-ser-hero__stats" role="group">
        <article class="wh-ser-stat wh-ser-stat--primary">
            <span class="wh-ser-stat__label"><?php echo __t('wh_ser_stat_total', 'warehouse'); ?></span>
            <strong class="wh-ser-stat__value is-loading" id="whSerStatTotal">—</strong>
        </article>
        <article class="wh-ser-stat wh-ser-stat--success">
            <span class="wh-ser-stat__label"><?php echo __t('wh_ser_stat_active', 'warehouse'); ?></span>
            <strong class="wh-ser-stat__value is-loading" id="whSerStatActive">—</strong>
        </article>
        <article class="wh-ser-stat wh-ser-stat--warn">
            <span class="wh-ser-stat__label"><?php echo __t('wh_ser_stat_inactive', 'warehouse'); ?></span>
            <strong class="wh-ser-stat__value is-loading" id="whSerStatInactive">—</strong>
        </article>
        <article class="wh-ser-stat wh-ser-stat--info">
            <span class="wh-ser-stat__label"><?php echo __t('wh_ser_stat_products', 'warehouse'); ?></span>
            <strong class="wh-ser-stat__value is-loading" id="whSerStatProducts">—</strong>
        </article>
    </div>
</section>

<section class="wh-ser-breakdown" id="whSerBreakdownPanel" hidden aria-labelledby="whSerBreakdownTitle">
    <div class="wh-ser-breakdown__head">
        <h3 id="whSerBreakdownTitle"><?php echo __t('wh_ser_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-ser-status-chips" id="whSerStatusChips"></div>
</section>

<div class="wh-ser-toolbar">
    <div class="wh-ser-toolbar__row">
        <div class="wh-ser-toolbar__filters">
            <label class="wh-ser-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whSerSearch" class="wh-ser-search" placeholder="<?php echo htmlspecialchars(__t('wh_ser_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whSerWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whSerStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                <option value="expiring_soon"><?php echo __t('wms_filter_expiring_soon', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_status_expired', 'wms'); ?></option>
                <option value="recalled"><?php echo __t('wms_status_recalled', 'wms'); ?></option>
                <option value="depleted"><?php echo __t('wms_status_depleted', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-ser-toolbar__actions">
            <?php if ($whCanCreate): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whSerNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_ser_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whSerExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whSerRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-ser-panel" aria-live="polite">
    <div class="wh-ser-table-wrap" id="whSerTableWrap"></div>
    <div class="wh-ser-empty" id="whSerEmpty" hidden>
        <span class="material-icons-round">pin</span>
        <p><?php echo __t('wh_ser_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whSerLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-ser-pagination" id="whSerPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSerPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-ser-pagination__meta" id="whSerPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whSerNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-ser-toast" id="whSerToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreate): ?>
<div class="wms-modal-overlay" id="whSerCreateModal" aria-hidden="true">
    <div class="wms-modal wms-modal--batch wh-form-modal wh-form-modal--ser" role="dialog" aria-labelledby="whSerCreateTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">pin</span></div>
                <div>
                    <h3 id="whSerCreateTitle"><?php echo __t('wh_ser_new', 'warehouse'); ?></h3>
                    <p class="wms-grn-modal__subtitle"><?php echo __t('wms_batch_form_subtitle', 'wms'); ?></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whSerCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <form id="whSerCreateForm" class="wms-grn-form wms-grn-form--compact">
            <div class="wms-grn-form__body">
                <section class="wms-grn-section wms-grn-section--info">
                    <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_batch_section_info', 'wms'); ?></h4>
                    <div class="wms-grn-fields wms-grn-fields--bat-info">
                        <label class="wms-grn-field wms-grn-field--highlight">
                            <span><?php echo __t('wms_col_serial', 'wms'); ?> *</span>
                            <input type="text" name="serial_number" id="whSerFormSerial" required autocomplete="off">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whSerFormWarehouse" required></select>
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                            <select name="product_id" id="whSerFormProduct" required></select>
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
                            <span><?php echo __t('wms_col_mfg', 'wms'); ?></span>
                            <input type="date" name="manufacturing_date">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_expiry', 'wms'); ?></span>
                            <input type="date" name="expiry_date">
                        </label>
                        <label class="wms-grn-field">
                            <span><?php echo __t('wms_col_qty', 'wms'); ?></span>
                            <input type="number" name="quantity" min="0" step="1" value="1">
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
                    <button type="button" class="wh-btn wh-btn--ghost" id="whSerCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                    <button type="submit" class="wh-btn wh-btn--primary">
                        <span class="material-icons-round">save</span><?php echo __t('save', 'warehouse'); ?>
                    </button>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="whSerDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--ser" role="dialog" aria-labelledby="whSerDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whSerDetailTitle"><?php echo __t('wms_batch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whSerDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whSerDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whSerDetailBody" class="wh-ser-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="whSerDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="wh-btn wh-btn--warn" id="whSerRecallBtn"><?php echo __t('wms_mark_recalled', 'wms'); ?></button>
                <button type="button" class="wh-btn" id="whSerDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whSerExpiredBtn"><?php echo __t('wms_mark_expired', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

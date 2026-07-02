<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('batch');

$useWmsModules = true;
$activeWhPage = 'batch_tracking';
$pageTitle = __t('wh_nav_batch', 'warehouse');
$whCanCreate = $whCanManage && !$whReadOnly;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-batch-tracking.js'];
$extraCss = ['wh-grn-create.css', 'wh-bat-create.css'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_bat_subtitle', 'wh_bat_hint', 'wh_bat_stat_total', 'wh_bat_stat_active', 'wh_bat_stat_expiring',
        'wh_bat_stat_expired', 'wh_bat_search', 'wh_bat_empty', 'wh_bat_hero_meta', 'wh_bat_status_breakdown',
        'wh_bat_link_expiry', 'wh_bat_link_serial', 'wh_bat_link_fifo', 'wh_bat_new', 'wh_bat_toast_created',
        'wh_bat_toast_recalled', 'wh_bat_toast_depleted', 'wh_bat_toast_expired', 'wh_all_warehouses',
        'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated', 'export_csv', 'prev_page',
        'next_page', 'records', 'close', 'error', 'col_date', 'col_status', 'save', 'cancel',
    ]),
    wms_i18n([
        'wms_batches_title', 'wms_new_batch', 'wms_batch_form_subtitle', 'wms_batch_section_info',
        'wms_batch_section_details', 'wms_batch_product_search',
        'wms_select_product', 'wms_select_warehouse', 'wms_nav_warehouses', 'wms_col_batch', 'wms_col_product',
        'wms_col_expiry', 'wms_col_mfg', 'wms_col_qty', 'wms_col_value', 'wms_col_barcode', 'wms_col_serial',
        'wms_batch_details', 'wms_view_details', 'wms_unit_cost', 'wms_days_to_expiry', 'wms_days_short',
        'wms_mark_recalled', 'wms_mark_depleted', 'wms_mark_expired', 'wms_confirm_recall', 'wms_confirm_deplete',
        'wms_confirm_mark_expired', 'wms_filter_all_status', 'wms_filter_expiring_soon', 'wms_status_active',
        'wms_status_expired', 'wms_status_recalled', 'wms_status_depleted',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-bat-hero" aria-labelledby="whBatHeroTitle">
    <div class="wh-bat-hero__intro">
        <h2 class="wh-bat-hero__title" id="whBatHeroTitle"><?php echo __t('wh_bat_subtitle', 'warehouse'); ?></h2>
        <p class="wh-bat-hero__meta" id="whBatHeroMeta" aria-live="polite">—</p>
        <p class="wh-bat-hero__hint"><?php echo __t('wh_bat_hint', 'warehouse'); ?></p>
        <div class="wh-bat-hero__links">
            <a class="wh-bat-hero__link" href="expiry_management.php"><?php echo __t('wh_bat_link_expiry', 'warehouse'); ?></a>
            <a class="wh-bat-hero__link" href="serial_numbers.php"><?php echo __t('wh_bat_link_serial', 'warehouse'); ?></a>
            <a class="wh-bat-hero__link" href="fifo_fefo.php"><?php echo __t('wh_bat_link_fifo', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-bat-hero__stats" role="group">
        <article class="wh-bat-stat wh-bat-stat--primary">
            <span class="wh-bat-stat__label"><?php echo __t('wh_bat_stat_total', 'warehouse'); ?></span>
            <strong class="wh-bat-stat__value is-loading" id="whBatStatTotal">—</strong>
        </article>
        <article class="wh-bat-stat wh-bat-stat--success">
            <span class="wh-bat-stat__label"><?php echo __t('wh_bat_stat_active', 'warehouse'); ?></span>
            <strong class="wh-bat-stat__value is-loading" id="whBatStatActive">—</strong>
        </article>
        <article class="wh-bat-stat wh-bat-stat--warn">
            <span class="wh-bat-stat__label"><?php echo __t('wh_bat_stat_expiring', 'warehouse'); ?></span>
            <strong class="wh-bat-stat__value is-loading" id="whBatStatExpiring">—</strong>
        </article>
        <article class="wh-bat-stat wh-bat-stat--danger">
            <span class="wh-bat-stat__label"><?php echo __t('wh_bat_stat_expired', 'warehouse'); ?></span>
            <strong class="wh-bat-stat__value is-loading" id="whBatStatExpired">—</strong>
        </article>
    </div>
</section>

<section class="wh-bat-breakdown" id="whBatBreakdownPanel" hidden aria-labelledby="whBatBreakdownTitle">
    <div class="wh-bat-breakdown__head">
        <h3 id="whBatBreakdownTitle"><?php echo __t('wh_bat_status_breakdown', 'warehouse'); ?></h3>
    </div>
    <div class="wh-bat-status-chips" id="whBatStatusChips"></div>
</section>

<div class="wh-bat-toolbar">
    <div class="wh-bat-toolbar__row">
        <div class="wh-bat-toolbar__filters">
            <label class="wh-bat-search-wrap">
                <span class="material-icons-round" aria-hidden="true">search</span>
                <input type="search" id="whBatSearch" class="wh-bat-search" placeholder="<?php echo htmlspecialchars(__t('wh_bat_search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
            </label>
            <select id="whBatWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_all_warehouses', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
            <select id="whBatStatus" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('col_status', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wms_filter_all_status', 'wms'); ?></option>
                <option value="active"><?php echo __t('wms_status_active', 'wms'); ?></option>
                <option value="expiring_soon"><?php echo __t('wms_filter_expiring_soon', 'wms'); ?></option>
                <option value="expired"><?php echo __t('wms_status_expired', 'wms'); ?></option>
                <option value="recalled"><?php echo __t('wms_status_recalled', 'wms'); ?></option>
                <option value="depleted"><?php echo __t('wms_status_depleted', 'wms'); ?></option>
            </select>
        </div>
        <div class="wh-bat-toolbar__actions">
            <?php if ($whCanCreate): ?>
            <button type="button" class="wh-btn wh-btn--primary" id="whBatNewBtn">
                <span class="material-icons-round">add</span>
                <span class="wh-btn-label"><?php echo __t('wh_bat_new', 'warehouse'); ?></span>
            </button>
            <?php endif; ?>
            <button type="button" class="wh-btn wh-btn--ghost" id="whBatExportBtn">
                <span class="material-icons-round">download</span>
                <span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span>
            </button>
            <button type="button" class="wh-btn" id="whBatRefreshBtn">
                <span class="material-icons-round">refresh</span>
                <span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span>
            </button>
        </div>
    </div>
</div>

<section class="wh-bat-panel" aria-live="polite">
    <div class="wh-bat-table-wrap" id="whBatTableWrap"></div>
    <div class="wh-bat-empty" id="whBatEmpty" hidden>
        <span class="material-icons-round">inventory_2</span>
        <p><?php echo __t('wh_bat_empty', 'warehouse'); ?></p>
    </div>
    <div class="wh-loading" id="whBatLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</section>

<nav class="wh-bat-pagination" id="whBatPagination" aria-label="Pagination" hidden>
    <button type="button" class="wh-btn wh-btn--ghost" id="whBatPrev" disabled><?php echo __t('prev_page', 'warehouse'); ?></button>
    <span class="wh-bat-pagination__meta" id="whBatPageMeta">—</span>
    <button type="button" class="wh-btn wh-btn--ghost" id="whBatNext" disabled><?php echo __t('next_page', 'warehouse'); ?></button>
</nav>

<p class="wh-dash-updated" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-bat-toast" id="whBatToast" role="status" aria-live="polite"></div>

<?php if ($whCanCreate): ?>
<div class="wms-modal-overlay" id="whBatCreateModal" aria-hidden="true">
    <div class="wh-grn-modal wh-grn-modal--bat" role="dialog" aria-labelledby="whBatCreateTitle" aria-modal="true">
        <div class="wh-grn-modal__swipe-zone">
            <div class="wh-grn-modal__handle" aria-hidden="true"></div>
            <header class="wh-grn-modal__header">
                <div class="wh-grn-modal__brand">
                    <div class="wh-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">inventory_2</span></div>
                    <div class="wh-grn-modal__titles">
                        <p class="wh-grn-modal__eyebrow"><?php echo __t('wms_batch_section_info', 'wms'); ?></p>
                        <h3 class="wh-grn-modal__title" id="whBatCreateTitle"><?php echo __t('wms_new_batch', 'wms'); ?></h3>
                    </div>
                </div>
                <button type="button" class="wh-grn-modal__close" id="whBatCreateClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                    <span class="material-icons-round">close</span>
                </button>
            </header>
        </div>

        <form id="whBatCreateForm" class="wh-grn-modal__form" novalidate>
            <div class="wh-grn-modal__body">
                <details class="wh-grn-meta-wrap" id="whBatMetaWrap">
                    <summary class="wh-grn-meta-wrap__toggle">
                        <span><?php echo __t('wms_batch_section_info', 'wms'); ?></span>
                        <span class="material-icons-round" aria-hidden="true">expand_more</span>
                    </summary>
                    <fieldset class="wh-grn-meta" aria-labelledby="whBatMetaTitle">
                        <legend class="wh-grn-sr-only" id="whBatMetaTitle"><?php echo __t('wms_batch_section_info', 'wms'); ?></legend>
                        <label class="wh-grn-field wh-grn-field--warehouse">
                            <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                            <select name="warehouse_id" id="whBatFormWarehouse" required></select>
                        </label>
                        <label class="wh-grn-field wh-grn-field--product">
                            <span><?php echo __t('wms_col_product', 'wms'); ?></span>
                            <div class="wh-grn-product-picker" id="whBatProductPicker">
                                <div class="wh-grn-product-input-wrap">
                                    <input type="text" class="wh-grn-product-input" id="whBatProductInput" placeholder="<?php echo htmlspecialchars(__t('wms_batch_product_search', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" required>
                                    <input type="hidden" name="product_id" id="whBatProductId" value="">
                                </div>
                                <div class="wh-grn-product-dropdown" hidden></div>
                            </div>
                        </label>
                    </fieldset>
                </details>

                <section class="wh-grn-workspace" aria-labelledby="whBatDetailsTitle">
                    <div class="wh-grn-workspace__toolbar">
                        <div class="wh-grn-workspace__heading">
                            <h4 class="wh-grn-workspace__title" id="whBatDetailsTitle"><?php echo __t('wms_batch_section_details', 'wms'); ?></h4>
                            <p class="wh-grn-workspace__hint"><?php echo __t('wms_batch_form_subtitle', 'wms'); ?></p>
                        </div>
                    </div>
                    <div class="wh-bat-details-grid">
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_batch', 'wms'); ?></span>
                            <input type="text" name="batch_number" id="whBatBatchNumber" required autocomplete="off">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_barcode', 'wms'); ?></span>
                            <input type="text" name="barcode" autocomplete="off">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_serial', 'wms'); ?></span>
                            <input type="text" name="serial_number" autocomplete="off">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_mfg', 'wms'); ?></span>
                            <input type="date" name="manufacturing_date">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_expiry', 'wms'); ?></span>
                            <input type="date" name="expiry_date">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_col_qty', 'wms'); ?></span>
                            <input type="number" name="quantity" id="whBatQty" min="0" step="1" value="0" inputmode="numeric">
                        </label>
                        <label class="wh-grn-field">
                            <span><?php echo __t('wms_unit_cost', 'wms'); ?></span>
                            <input type="number" name="unit_cost" id="whBatUnitCost" min="0" step="0.01" value="0" inputmode="decimal">
                        </label>
                    </div>
                </section>
            </div>

            <footer class="wh-grn-modal__footer">
                <p class="wh-grn-modal__error" id="whBatFormError" hidden role="alert"></p>
                <div class="wh-grn-footer-bar">
                    <div class="wh-grn-footer-bar__metrics">
                        <div class="wh-grn-metric">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_qty', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whBatMetricQty">0</strong>
                        </div>
                        <div class="wh-grn-metric wh-grn-metric--total">
                            <span class="wh-grn-metric__label"><?php echo __t('wms_col_value', 'wms'); ?></span>
                            <strong class="wh-grn-metric__value" id="whBatMetricValue">0</strong>
                        </div>
                    </div>
                    <div class="wh-grn-footer-bar__actions">
                        <button type="button" class="wh-btn wh-btn--ghost" id="whBatCreateCancel"><?php echo __t('cancel', 'warehouse'); ?></button>
                        <button type="submit" class="wh-btn wh-btn--primary wh-grn-btn-submit">
                            <span class="material-icons-round">save</span><?php echo __t('save', 'warehouse'); ?>
                        </button>
                    </div>
                </div>
            </footer>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="wms-modal-overlay" id="whBatDetailModal" aria-hidden="true">
    <div class="wms-modal wms-modal--grn wms-modal--wide wh-form-modal wh-form-modal--bat" role="dialog" aria-labelledby="whBatDetailTitle">
        <header class="wms-grn-modal__head">
            <div class="wms-grn-modal__head-main">
                <div class="wms-grn-modal__icon" aria-hidden="true"><span class="material-icons-round">description</span></div>
                <div>
                    <h3 id="whBatDetailTitle"><?php echo __t('wms_batch_details', 'wms'); ?></h3>
                    <p class="wms-grn-modal__subtitle" id="whBatDetailSubtitle"></p>
                </div>
            </div>
            <button type="button" class="wms-grn-modal__close" id="whBatDetailClose" aria-label="<?php echo __t('close', 'warehouse'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </header>
        <div id="whBatDetailBody" class="wh-bat-detail-body"></div>
        <footer class="wms-grn-modal__footer" id="whBatDetailActions" hidden>
            <div class="wms-grn-modal__actions">
                <button type="button" class="wh-btn wh-btn--warn" id="whBatRecallBtn"><?php echo __t('wms_mark_recalled', 'wms'); ?></button>
                <button type="button" class="wh-btn" id="whBatDepleteBtn"><?php echo __t('wms_mark_depleted', 'wms'); ?></button>
                <button type="button" class="wh-btn wh-btn--ghost" id="whBatExpiredBtn"><?php echo __t('wms_mark_expired', 'wms'); ?></button>
            </div>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php';

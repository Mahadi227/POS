<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('receiving');

$useWmsModules = true;
$activeWhPage = 'receive_stock';
$pageTitle = __t('wh_nav_receive_stock', 'warehouse');
$whCanReceiveStock = $whCanReceive && !$whReadOnly;
$loadScanner = true;
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-receive-scan.js', 'warehouse-receive-stock.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_nav_receive_stock',         'wh_rcv_subtitle', 'wh_rcv_hint', 'wh_rcv_stat_today', 'wh_rcv_stat_today_items',
        'wh_rcv_stat_today_value', 'wh_rcv_hero_meta', 'wh_rcv_scan_label', 'wh_rcv_scan_placeholder', 'wh_rcv_scan_wedge',
        'wh_rcv_mode_post', 'wh_rcv_mode_pending', 'wh_rcv_mode_legend', 'wh_rcv_submit_post', 'wh_rcv_submit_pending',
        'wh_rcv_recent', 'wh_rcv_recent_empty', 'wh_rcv_toast_posted', 'wh_rcv_toast_saved', 'wh_rcv_link_grn',
        'wh_rcv_link_deliveries', 'wh_rcv_link_po', 'wh_rcv_link_scanner', 'wh_rcv_location_optional',
        'wh_rcv_select_warehouse', 'wh_rcv_product_not_found', 'wh_rcv_readonly', 'wh_rcv_from_po', 'wh_rcv_po_loaded',
        'wh_rcv_po_error', 'wh_rcv_view_grn', 'wh_rcv_submitting', 'wh_select_warehouse', 'wh_all_warehouses',
        'wh_migration_hint', 'loading', 'load_error', 'refresh', 'last_updated', 'close', 'cancel', 'save', 'error', 'col_status',
    ]),
    wms_i18n([
        'wms_grn_section_info', 'wms_grn_section_lines', 'wms_grn_lines_hint', 'wms_grn_estimated_total',
        'wms_grn_lines_count', 'wms_supplier_placeholder', 'wms_add_line', 'wms_select_product',
        'wms_col_supplier', 'wms_receipt_notes', 'wms_nav_warehouses', 'wms_col_product', 'wms_col_grn',
        'wms_qty_short', 'wms_unit_cost', 'wms_line_subtotal', 'wms_line_tracking', 'wms_batch_optional',
        'wms_expiry_optional', 'wms_product_filter', 'wms_remove_line', 'wms_col_items', 'wms_col_value',
        'wms_confirm_complete', 'wms_nav_locations', 'wms_status_pending', 'wms_status_completed',
        'wms_status_inspecting', 'wms_status_accepted', 'wms_status_rejected', 'wms_po_col_number',
    ]),
    array_combine(
        [
            'scanner_usb_hint', 'scanner_tab_camera', 'scanner_status_ready', 'scanner_status_scanning',
            'scanner_status_processing', 'scanner_status_found', 'scanner_code_too_short', 'scanner_permission_denied',
            'scanner_allow_camera', 'scanner_insecure_context', 'scanner_no_camera', 'scanner_not_loaded',
            'scanner_camera_start', 'scanner_camera_stop', 'scanner_select_camera', 'scanner_camera_rear', 'scanner_camera_front',
        ],
        array_map(fn ($k) => __t($k, 'inventory'), [
            'scanner_usb_hint', 'scanner_tab_camera', 'scanner_status_ready', 'scanner_status_scanning',
            'scanner_status_processing', 'scanner_status_found', 'scanner_code_too_short', 'scanner_permission_denied',
            'scanner_allow_camera', 'scanner_insecure_context', 'scanner_no_camera', 'scanner_not_loaded',
            'scanner_camera_start', 'scanner_camera_stop', 'scanner_select_camera', 'scanner_camera_rear', 'scanner_camera_front',
        ])
    )
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-rcv-hero" aria-labelledby="whRcvHeroTitle">
    <div class="wh-rcv-hero__intro">
        <p class="wh-rcv-hero__intro-text" id="whRcvHeroTitle"><?php echo __t('wh_rcv_subtitle', 'warehouse'); ?></p>
        <p class="wh-rcv-hero__meta" id="whRcvHeroMeta" aria-live="polite">—</p>
        <p class="wh-rcv-hero__hint"><?php echo __t('wh_rcv_hint', 'warehouse'); ?></p>
        <div class="wh-rcv-hero__links">
            <a class="wh-rcv-hero__link" href="goods_receipts.php"><?php echo __t('wh_rcv_link_grn', 'warehouse'); ?></a>
            <a class="wh-rcv-hero__link" href="purchase_orders.php"><?php echo __t('wh_rcv_link_po', 'warehouse'); ?></a>
            <a class="wh-rcv-hero__link" href="supplier_deliveries.php"><?php echo __t('wh_rcv_link_deliveries', 'warehouse'); ?></a>
            <a class="wh-rcv-hero__link" href="../inventory/barcode_scanner.php"><?php echo __t('wh_rcv_link_scanner', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-rcv-hero__stats" role="group">
        <article class="wh-rcv-stat wh-rcv-stat--primary">
            <span class="wh-rcv-stat__label"><?php echo __t('wh_rcv_stat_today', 'warehouse'); ?></span>
            <strong class="wh-rcv-stat__value is-loading" id="whRcvStatToday">—</strong>
        </article>
        <article class="wh-rcv-stat">
            <span class="wh-rcv-stat__label"><?php echo __t('wh_rcv_stat_today_items', 'warehouse'); ?></span>
            <strong class="wh-rcv-stat__value is-loading" id="whRcvStatItems">—</strong>
        </article>
        <article class="wh-rcv-stat wh-rcv-stat--success">
            <span class="wh-rcv-stat__label"><?php echo __t('wh_rcv_stat_today_value', 'warehouse'); ?></span>
            <strong class="wh-rcv-stat__value is-loading" id="whRcvStatValue">—</strong>
        </article>
    </div>
</section>

<div class="wh-rcv-grid">
    <section class="wh-rcv-panel wh-rcv-panel--form" aria-labelledby="whRcvFormTitle">
        <?php if (!$whCanReceiveStock): ?>
        <div class="wh-rcv-readonly">
            <span class="material-icons-round">lock</span>
            <p><?php echo __t('wh_rcv_readonly', 'warehouse'); ?></p>
            <a class="wh-btn wh-btn--ghost" href="goods_receipts.php"><?php echo __t('wh_rcv_link_grn', 'warehouse'); ?></a>
        </div>
        <?php else: ?>
        <div class="wh-rcv-po-banner" id="whRcvPoBanner" hidden role="status" aria-live="polite">
            <span class="material-icons-round">shopping_cart</span>
            <span id="whRcvPoBannerText"></span>
            <button type="button" class="wh-rcv-po-banner__close" id="whRcvPoBannerClose" aria-label="<?php echo htmlspecialchars(__t('close', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <p class="wh-rcv-form-error" id="whRcvFormError" role="alert" hidden></p>
        <h3 class="wh-rcv-panel__title" id="whRcvFormTitle"><?php echo __t('wh_nav_receive_stock', 'warehouse'); ?></h3>
        <form id="whRcvForm" class="wms-grn-form wms-grn-form--create wh-rcv-form">
            <section class="wms-grn-section wms-grn-section--info">
                <h4 class="wms-grn-section__title"><span class="material-icons-round">info</span><?php echo __t('wms_grn_section_info', 'wms'); ?></h4>
                <div class="wms-grn-fields wms-grn-fields--grn-info">
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_nav_warehouses', 'wms'); ?></span>
                        <select name="warehouse_id" id="whRcvWarehouse" required>
                            <option value=""><?php echo __t('wh_rcv_select_warehouse', 'warehouse'); ?></option>
                        </select>
                    </label>
                    <label class="wms-grn-field">
                        <span><?php echo __t('wms_col_supplier', 'wms'); ?></span>
                        <input type="text" name="supplier_name" placeholder="<?php echo htmlspecialchars(__t('wms_supplier_placeholder', 'wms'), ENT_QUOTES, 'UTF-8'); ?>">
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
                    <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whRcvAddLine">
                        <span class="material-icons-round">add</span><?php echo __t('wms_add_line', 'wms'); ?>
                    </button>
                </div>
                <div class="wh-rcv-scan-dock" id="whRcvScanDock">
                    <div class="wh-rcv-scan-dock__head">
                        <span class="wh-rcv-scan-dock__title">
                            <span class="material-icons-round">qr_code_scanner</span>
                            <?php echo __t('wh_rcv_scan_label', 'warehouse'); ?>
                        </span>
                        <span class="wh-rcv-scan-status wh-rcv-scan-status--ready" id="whRcvScanStatus">
                            <span class="wh-rcv-scan-status__dot"></span>
                            <span id="whRcvScanStatusText"><?php echo __t('scanner_status_ready', 'inventory'); ?></span>
                        </span>
                    </div>
                    <div class="wh-rcv-scan-tabs" role="tablist">
                        <button type="button" class="wh-rcv-scan-tab active" id="whRcvScanTabWedge" role="tab" aria-selected="true" data-tab="wedge">
                            <span class="material-icons-round">usb</span>
                            <?php echo __t('wh_rcv_scan_wedge', 'warehouse'); ?>
                        </button>
                        <button type="button" class="wh-rcv-scan-tab" id="whRcvScanTabCamera" role="tab" aria-selected="false" data-tab="camera">
                            <span class="material-icons-round">photo_camera</span>
                            <?php echo __t('scanner_tab_camera', 'inventory'); ?>
                        </button>
                    </div>
                    <div class="wh-rcv-scan-panel" id="whRcvScanPanelWedge" role="tabpanel">
                        <label class="wh-rcv-scan">
                            <input type="text" id="whRcvScan" class="wh-rcv-scan__input" placeholder="<?php echo htmlspecialchars(__t('wh_rcv_scan_placeholder', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" inputmode="numeric">
                            <p class="wh-rcv-scan__hint"><?php echo __t('scanner_usb_hint', 'inventory'); ?></p>
                        </label>
                    </div>
                    <div class="wh-rcv-scan-panel" id="whRcvScanPanelCamera" role="tabpanel" hidden>
                        <div class="wh-rcv-scan-viewport">
                            <div id="whRcvCameraReader" class="wh-rcv-scan-reader"></div>
                            <div class="wh-rcv-scan-flash" id="whRcvScanFlash" hidden></div>
                        </div>
                        <div class="wh-rcv-scan-controls">
                            <label class="wh-rcv-scan-control">
                                <span><?php echo __t('scanner_select_camera', 'inventory'); ?></span>
                                <select id="whRcvCameraSelect" class="wh-select wh-select--sm">
                                    <option value=""><?php echo __t('scanner_select_camera', 'inventory'); ?></option>
                                </select>
                            </label>
                            <div class="wh-rcv-scan-control-actions">
                                <button type="button" class="wh-btn wh-btn--primary wh-btn--sm" id="whRcvCameraStart">
                                    <span class="material-icons-round">play_arrow</span>
                                    <?php echo __t('scanner_camera_start', 'inventory'); ?>
                                </button>
                                <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whRcvCameraStop" hidden>
                                    <span class="material-icons-round">stop</span>
                                    <?php echo __t('scanner_camera_stop', 'inventory'); ?>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="wms-grn-lines-toolbar">
                    <span class="material-icons-round">search</span>
                    <input type="search" id="whRcvProductFilter" placeholder="<?php echo htmlspecialchars(__t('wms_product_filter', 'wms'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off">
                </div>
                <div class="wms-grn-lines-panel">
                    <div id="whRcvLineItems" class="wms-grn-lines__body"></div>
                    <p class="wms-grn-lines__empty" id="whRcvLinesEmpty" hidden><?php echo __t('wms_add_line', 'wms'); ?></p>
                </div>
            </section>

            <footer class="wh-rcv-form__footer">
                <div class="wms-grn-summary">
                    <span id="whRcvLineCount">0 <?php echo __t('wms_col_items', 'wms'); ?></span>
                    <div class="wms-grn-summary__total">
                        <span><?php echo __t('wms_grn_estimated_total', 'wms'); ?></span>
                        <strong id="whRcvEstTotal">0</strong>
                    </div>
                </div>
                <fieldset class="wh-rcv-mode" id="whRcvMode">
                    <legend class="wh-rcv-mode__legend"><?php echo __t('wh_rcv_mode_legend', 'warehouse'); ?></legend>
                    <label class="wh-rcv-mode__opt">
                        <input type="radio" name="receive_mode" value="post" checked>
                        <span><?php echo __t('wh_rcv_mode_post', 'warehouse'); ?></span>
                    </label>
                    <label class="wh-rcv-mode__opt">
                        <input type="radio" name="receive_mode" value="pending">
                        <span><?php echo __t('wh_rcv_mode_pending', 'warehouse'); ?></span>
                    </label>
                </fieldset>
                <div class="wh-rcv-form__actions">
                    <button type="button" class="wh-btn wh-btn--ghost" id="whRcvReset"><?php echo __t('cancel', 'warehouse'); ?></button>
                    <button type="submit" class="wh-btn wh-btn--primary" id="whRcvSubmit">
                        <span class="material-icons-round">inventory</span>
                        <span class="wh-rcv-submit-label"><?php echo __t('wh_rcv_submit_post', 'warehouse'); ?></span>
                    </button>
                </div>
            </footer>
        </form>
        <?php endif; ?>
    </section>

    <aside class="wh-rcv-panel wh-rcv-panel--recent" aria-labelledby="whRcvRecentTitle">
        <div class="wh-rcv-panel__head">
            <h3 class="wh-rcv-panel__title" id="whRcvRecentTitle"><?php echo __t('wh_rcv_recent', 'warehouse'); ?></h3>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whRcvRefreshRecent">
                <span class="material-icons-round">refresh</span>
            </button>
        </div>
        <div class="wh-rcv-recent" id="whRcvRecentList">
            <div class="wh-rcv-recent-skeleton" id="whRcvRecentSkeleton" aria-hidden="true">
                <?php for ($i = 0; $i < 4; $i++): ?>
                <div class="wh-rcv-recent-skeleton__row"></div>
                <?php endfor; ?>
            </div>
        </div>
    </aside>
</div>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>
<div class="wh-rcv-toast" id="whRcvToast" role="status" aria-live="polite"></div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

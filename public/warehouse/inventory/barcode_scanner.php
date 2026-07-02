<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('inventory');

$useWmsModules = true;
$loadScanner = true;
$activeWhPage = 'barcode_scanner';
$pageTitle = __t('wh_nav_scanner', 'warehouse');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-barcode-scanner.js'];

$invScannerKeys = [
    'scanner_title', 'scanner_sub', 'scanner_usb_hint', 'scanner_tab_camera', 'scanner_tab_manual',
    'scanner_manual_placeholder', 'scanner_manual_submit', 'scanner_status_ready', 'scanner_status_scanning',
    'scanner_status_processing', 'scanner_status_found', 'scanner_status_not_found', 'scanner_last_scan',
    'scanner_camera_start', 'scanner_camera_stop', 'scanner_select_camera', 'scanner_no_camera',
    'scanner_camera_rear', 'scanner_camera_front', 'scanner_code_too_short', 'scanner_permission_denied',
    'scanner_allow_camera', 'scanner_insecure_context', 'barcode', 'no_barcode', 'scanner_not_loaded',
];
$invI18n = [];
foreach ($invScannerKeys as $key) {
    $invI18n[$key] = __t($key, 'inventory');
}

$pageI18n = array_merge(
    wh_i18n([
        'wh_scan_subtitle', 'wh_scan_hint', 'wh_scan_stat_session', 'wh_scan_stat_found', 'wh_scan_stat_not_found',
        'wh_scan_input_label', 'wh_scan_input_placeholder', 'wh_scan_no_result', 'wh_scan_result_title',
        'wh_scan_history_title', 'wh_scan_history_empty', 'wh_scan_link_products', 'wh_scan_link_inv',
        'wh_scan_link_receive', 'wh_scan_col_code', 'wh_scan_col_product', 'wh_scan_col_status', 'wh_scan_col_time',
        'wh_scan_status_found', 'wh_scan_status_not_found', 'wh_scan_clear_history', 'wh_scan_retail_stock',
        'wh_scan_wh_stock', 'wh_select_warehouse', 'wh_all_warehouses', 'wh_migration_hint',
        'wh_prod_wh_breakdown', 'wh_prod_no_wh_stock', 'wh_prod_link_ledger', 'wh_prod_col_available',
        'wh_prod_col_reserved', 'loading', 'load_error', 'refresh', 'last_updated', 'no_data', 'export_csv',
        'close', 'col_status',
    ]),
    wms_i18n([
        'wms_nav_warehouses', 'wms_col_qty', 'wms_col_value', 'wms_col_location', 'wms_col_product', 'wms_col_sku',
        'wms_stock_ok', 'wms_stock_low', 'wms_stock_out', 'wms_stock_alert',
    ]),
    $invI18n
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-scan-hero" aria-labelledby="whScanHeroTitle">
    <div class="wh-scan-hero__intro">
        <p class="wh-scan-hero__intro-text" id="whScanHeroTitle"><?php echo __t('wh_scan_subtitle', 'warehouse'); ?></p>
        <p class="wh-scan-hero__meta" id="whScanHeroMeta" aria-live="polite">—</p>
        <p class="wh-scan-hero__hint"><?php echo __t('wh_scan_hint', 'warehouse'); ?></p>
        <div class="wh-scan-hero__links">
            <a class="wh-scan-hero__link" href="products.php"><?php echo __t('wh_scan_link_products', 'warehouse'); ?></a>
            <a class="wh-scan-hero__link" href="warehouse_inventory.php"><?php echo __t('wh_scan_link_inv', 'warehouse'); ?></a>
            <a class="wh-scan-hero__link" href="../receiving/receive_stock.php"><?php echo __t('wh_scan_link_receive', 'warehouse'); ?></a>
        </div>
    </div>
    <div class="wh-scan-hero__stats" role="group">
        <article class="wh-scan-stat wh-scan-stat--primary">
            <span class="wh-scan-stat__label"><?php echo __t('wh_scan_stat_session', 'warehouse'); ?></span>
            <strong class="wh-scan-stat__value" id="whScanStatSession">0</strong>
        </article>
        <article class="wh-scan-stat wh-scan-stat--success">
            <span class="wh-scan-stat__label"><?php echo __t('wh_scan_stat_found', 'warehouse'); ?></span>
            <strong class="wh-scan-stat__value" id="whScanStatFound">0</strong>
        </article>
        <article class="wh-scan-stat wh-scan-stat--danger">
            <span class="wh-scan-stat__label"><?php echo __t('wh_scan_stat_not_found', 'warehouse'); ?></span>
            <strong class="wh-scan-stat__value" id="whScanStatNotFound">0</strong>
        </article>
    </div>
</section>

<div class="wh-scan-grid">
    <section class="wh-scan-panel wh-scan-panel--scanner" aria-labelledby="whScanPanelTitle">
        <div class="wh-scan-panel__head">
            <h3 class="wh-scan-panel__title" id="whScanPanelTitle"><?php echo __t('scanner_title', 'inventory'); ?></h3>
            <select id="whScanWarehouse" class="wh-select wh-select--sm" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
                <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
            </select>
        </div>

        <label class="wh-scan-wedge">
            <span class="wh-scan-wedge__label">
                <span class="material-icons-round" aria-hidden="true">qr_code_scanner</span>
                <?php echo __t('wh_scan_input_label', 'warehouse'); ?>
            </span>
            <input type="text" id="whScanWedgeInput" class="wh-scan-wedge__input" placeholder="<?php echo htmlspecialchars(__t('wh_scan_input_placeholder', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" inputmode="numeric" autofocus>
            <p class="wh-scan-wedge__hint"><?php echo __t('scanner_usb_hint', 'inventory'); ?></p>
        </label>

        <div class="wh-scan-tabs" role="tablist">
            <button type="button" class="wh-scan-tab active" id="whScanTabCamera" role="tab" aria-selected="true" data-tab="camera">
                <span class="material-icons-round">videocam</span>
                <?php echo __t('scanner_tab_camera', 'inventory'); ?>
            </button>
            <button type="button" class="wh-scan-tab" id="whScanTabManual" role="tab" aria-selected="false" data-tab="manual">
                <span class="material-icons-round">keyboard</span>
                <?php echo __t('scanner_tab_manual', 'inventory'); ?>
            </button>
        </div>

        <div class="wh-scan-tabpanel" id="whScanPanelCamera" role="tabpanel">
            <div class="wh-scan-viewport">
                <div id="whScanCameraReader" class="wh-scan-reader"></div>
                <div class="wh-scan-frame" aria-hidden="true">
                    <div class="wh-scan-target">
                        <span class="wh-scan-corner wh-scan-corner--tl"></span>
                        <span class="wh-scan-corner wh-scan-corner--tr"></span>
                        <span class="wh-scan-corner wh-scan-corner--bl"></span>
                        <span class="wh-scan-corner wh-scan-corner--br"></span>
                        <div class="wh-scan-scanline"></div>
                    </div>
                </div>
                <div class="wh-scan-flash" id="whScanFlash" hidden></div>
            </div>
            <div class="wh-scan-controls">
                <label class="wh-scan-control">
                    <span><?php echo __t('scanner_select_camera', 'inventory'); ?></span>
                    <select id="whScanCameraSelect" class="wh-select">
                        <option value=""><?php echo __t('scanner_select_camera', 'inventory'); ?></option>
                    </select>
                </label>
                <div class="wh-scan-control-actions">
                    <button type="button" class="wh-btn wh-btn--primary" id="whScanCameraStart">
                        <span class="material-icons-round">play_arrow</span>
                        <?php echo __t('scanner_camera_start', 'inventory'); ?>
                    </button>
                    <button type="button" class="wh-btn wh-btn--ghost" id="whScanCameraStop" hidden>
                        <span class="material-icons-round">stop</span>
                        <?php echo __t('scanner_camera_stop', 'inventory'); ?>
                    </button>
                </div>
            </div>
        </div>

        <div class="wh-scan-tabpanel" id="whScanPanelManual" role="tabpanel" hidden>
            <form id="whScanManualForm" class="wh-scan-manual">
                <label class="wh-scan-manual__field">
                    <span><?php echo __t('barcode', 'inventory'); ?></span>
                    <div class="wh-scan-manual__row">
                        <input type="text" id="whScanManualInput" placeholder="<?php echo htmlspecialchars(__t('scanner_manual_placeholder', 'inventory'), ENT_QUOTES, 'UTF-8'); ?>" autocomplete="off" inputmode="numeric">
                        <button type="submit" class="wh-btn wh-btn--primary">
                            <span class="material-icons-round">search</span>
                            <?php echo __t('scanner_manual_submit', 'inventory'); ?>
                        </button>
                    </div>
                </label>
            </form>
        </div>

        <div class="wh-scan-status-row">
            <span class="wh-scan-status wh-scan-status--ready" id="whScanStatusBadge">
                <span class="wh-scan-status__dot"></span>
                <span id="whScanStatusText"><?php echo __t('scanner_status_ready', 'inventory'); ?></span>
            </span>
            <div class="wh-scan-last" id="whScanLastWrap" hidden>
                <span class="wh-scan-last__label"><?php echo __t('scanner_last_scan', 'inventory'); ?></span>
                <code id="whScanLastCode"></code>
                <span class="wh-scan-last__result" id="whScanLastResult"></span>
            </div>
        </div>
    </section>

    <section class="wh-scan-panel wh-scan-panel--result" aria-labelledby="whScanResultTitle">
        <h3 class="wh-scan-panel__title" id="whScanResultTitle"><?php echo __t('wh_scan_result_title', 'warehouse'); ?></h3>
        <div id="whScanResult" class="wh-scan-result">
            <div class="wh-scan-result__empty" id="whScanResultEmpty">
                <span class="material-icons-round">inventory_2</span>
                <p><?php echo __t('wh_scan_no_result', 'warehouse'); ?></p>
            </div>
        </div>
    </section>
</div>

<section class="wh-scan-panel wh-scan-panel--history" aria-labelledby="whScanHistoryTitle">
    <div class="wh-scan-panel__head">
        <h3 class="wh-scan-panel__title" id="whScanHistoryTitle"><?php echo __t('wh_scan_history_title', 'warehouse'); ?></h3>
        <div class="wh-scan-panel__actions">
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whScanExportBtn">
                <span class="material-icons-round">download</span><?php echo __t('export_csv', 'warehouse'); ?>
            </button>
            <button type="button" class="wh-btn wh-btn--ghost wh-btn--sm" id="whScanClearBtn">
                <span class="material-icons-round">delete_sweep</span><?php echo __t('wh_scan_clear_history', 'warehouse'); ?>
            </button>
        </div>
    </div>
    <div class="wh-scan-table-wrap">
        <table class="modern-table wh-table wh-scan-list-table">
            <thead>
                <tr>
                    <th class="wh-scan-col--time"><?php echo __t('wh_scan_col_time', 'warehouse'); ?></th>
                    <th class="wh-scan-col--code"><?php echo __t('wh_scan_col_code', 'warehouse'); ?></th>
                    <th class="wh-scan-col--product"><?php echo __t('wh_scan_col_product', 'warehouse'); ?></th>
                    <th class="wh-scan-col--status"><?php echo __t('wh_scan_col_status', 'warehouse'); ?></th>
                </tr>
            </thead>
            <tbody id="whScanHistoryBody">
                <tr><td colspan="4" class="wh-scan-empty-cell"><?php echo __t('wh_scan_history_empty', 'warehouse'); ?></td></tr>
            </tbody>
        </table>
    </div>
</section>

<p class="wh-page-meta" id="whLastUpdated" aria-live="polite"></p>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

<?php
/**
 * Generic warehouse module page shell — loads data via warehouse-page.js + WMS API
 * Set $whModule, $whEndpoint, $whPageId, $whTitleKey before including.
 */
if (!isset($whModule)) {
    $whModule = 'inventory';
}
if (!isset($whEndpoint)) {
    $whEndpoint = 'inventory';
}
if (!isset($whPageId)) {
    $whPageId = 'module';
}
if (!isset($whTitleKey)) {
    $whTitleKey = 'wh_nav_' . $whPageId;
}

require_once __DIR__ . '/bootstrap.php';
WarehousePortalAuth::assertModule($whModule);

$activeWhPage = $whPageId;
$pageTitle = __t($whTitleKey, 'warehouse');
$loadChart = $loadChart ?? false;
$extraScripts = array_merge(['warehouse-common.js', 'warehouse-search.js', 'warehouse-page.js'], $extraScripts ?? []);
$pageI18n = array_merge(wh_i18n([
    'wh_module_loading', 'wh_export_csv', 'wh_print', 'wh_filter_all', 'wh_no_results',
]), $pageI18n ?? []);

require __DIR__ . '/layout-start.php';
?>

<div class="wh-module-hero">
    <p class="wh-module-hero__sub"><?php echo __t('wh_portal_subtitle', 'warehouse'); ?></p>
    <div class="wh-module-toolbar">
        <select id="whPageWarehouse" class="wh-select" aria-label="<?php echo htmlspecialchars(__t('wh_select_warehouse', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
            <option value=""><?php echo __t('wh_all_warehouses', 'warehouse'); ?></option>
        </select>
        <input type="search" id="whPageSearch" class="wh-input" placeholder="<?php echo htmlspecialchars(__t('search', 'warehouse'), ENT_QUOTES, 'UTF-8'); ?>">
        <button type="button" class="acc-btn acc-btn--ghost" id="whPageExport"><span class="material-icons-round">download</span><span class="wh-btn-label"><?php echo __t('export_csv', 'warehouse'); ?></span></button>
        <button type="button" class="acc-btn acc-btn--ghost" id="whPagePrint"><span class="material-icons-round">print</span><span class="wh-btn-label"><?php echo __t('print', 'warehouse'); ?></span></button>
        <button type="button" class="acc-btn" id="whPageRefresh"><span class="material-icons-round">refresh</span><span class="wh-btn-label"><?php echo __t('refresh', 'warehouse'); ?></span></button>
    </div>
</div>

<div class="wh-module-panel" id="whModuleRoot"
     data-wh-module="<?php echo htmlspecialchars($whModule, ENT_QUOTES, 'UTF-8'); ?>"
     data-wh-endpoint="<?php echo htmlspecialchars($whEndpoint, ENT_QUOTES, 'UTF-8'); ?>"
     data-wh-page="<?php echo htmlspecialchars($whPageId, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="acc-loading"><?php echo __t('loading', 'warehouse'); ?></div>
</div>

<?php require __DIR__ . '/layout-end.php'; ?>

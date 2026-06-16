<?php
require __DIR__ . '/includes/bootstrap.php';
if (!$canManageWms) { header('Location: warehouses.php'); exit; }
$activeWmsPage = 'warehouses';
$pageTitle = __t('wms_new_warehouse', 'wms');
$extraScripts = ['wms-common.js', 'wms-warehouse-form.js'];
$pageI18n = wms_i18n([
    'wms_create_wh_subtitle', 'wms_wh_code', 'wms_wh_name', 'wms_wh_type', 'wms_wh_address',
    'wms_wh_city', 'wms_wh_capacity', 'save', 'cancel', 'col_status',
    'wms_status_active', 'wms_status_inactive',
    'wms_wh_type_central', 'wms_wh_type_regional', 'wms_wh_type_store',
    'wms_wh_type_distribution', 'wms_wh_type_cold_storage', 'wms_wh_type_temporary',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<p class="cr-intro"><?php echo __t('wms_create_wh_subtitle', 'wms'); ?></p>
<div class="cr-panel" id="wmsWhForm" data-mode="create"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>
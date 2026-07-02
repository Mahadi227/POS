<?php
require __DIR__ . '/../includes/bootstrap.php';
WarehousePortalAuth::assertModule('management');

if (!$canManageWms) {
    header('Location: warehouses.php');
    exit;
}

$warehouseId = (int) ($_GET['id'] ?? 0);
if ($warehouseId <= 0) {
    header('Location: warehouses.php');
    exit;
}

$useWmsModules = true;
$activeWhPage = 'warehouses';
$pageTitle = __t('wh_wh_edit', 'warehouse');
$whBreadcrumb = '<a href="warehouses.php">' . htmlspecialchars(__t('wms_nav_warehouses', 'wms'), ENT_QUOTES, 'UTF-8') . '</a>'
    . ' <span aria-hidden="true">›</span> '
    . htmlspecialchars(__t('wh_wh_edit', 'warehouse'), ENT_QUOTES, 'UTF-8');
$extraScripts = ['warehouse-common.js', 'warehouse-search.js', 'warehouse-warehouse-form.js'];
$pageI18n = array_merge(
    wh_i18n([
        'wh_wh_form_create_subtitle', 'wh_wh_form_edit_subtitle', 'wh_wh_form_section_identity',
        'wh_wh_form_section_branch', 'wh_wh_form_section_location', 'wh_wh_form_section_operations',
        'wh_wh_form_section_notes', 'wh_wh_form_country', 'wh_wh_form_phone', 'wh_wh_form_email',
        'wh_wh_form_manager_none', 'wh_wh_form_code_hint', 'wh_wh_form_generate_code',
        'wh_wh_form_back', 'wh_wh_form_saving', 'wh_wh_form_saved', 'wh_wh_link_locations',
        'loading', 'load_error', 'save', 'cancel', 'error', 'col_status', 'dash_all_stores',
    ]),
    wms_i18n([
        'wms_create_wh_subtitle', 'wms_edit_wh_subtitle', 'wms_wh_code', 'wms_wh_name', 'wms_wh_type',
        'wms_wh_manager', 'wms_wh_address', 'wms_wh_city', 'wms_wh_capacity', 'wms_col_store',
        'wms_select_store', 'wms_status_active', 'wms_status_inactive', 'wms_wh_type_central',
        'wms_wh_type_regional', 'wms_wh_type_store', 'wms_wh_type_distribution',
        'wms_wh_type_cold_storage', 'wms_wh_type_temporary',
    ])
);
require __DIR__ . '/../includes/layout-start.php';
?>

<section class="wh-wh-form-hero" aria-labelledby="whWhFormTitle">
    <div class="wh-wh-form-hero__intro">
        <h2 class="wh-wh-form-hero__title" id="whWhFormTitle"><?php echo __t('wh_wh_edit', 'warehouse'); ?></h2>
        <p class="wh-wh-form-hero__sub"><?php echo __t('wh_wh_form_edit_subtitle', 'warehouse'); ?></p>
        <div class="wh-wh-form-hero__links">
            <a class="wh-wh-hero__link" href="warehouses.php"><?php echo __t('wh_wh_form_back', 'warehouse'); ?></a>
            <a class="wh-wh-hero__link" href="locations.php"><?php echo __t('wh_wh_link_locations', 'warehouse'); ?></a>
        </div>
    </div>
</section>

<div id="whWhFormRoot" class="wh-wh-form-root" data-mode="edit" data-warehouse-id="<?php echo $warehouseId; ?>">
    <div class="wh-loading" id="whWhFormLoading"><?php echo __t('loading', 'warehouse'); ?></div>
</div>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

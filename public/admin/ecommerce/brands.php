<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!$canManageEcom) {
    header('Location: dashboard.php');
    exit;
}

$activeEcomPage = 'brands';
$pageTitle = __t('ecom_brands_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-brands.js'];
$pageI18n = ecom_i18n([
    'ecom_brands_subtitle', 'ecom_add_brand', 'ecom_brand_name', 'ecom_brand_slug', 'ecom_brand_logo',
    'ecom_no_brands', 'ecom_saved', 'delete_confirm',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head ecom-page-head--split">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_brands_subtitle', 'admin'); ?></p>
    <button type="button" class="ecom-btn ecom-btn--primary" id="ecomAddBrandBtn">
        <span class="material-icons-round">add</span>
        <?php echo __t('ecom_add_brand', 'admin'); ?>
    </button>
</section>

<div class="ecom-cards" id="ecomBrandsGrid"><p><?php echo __t('loading', 'admin'); ?></p></div>

<dialog class="ecom-modal" id="ecomBrandModal">
    <form id="ecomBrandForm" class="ecom-form">
        <div class="ecom-modal__head">
            <h2 id="ecomBrandModalTitle"><?php echo __t('ecom_add_brand', 'admin'); ?></h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="<?php echo __t('close', 'admin'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="ecom-modal__body">
            <input type="hidden" name="id" id="ecomBrandId">
            <label class="ecom-field">
                <span><?php echo __t('ecom_brand_name', 'admin'); ?></span>
                <input type="text" name="name" id="ecomBrandName" required>
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_brand_slug', 'admin'); ?></span>
                <input type="text" name="slug" id="ecomBrandSlug" placeholder="auto">
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_brand_logo', 'admin'); ?></span>
                <input type="url" name="logo_url" id="ecomBrandLogo">
            </label>
        </div>
        <div class="ecom-modal__foot">
            <button type="button" class="ecom-btn ecom-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
            <button type="submit" class="ecom-btn ecom-btn--primary"><?php echo __t('save', 'admin'); ?></button>
        </div>
    </form>
</dialog>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

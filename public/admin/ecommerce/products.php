<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeEcomPage = 'products';
$pageTitle = __t('ecom_products_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-products.js'];
$pageI18n = ecom_i18n([
    'ecom_products_subtitle', 'ecom_filter_all', 'ecom_filter_online', 'ecom_filter_offline',
    'ecom_search_products', 'ecom_col_product', 'ecom_col_sku', 'ecom_col_price', 'ecom_col_stock',
    'ecom_col_online', 'ecom_col_slug', 'ecom_toggle_online', 'ecom_no_products', 'ecom_saved',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_products_subtitle', 'admin'); ?></p>
    <div class="ecom-toolbar">
        <div class="ecom-toolbar__filters" role="group">
            <button type="button" class="ecom-chip is-active" data-online=""><?php echo __t('ecom_filter_all', 'admin'); ?></button>
            <button type="button" class="ecom-chip" data-online="1"><?php echo __t('ecom_filter_online', 'admin'); ?></button>
            <button type="button" class="ecom-chip" data-online="0"><?php echo __t('ecom_filter_offline', 'admin'); ?></button>
        </div>
        <label class="ecom-search">
            <span class="material-icons-round">search</span>
            <input type="search" id="ecomProductSearch" placeholder="<?php echo htmlspecialchars(__t('ecom_search_products', 'admin'), ENT_QUOTES, 'UTF-8'); ?>">
        </label>
    </div>
</section>

<div class="ecom-table-wrap">
    <table class="ecom-table" id="ecomProductsTable">
        <thead>
            <tr>
                <th><?php echo __t('ecom_col_product', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_sku', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_price', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_stock', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_online', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_slug', 'admin'); ?></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="6"><?php echo __t('loading', 'admin'); ?></td></tr></tbody>
    </table>
</div>
<p class="ecom-pagination" id="ecomProductsPager" hidden></p>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

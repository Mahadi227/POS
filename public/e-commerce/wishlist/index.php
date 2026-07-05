<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_wishlist_title', 'ecommerce');
$activePage = 'wishlist';
$items = $wishlist->items($ecomAccountId ?: null);
require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_wishlist_title', 'ecommerce'); ?></h1>
</section>
<div class="ecom-product-grid">
    <?php foreach ($items as $product): ?>
        <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
    <?php endforeach; ?>
    <?php if ($items === []): ?>
    <p class="ecom-empty"><?php echo __t('ecom_wishlist_empty', 'ecommerce'); ?></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

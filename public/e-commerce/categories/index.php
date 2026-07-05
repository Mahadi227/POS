<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_categories_title', 'ecommerce');
$activePage = 'categories';
$categories = $catalog->listCategories($storeId);
require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_categories_title', 'ecommerce'); ?></h1>
</section>
<div class="ecom-category-grid">
    <?php foreach ($categories as $cat): ?>
    <a class="ecom-category-card" href="<?php echo ecom_href('shop/?category_id=' . (int) $cat['id']); ?>">
        <span class="material-icons-round" aria-hidden="true">category</span>
        <h3><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></h3>
        <p><?php echo __t('ecom_products_count', 'ecommerce', ['count' => (int) ($cat['product_count'] ?? 0)]); ?></p>
    </a>
    <?php endforeach; ?>
    <?php if ($categories === []): ?>
    <p class="ecom-empty"><?php echo __t('ecom_no_categories', 'ecommerce'); ?></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

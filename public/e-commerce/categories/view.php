<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$categoryId = (int) ($_GET['id'] ?? 0);
$category = $categoryId > 0 ? $catalog->getCategory($storeId, $categoryId) : null;
if (!$category) {
    header('Location: ' . ecom_href('categories/'));
    exit;
}

$pageTitle = $category['name'];
$activePage = 'categories';
$products = $catalog->listProducts($tenantId, $storeId, ['category_id' => $categoryId], 48, 0);

require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <a href="<?php echo ecom_href('categories/'); ?>">← <?php echo __t('ecom_back_categories', 'ecommerce'); ?></a>
</section>
<div class="ecom-grid">
    <?php foreach ($products as $product): ?>
    <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
    <?php endforeach; ?>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

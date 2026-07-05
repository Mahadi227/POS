<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_shop_title', 'ecommerce');
$activePage = 'shop';
$extraScripts = ['ecommerce/shop.js'];
$filters = [];
if (!empty($_GET['category_id'])) {
    $filters['category_id'] = (int) $_GET['category_id'];
}
if (!empty($_GET['brand_id'])) {
    $filters['brand_id'] = (int) $_GET['brand_id'];
}
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 24;
$products = $catalog->listProducts($tenantId, $storeId, $filters, $perPage, ($page - 1) * $perPage);
$total = $catalog->countProducts($tenantId, $storeId, $filters);
$pages = max(1, (int) ceil($total / $perPage));
require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_shop_title', 'ecommerce'); ?></h1>
    <p><?php echo __t('ecom_shop_sub', 'ecommerce', ['count' => $total]); ?></p>
    <?php if (!empty($ecomHasMultipleStores)): ?>
    <p class="ecom-shop-store-meta">
        <?php echo __t('ecom_shop_store_active', 'ecommerce', ['store' => $ecomStoreName]); ?>
        <?php if ($ecomStoreLocation !== ''): ?>
        <span class="ecom-shop-store-meta__loc">· <?php echo htmlspecialchars($ecomStoreLocation, ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <span class="ecom-shop-store-meta__currency">· <?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?></span>
    </p>
    <?php endif; ?>
</section>

<?php if (!empty($ecomHasMultipleStores)): ?>
<section class="ecom-shop-toolbar" aria-label="<?php echo __t('ecom_shop_store_filter', 'ecommerce'); ?>">
    <div class="ecom-shop-toolbar__head">
        <span class="material-icons-round" aria-hidden="true">store</span>
        <div>
            <strong><?php echo __t('ecom_shop_store_filter', 'ecommerce'); ?></strong>
            <p><?php echo __t('ecom_shop_store_hint', 'ecommerce'); ?></p>
        </div>
    </div>
    <div class="ecom-store-select-wrap">
        <label class="ecom-sr-only" for="ecom-store-select"><?php echo __t('ecom_shop_store_filter', 'ecommerce'); ?></label>
        <span class="material-icons-round ecom-store-select__icon" aria-hidden="true">storefront</span>
        <select id="ecom-store-select" class="ecom-store-select" data-ecom-store-filter>
            <?php foreach ($ecomStores as $branch): ?>
            <?php
                $branchId = (int) ($branch['id'] ?? 0);
                $branchLabel = (string) ($branch['name'] ?? '');
                $branchLoc = trim((string) ($branch['location'] ?? ''));
                $branchCurrency = (string) ($branch['currency'] ?? '');
                $optionLabel = $branchLabel;
                if ($branchLoc !== '') {
                    $optionLabel .= ' · ' . $branchLoc;
                }
                if ($branchCurrency !== '') {
                    $optionLabel .= ' · ' . $branchCurrency;
                }
            ?>
            <option value="<?php echo $branchId; ?>"<?php echo $branchId === $storeId ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($optionLabel, ENT_QUOTES, 'UTF-8'); ?>
            </option>
            <?php endforeach; ?>
        </select>
        <span class="material-icons-round ecom-store-select__chevron" aria-hidden="true">expand_more</span>
    </div>
</section>
<?php endif; ?>

<div class="ecom-product-grid">
    <?php foreach ($products as $product): ?>
        <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
    <?php endforeach; ?>
    <?php if ($products === []): ?>
    <p class="ecom-empty"><?php echo __t('ecom_no_products', 'ecommerce'); ?></p>
    <?php endif; ?>
</div>
<?php if ($pages > 1): ?>
<nav class="ecom-pagination" aria-label="Pagination">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?<?php echo htmlspecialchars(http_build_query(ecom_query_params(['page' => $i])), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo $i === $page ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

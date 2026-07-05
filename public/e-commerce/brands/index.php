<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __t('ecom_brands_title', 'ecommerce');
$activePage = 'brands';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-brands');
$extraScripts = ['ecommerce/brands.js'];
$brands = $catalog->listBrands($tenantId, $storeId);
$brandCount = count($brands);
$productTotal = array_sum(array_map(static fn(array $b): int => (int) ($b['product_count'] ?? 0), $brands));

require __DIR__ . '/../includes/layout-start.php';
?>

<nav class="ecom-breadcrumb" aria-label="<?php echo __t('ecom_breadcrumb', 'ecommerce'); ?>">
    <a href="<?php echo ecom_href('home/'); ?>"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="ecom-breadcrumb__current" aria-current="page"><?php echo __t('ecom_brands_title', 'ecommerce'); ?></span>
</nav>

<section class="ecom-page-head ecom-page-head--brands">
    <div>
        <h1><?php echo __t('ecom_brands_title', 'ecommerce'); ?></h1>
        <p><?php echo __t('ecom_brands_sub', 'ecommerce', ['brands' => $brandCount, 'products' => $productTotal]); ?></p>
        <?php if (!empty($ecomHasMultipleStores) && $ecomStoreName !== ''): ?>
        <p class="ecom-brands-store-meta">
            <span class="material-icons-round" aria-hidden="true">store</span>
            <?php echo __t('ecom_shop_store_active', 'ecommerce', ['store' => $ecomStoreName]); ?>
        </p>
        <?php endif; ?>
    </div>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--ghost ecom-page-head__action">
        <?php echo __t('ecom_view_all_products', 'ecommerce'); ?>
        <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
    </a>
</section>

<?php if ($brands !== []): ?>
<section class="ecom-brands-toolbar" aria-label="<?php echo __t('ecom_brands_search_label', 'ecommerce'); ?>">
    <label class="ecom-brands-search" for="ecom-brands-search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="ecom-brands-search" class="ecom-brands-search__input" data-ecom-brands-search placeholder="<?php echo __t('ecom_brands_search_placeholder', 'ecommerce'); ?>" autocomplete="off">
        <button type="button" class="ecom-brands-search__clear" data-ecom-brands-clear hidden aria-label="<?php echo __t('ecom_search_clear', 'ecommerce'); ?>">
            <span class="material-icons-round">close</span>
        </button>
    </label>
    <p class="ecom-brands-toolbar__hint" data-ecom-brands-status aria-live="polite"></p>
</section>

<div class="ecom-brand-grid" data-ecom-brand-grid>
    <?php foreach ($brands as $brand): ?>
        <?php include __DIR__ . '/../includes/partials/brand-card.php'; ?>
    <?php endforeach; ?>
</div>
<p class="ecom-empty ecom-brands-empty-filter" data-ecom-brands-no-match hidden><?php echo __t('ecom_brands_no_match', 'ecommerce'); ?></p>
<?php else: ?>
<section class="ecom-brands-empty">
    <span class="material-icons-round ecom-brands-empty__icon" aria-hidden="true">sell</span>
    <h2><?php echo __t('ecom_no_brands', 'ecommerce'); ?></h2>
    <p><?php echo __t('ecom_no_brands_hint', 'ecommerce'); ?></p>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_shop_now', 'ecommerce'); ?></a>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

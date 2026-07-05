<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$brandId = (int) ($_GET['id'] ?? 0);
$slug = trim($_GET['slug'] ?? '');
$brand = $catalog->resolveBrand($tenantId, $storeId, $slug, $brandId);

if (!$brand) {
    http_response_code(404);
    $pageTitle = __t('ecom_brand_not_found', 'ecommerce');
    $activePage = 'brands';
    $bodyClass = trim(($bodyClass ?? '') . ' ecom-page-brands ecom-page-brands--missing');
    require __DIR__ . '/../includes/layout-start.php';
    ?>
    <section class="ecom-brands-empty ecom-brands-empty--404">
        <span class="material-icons-round ecom-brands-empty__icon" aria-hidden="true">sell</span>
        <h1><?php echo __t('ecom_brand_not_found', 'ecommerce'); ?></h1>
        <p><?php echo __t('ecom_brand_not_found_hint', 'ecommerce'); ?></p>
        <div class="ecom-brands-empty__actions">
            <a href="<?php echo ecom_href('brands/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_back_brands', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_back_shop', 'ecommerce'); ?></a>
        </div>
    </section>
    <?php
    require __DIR__ . '/../includes/layout-end.php';
    exit;
}

$pageTitle = (string) $brand['name'];
$activePage = 'brands';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-brands ecom-page-brand-view');
$brandLogo = ecom_brand_logo($brand);
$brandProductCount = (int) ($brand['product_count'] ?? 0);
$products = $catalog->listProducts($tenantId, $storeId, ['brand_id' => (int) $brand['id']], 48, 0);

require __DIR__ . '/../includes/layout-start.php';
?>

<nav class="ecom-breadcrumb" aria-label="<?php echo __t('ecom_breadcrumb', 'ecommerce'); ?>">
    <a href="<?php echo ecom_href('home/'); ?>"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <a href="<?php echo ecom_href('brands/'); ?>"><?php echo __t('ecom_brands_title', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="ecom-breadcrumb__current" aria-current="page"><?php echo htmlspecialchars((string) $brand['name'], ENT_QUOTES, 'UTF-8'); ?></span>
</nav>

<header class="ecom-brand-view">
    <div class="ecom-brand-view__logo-wrap">
        <?php if ($brandLogo): ?>
        <img src="<?php echo htmlspecialchars($brandLogo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $brand['name'], ENT_QUOTES, 'UTF-8'); ?>" class="ecom-brand-view__logo">
        <?php else: ?>
        <span class="ecom-brand-view__logo-placeholder material-icons-round" aria-hidden="true">sell</span>
        <?php endif; ?>
    </div>
    <div class="ecom-brand-view__info">
        <p class="ecom-brand-view__eyebrow"><?php echo __t('ecom_brand_label', 'ecommerce'); ?></p>
        <h1><?php echo htmlspecialchars((string) $brand['name'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <p class="ecom-brand-view__meta"><?php echo __t('ecom_brand_products_meta', 'ecommerce', ['count' => $brandProductCount]); ?></p>
        <?php if (!empty($ecomHasMultipleStores) && $ecomStoreName !== ''): ?>
        <p class="ecom-brand-view__branch">
            <span class="material-icons-round" aria-hidden="true">store</span>
            <?php echo __t('ecom_pdp_branch', 'ecommerce', ['store' => $ecomStoreName]); ?>
        </p>
        <?php endif; ?>
        <div class="ecom-brand-view__actions">
            <a href="<?php echo ecom_href('shop/?brand_id=' . (int) $brand['id']); ?>" class="ecom-btn ecom-btn--accent">
                <?php echo __t('ecom_shop_brand', 'ecommerce'); ?>
                <span class="material-icons-round" aria-hidden="true">storefront</span>
            </a>
            <a href="<?php echo ecom_href('brands/'); ?>" class="ecom-btn ecom-btn--ghost">
                <span class="material-icons-round" aria-hidden="true">arrow_back</span>
                <?php echo __t('ecom_back_brands', 'ecommerce'); ?>
            </a>
        </div>
    </div>
</header>

<section class="ecom-section ecom-section--brand-products">
    <div class="ecom-section__head">
        <h2><?php echo __t('ecom_brand_products_title', 'ecommerce'); ?></h2>
    </div>
    <div class="ecom-product-grid">
        <?php foreach ($products as $product): ?>
            <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
        <?php if ($products === []): ?>
        <p class="ecom-empty"><?php echo __t('ecom_no_products', 'ecommerce'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

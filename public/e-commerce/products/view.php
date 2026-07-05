<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$id = (int) ($_GET['id'] ?? 0);
$product = $catalog->resolveProduct($storeId, $slug, $id);

if (!$product) {
    http_response_code(404);
    $pageTitle = __t('ecom_product_not_found', 'ecommerce');
    $activePage = 'products';
    $bodyClass = trim(($bodyClass ?? '') . ' ecom-page-product ecom-page-product--missing');
    require __DIR__ . '/../includes/layout-start.php';
    ?>
    <section class="ecom-pdp-missing">
        <span class="material-icons-round ecom-pdp-missing__icon" aria-hidden="true">inventory_2</span>
        <h1><?php echo __t('ecom_product_not_found', 'ecommerce'); ?></h1>
        <p><?php echo __t('ecom_product_not_found_hint', 'ecommerce'); ?></p>
        <div class="ecom-pdp-missing__actions">
            <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_back_shop', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('home/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
        </div>
    </section>
    <?php
    require __DIR__ . '/../includes/layout-end.php';
    exit;
}

$pageTitle = (string) $product['name'];
$activePage = 'products';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-product');
$extraScripts = ['ecommerce/product-view.js'];
$pageMetaDescription = trim((string) ($product['description'] ?? ''));
if ($pageMetaDescription !== '') {
    $pageMetaDescription = mb_substr(preg_replace('/\s+/', ' ', strip_tags($pageMetaDescription)) ?? '', 0, 160);
}

$stockQty = max(0, (int) ($product['stock_quantity'] ?? 0));
$minStock = max(0, (int) ($product['min_stock_level'] ?? 5));
$categoryId = isset($product['category_id']) ? (int) $product['category_id'] : 0;
$relatedProducts = $catalog->listRelatedProducts($tenantId, $storeId, (int) $product['id'], $categoryId > 0 ? $categoryId : null, 4);
$detailImg = ecom_product_image($product);
$isOutOfStock = $stockQty <= 0;
$isLowStock = !$isOutOfStock && $stockQty <= $minStock;

require __DIR__ . '/../includes/layout-start.php';
?>

<nav class="ecom-breadcrumb" aria-label="<?php echo __t('ecom_breadcrumb', 'ecommerce'); ?>">
    <a href="<?php echo ecom_href('home/'); ?>"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <a href="<?php echo ecom_href('shop/'); ?>"><?php echo __t('ecom_nav_shop', 'ecommerce'); ?></a>
    <?php if (!empty($product['category_name']) && $categoryId > 0): ?>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <a href="<?php echo ecom_href('shop/?category_id=' . $categoryId); ?>"><?php echo htmlspecialchars((string) $product['category_name'], ENT_QUOTES, 'UTF-8'); ?></a>
    <?php endif; ?>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="ecom-breadcrumb__current" aria-current="page"><?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?></span>
</nav>

<article class="ecom-pdp" itemscope itemtype="https://schema.org/Product">
    <div class="ecom-pdp__gallery">
        <div class="ecom-pdp__media">
            <?php if ($detailImg): ?>
            <img src="<?php echo htmlspecialchars($detailImg, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?>" itemprop="image" id="ecom-pdp-image">
            <?php else: ?>
            <span class="material-icons-round ecom-pdp__placeholder" aria-hidden="true">inventory_2</span>
            <?php endif; ?>
        </div>
        <?php if ($isOutOfStock): ?>
        <span class="ecom-pdp__badge ecom-pdp__badge--out"><?php echo __t('ecom_out_of_stock', 'ecommerce'); ?></span>
        <?php elseif ($isLowStock): ?>
        <span class="ecom-pdp__badge ecom-pdp__badge--low"><?php echo __t('ecom_low_stock', 'ecommerce'); ?></span>
        <?php endif; ?>
    </div>

    <div class="ecom-pdp__info">
        <div class="ecom-pdp__meta">
            <?php if (!empty($product['category_name']) && $categoryId > 0): ?>
            <a class="ecom-pdp__cat" href="<?php echo ecom_href('shop/?category_id=' . $categoryId); ?>"><?php echo htmlspecialchars((string) $product['category_name'], ENT_QUOTES, 'UTF-8'); ?></a>
            <?php endif; ?>
            <?php if (!empty($product['brand_name'])): ?>
            <span class="ecom-pdp__brand">
                <?php echo __t('ecom_brand_label', 'ecommerce'); ?>:
                <?php if (!empty($product['brand_slug'])): ?>
                <a href="<?php echo ecom_href('shop/?brand_id=' . (int) ($product['brand_id'] ?? 0)); ?>"><?php echo htmlspecialchars((string) $product['brand_name'], ENT_QUOTES, 'UTF-8'); ?></a>
                <?php else: ?>
                <?php echo htmlspecialchars((string) $product['brand_name'], ENT_QUOTES, 'UTF-8'); ?>
                <?php endif; ?>
            </span>
            <?php endif; ?>
        </div>

        <h1 class="ecom-pdp__title" itemprop="name"><?php echo htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8'); ?></h1>

        <?php if (!empty($ecomHasMultipleStores) && $ecomStoreName !== ''): ?>
        <p class="ecom-pdp__branch">
            <span class="material-icons-round" aria-hidden="true">store</span>
            <?php echo __t('ecom_pdp_branch', 'ecommerce', ['store' => $ecomStoreName]); ?>
        </p>
        <?php endif; ?>

        <p class="ecom-pdp__price" itemprop="offers" itemscope itemtype="https://schema.org/Offer">
            <meta itemprop="priceCurrency" content="<?php echo htmlspecialchars($currency, ENT_QUOTES, 'UTF-8'); ?>">
            <span itemprop="price" content="<?php echo htmlspecialchars((string) ((float) $product['price']), ENT_QUOTES, 'UTF-8'); ?>"><?php echo ecom_money((float) $product['price']); ?></span>
            <link itemprop="availability" href="https://schema.org/<?php echo $isOutOfStock ? 'OutOfStock' : 'InStock'; ?>">
        </p>

        <div class="ecom-pdp__stock<?php echo $isOutOfStock ? ' is-out' : ($isLowStock ? ' is-low' : ' is-in'); ?>">
            <span class="material-icons-round" aria-hidden="true"><?php echo $isOutOfStock ? 'remove_shopping_cart' : 'inventory'; ?></span>
            <?php if ($isOutOfStock): ?>
            <?php echo __t('ecom_out_of_stock', 'ecommerce'); ?>
            <?php elseif ($isLowStock): ?>
            <?php echo __t('ecom_low_stock_count', 'ecommerce', ['qty' => $stockQty]); ?>
            <?php else: ?>
            <?php echo __t('ecom_in_stock', 'ecommerce', ['qty' => $stockQty]); ?>
            <?php endif; ?>
        </div>

        <div class="ecom-pdp__buy" data-ecom-product-actions>
            <label class="ecom-pdp__qty" for="ecom-pdp-qty">
                <span><?php echo __t('ecom_qty_label', 'ecommerce'); ?></span>
                <div class="ecom-qty-stepper">
                    <button type="button" class="ecom-qty-stepper__btn" data-ecom-qty-minus aria-label="<?php echo __t('ecom_decrease_qty', 'ecommerce'); ?>">
                        <span class="material-icons-round">remove</span>
                    </button>
                    <input type="number" id="ecom-pdp-qty" class="ecom-qty-stepper__input" data-ecom-qty value="1" min="1" max="<?php echo max(1, $stockQty); ?>" <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                    <button type="button" class="ecom-qty-stepper__btn" data-ecom-qty-plus aria-label="<?php echo __t('ecom_increase_qty', 'ecommerce'); ?>" <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                        <span class="material-icons-round">add</span>
                    </button>
                </div>
            </label>
            <div class="ecom-pdp__actions">
                <button type="button" class="ecom-btn ecom-btn--accent ecom-btn--block" data-ecom-add="<?php echo (int) $product['id']; ?>" data-ecom-use-qty <?php echo $isOutOfStock ? 'disabled' : ''; ?>>
                    <span class="material-icons-round">shopping_cart</span>
                    <?php echo __t('ecom_add_cart', 'ecommerce'); ?>
                </button>
                <button type="button" class="ecom-btn ecom-btn--ghost ecom-pdp__wishlist" data-ecom-wishlist="<?php echo (int) $product['id']; ?>" title="<?php echo __t('ecom_add_wishlist', 'ecommerce'); ?>">
                    <span class="material-icons-round">favorite_border</span>
                </button>
            </div>
        </div>

        <ul class="ecom-pdp__trust">
            <li><span class="material-icons-round">verified_user</span><?php echo __t('ecom_trust_secure', 'ecommerce'); ?></li>
            <li><span class="material-icons-round">sync</span><?php echo __t('ecom_trust_stock', 'ecommerce'); ?></li>
            <li><span class="material-icons-round">support_agent</span><?php echo __t('ecom_trust_support', 'ecommerce'); ?></li>
        </ul>

        <dl class="ecom-pdp__specs">
            <?php if (!empty($product['sku'])): ?>
            <div class="ecom-pdp__spec">
                <dt><?php echo __t('ecom_sku_label', 'ecommerce'); ?></dt>
                <dd itemprop="sku"><?php echo htmlspecialchars((string) $product['sku'], ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <?php endif; ?>
            <?php if (!empty($product['barcode'])): ?>
            <div class="ecom-pdp__spec">
                <dt><?php echo __t('ecom_barcode', 'ecommerce'); ?></dt>
                <dd><?php echo htmlspecialchars((string) $product['barcode'], ENT_QUOTES, 'UTF-8'); ?></dd>
            </div>
            <?php endif; ?>
        </dl>

        <?php if (!empty($product['description'])): ?>
        <section class="ecom-pdp__desc">
            <h2><?php echo __t('ecom_product_details', 'ecommerce'); ?></h2>
            <div class="ecom-pdp__desc-body" itemprop="description"><?php echo nl2br(htmlspecialchars((string) $product['description'], ENT_QUOTES, 'UTF-8')); ?></div>
        </section>
        <?php endif; ?>
    </div>
</article>

<?php if ($relatedProducts !== []): ?>
<section class="ecom-section ecom-section--related">
    <div class="ecom-section__head">
        <h2><?php echo __t('ecom_related_products', 'ecommerce'); ?></h2>
        <?php if ($categoryId > 0): ?>
        <a class="ecom-section__link" href="<?php echo ecom_href('shop/?category_id=' . $categoryId); ?>">
            <?php echo __t('ecom_view_all', 'ecommerce'); ?>
            <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
        </a>
        <?php endif; ?>
    </div>
    <div class="ecom-product-grid">
        <?php foreach ($relatedProducts as $product): ?>
            <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

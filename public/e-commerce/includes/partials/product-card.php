<?php
/** @var array<string, mixed> $product */
$slug = ecom_product_slug($product);
$img = ecom_product_image($product);
$name = htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8');
?>
<article class="ecom-product-card">
    <a href="<?php echo ecom_href('products/view.php?slug=' . urlencode($slug)); ?>" class="ecom-product-card__media">
        <?php if ($img): ?>
        <img class="ecom-product-card__img" src="<?php echo htmlspecialchars($img, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $name; ?>" loading="lazy" decoding="async">
        <?php else: ?>
        <span class="ecom-product-card__placeholder" aria-hidden="true">
            <span class="material-icons-round">inventory_2</span>
        </span>
        <?php endif; ?>
    </a>
    <div class="ecom-product-card__body">
        <?php if (!empty($product['category_name'])): ?>
        <span class="ecom-product-card__cat"><?php echo htmlspecialchars($product['category_name'], ENT_QUOTES, 'UTF-8'); ?></span>
        <?php endif; ?>
        <h3><a href="<?php echo ecom_href('products/view.php?slug=' . urlencode($slug)); ?>"><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></a></h3>
        <p class="ecom-product-card__price"><?php echo ecom_money((float) $product['price']); ?></p>
        <div class="ecom-product-card__actions">
            <button type="button" class="ecom-btn ecom-btn--primary ecom-btn--sm" data-ecom-add="<?php echo (int) $product['id']; ?>"><?php echo __t('ecom_add_cart', 'ecommerce'); ?></button>
            <button type="button" class="ecom-btn ecom-btn--ghost ecom-btn--sm" data-ecom-wishlist="<?php echo (int) $product['id']; ?>" title="<?php echo __t('ecom_add_wishlist', 'ecommerce'); ?>">
                <span class="material-icons-round">favorite_border</span>
            </button>
        </div>
    </div>
</article>

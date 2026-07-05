<?php

require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __t('ecom_home_title', 'ecommerce');

$activePage = 'home';

$bodyClass = 'ecom-page-home';

$featured = $catalog->listProducts($tenantId, $storeId, [], 8, 0);

$categories = array_slice($catalog->listCategories($storeId), 0, 6);

$storeLabel = $ecomBrandName ?? ($tenant['name'] ?? 'Store');

require __DIR__ . '/../includes/layout-start.php';

?>

<section class="ecom-hero ecom-hero--home">

    <div class="ecom-hero__grid">

        <div class="ecom-hero__content">

            <p class="ecom-hero__eyebrow">

                <span class="material-icons-round" aria-hidden="true">storefront</span>

                <?php echo __t('ecom_hero_eyebrow', 'ecommerce'); ?>

            </p>

            <h1><?php echo __t('ecom_hero_title', 'ecommerce', ['store' => $storeLabel]); ?></h1>

            <p class="ecom-hero__sub"><?php echo __t('ecom_hero_sub', 'ecommerce'); ?></p>

            <div class="ecom-hero__cta">

                <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_shop_now', 'ecommerce'); ?></a>

                <a href="<?php echo ecom_href('categories/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_browse_categories', 'ecommerce'); ?></a>

            </div>

        </div>

        <?php if ($featured !== []): ?>

        <div class="ecom-hero__preview" aria-hidden="true">

            <div class="ecom-hero__preview-grid">

                <?php foreach (array_slice($featured, 0, 4) as $preview): ?>

                <?php $previewImg = ecom_product_image($preview); ?>

                <div class="ecom-hero__preview-item">

                    <?php if ($previewImg): ?>

                    <img src="<?php echo htmlspecialchars($previewImg, ENT_QUOTES, 'UTF-8'); ?>" alt="">

                    <?php else: ?>

                    <span class="material-icons-round">inventory_2</span>

                    <?php endif; ?>

                </div>

                <?php endforeach; ?>

            </div>

        </div>

        <?php endif; ?>

    </div>

</section>



<?php if ($categories !== []): ?>

<section class="ecom-section ecom-section--categories">

    <div class="ecom-section__head">

        <h2><?php echo __t('ecom_top_categories', 'ecommerce'); ?></h2>

    </div>

    <div class="ecom-chip-grid">

        <?php foreach ($categories as $cat): ?>

        <a class="ecom-chip" href="<?php echo ecom_href('shop/?category_id=' . (int) $cat['id']); ?>">

            <span class="material-icons-round" aria-hidden="true">category</span>

            <?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>

            <span class="ecom-chip__count"><?php echo (int) ($cat['product_count'] ?? 0); ?></span>

        </a>

        <?php endforeach; ?>

    </div>

</section>

<?php endif; ?>



<section class="ecom-section ecom-section--featured">

    <div class="ecom-section__head">

        <div>

            <p class="ecom-section__eyebrow"><?php echo __t('ecom_featured', 'ecommerce'); ?></p>

            <h2><?php echo __t('ecom_featured', 'ecommerce'); ?></h2>

        </div>

        <a class="ecom-section__link" href="<?php echo ecom_href('shop/'); ?>">

            <?php echo __t('ecom_view_all', 'ecommerce'); ?>

            <span class="material-icons-round" aria-hidden="true">arrow_forward</span>

        </a>

    </div>

    <div class="ecom-product-grid ecom-product-grid--home">

        <?php foreach ($featured as $product): ?>

            <?php include __DIR__ . '/../includes/partials/product-card.php'; ?>

        <?php endforeach; ?>

        <?php if ($featured === []): ?>

        <p class="ecom-empty"><?php echo __t('ecom_no_products', 'ecommerce'); ?></p>

        <?php endif; ?>

    </div>

</section>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>


<?php
declare(strict_types=1);

$mockPrices = ['24,99', '18,50', '32,00', '14,99'];
?>
<div class="mkt-storefront-mock" aria-hidden="true">
    <div class="mkt-storefront-mock__chrome">
        <div class="mkt-storefront-mock__dots" aria-hidden="true">
            <span></span><span></span><span></span>
        </div>
        <div class="mkt-storefront-mock__url">
            <span class="material-icons-round">lock</span>
            <?php echo __t('mkt_ecom_mock_url', 'marketing'); ?>
        </div>
    </div>

    <div class="mkt-storefront-mock__body">
        <header class="mkt-storefront-mock__header">
            <div class="mkt-storefront-mock__brand">
                <span class="mkt-storefront-mock__logo"></span>
                <span class="mkt-storefront-mock__brand-name">RetailPOS</span>
            </div>
            <nav class="mkt-storefront-mock__nav">
                <span><span class="material-icons-round">storefront</span><?php echo __t('mkt_ecom_mock_shop', 'marketing'); ?></span>
                <span><span class="material-icons-round">favorite_border</span><?php echo __t('mkt_ecom_mock_wishlist', 'marketing'); ?></span>
                <span class="is-cart"><span class="material-icons-round">shopping_cart</span><?php echo __t('mkt_ecom_mock_cart', 'marketing'); ?><em>2</em></span>
            </nav>
        </header>

        <div class="mkt-storefront-mock__hero">
            <p class="mkt-storefront-mock__hero-tag"><?php echo __t('mkt_ecom_mock_hero_tag', 'marketing'); ?></p>
            <p class="mkt-storefront-mock__hero-title"><?php echo __t('mkt_ecom_mock_hero', 'marketing'); ?></p>
        </div>

        <div class="mkt-storefront-mock__grid">
            <?php for ($p = 0; $p < 4; $p++): ?>
            <article class="mkt-storefront-mock__card">
                <div class="mkt-storefront-mock__img">
                    <?php if ($p === 0): ?>
                    <span class="mkt-storefront-mock__badge"><?php echo __t('mkt_ecom_mock_new', 'marketing'); ?></span>
                    <?php endif; ?>
                </div>
                <div class="mkt-storefront-mock__meta">
                    <span class="mkt-storefront-mock__name"><?php echo __t('mkt_ecom_mock_product', 'marketing'); ?> <?php echo $p + 1; ?></span>
                    <span class="mkt-storefront-mock__price">€<?php echo $mockPrices[$p]; ?></span>
                </div>
                <span class="mkt-storefront-mock__btn">
                    <span class="material-icons-round">add_shopping_cart</span>
                    <?php echo __t('mkt_ecom_mock_add', 'marketing'); ?>
                </span>
            </article>
            <?php endfor; ?>
        </div>
    </div>

    <div class="mkt-storefront-mock__sync">
        <span class="material-icons-round">sync</span>
        <?php echo __t('mkt_ecom_mock_sync', 'marketing'); ?>
    </div>
</div>


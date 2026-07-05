<?php

declare(strict_types=1);



require __DIR__ . '/includes/bootstrap.php';



$activePage = 'home';

$pageTitle = __t('mkt_site_title', 'marketing');

$darkHero = true;

$pageMeta = [

    'description' => __t('mkt_site_description', 'marketing'),

    'schema' => [mkt_org_schema(), mkt_product_schema()],

];

$ecomDemoUrl = $publicRoot . 'e-commerce/?tenant=demo';
$ecomMod = mkt_feature_module_by_key('ecommerce');

require __DIR__ . '/includes/layout-start.php';

?>



<section class="mkt-hero">

    <div class="mkt-hero__grid">

        <div class="mkt-hero__content">

            <div class="mkt-hero__badges">

                <div class="mkt-hero__badge">

                    <span class="material-icons-round" aria-hidden="true">cloud</span>

                    <?php echo __t('mkt_hero_badge', 'marketing'); ?>

                </div>

                <div class="mkt-hero__badge mkt-hero__badge--ecom">

                    <span class="material-icons-round" aria-hidden="true">storefront</span>

                    <?php echo __t('mkt_hero_badge_ecom', 'marketing'); ?>

                </div>

            </div>

            <h1 class="mkt-hero__title"><?php

                $title = __t('mkt_hero_title', 'marketing');

                echo str_replace(['<em>', '</em>'], ['<em>', '</em>'], $title);

            ?></h1>

            <p class="mkt-hero__subtitle"><?php echo __t('mkt_hero_subtitle', 'marketing'); ?></p>

            <div class="mkt-hero__actions">

                <a href="<?php echo $publicRoot; ?>register.php" class="mkt-btn mkt-btn--primary mkt-btn--lg">

                    <span class="material-icons-round" aria-hidden="true">rocket_launch</span>

                    <?php echo __t('mkt_hero_cta_trial', 'marketing'); ?>

                </a>

                <a href="<?php echo htmlspecialchars($ecomDemoUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mkt-btn mkt-btn--ghost mkt-btn--lg">

                    <span class="material-icons-round" aria-hidden="true">storefront</span>

                    <?php echo __t('mkt_hero_cta_store', 'marketing'); ?>

                </a>

                <a href="demo.php" class="mkt-btn mkt-btn--ghost mkt-btn--lg">

                    <span class="material-icons-round" aria-hidden="true">videocam</span>

                    <?php echo __t('mkt_hero_cta_demo', 'marketing'); ?>

                </a>

            </div>

            <div class="mkt-hero__stats">

                <div class="mkt-hero__stat"><strong>POS</strong><span><?php echo __t('mkt_hero_stat_stores', 'marketing'); ?></span></div>

                <div class="mkt-hero__stat"><strong>Web</strong><span><?php echo __t('mkt_hero_stat_omni', 'marketing'); ?></span></div>

                <div class="mkt-hero__stat"><strong>24/7</strong><span><?php echo __t('mkt_hero_stat_offline', 'marketing'); ?></span></div>

            </div>

        </div>

        <div class="mkt-hero__visual">

            <div class="mkt-omni-mock" role="img" aria-label="POS and Online Store unified">

                <div class="mkt-omni-mock__panel mkt-omni-mock__panel--pos">

                    <div class="mkt-omni-mock__label"><span class="material-icons-round">point_of_sale</span> POS</div>

                    <div class="mkt-omni-mock__pos-lines">

                        <span></span><span></span><span class="is-short"></span>

                    </div>

                    <div class="mkt-omni-mock__pos-total"></div>

                </div>

                <div class="mkt-omni-mock__sync" aria-hidden="true">

                    <span class="material-icons-round">sync</span>

                    <small><?php echo __t('mkt_ecom_mock_sync', 'marketing'); ?></small>

                </div>

                <div class="mkt-omni-mock__panel mkt-omni-mock__panel--store">

                    <div class="mkt-omni-mock__store-nav">

                        <span><?php echo __t('mkt_ecom_mock_shop', 'marketing'); ?></span>

                        <span><?php echo __t('mkt_ecom_mock_wishlist', 'marketing'); ?></span>

                        <span class="is-active"><?php echo __t('mkt_ecom_mock_cart', 'marketing'); ?> · 2</span>

                    </div>

                    <div class="mkt-omni-mock__products">

                        <?php for ($i = 0; $i < 3; $i++): ?>

                        <div class="mkt-omni-mock__product">

                            <div class="mkt-omni-mock__product-img"></div>

                            <div class="mkt-omni-mock__product-meta">

                                <span></span><span class="is-btn"></span>

                            </div>

                        </div>

                        <?php endfor; ?>

                    </div>

                </div>

            </div>

            <div class="mkt-floating-card mkt-floating-card--1">

                <span class="material-icons-round" aria-hidden="true">trending_up</span>

                <?php echo __t('mkt_hero_float_sales', 'marketing'); ?>

            </div>

            <div class="mkt-floating-card mkt-floating-card--2">

                <span class="material-icons-round" aria-hidden="true">inventory</span>

                <?php echo __t('mkt_hero_float_stock', 'marketing'); ?>

            </div>

            <div class="mkt-floating-card mkt-floating-card--3">

                <span class="material-icons-round" aria-hidden="true">storefront</span>

                <?php echo __t('mkt_hero_float_orders', 'marketing'); ?>

            </div>

        </div>

    </div>

</section>



<section class="mkt-trusted">

    <div class="mkt-trusted__inner">

        <p class="mkt-trusted__label"><?php echo __t('mkt_trusted', 'marketing'); ?></p>

        <div class="mkt-trusted__logos">

            <?php foreach (mkt_trusted_logos() as $logo): ?>

            <span class="mkt-trusted__logo"><?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?></span>

            <?php endforeach; ?>

        </div>

    </div>

</section>



<?php if ($ecomMod !== null): ?>
<section class="mkt-section mkt-section--dark mkt-ecom-section" id="ecommerce">
    <div class="mkt-container">
        <?php
        $ecomTitleKey = 'mkt_ecom_title';
        $ecomDescKey = 'mkt_ecom_desc';
        require __DIR__ . '/includes/partials/ecommerce-section-content.php';
        ?>
    </div>
</section>
<?php endif; ?>



<section class="mkt-section" id="features">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <div class="mkt-section__badge"><span class="material-icons-round" aria-hidden="true">extension</span> Modules</div>

            <h2 class="mkt-section__title"><?php echo __t('mkt_features_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_features_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-grid mkt-grid--3">

            <?php foreach (mkt_feature_modules() as $mod): ?>

            <article class="mkt-card<?php echo $mod['key'] === 'ecommerce' ? ' mkt-card--featured' : ''; ?>">

                <?php if ($mod['key'] === 'ecommerce'): ?>

                <span class="mkt-card__tag"><?php echo __t('mkt_hero_badge_ecom', 'marketing'); ?></span>

                <?php endif; ?>

                <div class="mkt-card__icon" style="background:<?php echo $mod['color']; ?>20;color:<?php echo $mod['color']; ?>">

                    <span class="material-icons-round"><?php echo $mod['icon']; ?></span>

                </div>

                <h3 class="mkt-card__title"><?php echo __t('mkt_feat_' . $mod['key'], 'marketing'); ?></h3>

                <p class="mkt-card__desc"><?php echo __t('mkt_feat_' . $mod['key'] . '_desc', 'marketing'); ?></p>

            </article>

            <?php endforeach; ?>

        </div>

        <p style="text-align:center;margin-top:32px;">

            <a href="features.php" class="mkt-btn mkt-btn--ghost"><?php echo __t('mkt_nav_features', 'marketing'); ?> →</a>

        </p>

    </div>

</section>



<section class="mkt-section mkt-section--alt">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_why_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_why_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-benefits">

            <?php foreach (mkt_benefits() as $b): ?>

            <div class="mkt-benefit">

                <span class="material-icons-round" aria-hidden="true">check_circle</span>

                <span><?php echo __t('mkt_benefit_' . $b, 'marketing'); ?></span>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>



<section class="mkt-section" id="industries">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_industries_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_industries_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-grid mkt-grid--4">

            <?php foreach (mkt_industries() as $ind): ?>

            <article class="mkt-card" style="text-align:center;">

                <div class="mkt-card__icon" style="margin:0 auto 12px;background:var(--mkt-primary-soft);color:var(--mkt-primary);">

                    <span class="material-icons-round"><?php echo $ind['icon']; ?></span>

                </div>

                <h3 class="mkt-card__title"><?php echo __t('mkt_ind_' . $ind['key'], 'marketing'); ?></h3>

            </article>

            <?php endforeach; ?>

        </div>

    </div>

</section>



<section class="mkt-section mkt-section--alt">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_screenshots_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_screenshots_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-screenshots">

            <?php foreach (mkt_screenshots() as $ss): ?>

            <div class="mkt-screenshot">

                <div class="mkt-screenshot__mock" style="background:<?php echo $ss['gradient']; ?>">

                    <div class="mkt-screenshot__bar"></div>

                    <div class="mkt-screenshot__content">

                        <div class="mkt-screenshot__row mkt-screenshot__row--wide"></div>

                        <div class="mkt-screenshot__row"></div>

                        <div class="mkt-screenshot__row"></div>

                        <div class="mkt-screenshot__row mkt-screenshot__row--wide"></div>

                    </div>

                </div>

                <span class="mkt-screenshot__label"><?php echo __t('mkt_screen_' . $ss['key'], 'marketing'); ?></span>

            </div>

            <?php endforeach; ?>

        </div>

    </div>

</section>



<section class="mkt-section">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_video_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_video_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-video">

            <button type="button" class="mkt-video__play" aria-label="Play demo video">

                <span class="material-icons-round">play_arrow</span>

            </button>

        </div>

    </div>

</section>



<section class="mkt-section mkt-section--alt" id="pricing">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_pricing_title', 'marketing'); ?></h2>

            <p class="mkt-section__desc"><?php echo __t('mkt_pricing_desc', 'marketing'); ?></p>

        </div>

        <div class="mkt-pricing">

            <?php $pricingCompact = false; require __DIR__ . '/includes/partials/pricing-cards.php'; ?>

        </div>

        <p style="text-align:center;margin-top:24px;">

            <a href="pricing.php" class="mkt-btn mkt-btn--ghost"><?php echo __t('mkt_compare_title', 'marketing'); ?> →</a>

        </p>

    </div>

</section>



<section class="mkt-section">

    <div class="mkt-container">

        <div class="mkt-section__head">

            <h2 class="mkt-section__title"><?php echo __t('mkt_testimonials_title', 'marketing'); ?></h2>

        </div>

        <div class="mkt-testimonials">

            <?php foreach (mkt_testimonials() as $t): ?>

            <article class="mkt-testimonial">

                <div class="mkt-testimonial__stars" aria-label="<?php echo $t['rating']; ?> stars"><?php echo str_repeat('★', $t['rating']); ?></div>

                <p class="mkt-testimonial__quote">"<?php echo __t($t['quote'], 'marketing'); ?>"</p>

                <div class="mkt-testimonial__author">

                    <div class="mkt-testimonial__avatar"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></div>

                    <div>

                        <div class="mkt-testimonial__name"><?php echo htmlspecialchars($t['name'], ENT_QUOTES, 'UTF-8'); ?></div>

                        <div class="mkt-testimonial__role"><?php echo __t($t['role'], 'marketing'); ?> — <?php echo htmlspecialchars($t['company'], ENT_QUOTES, 'UTF-8'); ?></div>

                    </div>

                </div>

            </article>

            <?php endforeach; ?>

        </div>

    </div>

</section>



<?php require __DIR__ . '/includes/partials/faq.php'; ?>

<?php require __DIR__ . '/includes/partials/cta.php'; ?>



<?php require __DIR__ . '/includes/layout-end.php'; ?>



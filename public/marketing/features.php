<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$activePage = 'features';
$pageTitle = __t('mkt_nav_features', 'marketing');
$extraStyles = ['marketing-features.css'];
$ecomDemoUrl = $publicRoot . 'e-commerce/?tenant=demo';
$ecomMod = mkt_feature_module_by_key('ecommerce');

require __DIR__ . '/includes/layout-start.php';

$heroTitle = __t('mkt_nav_features', 'marketing');
$heroDesc = __t('mkt_features_page_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>

<div class="mkt-features-page">

<nav class="mkt-module-nav" aria-label="<?php echo __t('mkt_features_nav', 'marketing'); ?>">
    <div class="mkt-container mkt-module-nav__inner">
        <span class="mkt-module-nav__label"><?php echo __t('mkt_features_nav', 'marketing'); ?></span>
        <div class="mkt-module-nav__links">
            <?php foreach (mkt_feature_modules() as $mod): ?>
            <a href="#<?php echo htmlspecialchars($mod['key'], ENT_QUOTES, 'UTF-8'); ?>" class="mkt-module-nav__link<?php echo $mod['key'] === 'ecommerce' ? ' mkt-module-nav__link--ecom' : ''; ?>">
                <span class="material-icons-round" aria-hidden="true"><?php echo $mod['icon']; ?></span>
                <?php echo __t('mkt_feat_' . $mod['key'], 'marketing'); ?>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</nav>

<?php if ($ecomMod !== null): ?>
<section class="mkt-section mkt-section--dark mkt-ecom-section" id="ecommerce">
    <div class="mkt-container">
        <?php require __DIR__ . '/includes/partials/ecommerce-section-content.php'; ?>
    </div>
</section>
<?php endif; ?>

<?php
$i = 0;
foreach (mkt_feature_modules_except('ecommerce') as $mod):
    $i++;
?>
<section class="mkt-section<?php echo $i % 2 ? '' : ' mkt-section--alt'; ?>" id="<?php echo htmlspecialchars($mod['key'], ENT_QUOTES, 'UTF-8'); ?>">
    <div class="mkt-container">
        <div class="mkt-feature-detail<?php echo $i % 2 ? '' : ' mkt-feature-detail--reverse'; ?>">
            <div class="mkt-feature-block">
                <div class="mkt-section__badge mkt-feature-block__badge">
                    <span class="material-icons-round" aria-hidden="true"><?php echo $mod['icon']; ?></span>
                    <?php echo __t('mkt_feat_' . $mod['key'], 'marketing'); ?>
                </div>
                <h2 class="mkt-section__title mkt-feature-block__title"><?php echo __t('mkt_feat_' . $mod['key'], 'marketing'); ?></h2>
                <p class="mkt-section__desc mkt-feature-block__desc"><?php echo __t('mkt_feat_' . $mod['key'] . '_desc', 'marketing'); ?></p>
                <ul class="mkt-feature-detail__list">
                    <?php foreach (mkt_module_highlights($mod) as $highlight): ?>
                    <li><span class="material-icons-round">check_circle</span> <?php echo mkt_module_highlight_label($mod, $highlight); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <div class="mkt-feature-detail__visual" style="background:<?php echo $mod['color']; ?>;">
                <span class="material-icons-round"><?php echo $mod['icon']; ?></span>
            </div>
        </div>
    </div>
</section>
<?php endforeach; ?>

</div>

<?php
require __DIR__ . '/includes/partials/cta.php';
require __DIR__ . '/includes/layout-end.php';

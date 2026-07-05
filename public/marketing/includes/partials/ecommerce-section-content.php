<?php
declare(strict_types=1);

/** @var array $ecomMod @var string $ecomDemoUrl */
$ecomTitleKey = $ecomTitleKey ?? 'mkt_feat_ecommerce';
$ecomDescKey = $ecomDescKey ?? 'mkt_feat_ecommerce_desc';
?>
<div class="mkt-feature-detail mkt-feature-detail--intro mkt-feature-detail--flush">
    <div class="mkt-feature-block">
        <div class="mkt-section__badge mkt-feature-block__badge">
            <span class="material-icons-round" aria-hidden="true">storefront</span>
            <?php echo __t('mkt_hero_badge_ecom', 'marketing'); ?>
        </div>
        <h2 class="mkt-section__title mkt-feature-block__title"><?php echo __t($ecomTitleKey, 'marketing'); ?></h2>
        <p class="mkt-section__desc mkt-feature-block__desc"><?php echo __t($ecomDescKey, 'marketing'); ?></p>
        <ul class="mkt-feature-detail__list mkt-ecom-showcase__list">
            <?php foreach (mkt_module_highlights($ecomMod) as $highlight): ?>
            <li><span class="material-icons-round">check_circle</span> <?php echo mkt_module_highlight_label($ecomMod, $highlight); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <div class="mkt-feature-detail__visual mkt-feature-detail__visual--ecom" style="background:<?php echo $ecomMod['color']; ?>;">
        <span class="material-icons-round"><?php echo $ecomMod['icon']; ?></span>
    </div>
</div>

<p class="mkt-section__desc mkt-ecom-flow-desc"><?php echo __t('mkt_ecom_flow_desc', 'marketing'); ?></p>
<?php require __DIR__ . '/ecommerce-showcase.php'; ?>

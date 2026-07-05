<?php
declare(strict_types=1);

/** @var string $ecomDemoUrl */
?>
<div class="mkt-ecom-flow">
    <?php foreach (mkt_ecommerce_flow_steps() as $i => $step): ?>
    <article class="mkt-ecom-flow__step">
        <div class="mkt-ecom-flow__icon">
            <span class="material-icons-round"><?php
                echo match ($step) {
                    'catalog' => 'inventory_2',
                    'storefront' => 'storefront',
                    default => 'receipt_long',
                };
            ?></span>
        </div>
        <h3><?php echo __t('mkt_ecom_flow_' . $step, 'marketing'); ?></h3>
        <p><?php echo __t('mkt_ecom_flow_' . $step . '_desc', 'marketing'); ?></p>
    </article>
    <?php if ($i < count(mkt_ecommerce_flow_steps()) - 1): ?>
    <div class="mkt-ecom-flow__arrow" aria-hidden="true"><span class="material-icons-round">arrow_forward</span></div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<div class="mkt-ecom-showcase">
    <div class="mkt-ecom-showcase__content">
        <ul class="mkt-feature-detail__list mkt-ecom-showcase__list">
            <?php foreach (['catalog', 'stock', 'orders', 'brand'] as $point): ?>
            <li><span class="material-icons-round">check_circle</span> <?php echo __t('mkt_ecom_point_' . $point, 'marketing'); ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="mkt-ecom-showcase__pages">
            <span class="material-icons-round" aria-hidden="true">web</span>
            <?php echo __t('mkt_feat_ecommerce_pages', 'marketing'); ?>
        </p>
        <p class="mkt-ecom-showcase__plan">
            <span class="material-icons-round" aria-hidden="true">verified</span>
            <?php echo __t('mkt_feat_ecommerce_plan', 'marketing'); ?>
        </p>
        <div class="mkt-ecom-showcase__payments">
            <p class="mkt-ecom-showcase__payments-label"><?php echo __t('mkt_ecom_payments_label', 'marketing'); ?></p>
            <div class="mkt-ecom-payments">
                <?php foreach (mkt_ecommerce_payment_methods() as $pay): ?>
                <span class="mkt-ecom-payments__chip">
                    <span class="material-icons-round" aria-hidden="true"><?php
                        echo match ($pay) {
                            'card' => 'credit_card',
                            'mobile' => 'smartphone',
                            default => 'local_shipping',
                        };
                    ?></span>
                    <?php echo __t('mkt_ecom_pay_' . $pay, 'marketing'); ?>
                </span>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="mkt-ecom-showcase__actions">
            <a href="<?php echo htmlspecialchars($ecomDemoUrl, ENT_QUOTES, 'UTF-8'); ?>" class="mkt-btn mkt-btn--white"><?php echo __t('mkt_ecom_cta_demo', 'marketing'); ?></a>
            <a href="pricing.php" class="mkt-btn mkt-btn--outline"><?php echo __t('mkt_nav_pricing', 'marketing'); ?></a>
        </div>
    </div>

    <?php require __DIR__ . '/storefront-mock.php'; ?>
</div>

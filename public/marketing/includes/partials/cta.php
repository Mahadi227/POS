<?php declare(strict_types=1); ?>
<section class="mkt-cta">
    <div class="mkt-container">
        <h2 class="mkt-cta__title"><?php echo __t('mkt_cta_title', 'marketing'); ?></h2>
        <p class="mkt-cta__desc"><?php echo __t('mkt_cta_desc', 'marketing'); ?></p>
        <div class="mkt-cta__actions">
            <a href="<?php echo $publicRoot; ?>register.php" class="mkt-btn mkt-btn--white mkt-btn--lg"><?php echo __t('mkt_cta_btn', 'marketing'); ?></a>
            <a href="<?php echo $depthPrefix; ?>demo.php" class="mkt-btn mkt-btn--outline mkt-btn--lg"><?php echo __t('mkt_cta_demo', 'marketing'); ?></a>
        </div>
    </div>
</section>

<?php declare(strict_types=1); ?>
<section class="mkt-section mkt-section--alt">
    <div class="mkt-container">
        <div class="mkt-section__head">
            <h2 class="mkt-section__title"><?php echo __t('mkt_faq_title', 'marketing'); ?></h2>
        </div>
        <div class="mkt-faq">
            <?php foreach (mkt_faq_items() as $key): ?>
            <div class="mkt-faq__item">
                <button type="button" class="mkt-faq__question">
                    <?php echo __t('mkt_faq_' . $key . '_q', 'marketing'); ?>
                    <span class="material-icons-round" aria-hidden="true">expand_more</span>
                </button>
                <div class="mkt-faq__answer">
                    <p><?php echo __t('mkt_faq_' . $key . '_a', 'marketing'); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

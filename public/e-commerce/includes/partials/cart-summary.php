<?php
declare(strict_types=1);

/** @var float $subtotal @var float $tax @var float $total @var float $taxRate @var int $itemCount @var array $items @var bool $showLineItems @var bool $showCheckoutBtn @var string|null $checkoutBtnLabel */
$showLineItems = $showLineItems ?? false;
$showCheckoutBtn = $showCheckoutBtn ?? true;
$checkoutBtnLabel = $checkoutBtnLabel ?? __t('ecom_proceed_checkout', 'ecommerce');
$itemCount = $itemCount ?? 0;
?>
<aside class="ecom-order-panel" aria-labelledby="ecom-order-panel-title">
    <div class="ecom-order-panel__card">
        <h2 id="ecom-order-panel-title" class="ecom-order-panel__title"><?php echo __t('ecom_order_summary', 'ecommerce'); ?></h2>
        <p class="ecom-order-panel__meta"><?php echo __t('ecom_items_count', 'ecommerce', ['count' => (string) $itemCount]); ?></p>

        <?php if ($showLineItems && ($items ?? []) !== []): ?>
        <ul class="ecom-order-panel__lines">
            <?php foreach ($items as $item): ?>
            <li class="ecom-order-panel__line">
                <span class="ecom-order-panel__line-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?> × <?php echo (int) $item['quantity']; ?></span>
                <span class="ecom-order-panel__line-price"><?php echo ecom_money((float) ($item['line_total'] ?? ($item['quantity'] * $item['unit_price']))); ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>

        <div class="ecom-order-panel__rows">
            <div class="ecom-summary-row">
                <span><?php echo __t('ecom_subtotal', 'ecommerce'); ?></span>
                <span id="ecom-summary-subtotal"><?php echo ecom_money($subtotal); ?></span>
            </div>
            <?php if (($taxRate ?? 0) > 0): ?>
            <div class="ecom-summary-row">
                <span><?php echo __t('ecom_tax', 'ecommerce'); ?> (<?php echo htmlspecialchars((string) $taxRate, ENT_QUOTES, 'UTF-8'); ?>%)</span>
                <span id="ecom-summary-tax"><?php echo ecom_money($tax); ?></span>
            </div>
            <?php endif; ?>
            <div class="ecom-summary-row ecom-summary-row--total">
                <span><?php echo __t('ecom_total', 'ecommerce'); ?></span>
                <span id="ecom-summary-total"><?php echo ecom_money($total); ?></span>
            </div>
        </div>

        <?php if ($showCheckoutBtn): ?>
        <a href="<?php echo ecom_href('checkout/'); ?>" class="ecom-btn ecom-btn--accent ecom-btn--block ecom-order-panel__cta">
            <span class="material-icons-round" aria-hidden="true">lock</span>
            <?php echo htmlspecialchars($checkoutBtnLabel, ENT_QUOTES, 'UTF-8'); ?>
        </a>
        <?php endif; ?>

        <ul class="ecom-trust-list" aria-label="<?php echo htmlspecialchars(__t('ecom_trust_title', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">
            <li><span class="material-icons-round" aria-hidden="true">verified_user</span><?php echo __t('ecom_trust_secure', 'ecommerce'); ?></li>
            <li><span class="material-icons-round" aria-hidden="true">inventory_2</span><?php echo __t('ecom_trust_stock', 'ecommerce'); ?></li>
            <li><span class="material-icons-round" aria-hidden="true">support_agent</span><?php echo __t('ecom_trust_support', 'ecommerce'); ?></li>
        </ul>
    </div>

    <div class="ecom-pay-badges">
        <span class="ecom-pay-badges__label"><?php echo __t('ecom_accepted_payments', 'ecommerce'); ?></span>
        <div class="ecom-pay-badges__icons">
            <span class="ecom-pay-badge" title="<?php echo htmlspecialchars(__t('ecom_pay_card', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">credit_card</span></span>
            <span class="ecom-pay-badge" title="<?php echo htmlspecialchars(__t('ecom_pay_mobile', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">smartphone</span></span>
            <span class="ecom-pay-badge" title="<?php echo htmlspecialchars(__t('ecom_pay_cod', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>"><span class="material-icons-round">local_shipping</span></span>
        </div>
    </div>
</aside>

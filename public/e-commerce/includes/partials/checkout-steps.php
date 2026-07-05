<?php
declare(strict_types=1);

/** @var int $checkoutStep 1 = cart, 2 = checkout, 3 = confirmation */
$checkoutStep = (int) ($checkoutStep ?? 1);
$steps = [
    1 => ['label' => __t('ecom_step_cart', 'ecommerce'), 'href' => ecom_href('cart/')],
    2 => ['label' => __t('ecom_step_payment', 'ecommerce'), 'href' => ecom_href('checkout/')],
    3 => ['label' => __t('ecom_step_confirm', 'ecommerce'), 'href' => null],
];
?>
<nav class="ecom-checkout-steps" aria-label="<?php echo htmlspecialchars(__t('ecom_checkout_progress', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">
    <ol class="ecom-checkout-steps__list">
        <?php foreach ($steps as $num => $step):
            $isDone = $checkoutStep > $num;
            $isActive = $checkoutStep === $num;
            $classes = 'ecom-checkout-steps__item';
            if ($isDone) {
                $classes .= ' is-done';
            }
            if ($isActive) {
                $classes .= ' is-active';
            }
        ?>
        <li class="<?php echo $classes; ?>">
            <?php if ($step['href'] !== null && ($isDone || $num < $checkoutStep)): ?>
            <a href="<?php echo htmlspecialchars($step['href'], ENT_QUOTES, 'UTF-8'); ?>" class="ecom-checkout-steps__link">
                <span class="ecom-checkout-steps__num"><?php echo $isDone ? '✓' : (int) $num; ?></span>
                <span class="ecom-checkout-steps__label"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </a>
            <?php else: ?>
            <span class="ecom-checkout-steps__link"<?php echo $isActive ? ' aria-current="step"' : ''; ?>>
                <span class="ecom-checkout-steps__num"><?php echo $isDone ? '✓' : (int) $num; ?></span>
                <span class="ecom-checkout-steps__label"><?php echo htmlspecialchars($step['label'], ENT_QUOTES, 'UTF-8'); ?></span>
            </span>
            <?php endif; ?>
        </li>
        <?php if ($num < count($steps)): ?>
        <li class="ecom-checkout-steps__divider" aria-hidden="true"></li>
        <?php endif; ?>
        <?php endforeach; ?>
    </ol>
</nav>

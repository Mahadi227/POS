<?php
/** @var array<string, mixed> $brand */
$logo = ecom_brand_logo($brand);
$name = htmlspecialchars((string) ($brand['name'] ?? ''), ENT_QUOTES, 'UTF-8');
$count = (int) ($brand['product_count'] ?? 0);
$href = ecom_brand_href($brand);
?>
<a class="ecom-brand-card" href="<?php echo $href; ?>" data-ecom-brand-card data-brand-name="<?php echo $name; ?>">
    <span class="ecom-brand-card__media">
        <?php if ($logo): ?>
        <img src="<?php echo htmlspecialchars($logo, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo $name; ?>" class="ecom-brand-card__logo" loading="lazy" decoding="async">
        <?php else: ?>
        <span class="ecom-brand-card__placeholder" aria-hidden="true">
            <span class="material-icons-round">sell</span>
        </span>
        <?php endif; ?>
    </span>
    <span class="ecom-brand-card__body">
        <span class="ecom-brand-card__name"><?php echo $name; ?></span>
        <span class="ecom-brand-card__count"><?php echo __t('ecom_products_count', 'ecommerce', ['count' => $count]); ?></span>
    </span>
    <span class="ecom-brand-card__arrow material-icons-round" aria-hidden="true">arrow_forward</span>
</a>

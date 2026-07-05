<?php declare(strict_types=1);
/** @var string $heroTitle @var string $heroDesc @var bool $heroDark */
$heroDark = $heroDark ?? false;
?>
<section class="mkt-page-hero<?php echo $heroDark ? ' mkt-page-hero--dark' : ''; ?>">
    <div class="mkt-container">
        <h1 class="mkt-page-hero__title"><?php echo htmlspecialchars($heroTitle, ENT_QUOTES, 'UTF-8'); ?></h1>
        <?php if (!empty($heroDesc)): ?>
        <p class="mkt-page-hero__desc"><?php echo htmlspecialchars($heroDesc, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </div>
</section>

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'testimonials';
$pageTitle = __t('mkt_testimonials_page_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_testimonials_title', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-testimonials">
            <?php foreach (mkt_testimonials() as $t): ?>
            <article class="mkt-testimonial">
                <div class="mkt-testimonial__stars"><?php echo str_repeat('★', $t['rating']); ?></div>
                <p class="mkt-testimonial__quote">"<?php echo __t($t['quote'], 'marketing'); ?>"</p>
                <div class="mkt-testimonial__author">
                    <div class="mkt-testimonial__avatar"><?php echo strtoupper(substr($t['name'], 0, 1)); ?></div>
                    <div><div class="mkt-testimonial__name"><?php echo htmlspecialchars($t['name']); ?></div>
                    <div class="mkt-testimonial__role"><?php echo __t($t['role'], 'marketing'); ?> — <?php echo htmlspecialchars($t['company']); ?></div></div>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

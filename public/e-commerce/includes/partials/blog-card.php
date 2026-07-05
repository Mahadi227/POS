<?php
/** @var array<string, mixed> $post @var bool $featured */
$featured = !empty($featured);
$title = htmlspecialchars((string) ($post['title'] ?? ''), ENT_QUOTES, 'UTF-8');
$excerpt = htmlspecialchars(trim((string) ($post['excerpt'] ?? '')), ENT_QUOTES, 'UTF-8');
$href = ecom_blog_href($post);
$dateLabel = ecom_blog_date($post);
$dateAttr = ecom_blog_datetime($post);
$readingMins = ecom_blog_reading_time((string) ($post['body'] ?? $post['excerpt'] ?? ''));
$cardClass = 'ecom-blog-card' . ($featured ? ' ecom-blog-card--featured' : '');
?>
<article class="<?php echo $cardClass; ?>" data-ecom-blog-card data-post-title="<?php echo $title; ?>">
    <a href="<?php echo $href; ?>" class="ecom-blog-card__media" aria-hidden="true" tabindex="-1">
        <span class="ecom-blog-card__icon material-icons-round">article</span>
    </a>
    <div class="ecom-blog-card__body">
        <div class="ecom-blog-card__meta">
            <?php if ($dateLabel !== ''): ?>
            <time datetime="<?php echo htmlspecialchars($dateAttr, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></time>
            <?php endif; ?>
            <span class="ecom-blog-card__read"><?php echo __t('ecom_blog_read_time', 'ecommerce', ['min' => $readingMins]); ?></span>
        </div>
        <h2 class="ecom-blog-card__title"><a href="<?php echo $href; ?>"><?php echo $title; ?></a></h2>
        <?php if ($excerpt !== ''): ?>
        <p class="ecom-blog-card__excerpt"><?php echo $excerpt; ?></p>
        <?php endif; ?>
        <a href="<?php echo $href; ?>" class="ecom-blog-card__link">
            <?php echo __t('ecom_blog_read_more', 'ecommerce'); ?>
            <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
        </a>
    </div>
</article>

<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$activePage = 'blog';
$pageTitle = __t('mkt_blog_title', 'marketing');
require __DIR__ . '/../includes/layout-start.php';
$heroTitle = __t('mkt_blog_title', 'marketing');
$heroDesc = __t('mkt_blog_desc', 'marketing');
require __DIR__ . '/../includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-blog-grid">
            <?php foreach (mkt_blog_posts() as $post): ?>
            <article class="mkt-blog-card">
                <div class="mkt-blog-card__img"></div>
                <div class="mkt-blog-card__body">
                    <div class="mkt-blog-card__cat"><?php echo __t($post['category'], 'marketing'); ?></div>
                    <h3 class="mkt-blog-card__title"><?php echo __t($post['title'], 'marketing'); ?></h3>
                    <p class="mkt-blog-card__excerpt"><?php echo __t($post['excerpt'], 'marketing'); ?></p>
                    <time class="mkt-blog-card__date" datetime="<?php echo $post['date']; ?>"><?php echo date('M j, Y', strtotime($post['date'])); ?></time>
                </div>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/layout-end.php';

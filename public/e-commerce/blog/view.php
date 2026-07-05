<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$postId = (int) ($_GET['id'] ?? 0);
$post = $catalog->resolveBlogPost($tenantId, $slug, $postId);

if (!$post) {
    http_response_code(404);
    $pageTitle = __t('ecom_post_not_found', 'ecommerce');
    $activePage = 'blog';
    $bodyClass = trim(($bodyClass ?? '') . ' ecom-page-blog ecom-page-blog--missing');
    require __DIR__ . '/../includes/layout-start.php';
    ?>
    <section class="ecom-blog-empty ecom-blog-empty--404">
        <span class="material-icons-round ecom-blog-empty__icon" aria-hidden="true">article</span>
        <h1><?php echo __t('ecom_post_not_found', 'ecommerce'); ?></h1>
        <p><?php echo __t('ecom_post_not_found_hint', 'ecommerce'); ?></p>
        <div class="ecom-blog-empty__actions">
            <a href="<?php echo ecom_href('blog/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_back_blog', 'ecommerce'); ?></a>
            <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_back_shop', 'ecommerce'); ?></a>
        </div>
    </section>
    <?php
    require __DIR__ . '/../includes/layout-end.php';
    exit;
}

$pageTitle = (string) $post['title'];
$activePage = 'blog';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-blog ecom-page-blog-view');
$pageMetaDescription = trim((string) ($post['excerpt'] ?? ''));
if ($pageMetaDescription === '') {
    $pageMetaDescription = trim((string) ($post['body'] ?? ''));
}
if ($pageMetaDescription !== '') {
    $pageMetaDescription = mb_substr(preg_replace('/\s+/', ' ', strip_tags($pageMetaDescription)) ?? '', 0, 160);
}

$dateLabel = ecom_blog_date($post);
$dateAttr = ecom_blog_datetime($post);
$readingMins = ecom_blog_reading_time((string) ($post['body'] ?? $post['excerpt'] ?? ''));
$relatedPosts = $catalog->listRelatedBlogPosts($tenantId, (string) ($post['slug'] ?? ''), 3);
$bodyText = trim((string) ($post['body'] ?? ''));
$excerptText = trim((string) ($post['excerpt'] ?? ''));

require __DIR__ . '/../includes/layout-start.php';
?>

<nav class="ecom-breadcrumb" aria-label="<?php echo __t('ecom_breadcrumb', 'ecommerce'); ?>">
    <a href="<?php echo ecom_href('home/'); ?>"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <a href="<?php echo ecom_href('blog/'); ?>"><?php echo __t('ecom_blog_title', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="ecom-breadcrumb__current" aria-current="page"><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></span>
</nav>

<article class="ecom-blog-article" itemscope itemtype="https://schema.org/BlogPosting">
    <header class="ecom-blog-article__head">
        <p class="ecom-blog-article__eyebrow"><?php echo __t('ecom_blog_title', 'ecommerce'); ?></p>
        <h1 itemprop="headline"><?php echo htmlspecialchars((string) $post['title'], ENT_QUOTES, 'UTF-8'); ?></h1>
        <div class="ecom-blog-article__meta">
            <?php if ($dateLabel !== ''): ?>
            <time datetime="<?php echo htmlspecialchars($dateAttr, ENT_QUOTES, 'UTF-8'); ?>" itemprop="datePublished"><?php echo htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8'); ?></time>
            <?php endif; ?>
            <span class="ecom-blog-article__read"><?php echo __t('ecom_blog_read_time', 'ecommerce', ['min' => $readingMins]); ?></span>
        </div>
        <?php if ($excerptText !== ''): ?>
        <p class="ecom-blog-article__lead" itemprop="description"><?php echo htmlspecialchars($excerptText, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
    </header>

    <div class="ecom-blog-article__body" itemprop="articleBody">
        <?php if ($bodyText !== ''): ?>
        <?php echo nl2br(htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8')); ?>
        <?php elseif ($excerptText !== ''): ?>
        <?php echo nl2br(htmlspecialchars($excerptText, ENT_QUOTES, 'UTF-8')); ?>
        <?php endif; ?>
    </div>

    <footer class="ecom-blog-article__foot">
        <a href="<?php echo ecom_href('blog/'); ?>" class="ecom-btn ecom-btn--ghost">
            <span class="material-icons-round" aria-hidden="true">arrow_back</span>
            <?php echo __t('ecom_back_blog', 'ecommerce'); ?>
        </a>
        <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent">
            <?php echo __t('ecom_shop_now', 'ecommerce'); ?>
            <span class="material-icons-round" aria-hidden="true">storefront</span>
        </a>
    </footer>
</article>

<?php if ($relatedPosts !== []): ?>
<section class="ecom-section ecom-section--blog-related">
    <div class="ecom-section__head">
        <h2><?php echo __t('ecom_blog_related', 'ecommerce'); ?></h2>
        <a class="ecom-section__link" href="<?php echo ecom_href('blog/'); ?>">
            <?php echo __t('ecom_view_all', 'ecommerce'); ?>
            <span class="material-icons-round" aria-hidden="true">arrow_forward</span>
        </a>
    </div>
    <div class="ecom-blog-grid ecom-blog-grid--related">
        <?php foreach ($relatedPosts as $post): ?>
            <?php $featured = false; include __DIR__ . '/../includes/partials/blog-card.php'; ?>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

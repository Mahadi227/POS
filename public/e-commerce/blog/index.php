<?php
require_once __DIR__ . '/../includes/bootstrap.php';

ecom_seed_blog_if_empty($tenantId);

$pageTitle = __t('ecom_blog_title', 'ecommerce');
$activePage = 'blog';
$bodyClass = trim(($bodyClass ?? '') . ' ecom-page-blog');
$extraScripts = ['ecommerce/blog.js'];

$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 9;
$totalPosts = $catalog->countBlogPosts($tenantId);
$pages = max(1, (int) ceil($totalPosts / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$posts = $catalog->listBlogPosts($tenantId, $perPage, ($page - 1) * $perPage);
$featuredPost = ($page === 1 && $posts !== []) ? array_shift($posts) : null;
$gridPosts = $posts;

require __DIR__ . '/../includes/layout-start.php';
?>

<nav class="ecom-breadcrumb" aria-label="<?php echo __t('ecom_breadcrumb', 'ecommerce'); ?>">
    <a href="<?php echo ecom_href('home/'); ?>"><?php echo __t('ecom_nav_home', 'ecommerce'); ?></a>
    <span class="ecom-breadcrumb__sep" aria-hidden="true">/</span>
    <span class="ecom-breadcrumb__current" aria-current="page"><?php echo __t('ecom_blog_title', 'ecommerce'); ?></span>
</nav>

<section class="ecom-page-head ecom-page-head--blog">
    <div>
        <h1><?php echo __t('ecom_blog_title', 'ecommerce'); ?></h1>
        <p><?php echo __t('ecom_blog_sub', 'ecommerce', ['count' => $totalPosts]); ?></p>
    </div>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--ghost ecom-page-head__action">
        <?php echo __t('ecom_shop_now', 'ecommerce'); ?>
        <span class="material-icons-round" aria-hidden="true">storefront</span>
    </a>
</section>

<?php if ($totalPosts > 0): ?>
<section class="ecom-blog-toolbar" aria-label="<?php echo __t('ecom_blog_search_label', 'ecommerce'); ?>">
    <label class="ecom-blog-search" for="ecom-blog-search">
        <span class="material-icons-round" aria-hidden="true">search</span>
        <input type="search" id="ecom-blog-search" class="ecom-blog-search__input" data-ecom-blog-search placeholder="<?php echo __t('ecom_blog_search_placeholder', 'ecommerce'); ?>" autocomplete="off">
        <button type="button" class="ecom-blog-search__clear" data-ecom-blog-clear hidden aria-label="<?php echo __t('ecom_search_clear', 'ecommerce'); ?>">
            <span class="material-icons-round">close</span>
        </button>
    </label>
    <p class="ecom-blog-toolbar__hint" data-ecom-blog-status aria-live="polite"></p>
</section>

<?php if ($featuredPost): ?>
<section class="ecom-blog-featured" data-ecom-blog-featured>
    <?php $post = $featuredPost; $featured = true; include __DIR__ . '/../includes/partials/blog-card.php'; ?>
</section>
<?php endif; ?>

<div class="ecom-blog-grid" data-ecom-blog-grid>
    <?php foreach ($gridPosts as $post): ?>
        <?php $featured = false; include __DIR__ . '/../includes/partials/blog-card.php'; ?>
    <?php endforeach; ?>
</div>
<p class="ecom-empty ecom-blog-empty-filter" data-ecom-blog-no-match hidden><?php echo __t('ecom_blog_no_match', 'ecommerce'); ?></p>

<?php if ($pages > 1): ?>
<nav class="ecom-pagination" aria-label="<?php echo __t('ecom_blog_pagination', 'ecommerce'); ?>">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="<?php echo ecom_href('blog/?page=' . $i); ?>" class="<?php echo $i === $page ? 'is-active' : ''; ?>"><?php echo $i; ?></a>
    <?php endfor; ?>
</nav>
<?php endif; ?>

<?php else: ?>
<section class="ecom-blog-empty">
    <span class="material-icons-round ecom-blog-empty__icon" aria-hidden="true">article</span>
    <h2><?php echo __t('ecom_no_posts', 'ecommerce'); ?></h2>
    <p><?php echo __t('ecom_no_posts_hint', 'ecommerce'); ?></p>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_shop_now', 'ecommerce'); ?></a>
</section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/layout-end.php'; ?>

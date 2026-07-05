<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$activePage = 'resources';
$pageTitle = __t('mkt_resources_title', 'marketing');
require __DIR__ . '/../includes/layout-start.php';
$heroTitle = __t('mkt_resources_title', 'marketing');
$heroDesc = __t('mkt_resources_desc', 'marketing');
require __DIR__ . '/../includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--3">
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">menu_book</span><h3><?php echo __t('mkt_nav_docs', 'marketing'); ?></h3><a href="../documentation/index.php">→</a></article>
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">article</span><h3><?php echo __t('mkt_nav_blog', 'marketing'); ?></h3><a href="../blog/index.php">→</a></article>
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">download</span><h3>Product Brochure</h3><p class="mkt-card__desc">PDF overview (coming soon)</p></article>
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">videocam</span><h3>Webinars</h3><p class="mkt-card__desc"><a href="../demo.php"><?php echo __t('mkt_nav_demo', 'marketing'); ?></a></p></article>
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">fact_check</span><h3><?php echo __t('mkt_nav_case_studies', 'marketing'); ?></h3><a href="../case-studies.php">→</a></article>
            <article class="mkt-card"><span class="material-icons-round" style="color:var(--mkt-primary);font-size:32px;">help</span><h3><?php echo __t('mkt_nav_faq', 'marketing'); ?></h3><a href="../faq.php">→</a></article>
        </div>
    </div>
</section>
<?php require __DIR__ . '/../includes/layout-end.php';

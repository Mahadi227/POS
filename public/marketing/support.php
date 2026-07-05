<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'support';
$pageTitle = __t('mkt_support_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_support_title', 'marketing');
$heroDesc = __t('mkt_support_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--3">
            <?php foreach (mkt_support_channels() as $ch): ?>
            <article class="mkt-card" style="text-align:center;">
                <div class="mkt-card__icon" style="margin:0 auto 12px;background:var(--mkt-primary-soft);color:var(--mkt-primary);">
                    <span class="material-icons-round"><?php echo $ch['icon']; ?></span>
                </div>
                <h3 class="mkt-card__title"><?php echo __t('mkt_sup_' . $ch['key'], 'marketing'); ?></h3>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="text-align:center;margin-top:32px;">
            <a href="documentation/index.php" class="mkt-btn mkt-btn--primary"><?php echo __t('mkt_nav_docs', 'marketing'); ?></a>
            <a href="faq.php" class="mkt-btn mkt-btn--ghost"><?php echo __t('mkt_nav_faq', 'marketing'); ?></a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

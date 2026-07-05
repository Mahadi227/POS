<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'careers';
$pageTitle = __t('mkt_careers_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_careers_title', 'marketing');
$heroDesc = __t('mkt_careers_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container" style="text-align:center;">
        <p style="color:var(--mkt-text-secondary);margin-bottom:24px;">We're hiring engineers, product designers, and retail specialists.</p>
        <a href="contact.php" class="mkt-btn mkt-btn--primary"><?php echo __t('mkt_nav_contact', 'marketing'); ?></a>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

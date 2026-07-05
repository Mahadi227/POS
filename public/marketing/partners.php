<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'partners';
$pageTitle = __t('mkt_partners_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_partners_title', 'marketing');
$heroDesc = __t('mkt_partners_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container" style="text-align:center;">
        <a href="contact.php" class="mkt-btn mkt-btn--primary mkt-btn--lg"><?php echo __t('mkt_nav_contact', 'marketing'); ?></a>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'about';
$pageTitle = __t('mkt_about_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_about_title', 'marketing');
$heroDesc = __t('mkt_about_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container" style="max-width:720px;text-align:center;">
        <p style="font-size:1.15rem;line-height:1.8;color:var(--mkt-text-secondary);"><?php echo __t('mkt_about_mission', 'marketing'); ?></p>
        <div class="mkt-benefits" style="margin-top:40px;">
            <?php foreach (mkt_benefits() as $b): ?>
            <div class="mkt-benefit"><span class="material-icons-round">check_circle</span><span><?php echo __t('mkt_benefit_' . $b, 'marketing'); ?></span></div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

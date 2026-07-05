<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'solutions';
$pageTitle = __t('mkt_solutions_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_solutions_title', 'marketing');
$heroDesc = __t('mkt_solutions_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
$solutions = ['omnichannel','supermarkets','pharmacies','restaurants','hardware','fashion','electronics','wholesale'];
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--2">
            <?php foreach ($solutions as $sol): ?>
            <article class="mkt-card">
                <h3 class="mkt-card__title"><?php echo __t('mkt_sol_' . $sol, 'marketing'); ?></h3>
                <p class="mkt-card__desc"><?php echo __t('mkt_sol_' . $sol . '_desc', 'marketing'); ?></p>
                <a href="<?php echo $sol === 'omnichannel' ? 'features.php#ecommerce' : 'industries.php'; ?>" class="mkt-btn mkt-btn--ghost" style="margin-top:12px;">
                    <?php echo __t($sol === 'omnichannel' ? 'mkt_ecom_cta' : 'mkt_nav_industries', 'marketing'); ?> →
                </a>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

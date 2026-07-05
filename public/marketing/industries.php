<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'industries';
$pageTitle = __t('mkt_industries_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_industries_title', 'marketing');
$heroDesc = __t('mkt_industries_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--4">
            <?php foreach (mkt_industries() as $ind): ?>
            <article class="mkt-card" style="text-align:center;">
                <div class="mkt-card__icon" style="margin:0 auto 12px;background:var(--mkt-primary-soft);color:var(--mkt-primary);">
                    <span class="material-icons-round"><?php echo $ind['icon']; ?></span>
                </div>
                <h3 class="mkt-card__title"><?php echo __t('mkt_ind_' . $ind['key'], 'marketing'); ?></h3>
                <p class="mkt-card__desc"><?php echo __t('mkt_sol_' . $ind['key'] . 's_desc', 'marketing') !== 'mkt_sol_' . $ind['key'] . 's_desc' ? __t('mkt_sol_' . $ind['key'] . 's_desc', 'marketing') : __t('mkt_features_desc', 'marketing'); ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

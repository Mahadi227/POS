<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'case-studies';
$pageTitle = __t('mkt_case_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_case_title', 'marketing');
$heroDesc = __t('mkt_case_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--3">
            <?php foreach (mkt_case_studies() as $cs): ?>
            <article class="mkt-card">
                <div class="mkt-section__badge" style="margin-bottom:12px;"><?php echo htmlspecialchars($cs['company']); ?></div>
                <h3 class="mkt-card__title" style="color:var(--mkt-primary);"><?php echo __t($cs['result'], 'marketing'); ?></h3>
                <p class="mkt-card__desc"><?php echo __t($cs['summary'], 'marketing'); ?></p>
            </article>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

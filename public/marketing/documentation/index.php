<?php
declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
$activePage = 'documentation';
$pageTitle = __t('mkt_docs_title', 'marketing');
require __DIR__ . '/../includes/layout-start.php';
$heroTitle = __t('mkt_docs_title', 'marketing');
$heroDesc = __t('mkt_docs_desc', 'marketing');
require __DIR__ . '/../includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--3">
            <?php foreach (mkt_doc_sections() as $doc): ?>
            <article class="mkt-card">
                <div class="mkt-card__icon" style="background:var(--mkt-primary-soft);color:var(--mkt-primary);">
                    <span class="material-icons-round"><?php echo $doc['icon']; ?></span>
                </div>
                <h3 class="mkt-card__title"><?php echo __t('mkt_doc_' . $doc['key'], 'marketing'); ?></h3>
                <p class="mkt-card__desc"><?php echo __t('mkt_features_desc', 'marketing'); ?></p>
            </article>
            <?php endforeach; ?>
        </div>
        <p style="text-align:center;margin-top:32px;">
            <a href="<?php echo $publicRoot; ?>platform/developers/openapi.php" class="mkt-btn mkt-btn--primary"><?php echo __t('mkt_api_cta', 'marketing'); ?></a>
        </p>
    </div>
</section>
<?php require __DIR__ . '/../includes/layout-end.php';

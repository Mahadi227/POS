<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'api';
$pageTitle = __t('mkt_api_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_api_title', 'marketing');
$heroDesc = __t('mkt_api_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container" style="text-align:center;">
        <div class="mkt-grid mkt-grid--3" style="margin-bottom:40px;">
            <article class="mkt-card"><span class="material-icons-round" style="font-size:40px;color:var(--mkt-primary);">api</span><h3><?php echo __t('mkt_api_rest', 'marketing'); ?></h3><p class="mkt-card__desc"><?php echo __t('mkt_api_rest_desc', 'marketing'); ?></p></article>
            <article class="mkt-card"><span class="material-icons-round" style="font-size:40px;color:var(--mkt-primary);">storefront</span><h3><?php echo __t('mkt_api_storefront', 'marketing'); ?></h3><p class="mkt-card__desc"><?php echo __t('mkt_api_storefront_desc', 'marketing'); ?></p></article>
            <article class="mkt-card"><span class="material-icons-round" style="font-size:40px;color:var(--mkt-primary);">webhook</span><h3><?php echo __t('mkt_api_webhooks', 'marketing'); ?></h3><p class="mkt-card__desc"><?php echo __t('mkt_api_webhooks_desc', 'marketing'); ?></p></article>
            <article class="mkt-card"><span class="material-icons-round" style="font-size:40px;color:var(--mkt-primary);">vpn_key</span><h3><?php echo __t('mkt_api_keys', 'marketing'); ?></h3><p class="mkt-card__desc"><?php echo __t('mkt_api_keys_desc', 'marketing'); ?></p></article>
        </div>
        <a href="<?php echo $publicRoot; ?>platform/developers/openapi.php" class="mkt-btn mkt-btn--primary mkt-btn--lg"><?php echo __t('mkt_api_cta', 'marketing'); ?></a>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

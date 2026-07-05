<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'integrations';
$pageTitle = __t('mkt_integrations_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_integrations_title', 'marketing');
$heroDesc = __t('mkt_integrations_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--4">
            <?php foreach (mkt_integrations() as $int): ?>
            <div class="mkt-integration">
                <div class="mkt-integration__icon" style="background:<?php echo $int['color']; ?>">
                    <span class="material-icons-round"><?php echo $int['icon']; ?></span>
                </div>
                <span class="mkt-integration__name"><?php echo __t('mkt_int_' . $int['key'], 'marketing'); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/partials/cta.php'; require __DIR__ . '/includes/layout-end.php';

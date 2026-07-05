<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
}
$activePage = 'contact';
$pageTitle = __t('mkt_contact_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_contact_title', 'marketing');
$heroDesc = __t('mkt_contact_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container">
        <div class="mkt-grid mkt-grid--2" style="gap:48px;align-items:start;">
            <div>
                <div class="mkt-card" style="margin-bottom:20px;">
                    <span class="material-icons-round" style="color:var(--mkt-primary);">mail</span>
                    <strong><?php echo __t('mkt_contact_email', 'marketing'); ?></strong>
                </div>
                <div class="mkt-card" style="margin-bottom:20px;">
                    <span class="material-icons-round" style="color:var(--mkt-success);">phone</span>
                    <strong><?php echo __t('mkt_contact_phone', 'marketing'); ?></strong>
                </div>
                <div class="mkt-card" style="margin-bottom:20px;">
                    <span class="material-icons-round" style="color:#25d366;">chat</span>
                    <a href="https://wa.me/221330000000" target="_blank" rel="noopener"><?php echo __t('mkt_contact_whatsapp', 'marketing'); ?></a>
                </div>
                <div class="mkt-card">
                    <span class="material-icons-round" style="color:var(--mkt-primary);">location_on</span>
                    <strong><?php echo __t('mkt_contact_address', 'marketing'); ?></strong>
                </div>
                <div class="mkt-map" style="margin-top:24px;">
                    <span class="material-icons-round">map</span> Google Maps
                </div>
            </div>
            <div>
                <h2 style="margin:0 0 20px;font-family:var(--mkt-display);"><?php echo __t('mkt_contact_form_title', 'marketing'); ?></h2>
                <?php if ($success): ?><div class="mkt-alert mkt-alert--success"><?php echo __t('mkt_contact_success', 'marketing'); ?></div><?php endif; ?>
                <form class="mkt-form" method="post" data-validate>
                    <div class="mkt-field"><label><?php echo __t('mkt_demo_name', 'marketing'); ?></label><input name="name" required></div>
                    <div class="mkt-field"><label><?php echo __t('mkt_demo_email', 'marketing'); ?></label><input type="email" name="email" required></div>
                    <div class="mkt-field"><label><?php echo __t('mkt_demo_message', 'marketing'); ?></label><textarea name="message" rows="5" required></textarea></div>
                    <button type="submit" class="mkt-btn mkt-btn--primary"><?php echo __t('mkt_contact_submit', 'marketing'); ?></button>
                </form>
            </div>
        </div>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

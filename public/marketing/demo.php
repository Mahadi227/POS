<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$success = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $success = true;
    $_SESSION['mkt_demo_sent'] = true;
}
$activePage = 'demo';
$pageTitle = __t('mkt_demo_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
$heroTitle = __t('mkt_demo_title', 'marketing');
$heroDesc = __t('mkt_demo_desc', 'marketing');
require __DIR__ . '/includes/partials/page-hero.php';
?>
<section class="mkt-section">
    <div class="mkt-container" style="max-width:640px;">
        <?php if ($success): ?>
        <div class="mkt-alert mkt-alert--success"><?php echo __t('mkt_demo_success', 'marketing'); ?></div>
        <?php endif; ?>
        <form class="mkt-form" method="post" data-validate>
            <div class="mkt-form-row">
                <div class="mkt-field"><label><?php echo __t('mkt_demo_name', 'marketing'); ?></label><input name="name" required></div>
                <div class="mkt-field"><label><?php echo __t('mkt_demo_email', 'marketing'); ?></label><input type="email" name="email" required></div>
            </div>
            <div class="mkt-form-row">
                <div class="mkt-field"><label><?php echo __t('mkt_demo_company', 'marketing'); ?></label><input name="company" required></div>
                <div class="mkt-field"><label><?php echo __t('mkt_demo_phone', 'marketing'); ?></label><input type="tel" name="phone"></div>
            </div>
            <div class="mkt-field">
                <label><?php echo __t('mkt_demo_industry', 'marketing'); ?></label>
                <select name="industry">
                    <?php foreach (mkt_industries() as $ind): ?>
                    <option value="<?php echo $ind['key']; ?>"><?php echo __t('mkt_ind_' . $ind['key'], 'marketing'); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="mkt-field"><label><?php echo __t('mkt_demo_message', 'marketing'); ?></label><textarea name="message" rows="4"></textarea></div>
            <button type="submit" class="mkt-btn mkt-btn--primary mkt-btn--lg"><?php echo __t('mkt_demo_submit', 'marketing'); ?></button>
        </form>
    </div>
</section>
<?php require __DIR__ . '/includes/layout-end.php';

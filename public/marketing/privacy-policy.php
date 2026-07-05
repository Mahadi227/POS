<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$activePage = 'privacy';
$pageTitle = __t('mkt_privacy_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
?>
<section class="mkt-section"><div class="mkt-container mkt-legal">
<h1 class="mkt-page-hero__title"><?php echo htmlspecialchars($pageTitle); ?></h1>
<p><em><?php echo __t('mkt_legal_updated', 'marketing'); ?></em></p>
<h2>1. Information We Collect</h2><p>We collect information you provide when registering, using our services, or contacting support. This includes name, email, company details, and usage data necessary to operate RetailPOS Cloud.</p>
<h2>2. How We Use Information</h2><p>Your data is used to provide, maintain, and improve our services, process transactions, send notifications, and comply with legal obligations.</p>
<h2>3. Data Security</h2><p>We implement enterprise-grade security including encryption, access controls, and regular audits.</p>
<h2>4. Your Rights</h2><p>You may request access, correction, or deletion of your personal data by contacting support@retailpos.local.</p>
<h2>5. Contact</h2><p>For privacy inquiries: support@retailpos.local</p>
</div></section>
<?php require __DIR__ . '/includes/layout-end.php';

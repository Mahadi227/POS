<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = __t('mkt_terms_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
?>
<section class="mkt-section"><div class="mkt-container mkt-legal">
<h1 class="mkt-page-hero__title"><?php echo htmlspecialchars($pageTitle); ?></h1>
<p><em><?php echo __t('mkt_legal_updated', 'marketing'); ?></em></p>
<h2>1. Acceptance</h2><p>By using RetailPOS Cloud, you agree to these terms.</p>
<h2>2. Service Description</h2><p>RetailPOS provides cloud-based retail management software including POS, inventory, warehouse, and accounting modules.</p>
<h2>3. Account Responsibilities</h2><p>You are responsible for maintaining the confidentiality of your credentials and for all activity under your account.</p>
<h2>4. Payment & Billing</h2><p>Subscription fees are billed monthly. Trials convert to paid plans unless cancelled before the trial period ends.</p>
<h2>5. Limitation of Liability</h2><p>RetailPOS is provided "as is" within the limits permitted by applicable law.</p>
</div></section>
<?php require __DIR__ . '/includes/layout-end.php';

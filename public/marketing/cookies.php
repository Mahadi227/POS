<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
$pageTitle = __t('mkt_cookies_title', 'marketing');
require __DIR__ . '/includes/layout-start.php';
?>
<section class="mkt-section"><div class="mkt-container mkt-legal">
<h1 class="mkt-page-hero__title"><?php echo htmlspecialchars($pageTitle); ?></h1>
<p><em><?php echo __t('mkt_legal_updated', 'marketing'); ?></em></p>
<h2>What Are Cookies</h2><p>Cookies are small text files stored on your device to improve your browsing experience.</p>
<h2>How We Use Cookies</h2><ul><li><strong>Essential:</strong> Session management and authentication</li><li><strong>Preferences:</strong> Language selection (EN/FR)</li><li><strong>Analytics:</strong> Anonymous usage statistics to improve our website</li></ul>
<h2>Managing Cookies</h2><p>You can disable cookies in your browser settings. Some features may not work correctly without essential cookies.</p>
</div></section>
<?php require __DIR__ . '/includes/layout-end.php';

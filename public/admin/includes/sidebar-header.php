<?php
declare(strict_types=1);

if (!isset($adminAccent)) {
    require __DIR__ . '/admin-branding.php';
}
$brandLabel = $adminBrandName ?? 'RetailPOS';
?>
<div class="sidebar-header">
    <div class="logo">
        <?php if (($adminLogoUrl ?? '') !== ''): ?>
        <img src="<?php echo htmlspecialchars($adminLogoUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="" class="admin-brand-logo">
        <?php else: ?>
        <span class="material-icons-round">storefront</span>
        <?php endif; ?>
        <h2><?php echo htmlspecialchars($brandLabel, ENT_QUOTES, 'UTF-8'); ?><span class="dot">.</span></h2>
    </div>
    <?php if (($adminCustomDomain ?? '') !== ''): ?>
    <p class="sidebar-domain" title="<?php echo htmlspecialchars($adminCustomDomain, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="material-icons-round" aria-hidden="true">language</span>
        <?php echo htmlspecialchars($adminCustomDomain, ENT_QUOTES, 'UTF-8'); ?>
    </p>
    <?php endif; ?>
</div>

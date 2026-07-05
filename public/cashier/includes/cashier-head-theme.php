<?php
declare(strict_types=1);

if (!isset($adminAccent)) {
    require __DIR__ . '/cashier-branding.php';
}
$themeAccent = $adminAccent;
$themePortal = 'cashier';
$accentEsc = htmlspecialchars($adminAccent, ENT_QUOTES, 'UTF-8');
?>
<meta name="theme-color" content="<?php echo $accentEsc; ?>">
<meta name="theme-accent" content="<?php echo $accentEsc; ?>">
<?php include __DIR__ . '/../../includes/theme-head.php'; ?>
<?php if (($adminFaviconUrl ?? '') !== ''): ?>
<link rel="icon" href="<?php echo htmlspecialchars($adminFaviconUrl, ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
<?php endif; ?>

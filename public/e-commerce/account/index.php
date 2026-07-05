<?php
require_once __DIR__ . '/../includes/bootstrap.php';
if (!$ecomAccount) {
    header('Location: ' . ecom_href('customer/login.php'));
    exit;
}
$pageTitle = __t('ecom_account_title', 'ecommerce');
$activePage = 'account';
$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        unset($_SESSION['ecommerce_account_id']);
        header('Location: ' . ecom_href('home/'));
        exit;
    }
    $accounts->updateProfile($tenantId, $ecomAccountId, (string) ($_POST['name'] ?? ''), (string) ($_POST['phone'] ?? '') ?: null);
    $ecomAccount = $accounts->findById($tenantId, $ecomAccountId);
    $message = __t('ecom_profile_saved', 'ecommerce');
}

require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_account_title', 'ecommerce'); ?></h1>
</section>
<?php if ($message): ?><div class="ecom-alert ecom-alert--success"><?php echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
<div class="ecom-account-grid">
    <form method="post" class="ecom-auth__form">
        <label class="ecom-field"><span><?php echo __t('ecom_name', 'ecommerce'); ?></span><input type="text" name="name" value="<?php echo htmlspecialchars($ecomAccount['name'], ENT_QUOTES, 'UTF-8'); ?>" class="ecom-input"></label>
        <label class="ecom-field"><span><?php echo __t('ecom_email', 'ecommerce'); ?></span><input type="email" value="<?php echo htmlspecialchars($ecomAccount['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled class="ecom-input"></label>
        <label class="ecom-field"><span><?php echo __t('ecom_phone', 'ecommerce'); ?></span><input type="tel" name="phone" value="<?php echo htmlspecialchars($ecomAccount['phone'] ?? '', ENT_QUOTES, 'UTF-8'); ?>" class="ecom-input"></label>
        <button type="submit" class="ecom-btn ecom-btn--primary"><?php echo __t('ecom_save', 'ecommerce'); ?></button>
    </form>
    <nav class="ecom-account-links">
        <a href="<?php echo ecom_href('orders/'); ?>" class="ecom-btn ecom-btn--ghost ecom-btn--block"><?php echo __t('ecom_nav_orders', 'ecommerce'); ?></a>
        <a href="<?php echo ecom_href('wishlist/'); ?>" class="ecom-btn ecom-btn--ghost ecom-btn--block"><?php echo __t('ecom_nav_wishlist', 'ecommerce'); ?></a>
        <form method="post"><button type="submit" name="logout" value="1" class="ecom-btn ecom-btn--ghost ecom-btn--block"><?php echo __t('ecom_logout', 'ecommerce'); ?></button></form>
    </nav>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

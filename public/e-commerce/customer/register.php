<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_register_title', 'ecommerce');
$error = '';

if ($ecomAccount) {
    header('Location: ' . ecom_href('account/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $id = $accounts->register(
            $tenantId,
            (string) ($_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? ''),
            (string) ($_POST['name'] ?? ''),
            (string) ($_POST['phone'] ?? '') ?: null
        );
        $_SESSION['ecommerce_account_id'] = $id;
        header('Location: ' . ecom_href('account/'));
        exit;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-auth">
    <h1><?php echo __t('ecom_register_title', 'ecommerce'); ?></h1>
    <?php if ($error): ?><div class="ecom-alert ecom-alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post" class="ecom-auth__form">
        <label class="ecom-field"><span><?php echo __t('ecom_name', 'ecommerce'); ?></span><input type="text" name="name" required class="ecom-input"></label>
        <label class="ecom-field"><span><?php echo __t('ecom_email', 'ecommerce'); ?></span><input type="email" name="email" required class="ecom-input"></label>
        <label class="ecom-field"><span><?php echo __t('ecom_phone', 'ecommerce'); ?></span><input type="tel" name="phone" class="ecom-input"></label>
        <label class="ecom-field"><span><?php echo __t('ecom_password', 'ecommerce'); ?></span><input type="password" name="password" required minlength="6" class="ecom-input"></label>
        <button type="submit" class="ecom-btn ecom-btn--primary ecom-btn--block"><?php echo __t('ecom_register_btn', 'ecommerce'); ?></button>
    </form>
    <p><a href="<?php echo ecom_href('customer/login.php'); ?>"><?php echo __t('ecom_login_link', 'ecommerce'); ?></a></p>
</section>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

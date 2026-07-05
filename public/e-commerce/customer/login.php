<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_login_title', 'ecommerce');
$error = '';

if ($ecomAccount) {
    header('Location: ' . ecom_href('account/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $acc = $accounts->login(
            $tenantId,
            (string) ($_POST['login'] ?? $_POST['email'] ?? ''),
            (string) ($_POST['password'] ?? '')
        );
        $_SESSION['ecommerce_account_id'] = (int) $acc['id'];
        $redirect = trim($_GET['redirect'] ?? 'account');
        $dest = $redirect === 'checkout' ? ecom_href('checkout/') : ecom_href('account/');
        header('Location: ' . $dest);
        exit;
    } catch (Throwable $e) {
        $error = __t('ecom_login_error', 'ecommerce');
    }
}

$loginValue = htmlspecialchars((string) ($_POST['login'] ?? $_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8');

require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-auth">
    <h1><?php echo __t('ecom_login_title', 'ecommerce'); ?></h1>
    <p class="ecom-auth__sub"><?php echo __t('ecom_login_sub', 'ecommerce'); ?></p>
    <?php if ($error): ?><div class="ecom-alert ecom-alert--error"><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div><?php endif; ?>
    <form method="post" class="ecom-auth__form">
        <label class="ecom-field">
            <span><?php echo __t('ecom_login_identifier', 'ecommerce'); ?></span>
            <input type="text" name="login" required class="ecom-input" autocomplete="username"
                   value="<?php echo $loginValue; ?>"
                   placeholder="<?php echo __t('ecom_login_identifier_placeholder', 'ecommerce'); ?>">
        </label>
        <label class="ecom-field">
            <span><?php echo __t('ecom_password', 'ecommerce'); ?></span>
            <input type="password" name="password" required class="ecom-input" autocomplete="current-password">
        </label>
        <button type="submit" class="ecom-btn ecom-btn--primary ecom-btn--block"><?php echo __t('ecom_nav_login', 'ecommerce'); ?></button>
    </form>
    <p><a href="<?php echo ecom_href('customer/register.php'); ?>"><?php echo __t('ecom_register_link', 'ecommerce'); ?></a></p>
</section>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

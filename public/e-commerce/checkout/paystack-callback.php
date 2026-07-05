<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/bootstrap.php';

$reference = trim((string) ($_GET['reference'] ?? $_GET['trxref'] ?? ''));
$error = '';
$saleId = 0;

if ($reference === '') {
    $error = __t('ecom_paystack_missing_reference', 'ecommerce');
} else {
    try {
        if (!$paystackEnabled) {
            throw new RuntimeException(__t('ecom_paystack_not_configured', 'ecommerce'));
        }

        $result = $orders->completePaystackPayment($reference, $tenantId, $storeId, $paystack);
        $saleId = (int) ($result['sale_id'] ?? 0);
        unset($_SESSION['ecom_paystack_sale_id']);

        if ($saleId > 0) {
            header('Location: ' . ecom_href('orders/view.php?id=' . $saleId . '&placed=1&paid=1'));
            exit;
        }

        $error = __t('ecom_paystack_verify_failed', 'ecommerce');
    } catch (Throwable $e) {
        $error = $e->getMessage();
        $pending = $orders->findByPaystackReference($reference);
        if ($pending) {
            $saleId = (int) ($pending['id'] ?? 0);
        }
    }
}

$pageTitle = __t('ecom_paystack_callback_title', 'ecommerce');
$activePage = 'checkout';
$bodyClass = 'ecom-page-checkout';
$extraStyles = ['ecommerce-cart.css'];

require __DIR__ . '/../includes/layout-start.php';
?>
<header class="ecom-page-head ecom-page-head--cart">
    <div>
        <h1><?php echo __t('ecom_paystack_callback_title', 'ecommerce'); ?></h1>
    </div>
</header>

<div class="ecom-checkout-card">
    <?php if ($error !== ''): ?>
    <div class="ecom-alert ecom-alert--error" role="alert">
        <span class="material-icons-round" aria-hidden="true">error_outline</span>
        <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
    </div>
    <p class="ecom-checkout-card__text"><?php echo __t('ecom_paystack_callback_failed_hint', 'ecommerce'); ?></p>
    <div class="ecom-checkout-card__actions">
        <?php if ($saleId > 0): ?>
        <a href="<?php echo ecom_href('orders/view.php?id=' . $saleId); ?>" class="ecom-btn ecom-btn--accent">
            <?php echo __t('ecom_view', 'ecommerce'); ?>
        </a>
        <?php endif; ?>
        <a href="<?php echo ecom_href('checkout/'); ?>" class="ecom-btn ecom-btn--ghost">
            <?php echo __t('ecom_try_again', 'ecommerce'); ?>
        </a>
    </div>
    <?php else: ?>
    <p class="ecom-checkout-card__text"><?php echo __t('ecom_paystack_processing', 'ecommerce'); ?></p>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

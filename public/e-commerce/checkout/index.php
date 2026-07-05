<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __t('ecom_checkout_title', 'ecommerce');
$activePage = 'checkout';
$bodyClass = 'ecom-page-checkout';
$extraStyles = ['ecommerce-cart.css'];
$extraScripts = ['ecommerce/cart.js'];
$checkoutStep = 2;
$loadPaystackInline = $paystackEnabled;

$items = $cart->items();
$subtotal = $cart->subtotal();
$tax = round($subtotal * ($taxRate / 100), 2);
$total = $subtotal + $tax;
$itemCount = $cart->count();
$error = '';
$selectedPayment = (string) ($_POST['payment_method'] ?? $_GET['payment'] ?? 'card');
$allowedPayments = ['card', 'mobile_money', 'cash_on_delivery'];
if (!in_array($selectedPayment, $allowedPayments, true)) {
    $selectedPayment = 'card';
}

if ($items === []) {
    header('Location: ' . ecom_href('cart/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $payment = (string) ($_POST['payment_method'] ?? 'card');
    if (!in_array($payment, $allowedPayments, true)) {
        $payment = 'card';
    }

    try {
        [$customerId, $guestAccountId] = ecom_resolve_checkout_customer($accounts, $tenantId, $ecomAccount, $_POST);
        if (!$ecomAccount && $guestAccountId > 0) {
            $_SESSION['ecommerce_account_id'] = $guestAccountId;
            $ecomAccount = $accounts->findById($tenantId, $guestAccountId);
        }

        if ($payment === 'cash_on_delivery') {
            $result = $orders->placeOrder($tenantId, $storeId, array_values($items), $total, $tax, $customerId, $payment);
            $cart->clear();
            header('Location: ' . ecom_href('orders/view.php?id=' . (int) $result['sale_id'] . '&placed=1'));
            exit;
        }

        // No-JS / Pop-unavailable fallback: full redirect to Paystack hosted page
        if (!empty($_POST['paystack_redirect']) && in_array($payment, ['card', 'mobile_money'], true)) {
            if (!$paystackEnabled) {
                throw new RuntimeException(__t('ecom_paystack_not_configured', 'ecommerce'));
            }

            $checkoutPhone = (string) ($ecomAccount['phone'] ?? $_POST['checkout_phone'] ?? '');
            $checkoutEmail = EcommerceAccountService::paystackEmail(
                (string) ($ecomAccount['email'] ?? $_POST['checkout_email'] ?? ''),
                $tenantId,
                $checkoutPhone
            );

            $reference = 'ECOM-' . $tenantId . '-' . strtoupper(bin2hex(random_bytes(6)));
            $result = $orders->createPaystackPendingOrder(
                $tenantId,
                $storeId,
                array_values($items),
                $total,
                $tax,
                $customerId,
                $payment,
                $reference
            );

            $scriptDir = dirname(str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '/e-commerce/checkout'));
            $callbackUrl = rtrim(request_app_base_url(), '/') . $scriptDir . '/paystack-callback.php';
            $channels = $payment === 'mobile_money' ? ['mobile_money'] : ['card'];

            $init = $paystack->initializeCheckout($tenantId, [
                'email' => $checkoutEmail,
                'amount' => $total,
                'currency' => $currency,
                'reference' => $reference,
                'callback_url' => $callbackUrl,
                'channels' => $channels,
                'metadata' => [
                    'sale_id' => (string) $result['sale_id'],
                    'tenant_id' => (string) $tenantId,
                    'payment_method' => $payment,
                ],
            ]);

            $redirectUrl = (string) ($init['authorization_url'] ?? '');
            if ($redirectUrl === '') {
                throw new RuntimeException(__t('ecom_paystack_init_failed', 'ecommerce'));
            }

            $_SESSION['ecom_paystack_sale_id'] = (int) $result['sale_id'];
            $cart->clear();
            header('Location: ' . $redirectUrl);
            exit;
        }
    } catch (Throwable $e) {
        if ($e instanceof InvalidArgumentException) {
            $error = match ($e->getMessage()) {
                'Phone is required' => __t('ecom_checkout_phone_required', 'ecommerce'),
                'Name is required' => __t('ecom_checkout_name_required', 'ecommerce'),
                'Invalid email format' => __t('ecom_checkout_email_invalid', 'ecommerce'),
                'Invalid email' => __t('ecom_checkout_email_invalid', 'ecommerce'),
                'Invalid phone number' => __t('ecom_checkout_phone_invalid', 'ecommerce'),
                default => $e->getMessage(),
            };
        } else {
            $error = $e->getMessage();
        }
    }
}

$checkoutAccountEmail = $ecomAccount ? trim((string) ($ecomAccount['email'] ?? '')) : '';

require __DIR__ . '/../includes/layout-start.php';
?>
<header class="ecom-page-head ecom-page-head--cart">
    <div>
        <h1><?php echo __t('ecom_checkout_title', 'ecommerce'); ?></h1>
        <p class="ecom-page-head__sub"><?php echo __t('ecom_checkout_sub', 'ecommerce'); ?></p>
    </div>
    <a href="<?php echo ecom_href('cart/'); ?>" class="ecom-btn ecom-btn--ghost ecom-btn--sm">
        <span class="material-icons-round" aria-hidden="true">arrow_back</span>
        <?php echo __t('ecom_back_to_cart', 'ecommerce'); ?>
    </a>
</header>

<?php include __DIR__ . '/../includes/partials/checkout-steps.php'; ?>

<?php if ($error !== ''): ?>
<div class="ecom-alert ecom-alert--error" role="alert">
    <span class="material-icons-round" aria-hidden="true">error_outline</span>
    <?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?>
</div>
<?php endif; ?>

<div class="ecom-cart-layout ecom-checkout-layout">
    <section class="ecom-checkout-main" aria-labelledby="ecom-payment-title">
        <form method="post" class="ecom-checkout-form" id="ecom-checkout-form" novalidate>
            <input type="hidden" name="paystack_redirect" id="ecomPaystackRedirect" value="0">

            <?php if ($ecomAccount): ?>
            <div class="ecom-checkout-card">
                <h2 class="ecom-checkout-card__title">
                    <span class="material-icons-round" aria-hidden="true">person</span>
                    <?php echo __t('ecom_checkout_account', 'ecommerce'); ?>
                </h2>
                <p class="ecom-checkout-card__text">
                    <?php echo htmlspecialchars($ecomAccount['name'], ENT_QUOTES, 'UTF-8'); ?>
                    · <?php echo htmlspecialchars($ecomAccount['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>
                    <?php if (!empty($ecomAccount['phone'])): ?>
                    · <?php echo htmlspecialchars($ecomAccount['phone'], ENT_QUOTES, 'UTF-8'); ?>
                    <?php endif; ?>
                </p>
            </div>
            <?php else: ?>
            <div class="ecom-checkout-card ecom-checkout-card--guest">
                <h2 class="ecom-checkout-card__title">
                    <span class="material-icons-round" aria-hidden="true">person_outline</span>
                    <?php echo __t('ecom_checkout_guest', 'ecommerce'); ?>
                </h2>
                <p class="ecom-checkout-card__text">
                    <?php echo __t('ecom_checkout_guest_details', 'ecommerce'); ?>
                    <?php echo __t('ecom_or', 'ecommerce'); ?>
                    <a href="<?php echo ecom_href('customer/login.php?redirect=checkout'); ?>"><?php echo __t('ecom_nav_login', 'ecommerce'); ?></a>
                </p>
                <div class="ecom-checkout-guest-fields">
                    <label class="ecom-field">
                        <span><?php echo __t('ecom_name', 'ecommerce'); ?> <span class="ecom-required">*</span></span>
                        <input type="text" name="checkout_name" id="ecomCheckoutName" autocomplete="name" required
                               value="<?php echo htmlspecialchars((string) ($_POST['checkout_name'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="<?php echo __t('ecom_checkout_name_placeholder', 'ecommerce'); ?>">
                    </label>
                    <label class="ecom-field">
                        <span><?php echo __t('ecom_email', 'ecommerce'); ?> <span class="ecom-optional">(<?php echo __t('ecom_optional', 'ecommerce'); ?>)</span></span>
                        <input type="email" name="checkout_email" id="ecomCheckoutEmail" autocomplete="email"
                               value="<?php echo htmlspecialchars((string) ($_POST['checkout_email'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="<?php echo __t('ecom_checkout_email_placeholder', 'ecommerce'); ?>">
                        <small class="ecom-field__hint"><?php echo __t('ecom_checkout_email_hint', 'ecommerce'); ?></small>
                    </label>
                    <label class="ecom-field">
                        <span><?php echo __t('ecom_phone', 'ecommerce'); ?> <span class="ecom-required">*</span></span>
                        <input type="tel" name="checkout_phone" id="ecomCheckoutPhone" autocomplete="tel" required
                               value="<?php echo htmlspecialchars((string) ($_POST['checkout_phone'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>"
                               placeholder="<?php echo __t('ecom_checkout_phone_placeholder', 'ecommerce'); ?>">
                    </label>
                </div>
                <p class="ecom-checkout-card__hint"><?php echo __t('ecom_checkout_guest_account_hint', 'ecommerce'); ?></p>
            </div>
            <?php endif; ?>

            <div class="ecom-checkout-card">
                <h2 id="ecom-payment-title" class="ecom-checkout-card__title">
                    <span class="material-icons-round" aria-hidden="true">payments</span>
                    <?php echo __t('ecom_payment_method', 'ecommerce'); ?>
                </h2>
                <p class="ecom-checkout-card__hint"><?php echo __t('ecom_payment_hint', 'ecommerce'); ?></p>

                <div class="ecom-pay-options" role="radiogroup" aria-labelledby="ecom-payment-title">
                    <?php
                    $payOptions = [
                        'card' => ['icon' => 'credit_card', 'label' => 'ecom_pay_card', 'desc' => 'ecom_pay_card_desc'],
                        'mobile_money' => ['icon' => 'smartphone', 'label' => 'ecom_pay_mobile', 'desc' => 'ecom_pay_mobile_desc'],
                        'cash_on_delivery' => ['icon' => 'local_shipping', 'label' => 'ecom_pay_cod', 'desc' => 'ecom_pay_cod_desc'],
                    ];
                    foreach ($payOptions as $value => $opt):
                        $checked = $selectedPayment === $value;
                        $disabled = in_array($value, ['card', 'mobile_money'], true) && !$paystackEnabled;
                    ?>
                    <label class="ecom-pay-option<?php echo $checked ? ' is-selected' : ''; ?><?php echo $disabled ? ' is-disabled' : ''; ?>">
                        <input type="radio" name="payment_method" value="<?php echo htmlspecialchars($value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $checked ? ' checked' : ''; ?><?php echo $disabled ? ' disabled' : ''; ?> class="ecom-pay-option__input">
                        <span class="ecom-pay-option__icon material-icons-round" aria-hidden="true"><?php echo $opt['icon']; ?></span>
                        <span class="ecom-pay-option__body">
                            <span class="ecom-pay-option__label"><?php echo __t($opt['label'], 'ecommerce'); ?></span>
                            <span class="ecom-pay-option__desc"><?php echo __t($opt['desc'], 'ecommerce'); ?></span>
                        </span>
                        <span class="ecom-pay-option__check material-icons-round" aria-hidden="true">check_circle</span>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php if (!$paystackEnabled): ?>
                <p class="ecom-checkout-card__hint ecom-checkout-card__hint--warn">
                    <?php echo __t('ecom_paystack_disabled_hint', 'ecommerce'); ?>
                </p>
                <?php else: ?>
                <p class="ecom-checkout-card__hint">
                    <?php echo __t('ecom_paystack_powered', 'ecommerce'); ?>
                </p>
                <?php endif; ?>
            </div>

            <label class="ecom-checkout-terms">
                <input type="checkbox" name="terms_accepted" value="1" required>
                <span><?php echo __t('ecom_terms_agree', 'ecommerce'); ?></span>
            </label>

            <button type="submit" class="ecom-btn ecom-btn--accent ecom-btn--block ecom-checkout-submit" id="ecomCheckoutSubmit">
                <span class="material-icons-round" aria-hidden="true">lock</span>
                <span id="ecomCheckoutSubmitLabel"><?php echo __t('ecom_place_order', 'ecommerce'); ?></span> · <?php echo ecom_money($total); ?>
            </button>

            <p class="ecom-checkout-secure-note">
                <span class="material-icons-round" aria-hidden="true">shield</span>
                <?php echo __t('ecom_checkout_secure_note', 'ecommerce'); ?>
            </p>
        </form>
    </section>

    <?php
    $showLineItems = true;
    $showCheckoutBtn = false;
    include __DIR__ . '/../includes/partials/cart-summary.php';
    ?>
</div>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

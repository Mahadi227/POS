<?php
require_once __DIR__ . '/../includes/bootstrap.php';

$pageTitle = __t('ecom_cart_title', 'ecommerce');
$activePage = 'cart';
$bodyClass = 'ecom-page-cart';
$extraStyles = ['ecommerce-cart.css'];
$extraScripts = ['ecommerce/cart.js'];
$checkoutStep = 1;

$items = $cart->items();
$subtotal = $cart->subtotal();
$tax = round($subtotal * ($taxRate / 100), 2);
$total = $subtotal + $tax;
$itemCount = $cart->count();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $pid = (int) ($_POST['product_id'] ?? 0);
    if ($action === 'update') {
        $cart->update($pid, (int) ($_POST['quantity'] ?? 0));
    } elseif ($action === 'remove') {
        $cart->remove($pid);
    } elseif ($action === 'clear') {
        $cart->clear();
    }
    header('Location: ' . ecom_href('cart/'));
    exit;
}

require __DIR__ . '/../includes/layout-start.php';
?>
<header class="ecom-page-head ecom-page-head--cart">
    <div>
        <h1><?php echo __t('ecom_cart_title', 'ecommerce'); ?></h1>
        <p class="ecom-page-head__sub"><?php echo __t('ecom_cart_sub', 'ecommerce'); ?></p>
    </div>
    <?php if ($items !== []): ?>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--ghost ecom-btn--sm">
        <span class="material-icons-round" aria-hidden="true">arrow_back</span>
        <?php echo __t('ecom_continue_shopping', 'ecommerce'); ?>
    </a>
    <?php endif; ?>
</header>

<?php include __DIR__ . '/../includes/partials/checkout-steps.php'; ?>

<?php if ($items === []): ?>
<section class="ecom-cart-empty">
    <div class="ecom-cart-empty__icon" aria-hidden="true">
        <span class="material-icons-round">shopping_cart</span>
    </div>
    <h2><?php echo __t('ecom_cart_empty_title', 'ecommerce'); ?></h2>
    <p><?php echo __t('ecom_cart_empty', 'ecommerce'); ?></p>
    <a href="<?php echo ecom_href('shop/'); ?>" class="ecom-btn ecom-btn--accent"><?php echo __t('ecom_shop_now', 'ecommerce'); ?></a>
</section>
<?php else: ?>
<div class="ecom-cart-layout">
    <section class="ecom-cart-main" aria-labelledby="ecom-cart-items-title">
        <div class="ecom-cart-main__toolbar">
            <h2 id="ecom-cart-items-title" class="ecom-cart-main__title"><?php echo __t('ecom_cart_items', 'ecommerce'); ?></h2>
            <form method="post" class="ecom-cart-clear-form" data-confirm="<?php echo htmlspecialchars(__t('ecom_clear_cart_confirm', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">
                <input type="hidden" name="action" value="clear">
                <button type="submit" class="ecom-btn ecom-btn--ghost ecom-btn--sm ecom-cart-clear-btn">
                    <span class="material-icons-round" aria-hidden="true">delete_outline</span>
                    <?php echo __t('ecom_clear_cart', 'ecommerce'); ?>
                </button>
            </form>
        </div>

        <div class="ecom-cart-table-wrap">
            <table class="ecom-cart-table">
                <thead>
                    <tr>
                        <th scope="col"><?php echo __t('ecom_product', 'ecommerce'); ?></th>
                        <th scope="col" class="ecom-cart-table__col-price"><?php echo __t('ecom_price', 'ecommerce'); ?></th>
                        <th scope="col" class="ecom-cart-table__col-qty"><?php echo __t('ecom_qty', 'ecommerce'); ?></th>
                        <th scope="col" class="ecom-cart-table__col-total"><?php echo __t('ecom_line_total', 'ecommerce'); ?></th>
                        <th scope="col" class="ecom-cart-table__col-action"><span class="ecom-sr-only"><?php echo __t('ecom_remove', 'ecommerce'); ?></span></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($items as $item):
                        $pid = (int) $item['product_id'];
                        $qty = (int) $item['quantity'];
                        $stock = (int) ($item['stock_quantity'] ?? 0);
                        $lineTotal = (float) ($item['line_total'] ?? ($qty * $item['unit_price']));
                        $productUrl = ecom_href('products/view.php?id=' . $pid);
                        $imgUrl = ecom_product_image($item['image_url'] ?? null);
                        $lowStock = $stock > 0 && $qty >= $stock;
                    ?>
                    <tr class="ecom-cart-table__row" data-product-id="<?php echo $pid; ?>">
                        <td class="ecom-cart-table__product">
                            <a href="<?php echo htmlspecialchars($productUrl, ENT_QUOTES, 'UTF-8'); ?>" class="ecom-cart-product">
                                <span class="ecom-cart-product__thumb">
                                    <?php if ($imgUrl): ?>
                                    <img src="<?php echo htmlspecialchars($imgUrl, ENT_QUOTES, 'UTF-8'); ?>" alt="">
                                    <?php else: ?>
                                    <span class="material-icons-round" aria-hidden="true">inventory_2</span>
                                    <?php endif; ?>
                                </span>
                                <span class="ecom-cart-product__info">
                                    <span class="ecom-cart-product__name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></span>
                                    <?php if ($lowStock): ?>
                                    <span class="ecom-cart-product__stock ecom-cart-product__stock--warn"><?php echo __t('ecom_max_stock', 'ecommerce', ['qty' => (string) $stock]); ?></span>
                                    <?php elseif ($stock > 0): ?>
                                    <span class="ecom-cart-product__stock"><?php echo __t('ecom_in_stock', 'ecommerce', ['qty' => (string) $stock]); ?></span>
                                    <?php endif; ?>
                                </span>
                            </a>
                        </td>
                        <td class="ecom-cart-table__col-price" data-label="<?php echo __t('ecom_price', 'ecommerce'); ?>">
                            <?php echo ecom_money((float) $item['unit_price']); ?>
                        </td>
                        <td class="ecom-cart-table__col-qty" data-label="<?php echo __t('ecom_qty', 'ecommerce'); ?>">
                            <form method="post" class="ecom-qty-form" data-product-id="<?php echo $pid; ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                <div class="ecom-qty-stepper">
                                    <button type="button" class="ecom-qty-stepper__btn" data-qty-delta="-1" aria-label="<?php echo htmlspecialchars(__t('ecom_decrease_qty', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">−</button>
                                    <input type="number" name="quantity" class="ecom-qty-stepper__input" min="1" max="<?php echo max(1, $stock); ?>" value="<?php echo $qty; ?>" aria-label="<?php echo htmlspecialchars(__t('ecom_qty', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <button type="button" class="ecom-qty-stepper__btn" data-qty-delta="1" aria-label="<?php echo htmlspecialchars(__t('ecom_increase_qty', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">+</button>
                                </div>
                            </form>
                        </td>
                        <td class="ecom-cart-table__col-total" data-label="<?php echo __t('ecom_line_total', 'ecommerce'); ?>">
                            <span class="ecom-cart-line-total"><?php echo ecom_money($lineTotal); ?></span>
                        </td>
                        <td class="ecom-cart-table__col-action">
                            <form method="post">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="product_id" value="<?php echo $pid; ?>">
                                <button type="submit" class="ecom-cart-remove" title="<?php echo htmlspecialchars(__t('ecom_remove', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>" aria-label="<?php echo htmlspecialchars(__t('ecom_remove', 'ecommerce'), ENT_QUOTES, 'UTF-8'); ?>">
                                    <span class="material-icons-round">close</span>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="ecom-cart-mobile-cta">
            <a href="<?php echo ecom_href('checkout/'); ?>" class="ecom-btn ecom-btn--accent ecom-btn--block">
                <span class="material-icons-round" aria-hidden="true">lock</span>
                <?php echo __t('ecom_proceed_checkout', 'ecommerce'); ?> · <?php echo ecom_money($total); ?>
            </a>
        </div>
    </section>

    <?php
    $showLineItems = false;
    $showCheckoutBtn = true;
    include __DIR__ . '/../includes/partials/cart-summary.php';
    ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

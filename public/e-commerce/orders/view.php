<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$customerId = $ecomAccount && !empty($ecomAccount['customer_id']) ? (int) $ecomAccount['customer_id'] : null;
$order = $id > 0 ? $orders->getOrder($id, $customerId) : null;

if (!$order && $id > 0 && !empty($_GET['placed'])) {
    $order = $orders->getOrder($id);
    if ($order && !empty($order['customer_id']) && (!$ecomAccount || (int) $order['customer_id'] !== (int) ($ecomAccount['customer_id'] ?? 0))) {
        $order = null;
    }
}

if (!$order) {
    http_response_code(404);
    $pageTitle = '404';
    require __DIR__ . '/../includes/layout-start.php';
    echo '<p class="ecom-empty">' . __t('ecom_order_not_found', 'ecommerce') . '</p>';
    require __DIR__ . '/../includes/layout-end.php';
    exit;
}

$pageTitle = __t('ecom_order_title', 'ecommerce', ['no' => $order['receipt_no']]);
$statusLabel = ecom_order_status_label($order);
$statusClass = 'ecom-order-status ecom-order-status--' . preg_replace('/[^a-z_]/', '', (string) ($order['status'] ?? 'completed'));
$extraStyles = ['ecommerce-cart.css'];
require __DIR__ . '/../includes/layout-start.php';
?>
<?php if (!empty($_GET['placed'])): ?>
<div class="ecom-alert ecom-alert--success ecom-alert--order-placed">
    <span class="material-icons-round" aria-hidden="true"><?php echo ($order['status'] ?? '') === 'pending' ? 'schedule' : 'check_circle'; ?></span>
    <span><?php echo htmlspecialchars(ecom_order_placed_message($order), ENT_QUOTES, 'UTF-8'); ?></span>
</div>
<?php endif; ?>
<section class="ecom-page-head">
    <h1><?php echo htmlspecialchars($order['receipt_no'], ENT_QUOTES, 'UTF-8'); ?></h1>
    <p class="<?php echo htmlspecialchars($statusClass, ENT_QUOTES, 'UTF-8'); ?>">
        <span class="material-icons-round" aria-hidden="true"><?php
            echo match ($order['checkout_method'] ?? '') {
                'mobile_money' => 'smartphone',
                'cash_on_delivery' => 'local_shipping',
                default => ($order['status'] ?? '') === 'pending' ? 'schedule' : 'check_circle',
            };
        ?></span>
        <?php echo __t('ecom_order_status', 'ecommerce', ['status' => $statusLabel]); ?>
    </p>
</section>
<div class="ecom-table-wrap">
    <table class="ecom-table">
        <thead><tr><th><?php echo __t('ecom_product', 'ecommerce'); ?></th><th><?php echo __t('ecom_qty', 'ecommerce'); ?></th><th><?php echo __t('ecom_price', 'ecommerce'); ?></th><th><?php echo __t('ecom_subtotal', 'ecommerce'); ?></th></tr></thead>
        <tbody>
        <?php foreach ($order['items'] as $line): ?>
        <tr>
            <td><?php echo htmlspecialchars($line['product_name'] ?? '#' . $line['product_id'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo (int) $line['quantity']; ?></td>
            <td><?php echo ecom_money((float) $line['unit_price']); ?></td>
            <td><?php echo ecom_money((float) $line['subtotal']); ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot><tr><td colspan="3"><?php echo __t('ecom_total', 'ecommerce'); ?></td><td><?php echo ecom_money((float) $order['total']); ?></td></tr></tfoot>
    </table>
</div>
<a href="<?php echo ecom_href('orders/'); ?>" class="ecom-btn ecom-btn--ghost"><?php echo __t('ecom_back_orders', 'ecommerce'); ?></a>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

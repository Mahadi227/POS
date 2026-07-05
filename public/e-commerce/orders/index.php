<?php
require_once __DIR__ . '/../includes/bootstrap.php';
$pageTitle = __t('ecom_orders_title', 'ecommerce');
$activePage = 'orders';
$orderList = [];
if ($ecomAccount && !empty($ecomAccount['customer_id'])) {
    $orderList = $orders->listOrdersForCustomer((int) $ecomAccount['customer_id']);
}
require __DIR__ . '/../includes/layout-start.php';
?>
<section class="ecom-page-head">
    <h1><?php echo __t('ecom_orders_title', 'ecommerce'); ?></h1>
</section>
<?php if (!$ecomAccount): ?>
<p class="ecom-empty"><?php echo __t('ecom_orders_login', 'ecommerce'); ?> <a href="<?php echo ecom_href('customer/login.php'); ?>"><?php echo __t('ecom_nav_login', 'ecommerce'); ?></a></p>
<?php elseif ($orderList === []): ?>
<p class="ecom-empty"><?php echo __t('ecom_no_orders', 'ecommerce'); ?></p>
<?php else: ?>
<div class="ecom-table-wrap">
    <table class="ecom-table">
        <thead><tr><th>#</th><th><?php echo __t('ecom_receipt', 'ecommerce'); ?></th><th><?php echo __t('ecom_total', 'ecommerce'); ?></th><th><?php echo __t('ecom_date', 'ecommerce'); ?></th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orderList as $o): ?>
        <tr>
            <td><?php echo (int) $o['id']; ?></td>
            <td><?php echo htmlspecialchars($o['receipt_no'], ENT_QUOTES, 'UTF-8'); ?></td>
            <td><?php echo ecom_money((float) $o['total']); ?></td>
            <td><?php echo htmlspecialchars($o['created_at'] ?? '', ENT_QUOTES, 'UTF-8'); ?></td>
            <td><a href="<?php echo ecom_href('orders/view.php?id=' . (int) $o['id']); ?>"><?php echo __t('ecom_view', 'ecommerce'); ?></a></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/layout-end.php'; ?>

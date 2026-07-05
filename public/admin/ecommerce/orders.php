<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeEcomPage = 'orders';
$pageTitle = __t('ecom_orders_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-orders.js'];
$pageI18n = ecom_i18n([
    'ecom_orders_subtitle', 'ecom_no_orders', 'col_receipt', 'col_customer', 'col_date',
    'col_amount', 'col_status', 'ecom_view_order', 'ecom_order_items', 'ecom_qty', 'ecom_product',
    'ecom_payment_method', 'ecom_pay_card', 'ecom_pay_mobile', 'ecom_pay_cod', 'ecom_accept_order',
    'ecom_cod_pending_hint', 'ecom_paystack_pending_hint', 'ecom_status_pending', 'ecom_status_pending_payment', 'ecom_status_completed', 'ecom_status_cancelled',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_orders_subtitle', 'admin'); ?></p>
</section>

<div class="ecom-table-wrap">
    <table class="ecom-table" id="ecomOrdersTable">
        <thead>
            <tr>
                <th><?php echo __t('col_receipt', 'admin'); ?></th>
                <th><?php echo __t('col_customer', 'admin'); ?></th>
                <th><?php echo __t('col_date', 'admin'); ?></th>
                <th><?php echo __t('col_amount', 'admin'); ?></th>
                <th><?php echo __t('ecom_payment_method', 'admin'); ?></th>
                <th><?php echo __t('col_status', 'admin'); ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody><tr><td colspan="7"><?php echo __t('loading', 'admin'); ?></td></tr></tbody>
    </table>
</div>

<dialog class="ecom-modal" id="ecomOrderModal">
    <div class="ecom-modal__head">
        <h2 id="ecomOrderModalTitle"><?php echo __t('ecom_view_order', 'admin'); ?></h2>
        <button type="button" class="icon-btn" id="ecomOrderModalClose" aria-label="<?php echo __t('close', 'admin'); ?>">
            <span class="material-icons-round">close</span>
        </button>
    </div>
    <div class="ecom-modal__body" id="ecomOrderModalBody"></div>
</dialog>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

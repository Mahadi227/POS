<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

$activeEcomPage = 'customers';
$pageTitle = __t('ecom_customers_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-customers.js'];
$pageI18n = ecom_i18n([
    'ecom_customers_subtitle', 'ecom_no_customers', 'ecom_col_email', 'ecom_col_name', 'ecom_col_phone',
    'ecom_col_registered', 'ecom_col_last_login', 'col_actions', 'ecom_add_customer', 'ecom_edit_customer',
    'ecom_customer_password', 'ecom_customer_password_hint', 'ecom_saved', 'delete', 'delete_confirm',
    'save', 'cancel', 'close', 'edit',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head ecom-page-head--split">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_customers_subtitle', 'admin'); ?></p>
    <?php if ($canManageEcom): ?>
    <button type="button" class="ecom-btn ecom-btn--primary" id="ecomAddCustomerBtn">
        <span class="material-icons-round">person_add</span>
        <?php echo __t('ecom_add_customer', 'admin'); ?>
    </button>
    <?php endif; ?>
</section>

<div class="ecom-table-wrap">
    <table class="ecom-table" id="ecomCustomersTable">
        <thead>
            <tr>
                <th><?php echo __t('ecom_col_name', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_email', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_phone', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_registered', 'admin'); ?></th>
                <th><?php echo __t('ecom_col_last_login', 'admin'); ?></th>
                <?php if ($canManageEcom): ?>
                <th><?php echo __t('col_actions', 'admin'); ?></th>
                <?php endif; ?>
            </tr>
        </thead>
        <tbody><tr><td colspan="<?php echo $canManageEcom ? 6 : 5; ?>"><?php echo __t('loading', 'admin'); ?></td></tr></tbody>
    </table>
</div>

<?php if ($canManageEcom): ?>
<dialog class="ecom-modal" id="ecomCustomerModal">
    <form id="ecomCustomerForm" class="ecom-form">
        <div class="ecom-modal__head">
            <h2 id="ecomCustomerModalTitle"><?php echo __t('ecom_add_customer', 'admin'); ?></h2>
            <button type="button" class="icon-btn" data-close-modal aria-label="<?php echo __t('close', 'admin'); ?>">
                <span class="material-icons-round">close</span>
            </button>
        </div>
        <div class="ecom-modal__body">
            <input type="hidden" name="id" id="ecomCustomerId">
            <label class="ecom-field">
                <span><?php echo __t('ecom_col_name', 'admin'); ?> *</span>
                <input type="text" name="name" id="ecomCustomerName" required>
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_col_phone', 'admin'); ?> *</span>
                <input type="tel" name="phone" id="ecomCustomerPhone" required>
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_col_email', 'admin'); ?></span>
                <input type="email" name="email" id="ecomCustomerEmail" autocomplete="off">
            </label>
            <label class="ecom-field">
                <span><?php echo __t('ecom_customer_password', 'admin'); ?></span>
                <input type="password" name="password" id="ecomCustomerPassword" autocomplete="new-password">
                <small class="ecom-field__hint"><?php echo __t('ecom_customer_password_hint', 'admin'); ?></small>
            </label>
        </div>
        <div class="ecom-modal__foot">
            <button type="button" class="ecom-btn ecom-btn--ghost" data-close-modal><?php echo __t('cancel', 'admin'); ?></button>
            <button type="submit" class="ecom-btn ecom-btn--primary"><?php echo __t('save', 'admin'); ?></button>
        </div>
    </form>
</dialog>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

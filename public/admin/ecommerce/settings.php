<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';

if (!$canManageEcom) {
    header('Location: dashboard.php');
    exit;
}

$activeEcomPage = 'settings';
$pageTitle = __t('ecom_settings_title', 'admin');
$extraScripts = ['ecommerce-common.js', 'ecommerce-settings.js'];
$pageI18n = ecom_i18n([
    'ecom_settings_subtitle', 'ecom_default_store', 'ecom_currency', 'ecom_tax_rate', 'ecom_saved',
    'ecom_paystack_section', 'ecom_paystack_enable', 'ecom_paystack_public_key', 'ecom_paystack_secret_key',
    'ecom_paystack_secret_placeholder', 'ecom_paystack_secret_saved', 'ecom_paystack_currency',
    'ecom_paystack_currency_auto', 'ecom_paystack_currency_hint',
]);

require __DIR__ . '/includes/layout-start.php';
?>

<section class="ecom-page-head">
    <p class="ecom-page-head__desc"><?php echo __t('ecom_settings_subtitle', 'admin'); ?></p>
</section>

<form id="ecomSettingsForm" class="ecom-settings-card">
    <h2 class="ecom-settings-card__title"><?php echo __t('ecom_settings_general', 'admin'); ?></h2>
    <label class="ecom-field">
        <span><?php echo __t('ecom_default_store', 'admin'); ?></span>
        <select name="default_store_id" id="ecomDefaultStore"></select>
    </label>
    <label class="ecom-field">
        <span><?php echo __t('ecom_currency', 'admin'); ?></span>
        <select name="currency" id="ecomCurrency">
            <?php foreach (['EUR', 'USD', 'XOF', 'XAF', 'GBP', 'NGN', 'GHS', 'ZAR', 'KES'] as $cur): ?>
            <option value="<?php echo $cur; ?>"><?php echo $cur; ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <label class="ecom-field">
        <span><?php echo __t('ecom_tax_rate', 'admin'); ?></span>
        <input type="number" name="tax_rate" id="ecomTaxRate" min="0" max="100" step="0.01">
    </label>

    <h2 class="ecom-settings-card__title"><?php echo __t('ecom_paystack_section', 'admin'); ?></h2>
    <p class="ecom-settings-card__hint"><?php echo __t('ecom_paystack_section_hint', 'admin'); ?></p>

    <label class="ecom-field ecom-field--checkbox">
        <input type="checkbox" name="paystack_enabled" id="ecomPaystackEnabled" value="1">
        <span><?php echo __t('ecom_paystack_enable', 'admin'); ?></span>
    </label>
    <label class="ecom-field">
        <span><?php echo __t('ecom_paystack_public_key', 'admin'); ?></span>
        <input type="text" name="paystack_public_key" id="ecomPaystackPublic" autocomplete="off" placeholder="pk_test_...">
    </label>
    <label class="ecom-field">
        <span><?php echo __t('ecom_paystack_secret_key', 'admin'); ?></span>
        <input type="password" name="paystack_secret_key" id="ecomPaystackSecret" autocomplete="new-password" placeholder="sk_test_...">
        <small class="ecom-field__hint" id="ecomPaystackSecretHint"></small>
    </label>
    <label class="ecom-field">
        <span><?php echo __t('ecom_paystack_currency', 'admin'); ?></span>
        <select name="paystack_currency" id="ecomPaystackCurrency">
            <option value=""><?php echo __t('ecom_paystack_currency_auto', 'admin'); ?></option>
        </select>
        <small class="ecom-field__hint"><?php echo __t('ecom_paystack_currency_hint', 'admin'); ?></small>
    </label>

    <div class="ecom-settings-card__foot">
        <button type="submit" class="ecom-btn ecom-btn--primary"><?php echo __t('save', 'admin'); ?></button>
    </div>
</form>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

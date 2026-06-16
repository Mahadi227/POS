<?php
require __DIR__ . '/includes/bootstrap.php';
if (!$canManageRegisters) {
    header('Location: registers.php');
    exit;
}
$activeCrPage = 'registers';
$pageTitle = __t('cr_new_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-form.js'];
$pageI18n = cr_i18n([
    'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier',
    'cr_opening_balance', 'cr_status_active', 'cr_status_inactive', 'save', 'cancel', 'cr_saved', 'error',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel cr-form-panel" id="crCreateForm" data-mode="create"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

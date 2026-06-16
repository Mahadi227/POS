<?php
require __DIR__ . '/includes/bootstrap.php';
if (!$canManageRegisters) {
    header('Location: registers.php');
    exit;
}
$id = (int) ($_GET['id'] ?? 0);
if (!$id) {
    header('Location: registers.php');
    exit;
}
$activeCrPage = 'registers';
$pageTitle = __t('cr_edit_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-form.js'];
$pageI18n = cr_i18n([
    'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier',
    'cr_opening_balance', 'cr_status_active', 'cr_status_inactive', 'cr_status_maintenance',
    'save', 'cancel', 'cr_saved', 'error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel cr-form-panel" id="crCreateForm" data-mode="edit" data-register-id="<?php echo $id; ?>"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

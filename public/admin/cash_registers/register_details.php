<?php
require __DIR__ . '/includes/bootstrap.php';
$id = (int) ($_GET['id'] ?? 0);
$activeCrPage = 'registers';
$pageTitle = __t('cr_register_details', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-detail.js'];
$pageI18n = cr_i18n([
    'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier', 'col_status',
    'cr_stat_cash_balance', 'cr_opening_balance', 'cr_session_open', 'cr_session_closed',
    'cr_col_opened', 'cr_col_closed', 'cr_movements_title', 'cr_edit_register', 'cr_open_register',
    'cr_close_register', 'load_error', 'cr_no_data', 'col_date', 'cr_amount',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel" id="crDetailRoot" data-register-id="<?php echo $id; ?>">
    <div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div>
</div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

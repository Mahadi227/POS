<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_registers_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-registers.js'];
$pageI18n = cr_i18n([
    'cr_new_register', 'cr_register_code', 'cr_register_name', 'cr_branch', 'cr_assigned_cashier',
    'cr_opening_balance', 'cr_open_register', 'cr_close_register', 'cr_no_registers', 'cr_saved', 'view_all',
    'cr_session_open', 'cr_session_closed', 'col_status', 'cr_stat_cash_balance', 'cr_counted_cash', 'cr_stat_expected',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar">
    <?php if ($canManageRegisters): ?>
    <a href="create_register.php" class="cr-btn"><span class="material-icons-round">add</span><?php echo __t('cr_new_register', 'admin'); ?></a>
    <a href="open_register.php" class="cr-btn cr-btn--ghost"><?php echo __t('cr_open_register', 'admin'); ?></a>
    <a href="close_register.php" class="cr-btn cr-btn--ghost"><?php echo __t('cr_close_register', 'admin'); ?></a>
    <?php endif; ?>
</div>
<div class="cr-panel" id="crRegistersRoot"><div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

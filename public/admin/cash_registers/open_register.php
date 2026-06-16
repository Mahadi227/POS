<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_open_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-session.js'];
$pageI18n = cr_i18n([
    'cr_open_register', 'cr_opening_balance', 'cr_shift_morning', 'cr_shift_afternoon',
    'cr_shift_evening', 'cr_shift_night', 'cr_register_name', 'cr_saved', 'error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel" id="crOpenSessionRoot"><div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

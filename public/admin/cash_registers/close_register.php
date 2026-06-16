<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'registers';
$pageTitle = __t('cr_close_register', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-session.js'];
$pageI18n = cr_i18n([
    'cr_close_register', 'cr_counted_cash', 'cr_stat_expected', 'cr_col_difference',
    'cr_register_name', 'cr_saved', 'error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel" id="crCloseSessionRoot" data-mode="close"><div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

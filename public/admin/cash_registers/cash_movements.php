<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'movements';
$crPageData = 'movements';
$pageTitle = __t('cr_movements_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_export_csv', 'cr_no_data', 'col_date', 'cr_col_register', 'cr_col_action', 'cr_amount', 'cr_col_cashier']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar cr-filter">
    <select id="crFilterType"><option value="all">All</option><option value="opening_cash">Opening</option><option value="sale">Sale</option><option value="refund">Refund</option><option value="closing_cash">Closing</option><option value="transfer_out">Transfer out</option></select>
    <input type="date" id="crDateFrom" value="<?php echo $today; ?>">
    <input type="date" id="crDateTo" value="<?php echo $today; ?>">
    <button type="button" class="cr-btn" id="crFilterBtn"><?php echo __t('refresh', 'admin'); ?></button>
    <button type="button" class="cr-btn cr-btn--ghost" id="crExportCsvBtn"><?php echo __t('cr_export_csv', 'admin'); ?></button>
</div>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

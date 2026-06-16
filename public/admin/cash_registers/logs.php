<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'logs';
$crPageData = 'logs';
$pageTitle = __t('cr_logs_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_col_action', 'cr_col_register', 'col_date', 'cr_no_data']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

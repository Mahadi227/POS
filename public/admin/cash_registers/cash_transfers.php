<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'transfers';
$crPageData = 'transfers';
$pageTitle = __t('cr_transfers_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_transfer_type', 'cr_amount', 'cr_reason', 'cr_recon_approve', 'cr_no_data']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar">
    <select id="crFilterStatus"><option value="all">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="completed">Completed</option></select>
    <button type="button" class="cr-btn" id="crNewTransferBtn"><span class="material-icons-round">add</span> New transfer</button>
    <button type="button" class="cr-btn" id="crFilterBtn"><?php echo __t('refresh', 'admin'); ?></button>
</div>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

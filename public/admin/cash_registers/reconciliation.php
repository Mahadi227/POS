<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'reconciliation';
$crPageData = 'reconciliation';
$pageTitle = __t('cr_recon_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_recon_approve', 'cr_recon_reject', 'cr_col_expected', 'cr_col_physical', 'cr_col_difference', 'cr_no_data']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar cr-filter">
    <select id="crFilterStatus"><option value="all">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option></select>
    <button type="button" class="cr-btn" id="crFilterBtn"><?php echo __t('refresh', 'admin'); ?></button>
</div>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

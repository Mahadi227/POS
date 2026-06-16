<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'shifts';
$crPageData = 'shifts';
$pageTitle = __t('cr_shifts_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_shift_morning', 'cr_shift_afternoon', 'cr_shift_evening', 'cr_shift_night', 'cr_col_opened', 'cr_col_closed', 'cr_no_data']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar">
    <select id="crFilterStatus"><option value="all">All</option><option value="open">Open</option><option value="closed">Closed</option></select>
    <button type="button" class="cr-btn" id="crFilterBtn"><?php echo __t('refresh', 'admin'); ?></button>
</div>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

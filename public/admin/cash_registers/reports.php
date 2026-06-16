<?php
require __DIR__ . '/includes/bootstrap.php';
$activeCrPage = 'reports';
$crPageData = 'reports';
$pageTitle = __t('cr_reports_title', 'admin');
$today = date('Y-m-d');
$extraScripts = ['cash-registers-common.js', 'cash-registers-report-export.js', 'cash-registers-tables.js'];
$pageI18n = cr_i18n(['cr_export_csv', 'cr_export_pdf', 'cr_export_print', 'cr_opening_balance', 'cr_counted_cash', 'cr_col_difference', 'cr_no_data', 'cr_reports_title', 'cr_branch', 'col_date', 'cr_col_register', 'cr_col_cashier', 'cr_col_expected', 'exporting_pdf', 'pdf_fallback_print', 'doc_page', 'last_updated']);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-toolbar cr-filter">
    <input type="date" id="crDateFrom" value="<?php echo date('Y-m-d', strtotime('-30 days')); ?>">
    <input type="date" id="crDateTo" value="<?php echo $today; ?>">
    <button type="button" class="cr-btn" id="crFilterBtn"><?php echo __t('refresh', 'admin'); ?></button>
    <button type="button" class="cr-btn cr-btn--ghost" id="crExportCsvBtn"><?php echo __t('cr_export_csv', 'admin'); ?></button>
    <button type="button" class="cr-btn cr-btn--ghost" id="crExportPdfBtn"><span class="material-icons-round">picture_as_pdf</span><?php echo __t('cr_export_pdf', 'admin'); ?></button>
    <button type="button" class="cr-btn cr-btn--ghost" id="crPrintBtn"><?php echo __t('cr_export_print', 'admin'); ?></button>
</div>
<div class="cr-panel" id="crTableRoot"></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

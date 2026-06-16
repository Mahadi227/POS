<?php
require __DIR__ . '/includes/bootstrap.php';
if (!$canManageRegisters) {
    header('Location: dashboard.php');
    exit;
}
$activeCrPage = 'settings';
$pageTitle = __t('cr_settings_title', 'admin');
$extraScripts = ['cash-registers-common.js', 'cash-registers-settings.js'];
$pageI18n = cr_i18n([
    'cr_variance_tolerance', 'cr_offline_sync', 'cr_auto_reconcile', 'save', 'cr_saved', 'error',
]);
require __DIR__ . '/includes/layout-start.php';
?>
<div class="cr-panel" id="crSettingsRoot"><div class="cr-loading"><?php echo __t('loading', 'admin'); ?></div></div>
<?php require __DIR__ . '/includes/layout-end.php'; ?>

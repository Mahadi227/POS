<?php
require __DIR__ . '/includes/bootstrap.php';
if (!$canManageWms) { header('Location: dashboard.php'); exit; }
$activeWmsPage = 'settings';
$pageTitle = __t('wms_settings_title', 'wms');
$extraScripts = ['wms-common.js', 'wms-settings.js'];
$pageI18n = wms_i18n([
    'wms_settings_subtitle', 'wms_settings_general', 'wms_offline_sync', 'wms_settings_offline_hint',
    'save', 'wms_saved', 'error', 'load_error',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<p class="cr-intro"><?php echo __t('wms_settings_subtitle', 'wms'); ?></p>

<section class="cr-panel">
    <h3><span class="material-icons-round">settings</span><?php echo __t('wms_settings_general', 'wms'); ?></h3>
    <div id="wmsSettingsRoot"><div class="cr-loading"><?php echo __t('loading', 'wms'); ?></div></div>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

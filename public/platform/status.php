<?php
require __DIR__ . '/includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');

$activePlatPage = 'status';
$pageTitle = __t('plat_nav_status', 'platform');
$extraScripts = ['platform-common.js', 'platform-status.js'];
$pageI18n = plat_i18n([
    'plat_status_components', 'plat_status_incidents', 'plat_status_create',
    'plat_status_title', 'plat_status_message', 'plat_status_severity', 'plat_no_data',
]);
require __DIR__ . '/includes/layout-start.php';
?>

<section class="plat-panel">
    <h2><?php echo __t('plat_status_components', 'platform'); ?></h2>
    <ul class="plat-status-list" id="platComponentList">
        <li><?php echo __t('loading', 'platform'); ?></li>
    </ul>
</section>

<section class="plat-panel">
    <h2><?php echo __t('plat_status_create', 'platform'); ?></h2>
    <form id="platIncidentForm" class="plat-incident-form">
        <label><?php echo __t('plat_status_title', 'platform'); ?>
            <input type="text" name="title" required maxlength="255">
        </label>
        <label><?php echo __t('plat_status_message', 'platform'); ?>
            <textarea name="message" rows="3" required></textarea>
        </label>
        <label><?php echo __t('plat_status_severity', 'platform'); ?>
            <select name="severity">
                <option value="minor">Minor</option>
                <option value="major">Major</option>
                <option value="critical">Critical</option>
            </select>
        </label>
        <fieldset>
            <legend>Components</legend>
            <label><input type="checkbox" name="affects" value="api"> API</label>
            <label><input type="checkbox" name="affects" value="pos"> POS</label>
            <label><input type="checkbox" name="affects" value="portals"> Portals</label>
            <label><input type="checkbox" name="affects" value="sync"> Sync</label>
            <label><input type="checkbox" name="affects" value="billing"> Billing</label>
        </fieldset>
        <button type="submit" class="btn-primary"><?php echo __t('plat_status_create', 'platform'); ?></button>
    </form>
</section>

<section class="plat-panel">
    <h2><?php echo __t('plat_status_incidents', 'platform'); ?></h2>
    <div id="platIncidentList"></div>
    <p class="plat-meta"><a href="../status.php" target="_blank" rel="noopener"><?php echo __t('plat_status_public', 'platform'); ?></a></p>
</section>

<?php require __DIR__ . '/includes/layout-end.php'; ?>

<?php
/** Generic WMS table page bootstrap */
return function (string $page, string $titleKey, array $extra = []) {
    require __DIR__ . '/bootstrap.php';
    $activeWmsPage = $page;
    $wmsPageData = $page;
    $pageTitle = __t($titleKey, 'wms');
    $extraScripts = array_merge(['wms-common.js', 'wms-tables.js'], $extra['scripts'] ?? []);
    $pageI18n = wms_i18n($extra['i18n'] ?? ['wms_no_data', 'refresh', 'wms_export_csv']);
    require __DIR__ . '/layout-start.php';
    if (!empty($extra['toolbar'])) {
        echo is_callable($extra['toolbar']) ? (string) $extra['toolbar']() : (string) $extra['toolbar'];
    }
    echo '<div class="cr-panel" id="wmsTableRoot"><div class="cr-loading">' . __t('loading', 'wms') . '</div></div>';
    require __DIR__ . '/layout-end.php';
};

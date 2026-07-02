<?php
/** Generic accounting page shell — set $activeAccPage, $pageTitle, $accEndpoint before include */
if (!isset($accEndpoint)) {
    $accEndpoint = $activeAccPage ?? 'dashboard';
}
$extraScripts = $extraScripts ?? ['accounting-common.js', 'accounting-page.js'];
require __DIR__ . '/layout-start.php';
?>
<div class="acc-page-shell" id="accPageRoot" data-endpoint="<?php echo htmlspecialchars($accEndpoint, ENT_QUOTES, 'UTF-8'); ?>">
    <div class="acc-loading"><?php echo __t('loading', 'accounting'); ?></div>
</div>
<?php require __DIR__ . '/layout-end.php'; ?>

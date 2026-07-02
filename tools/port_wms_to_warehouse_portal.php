<?php
/**
 * Port legacy admin WMS pages (from git HEAD) into public/warehouse/ with portal layout.
 */
declare(strict_types=1);

$root = dirname(__DIR__);
chdir($root);

$map = [
    'public/admin/warehouse/goods_receipts.php' => 'public/warehouse/receiving/goods_receipts.php',
    'public/admin/warehouse/warehouse_inventory.php' => 'public/warehouse/inventory/warehouse_inventory.php',
    'public/admin/warehouse/warehouse_locations.php' => 'public/warehouse/management/locations.php',
    'public/admin/warehouse/warehouses.php' => 'public/warehouse/management/warehouses.php',
    'public/admin/warehouse/stock_dispatch.php' => 'public/warehouse/dispatch/dispatch_orders.php',
    'public/admin/warehouse/stock_transfers.php' => 'public/warehouse/transfers/warehouse_transfer.php',
    'public/admin/warehouse/stock_requests.php' => 'public/warehouse/transfers/transfer_requests.php',
    'public/admin/warehouse/batch_management.php' => 'public/warehouse/batch/batch_tracking.php',
    'public/admin/warehouse/expiry_management.php' => 'public/warehouse/batch/expiry_management.php',
    'public/admin/warehouse/inventory_audit.php' => 'public/warehouse/inventory/stock_count.php',
    'public/admin/warehouse/logs.php' => 'public/warehouse/management/logs.php',
    'public/admin/warehouse/sync-monitor.php' => 'public/warehouse/management/sync-monitor.php',
    'public/admin/warehouse/reports.php' => 'public/warehouse/reports/inventory_report.php',
    'public/admin/warehouse/analytics.php' => 'public/warehouse/reports/warehouse_performance.php',
];

foreach ($map as $gitPath => $destPath) {
    $cmd = 'git show HEAD:' . escapeshellarg($gitPath);
    $src = shell_exec($cmd);
    if (!$src || !str_contains($src, '<?php')) {
        echo "SKIP (no git source): {$gitPath}\n";
        continue;
    }

    // Strip admin bootstrap + layouts; keep body between layout-start end and layout-end
    if (!preg_match('/require\s+__DIR__\s*\.\s*[\'"]\/includes\/layout-start\.php[\'"];\s*\?>\s*(.*)\s*<\?php\s+require\s+__DIR__\s*\.\s*[\'"]\/includes\/layout-end\.php[\'"];/s', $src, $m)) {
        echo "SKIP (parse fail): {$gitPath}\n";
        continue;
    }
    $body = trim($m[1]);

    // Extract header vars from source
    preg_match('/\$activeWmsPage\s*=\s*[\'"]([^\'"]+)[\'"];/', $src, $ap);
    preg_match('/\$pageTitle\s*=\s*__t\([\'"]([^\'"]+)[\'"],\s*[\'"]wms[\'"]\);/', $src, $pt);
    preg_match('/\$loadChart\s*=\s*(true|false);/', $src, $lc);
    preg_match('/\$extraScripts\s*=\s*\[(.*?)\];/s', $src, $es);

    $activeWhPage = $ap[1] ?? basename($destPath, '.php');
    $pageTitleKey = $pt[1] ?? 'wh_title';
    $loadChart = ($lc[1] ?? 'false') === 'true';
    $adminScripts = $es[1] ?? '';

    $depth = substr_count(str_replace('\\', '/', dirname($destPath)), '/');
    $bootstrapRel = str_repeat('../', $depth - 1) . 'includes/bootstrap.php';
    $layoutStart = str_repeat('../', $depth - 1) . 'includes/layout-start.php';
    $layoutEnd = str_repeat('../', $depth - 1) . 'includes/layout-end.php';

    $out = "<?php\n";
    $out .= "require __DIR__ . '/{$bootstrapRel}';\n";
    $out .= "\$useWmsModules = true;\n";
    $out .= "\$activeWhPage = " . var_export($activeWhPage, true) . ";\n";
    $out .= "\$pageTitle = __t(" . var_export($pageTitleKey, true) . ", 'wms');\n";
    if ($loadChart) {
        $out .= "\$loadChart = true;\n";
    }
    if (preg_match_all("/[\'\"](wms-[^\'\"]+)[\'\"]/", $adminScripts, $scr)) {
        $out .= "\$extraAdminScripts = " . var_export($scr[1], true) . ";\n";
    }
    if (preg_match('/\$pageI18n\s*=\s*wms_i18n\(\[(.*?)\]\);/s', $src, $pi)) {
        $keys = [];
        preg_match_all("/[\'\"]([^\'\"]+)[\'\"]/", $pi[1], $km);
        $keys = $km[1] ?? [];
        if ($keys) {
            $out .= "\$pageI18n = wms_i18n(" . var_export($keys, true) . ");\n";
        }
    }
    $out .= "\$extraScripts = ['warehouse-common.js', 'warehouse-search.js'];\n";
    $out .= "require __DIR__ . '/{$layoutStart}';\n";
    $out .= "?>\n\n";
    $out .= "<div id=\"wmsError\" class=\"ad-error-banner\"><span class=\"ad-error-text\"></span></div>\n";
    $out .= "<div id=\"wmsMigrationHint\" class=\"wh-migration-hint\" hidden></div>\n";
    $out .= $body . "\n\n";
    $out .= "<?php require __DIR__ . '/{$layoutEnd}';\n";

    $destFull = $root . '/' . $destPath;
    $dir = dirname($destFull);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    file_put_contents($destFull, $out);
    echo "Ported: {$destPath}\n";
}

echo "Port complete.\n";

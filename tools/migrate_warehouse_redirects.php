<?php
/**
 * One-time generator: admin/warehouse → public/warehouse redirect stubs
 */
declare(strict_types=1);

$adminWh = dirname(__DIR__) . '/public/admin/warehouse';
$map = [
    'dashboard.php' => '../../warehouse/dashboard.php',
    'warehouse_inventory.php' => '../../warehouse/inventory/warehouse_inventory.php',
    'warehouse_locations.php' => '../../warehouse/management/locations.php',
    'goods_receipts.php' => '../../warehouse/receiving/goods_receipts.php',
    'stock_dispatch.php' => '../../warehouse/dispatch/dispatch_orders.php',
    'stock_requests.php' => '../../warehouse/transfers/transfer_requests.php',
    'stock_transfers.php' => '../../warehouse/transfers/warehouse_transfer.php',
    'batch_management.php' => '../../warehouse/batch/batch_tracking.php',
    'expiry_management.php' => '../../warehouse/batch/expiry_management.php',
    'inventory_audit.php' => '../../warehouse/inventory/stock_count.php',
    'reports.php' => '../../warehouse/reports/inventory_report.php',
    'analytics.php' => '../../warehouse/reports/warehouse_performance.php',
    'logs.php' => '../../warehouse/management/logs.php',
    'sync-monitor.php' => '../../warehouse/management/sync-monitor.php',
    'settings.php' => '../../warehouse/settings.php',
    'warehouses.php' => '../../warehouse/management/warehouses.php',
    'create_warehouse.php' => '../../warehouse/management/warehouses.php?action=create',
    'edit_warehouse.php' => '../../warehouse/management/warehouses.php',
    'stores.php' => '../../warehouse/management/stores.php',
    'users.php' => '../../admin/users.php',
];

foreach ($map as $file => $target) {
    $path = $adminWh . '/' . $file;
    $qs = '';
    if (str_contains($file, 'edit_warehouse')) {
        $content = "<?php\n// Legacy admin WMS URL — redirects to Warehouse Portal\n\$id = isset(\$_GET['id']) ? '?id=' . urlencode((string) \$_GET['id']) : '';\nheader('Location: {$target}' . \$id);\nexit;\n";
    } else {
        $content = "<?php\n// Legacy admin WMS URL — redirects to Warehouse Portal (public/warehouse/)\nheader('Location: {$target}');\nexit;\n";
    }
    file_put_contents($path, $content);
    echo "Redirect: {$file} -> {$target}\n";
}

// README in admin/warehouse folder
file_put_contents($adminWh . '/README.md', <<<'MD'
# Legacy path — use `/public/warehouse/`

All WMS UI lives in **`public/warehouse/`** (Warehouse Portal), same pattern as **`public/accounting/`**.

Files here are **HTTP redirects only** so old bookmarks and links keep working.

Do not add new features under `public/admin/warehouse/`.
MD);

echo "Done.\n";

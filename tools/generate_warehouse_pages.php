<?php
declare(strict_types=1);

$base = dirname(__DIR__) . '/public/warehouse';
$pages = [
    ['inventory', 'products.php', 'products', 'inventory', 'inventory', 'wh_nav_products'],
    ['inventory', 'warehouse_inventory.php', 'warehouse_inventory', 'inventory', 'inventory', 'wh_nav_warehouse_inventory'],
    ['inventory', 'stock_levels.php', 'stock_levels', 'inventory', 'inventory', 'wh_nav_stock_levels'],
    ['inventory', 'stock_adjustments.php', 'stock_adjustments', 'movements', 'inventory', 'wh_nav_adjustments'],
    ['inventory', 'stock_count.php', 'stock_count', 'audits', 'inventory', 'wh_nav_stock_count'],
    ['inventory', 'inventory_history.php', 'inventory_history', 'movements', 'inventory', 'wh_nav_history'],
    ['inventory', 'stock_ledger.php', 'stock_ledger', 'movements', 'inventory', 'wh_nav_ledger'],
    ['inventory', 'barcode_scanner.php', 'barcode_scanner', 'inventory', 'inventory', 'wh_nav_scanner'],
    ['receiving', 'purchase_orders.php', 'purchase_orders', 'receipts', 'receiving', 'wh_nav_purchase_orders'],
    ['receiving', 'supplier_deliveries.php', 'supplier_deliveries', 'receipts', 'receiving', 'wh_nav_deliveries'],
    ['receiving', 'goods_receipts.php', 'goods_receipts', 'receipts', 'receiving', 'wh_nav_goods_receipts'],
    ['receiving', 'receive_stock.php', 'receive_stock', 'receipts', 'receiving', 'wh_nav_receive_stock'],
    ['receiving', 'quality_inspection.php', 'quality_inspection', 'receipts', 'receiving', 'wh_nav_inspection'],
    ['receiving', 'receiving_history.php', 'receiving_history', 'receipts', 'receiving', 'wh_nav_receiving_history'],
    ['dispatch', 'dispatch_orders.php', 'dispatch_orders', 'dispatches', 'dispatch', 'wh_nav_dispatch_orders'],
    ['dispatch', 'pick_list.php', 'pick_list', 'dispatches', 'dispatch', 'wh_nav_pick_list'],
    ['dispatch', 'packing.php', 'packing', 'dispatches', 'dispatch', 'wh_nav_packing'],
    ['dispatch', 'shipping.php', 'shipping', 'dispatches', 'dispatch', 'wh_nav_shipping'],
    ['dispatch', 'delivery_confirmation.php', 'delivery_confirmation', 'dispatches', 'dispatch', 'wh_nav_delivery'],
    ['dispatch', 'dispatch_history.php', 'dispatch_history', 'dispatches', 'dispatch', 'wh_nav_dispatch_history'],
    ['transfers', 'transfer_requests.php', 'transfer_requests', 'transfers', 'transfers', 'wh_nav_transfer_requests'],
    ['transfers', 'incoming_transfers.php', 'incoming_transfers', 'transfers', 'transfers', 'wh_nav_incoming'],
    ['transfers', 'outgoing_transfers.php', 'outgoing_transfers', 'transfers', 'transfers', 'wh_nav_outgoing'],
    ['transfers', 'warehouse_transfer.php', 'warehouse_transfer', 'transfers', 'transfers', 'wh_nav_wh_transfer'],
    ['transfers', 'branch_transfer.php', 'branch_transfer', 'transfers', 'transfers', 'wh_nav_branch_transfer'],
    ['transfers', 'approve_transfer.php', 'approve_transfer', 'transfers', 'transfers', 'wh_nav_approve_transfer'],
    ['transfers', 'transfer_history.php', 'transfer_history', 'transfers', 'transfers', 'wh_nav_transfer_history'],
    ['batch', 'batch_tracking.php', 'batch_tracking', 'batches', 'batch', 'wh_nav_batch'],
    ['batch', 'serial_numbers.php', 'serial_numbers', 'batches', 'batch', 'wh_nav_serial'],
    ['batch', 'expiry_management.php', 'expiry_management', 'batches', 'batch', 'wh_nav_expiry'],
    ['batch', 'fifo_fefo.php', 'fifo_fefo', 'batches', 'batch', 'wh_nav_fifo'],
    ['reports', 'inventory_report.php', 'inventory_report', 'inventory', 'reports', 'wh_nav_rpt_inventory'],
    ['reports', 'stock_movement_report.php', 'stock_movement_report', 'movements', 'reports', 'wh_nav_rpt_movements'],
    ['reports', 'receiving_report.php', 'receiving_report', 'receipts', 'reports', 'wh_nav_rpt_receiving'],
    ['reports', 'dispatch_report.php', 'dispatch_report', 'dispatches', 'reports', 'wh_nav_rpt_dispatch'],
    ['reports', 'transfer_report.php', 'transfer_report', 'transfers', 'reports', 'wh_nav_rpt_transfer'],
    ['reports', 'warehouse_performance.php', 'warehouse_performance', 'movements', 'reports', 'wh_nav_rpt_performance'],
    ['reports', 'inventory_valuation.php', 'inventory_valuation', 'inventory', 'reports', 'wh_nav_rpt_valuation'],
    ['reports', 'damage_report.php', 'damage_report', 'inventory', 'reports', 'wh_nav_rpt_damage'],
    ['reports', 'expiry_report.php', 'expiry_report', 'batches', 'reports', 'wh_nav_rpt_expiry'],
];

foreach ($pages as [$dir, $file, $id, $endpoint, $module, $titleKey]) {
    $path = $base . '/' . $dir;
    if (!is_dir($path)) {
        mkdir($path, 0777, true);
    }
    $content = "<?php\n"
        . "\$whModule = '$module';\n"
        . "\$whEndpoint = '$endpoint';\n"
        . "\$whPageId = '$id';\n"
        . "\$whTitleKey = '$titleKey';\n"
        . "require __DIR__ . '/../includes/page-shell.php';\n";
    file_put_contents($path . '/' . $file, $content);
}
echo 'Generated ' . count($pages) . " pages\n";

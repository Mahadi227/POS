<?php
declare(strict_types=1);

require_once __DIR__ . '/Repositories/WarehouseLogRepository.php';
require_once __DIR__ . '/../Notifications/NotificationManager.php';

class WmsNotifier
{
    /**
     * @param array<string, mixed> $details
     */
    private static function notify(string $action, ?int $warehouseId, ?int $userId, array $details): void
    {
        $details['notify'] = true;
        WarehouseLogRepository::log($action, $warehouseId, $userId, 'notification', null, $details);

        $templateMap = [
            'low_stock' => 'inventory.low_stock',
            'transfer_approved' => 'warehouse.transfer_approved',
            'transfer_rejected' => 'warehouse.transfer_rejected',
            'purchase_received' => 'warehouse.receiving_completed',
            'incoming_delivery' => 'warehouse.receiving_completed',
        ];
        $template = $templateMap[$action] ?? null;
        if ($template) {
            NotificationManager::dispatch([
                'template' => $template,
                'category' => str_starts_with($action, 'transfer') ? 'warehouse_transfer' : 'inventory_low_stock',
                'module' => str_starts_with($action, 'transfer') ? 'warehouse' : 'inventory',
                'roles' => ['admin', 'manager', 'warehouse_manager', 'inventory_officer', 'super_admin'],
                'warehouse_id' => $warehouseId,
                'params' => [
                    'product' => $details['product'] ?? ($details['message'] ?? 'Product'),
                    'qty' => (string) ($details['qty'] ?? ''),
                    'reference' => $details['reference'] ?? ($details['grnNumber'] ?? ''),
                ],
                'severity' => $details['severity'] ?? 'info',
                'channels' => ['in_app', 'browser'],
            ]);
        }
    }

    public static function lowStock(int $warehouseId, string $productName, int $qty): void
    {
        self::notify('low_stock', $warehouseId, null, [
            'severity' => 'warning',
            'product' => $productName,
            'qty' => $qty,
            'message' => "Low stock: {$productName} ({$qty} units remaining)",
        ]);
    }

    public static function transferApproved(int $warehouseId, string $transferNumber): void
    {
        self::notify('transfer_approved', $warehouseId, null, [
            'reference' => $transferNumber,
            'message' => "Transfer {$transferNumber} approved",
        ]);
    }

    public static function transferRejected(int $warehouseId, string $transferNumber): void
    {
        self::notify('transfer_rejected', $warehouseId, null, [
            'severity' => 'warning',
            'reference' => $transferNumber,
            'message' => "Transfer {$transferNumber} rejected",
        ]);
    }

    public static function purchaseReceived(int $warehouseId, string $grnNumber, float $value): void
    {
        self::notify('purchase_received', $warehouseId, null, [
            'reference' => $grnNumber,
            'message' => "Goods received {$grnNumber} — " . number_format($value, 0) . ' FCFA',
        ]);
    }

    public static function incomingDelivery(int $warehouseId, string $grnNumber): void
    {
        self::notify('incoming_delivery', $warehouseId, null, [
            'reference' => $grnNumber,
            'message' => "Incoming delivery {$grnNumber} pending inspection",
        ]);
    }
}

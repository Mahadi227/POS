<?php
declare(strict_types=1);

require_once __DIR__ . '/Repositories/WarehouseLogRepository.php';

class WmsNotifier
{
    /**
     * @param array<string, mixed> $details
     */
    private static function notify(string $action, ?int $warehouseId, ?int $userId, array $details): void
    {
        $details['notify'] = true;
        WarehouseLogRepository::log($action, $warehouseId, $userId, 'notification', null, $details);
    }

    public static function lowStock(int $warehouseId, string $productName, int $qty): void
    {
        self::notify('low_stock', $warehouseId, null, [
            'severity' => 'warning',
            'message' => "Low stock: {$productName} ({$qty} units remaining)",
        ]);
    }

    public static function transferApproved(int $warehouseId, string $transferNumber): void
    {
        self::notify('transfer_approved', $warehouseId, null, [
            'message' => "Transfer {$transferNumber} approved",
        ]);
    }

    public static function transferRejected(int $warehouseId, string $transferNumber): void
    {
        self::notify('transfer_rejected', $warehouseId, null, [
            'severity' => 'warning',
            'message' => "Transfer {$transferNumber} rejected",
        ]);
    }

    public static function purchaseReceived(int $warehouseId, string $grnNumber, float $value): void
    {
        self::notify('purchase_received', $warehouseId, null, [
            'message' => "Goods received {$grnNumber} — " . number_format($value, 0) . ' FCFA',
        ]);
    }

    public static function incomingDelivery(int $warehouseId, string $grnNumber): void
    {
        self::notify('incoming_delivery', $warehouseId, null, [
            'message' => "Incoming delivery {$grnNumber} pending inspection",
        ]);
    }
}

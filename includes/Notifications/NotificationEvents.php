<?php
declare(strict_types=1);

require_once __DIR__ . '/NotificationManager.php';
require_once __DIR__ . '/../Database/Database.php';

/**
 * Module integration helpers — call from POS, Inventory, Accounting, etc.
 */
class NotificationEvents
{
    private static array $currencyCache = [];

    private static function storeCurrency(int $storeId): string
    {
        if ($storeId <= 0) {
            return 'FCFA';
        }
        if (isset(self::$currencyCache[$storeId])) {
            return self::$currencyCache[$storeId];
        }
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('SELECT currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$storeId]);
            $currency = (string) ($stmt->fetchColumn() ?: 'FCFA');
            self::$currencyCache[$storeId] = $currency !== '' ? $currency : 'FCFA';
        } catch (Throwable $e) {
            self::$currencyCache[$storeId] = 'FCFA';
        }
        return self::$currencyCache[$storeId];
    }

    private static function formatMoney(float $amount, int $storeId): string
    {
        return number_format($amount, 0, '.', ' ') . ' ' . self::storeCurrency($storeId);
    }

    public static function lowStock(int $storeId, string $product, int $qty, ?int $warehouseId = null): void
    {
        NotificationManager::dispatch([
            'template' => 'inventory.low_stock',
            'category' => 'inventory_low_stock',
            'module' => 'inventory',
            'roles' => ['admin', 'manager', 'inventory_officer', 'warehouse_manager', 'super_admin'],
            'store_id' => $storeId > 0 ? $storeId : null,
            'warehouse_id' => $warehouseId,
            'params' => ['product' => $product, 'qty' => (string) $qty],
            'severity' => 'warning',
            'channels' => ['in_app', 'browser'],
        ]);
    }

    public static function outOfStock(int $storeId, string $product, ?int $warehouseId = null): void
    {
        NotificationManager::dispatch([
            'template' => 'inventory.out_of_stock',
            'category' => 'inventory_out_of_stock',
            'module' => 'inventory',
            'roles' => ['admin', 'manager', 'inventory_officer', 'warehouse_manager', 'super_admin'],
            'store_id' => $storeId > 0 ? $storeId : null,
            'warehouse_id' => $warehouseId,
            'params' => ['product' => $product],
            'severity' => 'critical',
            'channels' => ['in_app', 'browser', 'email'],
        ]);
    }

    public static function saleCompleted(int $storeId, string $reference, string $amount, array $roles = ['manager', 'admin', 'super_admin']): void
    {
        NotificationManager::dispatch([
            'template' => 'pos.sale_completed',
            'category' => 'pos_sale',
            'module' => 'pos',
            'roles' => $roles,
            'store_id' => $storeId,
            'params' => ['reference' => $reference, 'amount' => $amount],
            'channels' => ['in_app'],
        ]);
    }

    public static function securityAlert(int $userId, string $messageEn, string $messageFr): void
    {
        NotificationManager::notifyUser($userId, [
            'template' => 'system.security_alert',
            'category' => 'security',
            'module' => 'system',
            'params' => ['message' => $messageEn],
            'message_en' => $messageEn,
            'message_fr' => $messageFr,
            'severity' => 'critical',
            'channels' => ['in_app', 'email'],
        ]);
    }

    public static function offlineSyncComplete(int $userId): void
    {
        NotificationManager::notifyUser($userId, [
            'template' => 'system.offline_sync',
            'category' => 'system',
            'module' => 'system',
            'channels' => ['in_app'],
        ]);
    }

    /** POS checkout — sale completed + optional large-sale alert for managers/admins. */
    public static function posCheckout(int $storeId, string $receiptNo, float $total): void
    {
        $amount = self::formatMoney($total, $storeId);
        self::saleCompleted($storeId, $receiptNo, $amount);

        if ($total >= 100000) {
            NotificationManager::dispatch([
                'template' => 'pos.large_sale',
                'category' => 'pos_sale',
                'module' => 'pos',
                'roles' => ['manager', 'admin', 'super_admin'],
                'store_id' => $storeId,
                'params' => ['amount' => $amount, 'reference' => $receiptNo],
                'severity' => 'info',
                'channels' => ['in_app', 'browser'],
            ]);
        }
    }
}

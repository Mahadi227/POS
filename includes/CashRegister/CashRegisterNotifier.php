<?php
declare(strict_types=1);

require_once __DIR__ . '/Repositories/CashRegisterLogRepository.php';
require_once __DIR__ . '/../Notifications/NotificationManager.php';
require_once __DIR__ . '/../Database/Database.php';

/**
 * Admin notifications for cash register events (audit log + notify flag).
 */
class CashRegisterNotifier
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
        return number_format($amount, 0) . ' ' . self::storeCurrency($storeId);
    }

    public static function registerOpened(int $storeId, int $registerId, int $userId, float $opening, string $registerName): void
    {
        self::notify('register_opened', $storeId, $registerId, $userId, [
            'register_name' => $registerName,
            'opening_balance' => $opening,
            'message' => "Cash register \"{$registerName}\" opened with " . self::formatMoney($opening, $storeId),
        ]);
    }

    public static function registerClosed(int $storeId, int $registerId, int $userId, float $variance, string $registerName): void
    {
        self::notify('register_closed', $storeId, $registerId, $userId, [
            'register_name' => $registerName,
            'variance' => $variance,
            'message' => "Cash register \"{$registerName}\" closed (variance: " . self::formatMoney($variance, $storeId) . ')',
        ]);
    }

    public static function cashDifferenceDetected(int $storeId, int $registerId, float $variance, int $reconciliationId): void
    {
        self::notify('cash_difference', $storeId, $registerId, null, [
            'variance' => $variance,
            'reconciliation_id' => $reconciliationId,
            'severity' => 'warning',
            'message' => 'Cash difference detected: ' . self::formatMoney($variance, $storeId) . ' — reconciliation pending',
        ]);
    }

    public static function largeRefund(int $storeId, int $registerId, float $amount, int $userId): void
    {
        if ($amount < 50000) {
            return;
        }
        self::notify('large_refund', $storeId, $registerId, $userId, [
            'amount' => $amount,
            'severity' => 'warning',
            'message' => 'Large refund recorded: ' . self::formatMoney($amount, $storeId),
        ]);
    }

    public static function largeWithdrawal(int $storeId, int $registerId, float $amount, int $userId): void
    {
        if ($amount < 100000) {
            return;
        }
        self::notify('large_withdrawal', $storeId, $registerId, $userId, [
            'amount' => $amount,
            'severity' => 'warning',
            'message' => 'Large cash withdrawal: ' . self::formatMoney($amount, $storeId),
        ]);
    }

    public static function registerInactive(int $storeId, int $registerId, string $registerName): void
    {
        self::notify('register_inactive', $storeId, $registerId, null, [
            'register_name' => $registerName,
            'severity' => 'info',
            'message' => "Register \"{$registerName}\" marked inactive",
        ]);
    }

    /**
     * @param array<string, mixed> $details
     */
    private static function notify(string $action, int $storeId, ?int $registerId, ?int $userId, array $details): void
    {
        $details['notify'] = true;
        CashRegisterLogRepository::log($action, $storeId, $registerId, $userId, 'notification', null, $details);

        $templateMap = [
            'register_opened' => 'cash_register.opened',
            'register_closed' => 'cash_register.closed',
            'cash_difference' => 'cash_register.cash_difference',
            'large_refund' => 'pos.refund_completed',
            'large_withdrawal' => 'pos.large_sale',
        ];
        $template = $templateMap[$action] ?? null;
        if ($template) {
            $amountFormatted = isset($details['amount'])
                ? self::formatMoney((float) $details['amount'], $storeId)
                : (isset($details['opening_balance']) ? self::formatMoney((float) $details['opening_balance'], $storeId) : '');
            $isPos = in_array($action, ['large_refund', 'large_withdrawal'], true);
            NotificationManager::dispatch([
                'template' => $template,
                'category' => $isPos ? ($action === 'large_withdrawal' ? 'pos_sale' : 'pos_refund') : 'cash_register',
                'module' => $isPos ? 'pos' : 'cash_register',
                'roles' => ['admin', 'manager', 'super_admin'],
                'store_id' => $storeId,
                'entity_type' => 'cash_register',
                'entity_id' => $registerId,
                'action_url' => '/public/admin/cash_registers/register_details.php?id=' . ($registerId ?? ''),
                'params' => [
                    'register' => $details['register_name'] ?? 'Register',
                    'amount' => $amountFormatted,
                    'variance' => isset($details['variance']) ? self::formatMoney((float) $details['variance'], $storeId) : '',
                ],
                'severity' => $details['severity'] ?? 'info',
                'channels' => ['in_app', 'browser'],
            ]);
            return;
        }

        if ($action === 'register_inactive') {
            NotificationManager::dispatch([
                'category' => 'cash_register',
                'module' => 'cash_register',
                'roles' => ['admin', 'manager', 'super_admin'],
                'store_id' => $storeId,
                'title_en' => 'Register deactivated',
                'title_fr' => 'Caisse désactivée',
                'message_en' => (string) ($details['message'] ?? 'Register marked inactive'),
                'message_fr' => 'La caisse « ' . ($details['register_name'] ?? 'Caisse') . ' » a été désactivée',
                'severity' => 'info',
                'channels' => ['in_app'],
            ]);
        }
    }
}

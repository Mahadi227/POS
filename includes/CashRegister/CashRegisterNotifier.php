<?php
declare(strict_types=1);

require_once __DIR__ . '/Repositories/CashRegisterLogRepository.php';

/**
 * Admin notifications for cash register events (audit log + notify flag).
 */
class CashRegisterNotifier
{
    public static function registerOpened(int $storeId, int $registerId, int $userId, float $opening, string $registerName): void
    {
        self::notify('register_opened', $storeId, $registerId, $userId, [
            'register_name' => $registerName,
            'opening_balance' => $opening,
            'message' => "Cash register \"{$registerName}\" opened with " . number_format($opening, 0) . ' FCFA',
        ]);
    }

    public static function registerClosed(int $storeId, int $registerId, int $userId, float $variance, string $registerName): void
    {
        self::notify('register_closed', $storeId, $registerId, $userId, [
            'register_name' => $registerName,
            'variance' => $variance,
            'message' => "Cash register \"{$registerName}\" closed (variance: " . number_format($variance, 0) . ' FCFA)',
        ]);
    }

    public static function cashDifferenceDetected(int $storeId, int $registerId, float $variance, int $reconciliationId): void
    {
        self::notify('cash_difference', $storeId, $registerId, null, [
            'variance' => $variance,
            'reconciliation_id' => $reconciliationId,
            'severity' => 'warning',
            'message' => 'Cash difference detected: ' . number_format($variance, 0) . ' FCFA — reconciliation pending',
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
            'message' => 'Large refund recorded: ' . number_format($amount, 0) . ' FCFA',
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
            'message' => 'Large cash withdrawal: ' . number_format($amount, 0) . ' FCFA',
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
    }
}

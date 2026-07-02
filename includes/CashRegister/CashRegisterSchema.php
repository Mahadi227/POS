<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

final class CashRegisterSchema
{
    private static ?bool $ready = null;

    /** @var list<string> */
    private const REQUIRED_TABLES = [
        'cash_registers',
        'cash_register_sessions',
        'cash_movements',
    ];

    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }

        self::$ready = self::detectReady();
        return self::$ready;
    }

    public static function forgetCache(): void
    {
        self::$ready = null;
    }

    private static function detectReady(): bool
    {
        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare('
                SELECT COUNT(*) AS cnt
                FROM information_schema.tables
                WHERE table_schema = DATABASE()
                  AND table_name = ?
            ');

            foreach (self::REQUIRED_TABLES as $table) {
                $stmt->execute([$table]);
                if ((int) $stmt->fetchColumn() !== 1) {
                    return false;
                }
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

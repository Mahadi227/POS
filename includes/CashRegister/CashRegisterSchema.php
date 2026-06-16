<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

final class CashRegisterSchema
{
    private static ?bool $ready = null;

    public static function ready(): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        try {
            Database::getInstance()->getConnection()->query('SELECT 1 FROM cash_registers LIMIT 1');
            self::$ready = true;
        } catch (Throwable $e) {
            self::$ready = false;
        }
        return self::$ready;
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

class WmsSchema
{
    public static function ready(): bool
    {
        try {
            Database::getInstance()->getConnection()->query('SELECT 1 FROM warehouses LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

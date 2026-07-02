<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

/**
 * Probes accounting tables; optional idempotent migration runner.
 */
class AccountingSchema
{
    private static ?bool $ready = null;

    /** @var string[] */
    private const CORE_TABLES = [
        'acc_accounts',
        'acc_journal_entries',
        'acc_journal_lines',
        'acc_expense_records',
    ];

    public static function ready(?PDO $db = null): bool
    {
        if (self::$ready !== null) {
            return self::$ready;
        }
        self::$ready = self::probe($db);
        return self::$ready;
    }

    public static function ensure(?PDO $db = null): bool
    {
        $conn = $db ?? Database::getInstance()->getConnection();
        if (self::probe($conn)) {
            self::$ready = true;
            return true;
        }

        $path = __DIR__ . '/../Database/migrations/014_accounting.sql';
        if (!is_readable($path)) {
            return false;
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            return false;
        }

        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            try {
                $conn->exec($stmt);
            } catch (PDOException $e) {
                $msg = $e->getMessage();
                if (!str_contains($msg, 'Duplicate')
                    && !str_contains($msg, 'already exists')
                    && !str_contains($msg, '1050')) {
                    error_log('AccountingSchema: ' . $msg);
                }
            }
        }

        self::$ready = null;
        self::$ready = self::probe($conn);
        return self::$ready;
    }

    public static function resetCache(): void
    {
        self::$ready = null;
    }

    private static function probe(?PDO $db): bool
    {
        try {
            $conn = $db ?? Database::getInstance()->getConnection();
            foreach (self::CORE_TABLES as $table) {
                $stmt = $conn->prepare(
                    'SELECT 1 FROM information_schema.TABLES
                     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
                );
                $stmt->execute([$table]);
                if (!$stmt->fetchColumn()) {
                    return false;
                }
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

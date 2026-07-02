<?php
declare(strict_types=1);

class WarehouseHelpSchema
{
    private static bool $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        $path = __DIR__ . '/../Database/migrations/018_warehouse_help.sql';
        if (!is_readable($path)) {
            return;
        }
        $sql = preg_replace('/--[^\r\n]*/', '', file_get_contents($path) ?: '') ?? '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            try {
                $db->exec($stmt);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    error_log('WarehouseHelpSchema: ' . $e->getMessage());
                }
            }
        }
    }

    public static function ready(PDO $db): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['help_categories']);
        return (bool) $stmt->fetchColumn();
    }
}

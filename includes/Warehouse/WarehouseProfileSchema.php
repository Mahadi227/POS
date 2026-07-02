<?php
declare(strict_types=1);

/**
 * Idempotent schema for warehouse employee profile fields.
 */
class WarehouseProfileSchema
{
    private static bool $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        require_once __DIR__ . '/../Auth/RbacSchemaMigrator.php';
        RbacSchemaMigrator::ensure($db);

        $userCols = [
            'first_name' => "VARCHAR(120) NULL AFTER full_name",
            'last_name' => "VARCHAR(120) NULL AFTER first_name",
            'address' => "TEXT NULL AFTER phone",
            'emergency_contact' => "VARCHAR(255) NULL AFTER address",
            'avatar_path' => "VARCHAR(255) NULL AFTER emergency_contact",
            'department' => "VARCHAR(120) NULL AFTER avatar_path",
            'supervisor_id' => "INT NULL AFTER department",
            'timezone' => "VARCHAR(64) NULL DEFAULT 'UTC' AFTER language",
        ];
        foreach ($userCols as $col => $definition) {
            if (!self::hasColumn($db, 'users', $col)) {
                try {
                    $db->exec("ALTER TABLE users ADD COLUMN {$col} {$definition}");
                } catch (PDOException $e) {
                    error_log('WarehouseProfileSchema users.' . $col . ': ' . $e->getMessage());
                }
            }
        }

        $sqlFile = __DIR__ . '/../Database/migrations/016_warehouse_user_profile.sql';
        if (is_readable($sqlFile)) {
            $sql = preg_replace('/--[^\r\n]*/', '', file_get_contents($sqlFile) ?: '') ?? '';
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
                if ($stmt === '') {
                    continue;
                }
                try {
                    $db->exec($stmt);
                } catch (PDOException $e) {
                    if (!str_contains($e->getMessage(), 'Duplicate')) {
                        error_log('WarehouseProfileSchema SQL: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    public static function ready(PDO $db): bool
    {
        return self::hasColumn($db, 'users', 'avatar_path')
            && self::tableExists($db, 'user_preferences');
    }

    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

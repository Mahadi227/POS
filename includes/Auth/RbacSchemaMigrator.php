<?php
declare(strict_types=1);

/**
 * Applies RBAC schema upgrades idempotently at runtime.
 */
class RbacSchemaMigrator
{
    private static bool $columnsDone = false;
    private static bool $sqlDone = false;

    public static function ensure(PDO $db): void
    {
        if (!self::$columnsDone) {
            self::addUserColumns($db);
            self::$columnsDone = true;
        }
        if (!self::$sqlDone) {
            self::runSqlFile($db, __DIR__ . '/../Database/migrations/009_rbac_enterprise.sql');
            self::$sqlDone = true;
        }
    }

    private static function addUserColumns(PDO $db): void
    {
        $columns = [
            'employee_id' => "VARCHAR(50) NULL AFTER id",
            'phone' => "VARCHAR(30) NULL AFTER email",
            'full_name' => "VARCHAR(255) NULL AFTER name",
            'branch_id' => "INT NULL AFTER store_id",
            'warehouse_id' => "INT NULL AFTER branch_id",
            'language' => "VARCHAR(5) NULL DEFAULT 'en' AFTER warehouse_id",
            'status' => "ENUM('active','inactive','locked') NOT NULL DEFAULT 'active' AFTER language",
            'failed_login_attempts' => "INT NOT NULL DEFAULT 0 AFTER last_login",
            'locked_until' => "TIMESTAMP NULL DEFAULT NULL AFTER failed_login_attempts",
            'last_activity' => "TIMESTAMP NULL DEFAULT NULL AFTER locked_until",
            'email_verified_at' => "TIMESTAMP NULL DEFAULT NULL AFTER remember_token",
            'updated_at' => "TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP AFTER created_at",
        ];

        foreach ($columns as $col => $definition) {
            if (!self::hasColumn($db, 'users', $col)) {
                try {
                    $db->exec("ALTER TABLE users ADD COLUMN {$col} {$definition}");
                } catch (PDOException $e) {
                    error_log('RbacSchemaMigrator column ' . $col . ': ' . $e->getMessage());
                }
            }
        }

        try {
            $db->exec("UPDATE users SET full_name = name WHERE full_name IS NULL OR full_name = ''");
            $db->exec("UPDATE users SET status = IF(is_active = 1, 'active', 'inactive') WHERE status IS NULL OR status = ''");
            $db->exec('UPDATE users SET branch_id = store_id WHERE branch_id IS NULL AND store_id IS NOT NULL');
        } catch (PDOException $e) {
            // columns may not exist yet on first pass
        }
    }

    private static function runSqlFile(PDO $db, string $path): void
    {
        if (!is_readable($path)) {
            error_log('RbacSchemaMigrator: SQL file not found: ' . $path);
            return;
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            return;
        }
        // Strip line comments so INSERT blocks after header comments are not skipped
        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;

        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            try {
                $db->exec($stmt);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    error_log('RbacSchemaMigrator SQL: ' . $e->getMessage());
                }
            }
        }
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
}

<?php
declare(strict_types=1);

/**
 * Idempotent notification schema migration.
 */
class NotificationSchemaMigrator
{
    private static bool $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;
        self::migrateLegacyTable($db);
        self::runSqlFile($db, __DIR__ . '/../Database/migrations/010_notifications.sql');
        self::ensurePreferenceColumns($db);
    }

    private static function ensurePreferenceColumns(PDO $db): void
    {
        if (!self::tableExists($db, 'notification_preferences')) {
            return;
        }
        if (!self::hasColumn($db, 'notification_preferences', 'whatsapp_phone')) {
            try {
                $db->exec(
                    'ALTER TABLE notification_preferences
                     ADD COLUMN whatsapp_phone VARCHAR(20) NULL AFTER whatsapp_enabled'
                );
            } catch (PDOException $e) {
                error_log('NotificationSchemaMigrator whatsapp_phone: ' . $e->getMessage());
            }
        }
    }

    private static function migrateLegacyTable(PDO $db): void
    {
        try {
            if (!self::tableExists($db, 'notifications')) {
                return;
            }
            if (!self::hasColumn($db, 'notifications', 'uuid')) {
                if (self::tableExists($db, 'notifications_legacy')) {
                    $db->exec('DROP TABLE notifications');
                } else {
                    $db->exec('RENAME TABLE notifications TO notifications_legacy');
                }
            }
        } catch (PDOException $e) {
            error_log('NotificationSchemaMigrator legacy: ' . $e->getMessage());
        }
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    public static function isReady(PDO $db): bool
    {
        try {
            $stmt = $db->query(
                "SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notifications' LIMIT 1"
            );
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private static function runSqlFile(PDO $db, string $path): void
    {
        if (!is_readable($path)) {
            error_log('NotificationSchemaMigrator: file not found ' . $path);
            return;
        }
        $sql = file_get_contents($path);
        if ($sql === false) {
            return;
        }
        $sql = preg_replace('/--[^\r\n]*/', '', $sql) ?? $sql;
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $stmt) {
            if ($stmt === '') {
                continue;
            }
            try {
                $db->exec($stmt);
            } catch (PDOException $e) {
                if (!str_contains($e->getMessage(), 'Duplicate')) {
                    error_log('NotificationSchemaMigrator: ' . $e->getMessage());
                }
            }
        }
    }
}

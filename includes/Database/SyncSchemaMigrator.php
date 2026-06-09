<?php
/**
 * Colonnes/tables pour la surveillance sync hors ligne.
 */
class SyncSchemaMigrator
{
    private static bool $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        if (!self::tableExists($db, 'store_sync_status')) {
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS store_sync_status (
                        store_id INT NOT NULL PRIMARY KEY,
                        is_online TINYINT(1) NOT NULL DEFAULT 1,
                        last_seen_at TIMESTAMP NULL DEFAULT NULL,
                        last_sync_at TIMESTAMP NULL DEFAULT NULL,
                        pending_local_count INT NOT NULL DEFAULT 0,
                        updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $e) {
                error_log('SyncSchemaMigrator store_sync_status: ' . $e->getMessage());
            }
        }

        $queueCols = [
            'error_message' => 'ALTER TABLE synchronization_queue ADD COLUMN error_message TEXT NULL',
            'retry_count'   => 'ALTER TABLE synchronization_queue ADD COLUMN retry_count INT NOT NULL DEFAULT 0',
            'updated_at'    => 'ALTER TABLE synchronization_queue ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
            'resolved_at'   => 'ALTER TABLE synchronization_queue ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL',
            'local_uuid'    => 'ALTER TABLE synchronization_queue ADD COLUMN local_uuid VARCHAR(100) NULL',
        ];
        foreach ($queueCols as $col => $sql) {
            if (self::tableExists($db, 'synchronization_queue') && !self::hasColumn($db, 'synchronization_queue', $col)) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    error_log('SyncSchemaMigrator queue.' . $col . ': ' . $e->getMessage());
                }
            }
        }

        $offlineCols = [
            'error_message'    => 'ALTER TABLE offline_transactions ADD COLUMN error_message TEXT NULL',
            'conflict_reason'  => 'ALTER TABLE offline_transactions ADD COLUMN conflict_reason VARCHAR(255) NULL',
            'resolved_at'      => 'ALTER TABLE offline_transactions ADD COLUMN resolved_at TIMESTAMP NULL DEFAULT NULL',
            'resolved_by'      => 'ALTER TABLE offline_transactions ADD COLUMN resolved_by INT NULL',
        ];
        foreach ($offlineCols as $col => $sql) {
            if (self::tableExists($db, 'offline_transactions') && !self::hasColumn($db, 'offline_transactions', $col)) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    error_log('SyncSchemaMigrator offline.' . $col . ': ' . $e->getMessage());
                }
            }
        }

        if (self::tableExists($db, 'offline_transactions')) {
            try {
                $db->exec(
                    "ALTER TABLE offline_transactions
                     MODIFY COLUMN status ENUM('pending','synced','conflict','failed') NOT NULL DEFAULT 'pending'"
                );
            } catch (PDOException $e) {
                // enum déjà à jour
            }
        }

        if (self::tableExists($db, 'synchronization_queue')) {
            try {
                $db->exec(
                    "ALTER TABLE synchronization_queue
                     MODIFY COLUMN status ENUM('pending','synced','failed','conflict') NOT NULL DEFAULT 'pending'"
                );
            } catch (PDOException $e) {
                // ignore
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

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

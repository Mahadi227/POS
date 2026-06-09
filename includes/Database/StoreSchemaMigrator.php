<?php
/**
 * Applique les colonnes/tables multi-succursales si elles manquent (sans casser l'existant).
 */
class StoreSchemaMigrator
{
    /** @var bool */
    private static $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        $columns = [
            'code' => 'ALTER TABLE stores ADD COLUMN code VARCHAR(20) NULL AFTER id',
            'phone' => 'ALTER TABLE stores ADD COLUMN phone VARCHAR(30) NULL AFTER location',
            'email' => 'ALTER TABLE stores ADD COLUMN email VARCHAR(255) NULL AFTER location',
            'is_active' => 'ALTER TABLE stores ADD COLUMN is_active TINYINT(1) NOT NULL DEFAULT 1',
            'updated_at' => 'ALTER TABLE stores ADD COLUMN updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP',
        ];

        foreach ($columns as $col => $sql) {
            if (!self::hasColumn($db, 'stores', $col)) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    error_log('StoreSchemaMigrator: ' . $e->getMessage());
                }
            }
        }

        if (!self::hasColumn($db, 'stores', 'phone') && self::hasColumn($db, 'stores', 'location')) {
            try {
                $db->exec('ALTER TABLE stores ADD COLUMN phone VARCHAR(30) NULL AFTER location');
            } catch (PDOException $e) {
                // ignore
            }
        }
        if (!self::hasColumn($db, 'stores', 'email')) {
            try {
                $db->exec('ALTER TABLE stores ADD COLUMN email VARCHAR(255) NULL');
            } catch (PDOException $e) {
                // ignore
            }
        }

        if (!self::tableExists($db, 'user_stores')) {
            try {
                $db->exec("
                    CREATE TABLE IF NOT EXISTS user_stores (
                        user_id INT NOT NULL,
                        store_id INT NOT NULL,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                        PRIMARY KEY (user_id, store_id),
                        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                        FOREIGN KEY (store_id) REFERENCES stores(id) ON DELETE CASCADE
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
                ");
            } catch (PDOException $e) {
                error_log('StoreSchemaMigrator user_stores: ' . $e->getMessage());
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

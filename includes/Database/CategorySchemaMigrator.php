<?php
/**
 * Adds store_id to categories for per-store category isolation.
 */
class CategorySchemaMigrator
{
    /** @var bool */
    private static $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        if (!self::tableExists($db, 'categories')) {
            return;
        }

        if (!self::hasColumn($db, 'categories', 'store_id')) {
            try {
                $db->exec('ALTER TABLE categories ADD COLUMN store_id INT NULL AFTER parent_id');
            } catch (PDOException $e) {
                error_log('CategorySchemaMigrator store_id: ' . $e->getMessage());
                return;
            }
        }

        try {
            $db->exec('CREATE INDEX IF NOT EXISTS idx_categories_store ON categories(store_id)');
        } catch (PDOException $e) {
            try {
                $db->exec('CREATE INDEX idx_categories_store ON categories(store_id)');
            } catch (PDOException $e2) {
                // ignore duplicate index
            }
        }

        self::backfillStoreIds($db);
    }

    private static function backfillStoreIds(PDO $db): void
    {
        try {
            $db->exec(
                'UPDATE categories c
                 SET store_id = (
                     SELECT p.store_id FROM products p
                     WHERE p.category_id = c.id AND p.deleted_at IS NULL
                     ORDER BY p.id ASC
                     LIMIT 1
                 )
                 WHERE c.store_id IS NULL'
            );
        } catch (PDOException $e) {
            error_log('CategorySchemaMigrator backfill from products: ' . $e->getMessage());
        }

        try {
            $defaultStore = (int) ($db->query(
                'SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
            )->fetchColumn() ?: 1);
            $stmt = $db->prepare('UPDATE categories SET store_id = ? WHERE store_id IS NULL');
            $stmt->execute([$defaultStore > 0 ? $defaultStore : 1]);
        } catch (PDOException $e) {
            error_log('CategorySchemaMigrator backfill default store: ' . $e->getMessage());
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

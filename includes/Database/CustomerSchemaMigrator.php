<?php
/**
 * Adds store_id to customers for per-store customer lists (POS cart, cashier module).
 */
class CustomerSchemaMigrator
{
    /** @var bool */
    private static $done = false;

    public static function ensure(PDO $db): void
    {
        if (self::$done) {
            return;
        }
        self::$done = true;

        if (!self::tableExists($db, 'customers')) {
            return;
        }

        if (!self::hasColumn($db, 'customers', 'store_id')) {
            try {
                $db->exec('ALTER TABLE customers ADD COLUMN store_id INT NULL AFTER loyalty_points');
            } catch (PDOException $e) {
                error_log('CustomerSchemaMigrator store_id: ' . $e->getMessage());
                return;
            }
        }

        try {
            $db->exec('CREATE INDEX IF NOT EXISTS idx_customers_store ON customers(store_id)');
        } catch (PDOException $e) {
            try {
                $db->exec('CREATE INDEX idx_customers_store ON customers(store_id)');
            } catch (PDOException $e2) {
                // ignore duplicate index
            }
        }

        self::backfillStoreIds($db);
    }

    private static function backfillStoreIds(PDO $db): void
    {
        if (self::tableExists($db, 'sales')) {
            try {
                $db->exec(
                    'UPDATE customers c
                     SET store_id = (
                         SELECT s.store_id FROM sales s
                         WHERE s.customer_id = c.id AND s.deleted_at IS NULL
                         ORDER BY s.id DESC
                         LIMIT 1
                     )
                     WHERE c.store_id IS NULL'
                );
            } catch (PDOException $e) {
                error_log('CustomerSchemaMigrator backfill from sales: ' . $e->getMessage());
            }
        }

        try {
            $defaultStore = (int) ($db->query(
                'SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
            )->fetchColumn() ?: 1);
            $stmt = $db->prepare('UPDATE customers SET store_id = ? WHERE store_id IS NULL');
            $stmt->execute([$defaultStore > 0 ? $defaultStore : 1]);
        } catch (PDOException $e) {
            error_log('CustomerSchemaMigrator backfill default store: ' . $e->getMessage());
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

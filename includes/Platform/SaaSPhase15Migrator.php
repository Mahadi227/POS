<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 15 — e-commerce storefront schema and plan module.
 */
final class SaaSPhase15Migrator
{
    private static bool $done = false;

    public const VERSION = '033_saas_phase15';

    public static function ensure(PDO $db): bool
    {
        if (self::$done) {
            return self::isApplied($db);
        }
        self::$done = true;

        if (!TenantSchemaMigrator::isReady($db)) {
            TenantSchemaMigrator::ensure($db);
        }

        if (self::isApplied($db)) {
            return true;
        }

        self::createTables($db);
        self::alterProducts($db);
        self::alterSales($db);
        self::updatePlans($db);
        self::markApplied($db);

        return true;
    }

    public static function isApplied(PDO $db): bool
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = ? LIMIT 1');
        $stmt->execute([self::VERSION]);
        return (bool) $stmt->fetchColumn();
    }

    private static function createTables(PDO $db): void
    {
        $path = __DIR__ . '/../Database/migrations/033_ecommerce.sql';
        if (!is_file($path)) {
            return;
        }
        $sql = file_get_contents($path) ?: '';
        foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
            if ($statement === '' || str_starts_with($statement, '--')) {
                continue;
            }
            try {
                $db->exec($statement);
            } catch (PDOException $e) {
                error_log('SaaSPhase15Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function alterProducts(PDO $db): void
    {
        if (self::tableExists($db, 'products') && !self::hasColumn($db, 'products', 'is_online')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN is_online TINYINT(1) NOT NULL DEFAULT 1 AFTER image_url');
            } catch (PDOException $e) {
                error_log('SaaSPhase15Migrator products.is_online: ' . $e->getMessage());
            }
        }
        if (self::tableExists($db, 'products') && !self::hasColumn($db, 'products', 'brand_id')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN brand_id INT UNSIGNED NULL AFTER category_id');
            } catch (PDOException $e) {
                error_log('SaaSPhase15Migrator products.brand_id: ' . $e->getMessage());
            }
        }
        if (self::tableExists($db, 'products') && !self::hasColumn($db, 'products', 'slug')) {
            try {
                $db->exec('ALTER TABLE products ADD COLUMN slug VARCHAR(160) NULL AFTER name');
            } catch (PDOException $e) {
                error_log('SaaSPhase15Migrator products.slug: ' . $e->getMessage());
            }
        }
    }

    private static function alterSales(PDO $db): void
    {
        if (self::tableExists($db, 'sales') && !self::hasColumn($db, 'sales', 'channel')) {
            try {
                $db->exec("ALTER TABLE sales ADD COLUMN channel VARCHAR(16) NOT NULL DEFAULT 'pos' AFTER status");
            } catch (PDOException $e) {
                error_log('SaaSPhase15Migrator sales.channel: ' . $e->getMessage());
            }
        }
    }

    private static function updatePlans(PDO $db): void
    {
        if (!self::tableExists($db, 'subscription_plans')) {
            return;
        }
        $updates = [
            'business' => ['ecommerce' => true],
            'enterprise' => ['ecommerce' => true],
        ];
        foreach ($updates as $code => $add) {
            $stmt = $db->prepare('SELECT modules_json FROM subscription_plans WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
            $raw = $stmt->fetchColumn();
            if (!$raw) {
                continue;
            }
            $mods = json_decode((string) $raw, true) ?: [];
            foreach ($add as $k => $v) {
                $mods[$k] = $v;
            }
            $db->prepare('UPDATE subscription_plans SET modules_json = ? WHERE code = ?')
                ->execute([json_encode($mods, JSON_UNESCAPED_UNICODE), $code]);
        }
    }

    private static function markApplied(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }
        $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([self::VERSION]);
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
}

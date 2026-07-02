<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 3 — platform audit log, feature flags, console support tools.
 */
final class SaaSPhase3Migrator
{
    private static bool $done = false;

    public const VERSION = '021_saas_phase3';

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
        self::seedFeatureFlags($db);
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
        $statements = [
            "CREATE TABLE IF NOT EXISTS platform_audit_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                platform_user_id BIGINT UNSIGNED NULL,
                tenant_id BIGINT UNSIGNED NULL,
                action VARCHAR(64) NOT NULL,
                details_json JSON NULL,
                ip_address VARCHAR(45) NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_pal_tenant (tenant_id),
                KEY idx_pal_user (platform_user_id),
                KEY idx_pal_action (action)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS feature_flags (
                key_name VARCHAR(64) NOT NULL PRIMARY KEY,
                description VARCHAR(255) NOT NULL DEFAULT '',
                default_enabled TINYINT(1) NOT NULL DEFAULT 0
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS tenant_feature_flags (
                tenant_id BIGINT UNSIGNED NOT NULL,
                key_name VARCHAR(64) NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 0,
                PRIMARY KEY (tenant_id, key_name)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('SaaSPhase3Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function seedFeatureFlags(PDO $db): void
    {
        if (!self::tableExists($db, 'feature_flags')) {
            return;
        }
        $flags = [
            ['beta_sync_v2', 'Next-gen sync engine (beta)', 0],
            ['advanced_reports', 'Advanced analytics reports', 0],
            ['api_v2_preview', 'API v2 preview endpoints', 0],
        ];
        $stmt = $db->prepare(
            'INSERT IGNORE INTO feature_flags (key_name, description, default_enabled) VALUES (?, ?, ?)'
        );
        foreach ($flags as $row) {
            try {
                $stmt->execute($row);
            } catch (PDOException) {
            }
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
}

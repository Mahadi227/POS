<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 8 — Platform license keys for cloud, on-prem, and partner deployments.
 */
final class SaaSPhase8Migrator
{
    private static bool $done = false;

    public const VERSION = '026_saas_phase8';

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
        try {
            $db->exec(
                "CREATE TABLE IF NOT EXISTS tenant_licenses (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id BIGINT UNSIGNED NULL,
                    license_key_hash CHAR(64) NOT NULL,
                    key_prefix VARCHAR(16) NOT NULL,
                    license_type ENUM('cloud','on_prem','partner','trial') NOT NULL DEFAULT 'cloud',
                    status ENUM('active','revoked','expired') NOT NULL DEFAULT 'active',
                    plan_code VARCHAR(32) NULL,
                    max_seats INT UNSIGNED NULL,
                    notes VARCHAR(512) NULL,
                    issued_by BIGINT UNSIGNED NULL,
                    expires_at DATETIME NULL,
                    revoked_at DATETIME NULL,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_license_hash (license_key_hash),
                    KEY idx_license_tenant (tenant_id),
                    KEY idx_license_status (status),
                    KEY idx_license_type (license_type),
                    KEY idx_license_prefix (key_prefix)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (PDOException $e) {
            error_log('SaaSPhase8Migrator: ' . $e->getMessage());
        }
    }

    private static function markApplied(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }
        $db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute([self::VERSION]);
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 7 — API keys, rate limiting, developer integrations.
 */
final class SaaSPhase7Migrator
{
    private static bool $done = false;

    public const VERSION = '025_saas_phase7';

    public static function ensure(PDO $db): bool
    {
        if (self::$done) {
            self::ensureAuxiliary($db);
            return self::isApplied($db);
        }
        self::$done = true;

        if (!TenantSchemaMigrator::isReady($db)) {
            TenantSchemaMigrator::ensure($db);
        }

        if (self::isApplied($db)) {
            self::ensureAuxiliary($db);
            return true;
        }

        self::createTables($db);
        self::ensureAuxiliary($db);
        self::updatePlanApiLimits($db);
        self::markApplied($db);

        return true;
    }

    public static function ensureAuxiliary(PDO $db): void
    {
        if (!self::tableExists($db, 'api_idempotency_keys')) {
            try {
                $db->exec(
                    "CREATE TABLE IF NOT EXISTS api_idempotency_keys (
                        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                        tenant_id BIGINT UNSIGNED NOT NULL,
                        idempotency_key VARCHAR(64) NOT NULL,
                        response_json JSON NOT NULL,
                        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                        UNIQUE KEY uq_aik (tenant_id, idempotency_key)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
                );
            } catch (PDOException $e) {
                error_log('SaaSPhase7Migrator idempotency: ' . $e->getMessage());
            }
        }
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
            "CREATE TABLE IF NOT EXISTS tenant_api_keys (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(128) NOT NULL,
                key_prefix VARCHAR(16) NOT NULL,
                key_hash CHAR(64) NOT NULL,
                scopes_json JSON NOT NULL,
                created_by BIGINT UNSIGNED NULL,
                last_used_at DATETIME NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_tak_hash (key_hash),
                KEY idx_tak_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS api_rate_limit_buckets (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                bucket VARCHAR(16) NOT NULL,
                bucket_key VARCHAR(32) NOT NULL,
                hits INT UNSIGNED NOT NULL DEFAULT 0,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_arlb (tenant_id, bucket, bucket_key),
                KEY idx_arlb_updated (updated_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('SaaSPhase7Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function updatePlanApiLimits(PDO $db): void
    {
        if (!self::tableExists($db, 'subscription_plans')) {
            return;
        }

        $updates = [
            'business' => [
                'api_access' => true,
                'max_api_calls_per_month' => 50000,
                'api_burst_per_minute' => 100,
            ],
            'enterprise' => [
                'api_access' => true,
                'max_api_calls_per_month' => 500000,
                'api_burst_per_minute' => 1000,
            ],
        ];

        foreach ($updates as $code => $apiMods) {
            $stmt = $db->prepare('SELECT modules_json FROM subscription_plans WHERE code = ? LIMIT 1');
            $stmt->execute([$code]);
            $raw = $stmt->fetchColumn();
            if (!$raw) {
                continue;
            }
            $mods = json_decode((string) $raw, true) ?: [];
            foreach ($apiMods as $k => $v) {
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
}

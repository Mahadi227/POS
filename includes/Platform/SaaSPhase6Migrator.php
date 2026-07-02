<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 6 — outbound webhooks, status page, transactional email log.
 */
final class SaaSPhase6Migrator
{
    private static bool $done = false;

    public const VERSION = '024_saas_phase6';

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
        self::seedStatusComponents($db);
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
            "CREATE TABLE IF NOT EXISTS webhook_endpoints (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                url VARCHAR(512) NOT NULL,
                secret VARCHAR(64) NOT NULL,
                events_json JSON NOT NULL,
                description VARCHAR(255) NULL,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                KEY idx_we_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS webhook_deliveries (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                endpoint_id BIGINT UNSIGNED NOT NULL,
                tenant_id BIGINT UNSIGNED NOT NULL,
                delivery_uuid CHAR(36) NOT NULL,
                event_type VARCHAR(64) NOT NULL,
                payload_json JSON NOT NULL,
                response_status SMALLINT NULL,
                response_body TEXT NULL,
                attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
                next_retry_at DATETIME NULL,
                delivered_at DATETIME NULL,
                failed_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_wd_uuid (delivery_uuid),
                KEY idx_wd_endpoint (endpoint_id),
                KEY idx_wd_retry (next_retry_at, delivered_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS platform_status_components (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(128) NOT NULL,
                sort_order TINYINT UNSIGNED NOT NULL DEFAULT 0,
                status ENUM('operational','degraded','partial_outage','major_outage','maintenance') NOT NULL DEFAULT 'operational',
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_psc_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS platform_incidents (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                title VARCHAR(255) NOT NULL,
                message TEXT NOT NULL,
                severity ENUM('minor','major','critical') NOT NULL DEFAULT 'minor',
                status ENUM('investigating','identified','monitoring','resolved') NOT NULL DEFAULT 'investigating',
                affects_json JSON NULL,
                started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                resolved_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS transactional_email_log (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NULL,
                user_id BIGINT UNSIGNED NULL,
                template_key VARCHAR(64) NOT NULL,
                recipient VARCHAR(255) NOT NULL,
                sent_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_tel_dedupe (tenant_id, template_key, recipient, sent_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('SaaSPhase6Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function seedStatusComponents(PDO $db): void
    {
        if (!self::tableExists($db, 'platform_status_components')) {
            return;
        }
        $components = [
            ['api', 'API', 1],
            ['pos', 'POS & Cashier', 2],
            ['portals', 'Web Portals', 3],
            ['sync', 'Offline Sync', 4],
            ['billing', 'Billing & Subscriptions', 5],
        ];
        $stmt = $db->prepare(
            'INSERT IGNORE INTO platform_status_components (code, name, sort_order, status) VALUES (?, ?, ?, ?)'
        );
        foreach ($components as [$code, $name, $order]) {
            try {
                $stmt->execute([$code, $name, $order, 'operational']);
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

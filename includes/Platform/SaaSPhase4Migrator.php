<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 4 — tenant domains, white-label plan flag, usage alerts.
 */
final class SaaSPhase4Migrator
{
    private static bool $done = false;

    public const VERSION = '022_saas_phase4';

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
        self::updateEnterprisePlan($db);
        self::backfillTenantDomains($db);
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
        $sql = "CREATE TABLE IF NOT EXISTS tenant_domains (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            hostname VARCHAR(255) NOT NULL,
            kind ENUM('subdomain','custom') NOT NULL DEFAULT 'subdomain',
            is_verified TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_tenant_domains_host (hostname),
            KEY idx_td_tenant (tenant_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $db->exec($sql);
        } catch (PDOException $e) {
            error_log('SaaSPhase4Migrator tenant_domains: ' . $e->getMessage());
        }

        $sql2 = "CREATE TABLE IF NOT EXISTS usage_alerts (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            tenant_id BIGINT UNSIGNED NOT NULL,
            metric VARCHAR(64) NOT NULL,
            threshold_pct TINYINT UNSIGNED NOT NULL,
            period DATE NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uk_usage_alert (tenant_id, metric, threshold_pct, period)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
        try {
            $db->exec($sql2);
        } catch (PDOException $e) {
            error_log('SaaSPhase4Migrator usage_alerts: ' . $e->getMessage());
        }
    }

    private static function updateEnterprisePlan(PDO $db): void
    {
        if (!self::tableExists($db, 'subscription_plans')) {
            return;
        }
        $stmt = $db->query("SELECT modules_json FROM subscription_plans WHERE code = 'enterprise' LIMIT 1");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return;
        }
        $mods = json_decode($row['modules_json'] ?? '{}', true);
        if (!is_array($mods)) {
            $mods = [];
        }
        $mods['white_label'] = true;
        $db->prepare('UPDATE subscription_plans SET modules_json = ? WHERE code = ?')
            ->execute([json_encode($mods, JSON_UNESCAPED_UNICODE), 'enterprise']);
    }

    private static function backfillTenantDomains(PDO $db): void
    {
        if (!self::tableExists($db, 'tenant_domains') || !self::tableExists($db, 'tenants')) {
            return;
        }
        $rows = $db->query('SELECT id, slug FROM tenants WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $stmt = $db->prepare(
            'INSERT IGNORE INTO tenant_domains (tenant_id, hostname, kind, is_verified) VALUES (?, ?, ?, 1)'
        );
        foreach ($rows as $row) {
            $slug = trim((string) ($row['slug'] ?? ''));
            if ($slug === '') {
                continue;
            }
            try {
                $stmt->execute([(int) $row['id'], $slug, 'subdomain']);
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

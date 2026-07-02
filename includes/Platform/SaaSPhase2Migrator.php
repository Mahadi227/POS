<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 2 — billing, usage metrics, trial fields.
 */
final class SaaSPhase2Migrator
{
    private static bool $done = false;

    public const VERSION = '020_saas_phase2';

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

        self::extendTenants($db);
        self::extendSubscriptions($db);
        self::createBillingTables($db);
        self::updatePlanPricing($db);
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

    private static function extendTenants(PDO $db): void
    {
        if (!self::hasColumn($db, 'tenants', 'trial_ends_at')) {
            try {
                $db->exec('ALTER TABLE tenants ADD COLUMN trial_ends_at DATETIME NULL AFTER status');
            } catch (PDOException $e) {
                error_log('SaaSPhase2Migrator trial_ends_at: ' . $e->getMessage());
            }
        }
        if (!self::hasColumn($db, 'tenants', 'plan_id')) {
            try {
                $db->exec('ALTER TABLE tenants ADD COLUMN plan_id BIGINT UNSIGNED NULL AFTER trial_ends_at');
            } catch (PDOException $e) {
                error_log('SaaSPhase2Migrator plan_id: ' . $e->getMessage());
            }
        }
        // Legacy tenant: mark trial ended, active status
        try {
            $db->exec("UPDATE tenants SET status = 'active' WHERE id = 1 AND slug = 'legacy'");
        } catch (PDOException) {
        }
    }

    private static function extendSubscriptions(PDO $db): void
    {
        $cols = [
            'stripe_customer_id' => 'ALTER TABLE tenant_subscriptions ADD COLUMN stripe_customer_id VARCHAR(128) NULL',
            'stripe_subscription_id' => 'ALTER TABLE tenant_subscriptions ADD COLUMN stripe_subscription_id VARCHAR(128) NULL',
            'external_id' => 'ALTER TABLE tenant_subscriptions ADD COLUMN external_id VARCHAR(128) NULL',
        ];
        foreach ($cols as $col => $sql) {
            if (self::tableExists($db, 'tenant_subscriptions') && !self::hasColumn($db, 'tenant_subscriptions', $col)) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    error_log('SaaSPhase2Migrator sub.' . $col . ': ' . $e->getMessage());
                }
            }
        }
    }

    private static function createBillingTables(PDO $db): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS billing_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                type ENUM('invoice','payment','refund','failed','checkout','subscription_updated') NOT NULL,
                amount DECIMAL(12,2) NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                external_id VARCHAR(128) NULL,
                metadata_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_billing_tenant (tenant_id),
                KEY idx_billing_type (type)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS usage_metrics (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                metric VARCHAR(64) NOT NULL,
                period DATE NOT NULL,
                value BIGINT UNSIGNED NOT NULL DEFAULT 0,
                UNIQUE KEY uk_usage (tenant_id, metric, period),
                KEY idx_usage_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS tenant_module_overrides (
                tenant_id BIGINT UNSIGNED NOT NULL,
                module_key VARCHAR(64) NOT NULL,
                enabled TINYINT(1) NOT NULL DEFAULT 1,
                PRIMARY KEY (tenant_id, module_key)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];
        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('SaaSPhase2Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function updatePlanPricing(PDO $db): void
    {
        if (!self::tableExists($db, 'subscription_plans')) {
            return;
        }
        $plans = [
            ['starter', 29.00],
            ['business', 99.00],
            ['enterprise', 299.00],
        ];
        $stmt = $db->prepare('UPDATE subscription_plans SET price_monthly = ? WHERE code = ?');
        foreach ($plans as [$code, $price]) {
            try {
                $stmt->execute([$price, $code]);
            } catch (PDOException) {
            }
        }
    }

    private static function markApplied(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }
        $db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)')->execute([self::VERSION]);
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

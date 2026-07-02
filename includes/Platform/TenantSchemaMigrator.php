<?php
declare(strict_types=1);

/**
 * SaaS tenant schema — creates tenants/platform tables and adds tenant_id to core tables.
 */
final class TenantSchemaMigrator
{
    private static bool $done = false;

    public const VERSION = '019_tenant_foundation';

    /** @var string[] */
    private const TENANT_SCOPED_TABLES = [
        'stores',
        'users',
        'products',
        'categories',
        'customers',
        'sales',
        'warehouses',
        'cash_registers',
        'roles',
    ];

    public static function ensure(PDO $db): bool
    {
        if (self::$done) {
            return self::tableExists($db, 'tenants');
        }
        self::$done = true;

        if (self::isApplied($db)) {
            return true;
        }

        self::createCoreTables($db);
        self::seedLegacyTenant($db);
        self::seedDefaultPlans($db);
        self::seedPlatformAdmin($db);
        self::addTenantColumns($db);
        self::markApplied($db);

        return true;
    }

    public static function isReady(PDO $db): bool
    {
        return self::tableExists($db, 'tenants');
    }

    private static function createCoreTables(PDO $db): void
    {
        $statements = [
            "CREATE TABLE IF NOT EXISTS tenants (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                uuid CHAR(36) NOT NULL,
                slug VARCHAR(63) NOT NULL,
                name VARCHAR(255) NOT NULL,
                country_code CHAR(2) NOT NULL DEFAULT 'SN',
                timezone VARCHAR(64) NOT NULL DEFAULT 'Africa/Dakar',
                default_currency VARCHAR(8) NOT NULL DEFAULT 'XOF',
                status ENUM('trial','active','suspended','cancelled') NOT NULL DEFAULT 'active',
                settings_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                deleted_at DATETIME NULL,
                UNIQUE KEY uq_tenants_uuid (uuid),
                UNIQUE KEY uq_tenants_slug (slug),
                KEY idx_tenants_status (status)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS subscription_plans (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                code VARCHAR(32) NOT NULL,
                name VARCHAR(128) NOT NULL,
                max_stores INT UNSIGNED NULL,
                max_users INT UNSIGNED NULL,
                modules_json JSON NOT NULL,
                price_monthly DECIMAL(12,2) NOT NULL DEFAULT 0,
                currency CHAR(3) NOT NULL DEFAULT 'EUR',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                UNIQUE KEY uq_plans_code (code)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS tenant_subscriptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                plan_id BIGINT UNSIGNED NOT NULL,
                status ENUM('active','past_due','cancelled','trial') NOT NULL DEFAULT 'active',
                current_period_start DATE NULL,
                current_period_end DATE NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                KEY idx_ts_tenant (tenant_id),
                CONSTRAINT fk_ts_tenant FOREIGN KEY (tenant_id) REFERENCES tenants(id),
                CONSTRAINT fk_ts_plan FOREIGN KEY (plan_id) REFERENCES subscription_plans(id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS platform_users (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(255) NOT NULL,
                password_hash VARCHAR(255) NOT NULL,
                name VARCHAR(255) NOT NULL,
                role ENUM('platform_admin','support') NOT NULL DEFAULT 'platform_admin',
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                last_login DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY uq_platform_users_email (email)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS schema_migrations (
                version VARCHAR(32) NOT NULL PRIMARY KEY,
                applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS api_refresh_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                tenant_id BIGINT UNSIGNED NOT NULL,
                token_hash CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_refresh_hash (token_hash),
                KEY idx_refresh_user (user_id, tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('TenantSchemaMigrator: ' . $e->getMessage());
            }
        }
    }

    private static function seedLegacyTenant(PDO $db): void
    {
        if (!self::tableExists($db, 'tenants')) {
            return;
        }
        $count = (int) $db->query('SELECT COUNT(*) FROM tenants')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $uuid = self::uuid4();
        $stmt = $db->prepare(
            'INSERT INTO tenants (id, uuid, slug, name, country_code, default_currency, status)
             VALUES (1, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$uuid, 'legacy', 'Legacy Organization', 'SN', 'XOF', 'active']);
    }

    private static function seedDefaultPlans(PDO $db): void
    {
        if (!self::tableExists($db, 'subscription_plans')) {
            return;
        }
        $count = (int) $db->query('SELECT COUNT(*) FROM subscription_plans')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $plans = [
            ['starter', 'Starter', 1, 5, '{"pos":true,"inventory":true,"warehouse":false,"accounting":false}'],
            ['business', 'Business', 5, 25, '{"pos":true,"inventory":true,"cash_registers":true,"manager":true,"warehouse":false,"accounting":false}'],
            ['enterprise', 'Enterprise', null, null, '{"pos":true,"inventory":true,"cash_registers":true,"manager":true,"warehouse":true,"accounting":true,"api_access":true}'],
        ];

        $stmt = $db->prepare(
            'INSERT INTO subscription_plans (code, name, max_stores, max_users, modules_json, price_monthly, currency)
             VALUES (?, ?, ?, ?, ?, 0, ?)'
        );
        foreach ($plans as [$code, $name, $stores, $users, $modules]) {
            $stmt->execute([$code, $name, $stores, $users, $modules, 'EUR']);
        }

        if (self::tableExists($db, 'tenant_subscriptions') && self::tableExists($db, 'tenants')) {
            $planId = (int) $db->query("SELECT id FROM subscription_plans WHERE code = 'enterprise' LIMIT 1")->fetchColumn();
            if ($planId > 0) {
                $subCount = (int) $db->query('SELECT COUNT(*) FROM tenant_subscriptions WHERE tenant_id = 1')->fetchColumn();
                if ($subCount === 0) {
                    $db->prepare(
                        'INSERT INTO tenant_subscriptions (tenant_id, plan_id, status, current_period_start, current_period_end)
                         VALUES (1, ?, ?, CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))'
                    )->execute([$planId, 'active']);
                }
            }
        }
    }

    private static function seedPlatformAdmin(PDO $db): void
    {
        if (!self::tableExists($db, 'platform_users')) {
            return;
        }
        $count = (int) $db->query('SELECT COUNT(*) FROM platform_users')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $email = 'platform@retailpos.local';
        $hash = password_hash('PlatformAdmin2026!', PASSWORD_DEFAULT);
        $db->prepare(
            'INSERT INTO platform_users (email, password_hash, name, role) VALUES (?, ?, ?, ?)'
        )->execute([$email, $hash, 'Platform Admin', 'platform_admin']);
    }

    private static function addTenantColumns(PDO $db): void
    {
        foreach (self::TENANT_SCOPED_TABLES as $table) {
            if (!self::tableExists($db, $table)) {
                continue;
            }
            if (self::hasColumn($db, $table, 'tenant_id')) {
                self::backfillTenantId($db, $table);
                continue;
            }
            try {
                $db->exec("ALTER TABLE `{$table}` ADD COLUMN tenant_id BIGINT UNSIGNED NOT NULL DEFAULT 1 AFTER id");
                $db->exec("ALTER TABLE `{$table}` ADD INDEX idx_{$table}_tenant (tenant_id)");
            } catch (PDOException $e) {
                error_log("TenantSchemaMigrator {$table}.tenant_id: " . $e->getMessage());
                continue;
            }
            self::backfillTenantId($db, $table);
        }
    }

    private static function backfillTenantId(PDO $db, string $table): void
    {
        try {
            $db->exec("UPDATE `{$table}` SET tenant_id = 1 WHERE tenant_id IS NULL OR tenant_id = 0");
        } catch (PDOException $e) {
            error_log("TenantSchemaMigrator backfill {$table}: " . $e->getMessage());
        }
    }

    private static function isApplied(PDO $db): bool
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return false;
        }
        $stmt = $db->prepare('SELECT 1 FROM schema_migrations WHERE version = ? LIMIT 1');
        $stmt->execute([self::VERSION]);
        return (bool) $stmt->fetchColumn();
    }

    private static function markApplied(PDO $db): void
    {
        if (!self::tableExists($db, 'schema_migrations')) {
            return;
        }
        $stmt = $db->prepare('INSERT IGNORE INTO schema_migrations (version) VALUES (?)');
        $stmt->execute([self::VERSION]);
    }

    private static function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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

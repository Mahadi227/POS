<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 5 — email verification, onboarding wizard, regional payments.
 */
final class SaaSPhase5Migrator
{
    private static bool $done = false;

    public const VERSION = '023_saas_phase5';

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
        self::extendSubscriptions($db);
        self::backfillVerifiedUsers($db);
        self::seedOnboarding($db);
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
            "CREATE TABLE IF NOT EXISTS email_verification_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id BIGINT UNSIGNED NOT NULL,
                token CHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_evt_token (token),
                KEY idx_evt_user (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS tenant_onboarding (
                tenant_id BIGINT UNSIGNED NOT NULL PRIMARY KEY,
                current_step TINYINT UNSIGNED NOT NULL DEFAULT 1,
                steps_json JSON NULL,
                completed_at DATETIME NULL,
                updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS tenant_invites (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(255) NOT NULL,
                token CHAR(64) NOT NULL,
                role_id BIGINT UNSIGNED NULL,
                status ENUM('pending','accepted','expired') NOT NULL DEFAULT 'pending',
                invited_by BIGINT UNSIGNED NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uq_invite_token (token),
                KEY idx_invite_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
            "CREATE TABLE IF NOT EXISTS mobile_money_payments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                tenant_id BIGINT UNSIGNED NOT NULL,
                plan_code VARCHAR(32) NOT NULL,
                provider ENUM('orange','mtn','wave','moov','other') NOT NULL DEFAULT 'wave',
                phone VARCHAR(32) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'XOF',
                reference VARCHAR(64) NOT NULL,
                status ENUM('pending','confirmed','rejected') NOT NULL DEFAULT 'pending',
                metadata_json JSON NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                confirmed_at DATETIME NULL,
                UNIQUE KEY uq_mm_reference (reference),
                KEY idx_mm_tenant (tenant_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        ];

        foreach ($statements as $sql) {
            try {
                $db->exec($sql);
            } catch (PDOException $e) {
                error_log('SaaSPhase5Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function extendSubscriptions(PDO $db): void
    {
        if (!self::tableExists($db, 'tenant_subscriptions')) {
            return;
        }
        $cols = [
            'paystack_customer_code' => 'ALTER TABLE tenant_subscriptions ADD COLUMN paystack_customer_code VARCHAR(128) NULL',
            'paystack_subscription_code' => 'ALTER TABLE tenant_subscriptions ADD COLUMN paystack_subscription_code VARCHAR(128) NULL',
            'payment_provider' => "ALTER TABLE tenant_subscriptions ADD COLUMN payment_provider ENUM('stripe','paystack','mobile_money','manual') NULL DEFAULT 'manual'",
        ];
        foreach ($cols as $col => $sql) {
            if (!self::hasColumn($db, 'tenant_subscriptions', $col)) {
                try {
                    $db->exec($sql);
                } catch (PDOException $e) {
                    error_log('SaaSPhase5Migrator sub.' . $col . ': ' . $e->getMessage());
                }
            }
        }
    }

    private static function backfillVerifiedUsers(PDO $db): void
    {
        if (!self::hasColumn($db, 'users', 'email_verified_at')) {
            return;
        }
        try {
            $db->exec('UPDATE users SET email_verified_at = COALESCE(email_verified_at, NOW()) WHERE email_verified_at IS NULL');
        } catch (PDOException) {
        }
    }

    private static function seedOnboarding(PDO $db): void
    {
        if (!self::tableExists($db, 'tenant_onboarding') || !self::tableExists($db, 'tenants')) {
            return;
        }
        $rows = $db->query('SELECT id FROM tenants WHERE deleted_at IS NULL')->fetchAll(PDO::FETCH_COLUMN) ?: [];
        $stmt = $db->prepare(
            'INSERT IGNORE INTO tenant_onboarding (tenant_id, current_step, completed_at) VALUES (?, 6, NOW())'
        );
        foreach ($rows as $id) {
            try {
                $stmt->execute([(int) $id]);
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

    private static function hasColumn(PDO $db, string $table, string $column): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

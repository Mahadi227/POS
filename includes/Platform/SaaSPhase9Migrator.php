<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * SaaS Phase 9 — Integration marketplace catalog and tenant installs.
 */
final class SaaSPhase9Migrator
{
    private static bool $done = false;

    public const VERSION = '027_saas_phase9';

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
            self::seedApps($db);
            return true;
        }

        self::createTables($db);
        self::seedApps($db);
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
                "CREATE TABLE IF NOT EXISTS marketplace_apps (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    slug VARCHAR(64) NOT NULL,
                    name VARCHAR(128) NOT NULL,
                    short_description VARCHAR(255) NOT NULL DEFAULT '',
                    description TEXT NULL,
                    category ENUM('payments','developer','branding','analytics','shipping','other') NOT NULL DEFAULT 'other',
                    icon VARCHAR(64) NOT NULL DEFAULT 'extension',
                    vendor VARCHAR(128) NOT NULL DEFAULT 'RetailPOS',
                    status ENUM('published','draft','deprecated') NOT NULL DEFAULT 'published',
                    is_official TINYINT(1) NOT NULL DEFAULT 0,
                    pricing ENUM('free','paid','contact') NOT NULL DEFAULT 'free',
                    website_url VARCHAR(512) NULL,
                    docs_url VARCHAR(512) NULL,
                    modules_json JSON NULL,
                    sort_order INT NOT NULL DEFAULT 0,
                    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_marketplace_slug (slug),
                    KEY idx_marketplace_category (category),
                    KEY idx_marketplace_status (status)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            $db->exec(
                "CREATE TABLE IF NOT EXISTS tenant_marketplace_installs (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    tenant_id BIGINT UNSIGNED NOT NULL,
                    app_id BIGINT UNSIGNED NOT NULL,
                    status ENUM('active','removed') NOT NULL DEFAULT 'active',
                    installed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    removed_at DATETIME NULL,
                    UNIQUE KEY uq_tmi_tenant_app (tenant_id, app_id),
                    KEY idx_tmi_app (app_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
        } catch (PDOException $e) {
            error_log('SaaSPhase9Migrator: ' . $e->getMessage());
        }
    }

    private static function seedApps(PDO $db): void
    {
        if (!self::tableExists($db, 'marketplace_apps')) {
            return;
        }
        $count = (int) $db->query('SELECT COUNT(*) FROM marketplace_apps')->fetchColumn();
        if ($count > 0) {
            return;
        }

        $apps = [
            ['stripe', 'Stripe', 'Card payments and subscription billing', 'Accept cards worldwide with Stripe Checkout and webhooks.', 'payments', 'credit_card', 'Stripe', 1, 'paid', 'https://stripe.com', null, '[]', 10],
            ['paystack', 'Paystack', 'African card and bank payments', 'Regional checkout for West & East Africa via Paystack.', 'payments', 'payments', 'Paystack', 1, 'paid', 'https://paystack.com', null, '[]', 20],
            ['mobile-money', 'Mobile Money', 'Wave, Orange, MTN, Moov', 'Collect subscription payments via mobile money wallets.', 'payments', 'phone_android', 'RetailPOS', 1, 'free', null, null, '[]', 30],
            ['webhooks', 'Outbound Webhooks', 'Real-time tenant event delivery', 'Dispatch sale, inventory, and billing events to tenant endpoints.', 'developer', 'webhook', 'RetailPOS', 1, 'free', null, null, '["api_access"]', 40],
            ['api-v2', 'REST API v2', 'Machine-to-machine integrations', 'JWT and API key access to stores, products, sales, and tenant data.', 'developer', 'api', 'RetailPOS', 1, 'free', null, null, '["api_access"]', 50],
            ['openapi-portal', 'Developer Portal', 'OpenAPI spec & API keys UI', 'Interactive API docs and tenant API key management.', 'developer', 'code', 'RetailPOS', 1, 'free', null, '../developers/openapi.php', '["api_access"]', 60],
            ['white-label', 'White-label Branding', 'Logo, colors, custom domain', 'Tenant-branded login and receipt experience.', 'branding', 'palette', 'RetailPOS', 1, 'contact', null, '../branding.php', '["white_label"]', 70],
            ['usage-metering', 'Usage Metering', 'Plan limits & consumption', 'Track stores, users, and API usage against subscription limits.', 'analytics', 'monitoring', 'RetailPOS', 1, 'free', null, null, '[]', 80],
        ];

        $stmt = $db->prepare(
            'INSERT INTO marketplace_apps
             (slug, name, short_description, description, category, icon, vendor, is_official, pricing, website_url, docs_url, modules_json, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($apps as $row) {
            $stmt->execute($row);
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

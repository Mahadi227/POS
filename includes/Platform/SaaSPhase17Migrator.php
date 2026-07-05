<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/** SaaS Phase 17 — platform integration providers and tenant connections. */
final class SaaSPhase17Migrator
{
    private static bool $done = false;

    public const VERSION = '035_platform_integrations';

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
            self::seedProviders($db);
            return true;
        }

        self::createTables($db);
        self::seedProviders($db);
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
        $path = dirname(__DIR__) . '/Database/migrations/035_platform_integrations.sql';
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
                error_log('SaaSPhase17Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function seedProviders(PDO $db): void
    {
        if (!self::tableExists($db, 'platform_integration_providers')) {
            return;
        }

        $providers = [
            ['stripe', 'Stripe', 'Card payments and subscription billing', 'payments', 'credit_card', '#635bff', 10],
            ['paypal', 'PayPal', 'PayPal checkout and payouts', 'payments', 'payments', '#003087', 20],
            ['mtn_momo', 'MTN MoMo', 'Mobile money collections via MTN', 'payments', 'phone_android', '#ffcc00', 30],
            ['orange_money', 'Orange Money', 'Orange mobile money gateway', 'payments', 'phone_android', '#ff6600', 40],
            ['wave', 'Wave', 'Wave mobile wallet payments', 'payments', 'account_balance_wallet', '#1dc8f2', 50],
            ['whatsapp', 'WhatsApp Business', 'Order alerts and customer messaging', 'communications', 'chat', '#25d366', 60],
            ['email_smtp', 'Email (SMTP)', 'Transactional and marketing email', 'communications', 'mail', '#4f46e5', 70],
            ['sms_gateway', 'SMS Gateway', 'SMS notifications and OTP delivery', 'communications', 'sms', '#0891b2', 80],
            ['webhooks', 'Outbound Webhooks', 'Real-time event delivery to tenant endpoints', 'developer', 'webhook', '#6366f1', 90],
            ['api_v2', 'REST API v2', 'Machine-to-machine API access with scoped keys', 'developer', 'api', '#0ea5e9', 100],
            ['google_analytics', 'Google Analytics', 'Traffic and conversion analytics', 'analytics', 'analytics', '#ea4335', 110],
        ];

        $stmt = $db->prepare(
            'INSERT IGNORE INTO platform_integration_providers
             (slug, name, short_description, category, icon, brand_color, status, is_official, sort_order)
             VALUES (?, ?, ?, ?, ?, ?, \'enabled\', 1, ?)'
        );

        foreach ($providers as $p) {
            try {
                $stmt->execute([$p[0], $p[1], $p[2], $p[3], $p[4], $p[5], $p[6]]);
            } catch (PDOException $e) {
                error_log('SaaSPhase17Migrator seed: ' . $e->getMessage());
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

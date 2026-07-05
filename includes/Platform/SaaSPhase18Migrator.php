<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/** SaaS Phase 18 — platform release registry and changelog. */
final class SaaSPhase18Migrator
{
    private static bool $done = false;

    public const VERSION = '036_platform_updates';

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
            self::seedReleases($db);
            return true;
        }

        self::createTables($db);
        self::seedReleases($db);
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
        $path = dirname(__DIR__) . '/Database/migrations/036_platform_updates.sql';
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
                error_log('SaaSPhase18Migrator: ' . $e->getMessage());
            }
        }
    }

    private static function seedReleases(PDO $db): void
    {
        if (!self::tableExists($db, 'platform_releases')) {
            return;
        }

        $releases = [
            [
                '3.0.0',
                'Platform Phase 3',
                'Operations hub: backups, integrations, and release management.',
                "- Backups module with mysqldump and tenant snapshots\n- Integrations hub for payment and API connectors\n- Updates console for release tracking\n- French i18n across new modules",
                'minor',
                'released',
                '035_platform_integrations',
                0,
                '2026-07-01 10:00:00',
            ],
            [
                '2.9.0',
                'Governance & profile',
                'Operator profile, help center, and platform admin hub.',
                "- Platform admin hub with security KPIs\n- Operator profile and password change\n- Help center with FAQ and KB guides\n- Audit activity on profile page",
                'minor',
                'released',
                '032_platform_settings_logs',
                0,
                '2026-06-20 09:00:00',
            ],
            [
                '2.8.0',
                'E-commerce storefront',
                'Tenant-facing online shop and admin catalog tools.',
                "- Public e-commerce storefront\n- Product, cart, checkout, and wishlist\n- E-commerce admin portal for tenants\n- Marketing site pricing integration",
                'minor',
                'released',
                '033_ecommerce',
                0,
                '2026-06-10 08:00:00',
            ],
            [
                '3.1.0',
                'Scheduled maintenance window',
                'Database migration batch — pending rollout.',
                "- Schema migration 036\n- Performance indexes on audit log\n- Webhook retry policy tuning",
                'patch',
                'scheduled',
                '036_platform_updates',
                1,
                null,
            ],
        ];

        $stmt = $db->prepare(
            'INSERT IGNORE INTO platform_releases
             (version, title, summary, changelog, release_type, status, migration_version, requires_maintenance, published_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($releases as $r) {
            try {
                $stmt->execute($r);
            } catch (PDOException $e) {
                error_log('SaaSPhase18Migrator seed: ' . $e->getMessage());
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

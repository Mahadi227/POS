<?php
declare(strict_types=1);

require_once __DIR__ . '/TenantSchemaMigrator.php';

/**
 * Tenant data isolation scope (SaaS).
 */
final class TenantScope
{
    private static ?int $tenantId = null;
    private static ?array $tenantRow = null;

    public static function isReady(PDO $db): bool
    {
        return TenantSchemaMigrator::isReady($db);
    }

    public static function set(int $tenantId, ?array $tenantRow = null): void
    {
        self::$tenantId = $tenantId;
        self::$tenantRow = $tenantRow;
        $_SESSION['tenant_id'] = $tenantId;
        if ($tenantRow) {
            $_SESSION['tenant_uuid'] = $tenantRow['uuid'] ?? null;
            $_SESSION['tenant_slug'] = $tenantRow['slug'] ?? null;
            $_SESSION['tenant_name'] = $tenantRow['name'] ?? null;
        }
    }

    public static function id(): int
    {
        if (self::$tenantId !== null) {
            return self::$tenantId;
        }
        if (!empty($_SESSION['tenant_id'])) {
            self::$tenantId = (int) $_SESSION['tenant_id'];
            return self::$tenantId;
        }
        return 1;
    }

    public static function uuid(): ?string
    {
        return $_SESSION['tenant_uuid'] ?? self::$tenantRow['uuid'] ?? null;
    }

    public static function slug(): ?string
    {
        return $_SESSION['tenant_slug'] ?? self::$tenantRow['slug'] ?? null;
    }

    public static function name(): ?string
    {
        return $_SESSION['tenant_name'] ?? self::$tenantRow['name'] ?? null;
    }

    /**
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function sqlFilter(PDO $db, string $column = 'tenant_id', string $tableAlias = ''): array
    {
        if (!self::isReady($db)) {
            return ['', []];
        }

        $col = $tableAlias !== '' ? "{$tableAlias}.{$column}" : $column;
        return [" AND {$col} = ?", [self::id()]];
    }

    public static function assertResource(PDO $db, string $table, int $resourceId, string $idColumn = 'id'): void
    {
        if (!self::isReady($db) || !self::tableExists($db, $table)) {
            return;
        }
        if (!self::hasColumn($db, $table, 'tenant_id')) {
            return;
        }

        $stmt = $db->prepare("SELECT tenant_id FROM `{$table}` WHERE `{$idColumn}` = ? LIMIT 1");
        $stmt->execute([$resourceId]);
        $owner = $stmt->fetchColumn();
        if ($owner === false || (int) $owner !== self::id()) {
            throw new RuntimeException('Cross-tenant access denied');
        }
    }

    public static function resolveBySlug(PDO $db, string $slug): ?array
    {
        if (!self::isReady($db) || $slug === '') {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT id, uuid, slug, name, status FROM tenants
             WHERE slug = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function resolveById(PDO $db, int $tenantId): ?array
    {
        if (!self::isReady($db) || $tenantId <= 0) {
            return null;
        }
        $stmt = $db->prepare(
            'SELECT id, uuid, slug, name, status FROM tenants
             WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function resolveDefault(PDO $db): ?array
    {
        if (!self::isReady($db)) {
            return null;
        }
        $stmt = $db->query(
            'SELECT id, uuid, slug, name, status FROM tenants
             WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public static function loadFromSession(PDO $db): void
    {
        if (!empty($_SESSION['tenant_id'])) {
            self::$tenantId = (int) $_SESSION['tenant_id'];
            return;
        }
        $default = self::resolveDefault($db);
        if ($default) {
            self::set((int) $default['id'], $default);
        }
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

<?php
/**
 * Portée multi-succursales — filtrage SQL et contexte session.
 */
class StoreScope
{
    private static function tenantStoreFilter(PDO $db): array
    {
        if (!class_exists('TenantScope', false)) {
            require_once __DIR__ . '/../Platform/TenantScope.php';
        }
        if (!class_exists('TenantSchemaMigrator', false)) {
            require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
        }
        if (!TenantSchemaMigrator::isReady($db) || !self::hasColumn($db, 'stores', 'tenant_id')) {
            return ['', []];
        }
        return TenantScope::sqlFilter($db, 'tenant_id', 's');
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

    public static function roleSlug(): string
    {
        return strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
    }

    public static function isSuperAdmin(): bool
    {
        return self::roleSlug() === 'super_admin';
    }

    public static function canManageStores(): bool
    {
        return in_array(self::roleSlug(), ['super_admin', 'admin', 'manager'], true);
    }

    /** Magasin actif (contexte UI / API). Null = toutes les succursales (super admin). */
    public static function activeStoreId(): ?int
    {
        if (array_key_exists('active_store_id', $_SESSION) && $_SESSION['active_store_id'] === null) {
            return null;
        }
        if (!empty($_SESSION['active_store_id'])) {
            return (int) $_SESSION['active_store_id'];
        }
        if (!empty($_SESSION['store_id'])) {
            return (int) $_SESSION['store_id'];
        }
        return null;
    }

    public static function setActiveStore(?int $storeId): void
    {
        $_SESSION['active_store_id'] = $storeId;
        if ($storeId !== null) {
            $_SESSION['store_id'] = $storeId;
        }
    }

    /** Super admin sans filtre actif = vue globale. */
    public static function isGlobalView(): bool
    {
        return self::isSuperAdmin() && self::activeStoreId() === null;
    }

    /**
     * IDs des succursales accessibles par l'utilisateur connecté.
     *
     * @return int[]|null null = accès à toutes (super admin vue globale)
     */
    public static function accessibleStoreIds(PDO $db): ?array
    {
        if (self::isSuperAdmin() && self::isGlobalView()) {
            return null;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            return [];
        }

        $ids = [];

        if (self::tableExists($db, 'user_stores')) {
            [$tenantSql, $tenantParams] = self::tenantStoreFilter($db);
            $stmt = $db->prepare(
                'SELECT us.store_id FROM user_stores us
                 INNER JOIN stores s ON s.id = us.store_id AND s.deleted_at IS NULL'
                . $tenantSql .
                ' WHERE us.user_id = ?'
            );
            $stmt->execute(array_merge($tenantParams, [$userId]));
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $primary = !empty($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null;
        if ($primary && !in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        if (self::isSuperAdmin()) {
            [$tenantSql, $tenantParams] = self::tenantStoreFilter($db);
            if ($tenantSql !== '') {
                $stmt = $db->prepare(
                    'SELECT id FROM stores WHERE deleted_at IS NULL' . str_replace('s.tenant_id', 'tenant_id', $tenantSql) .
                    ' ORDER BY name ASC'
                );
                $stmt->execute($tenantParams);
            } else {
                $stmt = $db->query(
                    'SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY name ASC'
                );
            }
            $all = $stmt->fetchAll(PDO::FETCH_COLUMN);
            return array_map('intval', $all ?: []);
        }

        $active = self::activeStoreId();
        if ($active !== null) {
            return in_array($active, $ids, true) ? [$active] : ($ids ?: [$active]);
        }

        return $ids;
    }

    public static function canAccessStore(PDO $db, int $storeId): bool
    {
        $allowed = self::accessibleStoreIds($db);
        if ($allowed === null) {
            return true;
        }
        return in_array($storeId, $allowed, true);
    }

    /**
     * Resolve a valid store ID for the current session (POS / cashier APIs).
     * Persists the resolved ID to session when inferred from the user record.
     */
    public static function resolveStoreId(PDO $db): int
    {
        $active = self::activeStoreId();
        if ($active !== null && $active > 0) {
            return $active;
        }

        if (!empty($_SESSION['store_id'])) {
            $id = (int) $_SESSION['store_id'];
            if ($id > 0) {
                self::setActiveStore($id);
                return $id;
            }
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId > 0) {
            $stmt = $db->prepare('SELECT store_id FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$userId]);
            $fromUser = (int) ($stmt->fetchColumn() ?: 0);
            if ($fromUser > 0 && self::canAccessStore($db, $fromUser)) {
                self::setActiveStore($fromUser);
                return $fromUser;
            }

            if (self::tableExists($db, 'user_stores')) {
                $stmt = $db->prepare(
                    'SELECT us.store_id FROM user_stores us
                     INNER JOIN stores s ON s.id = us.store_id AND s.deleted_at IS NULL
                     WHERE us.user_id = ?
                     ORDER BY us.store_id ASC
                     LIMIT 1'
                );
                $stmt->execute([$userId]);
                $fromLink = (int) ($stmt->fetchColumn() ?: 0);
                if ($fromLink > 0 && self::canAccessStore($db, $fromLink)) {
                    self::setActiveStore($fromLink);
                    return $fromLink;
                }
            }
        }

        $allowed = self::accessibleStoreIds($db);
        if (is_array($allowed) && count($allowed) === 1) {
            self::setActiveStore($allowed[0]);
            return $allowed[0];
        }

        try {
            $stmt = $db->query('SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');
            $fallback = (int) ($stmt->fetchColumn() ?: 0);
            if ($fallback > 0) {
                self::setActiveStore($fallback);
                return $fallback;
            }
        } catch (Throwable $e) {
            // ignore
        }

        return 1;
    }

    /**
     * Fragment SQL AND … pour filtrer par succursale.
     *
     * @return array{0: string, 1: array<int, mixed>}
     */
    public static function sqlFilter(PDO $db, string $column = 'store_id', string $tableAlias = ''): array
    {
        $col = $tableAlias !== '' ? "{$tableAlias}.{$column}" : $column;

        if (self::isGlobalView()) {
            return ['', []];
        }

        $active = self::activeStoreId();
        if ($active !== null) {
            return [" AND {$col} = ?", [$active]];
        }

        $allowed = self::accessibleStoreIds($db);
        if ($allowed === null || $allowed === []) {
            return ['', []];
        }

        if (count($allowed) === 1) {
            return [" AND {$col} = ?", [$allowed[0]]];
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        return [" AND {$col} IN ({$placeholders})", $allowed];
    }

    public static function requireStoreAccess(PDO $db, int $storeId): bool
    {
        if (!self::canAccessStore($db, $storeId)) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Accès refusé à cette succursale',
            ]);
            return false;
        }
        return true;
    }

    public static function tableExists(PDO $db, string $table): bool
    {
        try {
            $stmt = $db->prepare(
                'SELECT 1 FROM information_schema.TABLES
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
            );
            $stmt->execute([$table]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }
}

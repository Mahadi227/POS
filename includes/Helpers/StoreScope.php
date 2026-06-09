<?php
/**
 * Portée multi-succursales — filtrage SQL et contexte session.
 */
class StoreScope
{
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
            $stmt = $db->prepare(
                'SELECT us.store_id FROM user_stores us
                 INNER JOIN stores s ON s.id = us.store_id AND s.deleted_at IS NULL
                 WHERE us.user_id = ?'
            );
            $stmt->execute([$userId]);
            $ids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
        }

        $primary = !empty($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null;
        if ($primary && !in_array($primary, $ids, true)) {
            $ids[] = $primary;
        }

        if (self::isSuperAdmin()) {
            $all = $db->query(
                'SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY name ASC'
            )->fetchAll(PDO::FETCH_COLUMN);
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

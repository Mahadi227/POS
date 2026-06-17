<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

/**
 * Loads and checks RBAC permissions (role + optional user overrides).
 */
class PermissionService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function loadForUser(int $userId, int $roleId): array
    {
        $perms = [];

        $stmt = $this->db->prepare(
            'SELECT p.name FROM permissions p
             INNER JOIN role_permissions rp ON rp.permission_id = p.id
             WHERE rp.role_id = ?'
        );
        $stmt->execute([$roleId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) ?: [] as $name) {
            $perms[$name] = true;
        }

        if ($this->tableExists('user_permissions')) {
            $stmt = $this->db->prepare(
                'SELECT p.name, up.granted FROM user_permissions up
                 INNER JOIN permissions p ON p.id = up.permission_id
                 WHERE up.user_id = ?'
            );
            $stmt->execute([$userId]);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
                if ((int) ($row['granted'] ?? 1) === 1) {
                    $perms[$row['name']] = true;
                } else {
                    unset($perms[$row['name']]);
                }
            }
        }

        return array_keys($perms);
    }

    public static function has(string $permission): bool
    {
        $perms = $_SESSION['permissions'] ?? [];
        if (!is_array($perms)) {
            return false;
        }
        if (in_array($permission, $perms, true)) {
            return true;
        }
        if (self::isSuperAdmin()) {
            return true;
        }
        return false;
    }

    public static function hasAny(array $permissions): bool
    {
        foreach ($permissions as $p) {
            if (self::has($p)) {
                return true;
            }
        }
        return false;
    }

    public static function isSuperAdmin(): bool
    {
        return RoleRedirect::slug($_SESSION['role'] ?? '') === 'super_admin';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

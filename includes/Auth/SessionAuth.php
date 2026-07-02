<?php
declare(strict_types=1);

require_once __DIR__ . '/PermissionService.php';
require_once __DIR__ . '/RoleRedirect.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantScope.php';

/**
 * Populates secure session after successful authentication.
 */
class SessionAuth
{
    public static function establish(array $user, array $permissions): void
    {
        $roleSlug = RoleRedirect::slug($user['role_name'] ?? '');

        $_SESSION['user_id'] = (int) $user['id'];
        $_SESSION['role_id'] = (int) ($user['role_id'] ?? 0);
        $_SESSION['name'] = $user['name'] ?? $user['full_name'] ?? '';
        $_SESSION['full_name'] = $user['full_name'] ?? $user['name'] ?? '';
        $_SESSION['email'] = $user['email'] ?? '';
        $_SESSION['role'] = $user['role_name'] ?? '';
        $_SESSION['role_slug'] = $roleSlug;
        $_SESSION['permissions'] = $permissions;
        $_SESSION['workspace'] = RoleRedirect::workspaceForRole($roleSlug);

        $storeId = isset($user['store_id']) ? (int) $user['store_id'] : null;
        $branchId = isset($user['branch_id']) ? (int) $user['branch_id'] : $storeId;
        $warehouseId = isset($user['warehouse_id']) ? (int) $user['warehouse_id'] : null;

        $_SESSION['store_id'] = $storeId ?: null;
        $_SESSION['branch_id'] = $branchId ?: null;
        $_SESSION['warehouse_id'] = $warehouseId ?: null;
        $_SESSION['active_store_id'] = ($roleSlug === 'super_admin' && !$storeId) ? null : ($storeId ?: $branchId);

        $_SESSION['lang'] = $user['language'] ?? ($_SESSION['lang'] ?? 'en');
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();

        $tenantId = isset($user['tenant_id']) ? (int) $user['tenant_id'] : 0;
        if ($tenantId <= 0) {
            $tenantId = 1;
        }
        $_SESSION['tenant_id'] = $tenantId;
        try {
            $db = Database::getInstance()->getConnection();
            if (!class_exists('TenantSchemaMigrator', false)) {
                require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
            }
            if (TenantSchemaMigrator::isReady($db)) {
                $stmt = $db->prepare(
                    'SELECT id, uuid, slug, name, status FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1'
                );
                $stmt->execute([$tenantId]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if ($row) {
                    TenantScope::set($tenantId, $row);
                } else {
                    TenantScope::set($tenantId);
                }
            }
        } catch (Throwable) {
            $_SESSION['tenant_slug'] = $_SESSION['tenant_slug'] ?? 'legacy';
        }
    }

    public static function clear(): void
    {
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        setcookie('retailpos_remember', '', time() - 3600, '/');
        session_destroy();
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Middleware/ActivityMiddleware.php';

/**
 * Centralized page-level RBAC guard for workspace entry points.
 */
class RbacGuard
{
    public static function workspace(string $workspace, ?string $loginPath = '../login.php'): void
    {
        requireLogin($loginPath ?? '../login.php');
        ActivityMiddleware::touch();

        $roleSlug = RoleRedirect::slug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
        $allowed = RoleRedirect::workspaceRoles($workspace);

        if (!in_array($roleSlug, $allowed, true)) {
            self::deny($roleSlug);
        }

        $_SESSION['workspace'] = $workspace;
    }

    public static function requireRoles(array $roleSlugs, ?string $loginPath = '../login.php'): void
    {
        requireLogin($loginPath ?? '../login.php');
        ActivityMiddleware::touch();

        $roleSlug = RoleRedirect::slug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
        $allowed = array_map([RoleRedirect::class, 'slug'], $roleSlugs);

        if (!in_array($roleSlug, $allowed, true) && !PermissionService::isSuperAdmin()) {
            self::deny($roleSlug);
        }
    }

    public static function requirePermission(string $permission): void
    {
        if (!PermissionService::has($permission)) {
            http_response_code(403);
            self::renderDenied('Missing required permission: ' . htmlspecialchars($permission));
        }
    }

    public static function assertBranchAccess(?int $branchId): void
    {
        if ($branchId === null || PermissionService::isSuperAdmin()) {
            return;
        }
        $roleSlug = RoleRedirect::slug($_SESSION['role'] ?? '');
        if (in_array($roleSlug, ['admin', 'manager'], true)) {
            $assigned = (int) ($_SESSION['branch_id'] ?? $_SESSION['store_id'] ?? 0);
            if ($assigned > 0 && $branchId !== $assigned) {
                self::renderDenied('Access denied for this branch.');
            }
        }
    }

    public static function assertWarehouseAccess(?int $warehouseId): void
    {
        if ($warehouseId === null || PermissionService::isSuperAdmin()) {
            return;
        }
        $roleSlug = RoleRedirect::slug($_SESSION['role'] ?? '');
        $warehouseRoles = [
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
            'warehouse_auditor', 'storekeeper',
        ];
        if (in_array($roleSlug, $warehouseRoles, true)) {
            $assigned = (int) ($_SESSION['warehouse_id'] ?? 0);
            if ($assigned > 0 && $warehouseId !== $assigned) {
                self::renderDenied('Access denied for this warehouse.');
            }
        }
    }

    private static function deny(string $roleSlug): void
    {
        $dest = RoleRedirect::publicPath(str_replace('_', ' ', $roleSlug));
        if ($dest !== 'login.php') {
            header('Location: ' . $dest);
            exit;
        }
        self::renderDenied();
    }

    private static function renderDenied(string $message = 'You do not have permission to access this page.'): void
    {
        http_response_code(403);
        echo '<!DOCTYPE html><html><head><title>403 Forbidden</title>'
            . '<style>body{font-family:Inter,sans-serif;display:flex;align-items:center;justify-content:center;'
            . 'height:100vh;background:#f8fafc;color:#0f172a;margin:0}'
            . '.card{background:#fff;padding:40px;border-radius:12px;box-shadow:0 4px 6px -1px rgba(0,0,0,.1);text-align:center}'
            . 'h1{color:#ef4444}a{color:#2563eb;text-decoration:none}</style></head><body>'
            . '<div class="card"><h1>403 Forbidden</h1><p>' . $message . '</p><br>'
            . '<a href="/public/login.php">Return to Login</a></div></body></html>';
        exit;
    }
}

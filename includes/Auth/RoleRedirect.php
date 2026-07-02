<?php
declare(strict_types=1);

/**
 * Role slug normalization and workspace redirect URLs.
 */
class RoleRedirect
{
    public static function slug(?string $roleName): string
    {
        return strtolower(str_replace(' ', '_', trim((string) $roleName)));
    }

    /** Redirect path relative to /public/ (for login.php). */
    public static function publicPath(string $roleName): string
    {
        return match (self::slug($roleName)) {
            'super_admin', 'admin' => 'admin/index.php',
            'manager' => 'manager/index.php',
            'cashier' => 'cashier/dashboard.php',
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
            'warehouse_auditor', 'storekeeper' => 'warehouse/index.php',
            'accountant' => 'accounting/index.php',
            'customer' => 'customer/index.php',
            'staff' => 'cashier/dashboard.php',
            default => 'login.php',
        };
    }

    /** Redirect path relative to API (auth/login JSON response). */
    public static function apiPath(string $roleName): string
    {
        return '../public/' . self::publicPath($roleName);
    }

    public static function workspaceForRole(string $roleSlug): string
    {
        return match ($roleSlug) {
            'super_admin', 'admin' => 'admin',
            'manager' => 'manager',
            'cashier', 'staff' => 'cashier',
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
            'warehouse_auditor', 'storekeeper' => 'warehouse',
            'accountant' => 'accounting',
            'customer' => 'customer',
            default => 'login',
        };
    }

    /** Roles allowed per workspace (slug list). */
    public static function workspaceRoles(string $workspace): array
    {
        return match ($workspace) {
            'admin' => ['super_admin', 'admin', 'manager'],
            'manager' => ['manager', 'admin', 'super_admin'],
            'cashier' => ['cashier', 'admin', 'manager', 'super_admin', 'staff'],
            'warehouse' => [
                'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
                'warehouse_auditor', 'storekeeper',
                'admin', 'manager', 'super_admin',
            ],
            'accounting' => ['accountant', 'admin', 'super_admin'],
            'cash_registers' => ['admin', 'manager', 'super_admin'],
            'customer' => ['customer'],
            default => [],
        };
    }
}

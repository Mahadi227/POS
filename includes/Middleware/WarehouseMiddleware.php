<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';

class WarehouseMiddleware
{
    public static function assertAccess(?int $warehouseId): void
    {
        AuthMiddleware::isAuthenticated();

        if ($warehouseId === null || PermissionService::isSuperAdmin()) {
            return;
        }

        $roleSlug = RoleRedirect::slug($_SESSION['role'] ?? '');
        $warehouseRoles = [
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
        ];

        if (!in_array($roleSlug, $warehouseRoles, true)) {
            return;
        }

        $assigned = (int) ($_SESSION['warehouse_id'] ?? 0);
        if ($assigned > 0 && $warehouseId !== $assigned) {
            AuthMiddleware::accessDeniedPublic();
        }
    }

    public static function assignedWarehouseId(): ?int
    {
        $id = $_SESSION['warehouse_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }
}

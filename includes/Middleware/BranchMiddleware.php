<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';

class BranchMiddleware
{
    public static function assertAccess(?int $branchId): void
    {
        AuthMiddleware::isAuthenticated();

        if ($branchId === null || PermissionService::isSuperAdmin()) {
            return;
        }

        $roleSlug = RoleRedirect::slug($_SESSION['role'] ?? '');
        if (!in_array($roleSlug, ['manager', 'cashier', 'staff'], true)) {
            return;
        }

        $assigned = (int) ($_SESSION['branch_id'] ?? $_SESSION['store_id'] ?? 0);
        if ($assigned > 0 && $branchId !== $assigned) {
            AuthMiddleware::accessDeniedPublic();
        }
    }

    public static function assignedBranchId(): ?int
    {
        $id = $_SESSION['branch_id'] ?? $_SESSION['store_id'] ?? null;
        return $id !== null ? (int) $id : null;
    }
}

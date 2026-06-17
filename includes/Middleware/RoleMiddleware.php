<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Auth/PermissionService.php';

class RoleMiddleware
{
    public static function require(array $allowedRoleSlugs): void
    {
        AuthMiddleware::isAuthenticated();

        $userSlug = RoleRedirect::slug($_SESSION['role_slug'] ?? $_SESSION['role'] ?? '');
        $allowed = array_map([RoleRedirect::class, 'slug'], $allowedRoleSlugs);

        if (!in_array($userSlug, $allowed, true) && !PermissionService::isSuperAdmin()) {
            AuthMiddleware::accessDeniedPublic();
        }
    }
}

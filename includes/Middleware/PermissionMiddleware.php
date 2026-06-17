<?php
declare(strict_types=1);

require_once __DIR__ . '/AuthMiddleware.php';
require_once __DIR__ . '/../Auth/PermissionService.php';

class PermissionMiddleware
{
    public static function require(string $permission): void
    {
        AuthMiddleware::isAuthenticated();

        if (!PermissionService::has($permission)) {
            AuthMiddleware::accessDeniedPublic();
        }
    }

    public static function requireAny(array $permissions): void
    {
        AuthMiddleware::isAuthenticated();

        if (!PermissionService::hasAny($permissions)) {
            AuthMiddleware::accessDeniedPublic();
        }
    }

    public static function apiRequire(string $permission): void
    {
        AuthMiddleware::apiProtect();

        if (!PermissionService::has($permission)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden. Insufficient permissions.']);
            exit;
        }
    }
}

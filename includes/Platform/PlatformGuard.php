<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/PlatformSessionAuth.php';

final class PlatformGuard
{
    public static function requireLogin(?string $loginPath = 'login.php'): void
    {
        if (!PlatformSessionAuth::isLoggedIn()) {
            header('Location: ' . ($loginPath ?? 'login.php'));
            exit;
        }
    }

    public static function requireRole(array $roles): void
    {
        self::requireLogin();
        $role = $_SESSION['platform_role'] ?? '';
        if (!in_array($role, $roles, true)) {
            http_response_code(403);
            echo '403 Forbidden';
            exit;
        }
    }
}

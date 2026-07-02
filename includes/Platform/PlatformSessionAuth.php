<?php
declare(strict_types=1);

final class PlatformSessionAuth
{
    public static function establish(array $user): void
    {
        $_SESSION['platform_user_id'] = (int) $user['id'];
        $_SESSION['platform_email'] = $user['email'] ?? '';
        $_SESSION['platform_name'] = $user['name'] ?? '';
        $_SESSION['platform_role'] = $user['role'] ?? 'platform_admin';
        $_SESSION['platform_login_time'] = time();
    }

    public static function clear(): void
    {
        unset(
            $_SESSION['platform_user_id'],
            $_SESSION['platform_email'],
            $_SESSION['platform_name'],
            $_SESSION['platform_role'],
            $_SESSION['platform_login_time']
        );
    }

    public static function isLoggedIn(): bool
    {
        return !empty($_SESSION['platform_user_id']);
    }

    public static function userId(): int
    {
        return (int) ($_SESSION['platform_user_id'] ?? 0);
    }
}

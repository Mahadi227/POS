<?php
declare(strict_types=1);

require_once __DIR__ . '/RememberMeService.php';

/**
 * Attempt auto-login from remember-me cookie (PWA / offline session restore).
 */
class AuthBootstrap
{
    public static function tryRememberMe(): void
    {
        if (!empty($_SESSION['user_id'])) {
            return;
        }
        RememberMeService::attempt();
    }
}

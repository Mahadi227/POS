<?php
declare(strict_types=1);

require_once __DIR__ . '/../Config/session.php';

class ActivityMiddleware
{
    public static function touch(): void
    {
        if (!isset($_SESSION['user_id'])) {
            return;
        }
        if (!check_session_timeout()) {
            header('Location: /public/login.php?error=timeout');
            exit;
        }
        $_SESSION['last_activity'] = time();
    }
}

<?php
// public/logout.php
require_once __DIR__ . '/../includes/Config/session.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    try {
        require_once __DIR__ . '/../includes/Database/Database.php';
        $db = Database::getInstance()->getConnection();
        $stmt = $db->prepare(
            'INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $userId,
            'logout',
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
            'success',
        ]);
    } catch (Throwable $e) {
        error_log('logout activity: ' . $e->getMessage());
    }
}

$_SESSION = [];

session_destroy();

header('Location: login.php');
exit;

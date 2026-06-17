<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Config/session.php';
require_once __DIR__ . '/../includes/Auth/RoleRedirect.php';
require_once __DIR__ . '/../includes/Auth/SessionAuth.php';
require_once __DIR__ . '/../includes/Auth/RememberMeService.php';
require_once __DIR__ . '/../includes/Auth/AuditLogger.php';

$userId = (int) ($_SESSION['user_id'] ?? 0);
if ($userId > 0) {
    AuditLogger::log($userId, 'logout', 'success');
    RememberMeService::revoke($userId);
}

SessionAuth::clear();

header('Location: login.php');
exit;

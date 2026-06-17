<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../includes/Config/session.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['role_slug'] = 'admin';
$_SESSION['name'] = 'Test';

require __DIR__ . '/../languages/LanguageMiddleware.php';
require __DIR__ . '/../languages/helpers.php';

chdir(__DIR__ . '/../public/admin');

try {
    ob_start();
    include __DIR__ . '/../public/admin/includes/notification-bell.php';
    $out = ob_get_clean();
    echo "notification-bell OK, length=" . strlen($out) . "\n";
} catch (Throwable $e) {
    echo "bell ERR: " . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

try {
    require __DIR__ . '/../includes/Notifications/Services/NotificationService.php';
    $svc = new NotificationService();
    echo "unread: " . $svc->unreadCount(1) . "\n";
} catch (Throwable $e) {
    echo "service ERR: " . $e->getMessage() . "\n" . $e->getTraceAsString() . "\n";
}

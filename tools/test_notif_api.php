<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['REQUEST_METHOD'] = 'GET';
$_GET['request'] = 'notifications/list';

require __DIR__ . '/../includes/Config/session.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';

ob_start();
try {
    include __DIR__ . '/../api/v1/index.php';
} catch (Throwable $e) {
    ob_end_clean();
    echo json_encode(['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    exit;
}
echo ob_get_clean();

<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['HTTPS'] = 'off';
$_SERVER['REQUEST_METHOD'] = 'GET';

require __DIR__ . '/../includes/Config/session.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Super Admin';
$_SESSION['role_slug'] = 'super_admin';
$_SESSION['name'] = 'Admin';
$_SESSION['store_id'] = 1;

try {
    ob_start();
    include __DIR__ . '/../public/admin/index.php';
    $html = ob_get_clean();
    echo 'index OK, bytes=' . strlen($html) . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo 'index ERR: ' . $e->getMessage() . "\n" . $e->getFile() . ':' . $e->getLine() . "\n";
}

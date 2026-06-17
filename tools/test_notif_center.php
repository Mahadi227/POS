<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');
$_SERVER['REQUEST_METHOD'] = 'GET';
require __DIR__ . '/../includes/Config/session.php';
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Admin';
$_SESSION['name'] = 'Test';
chdir(__DIR__ . '/../public/notifications');
include __DIR__ . '/../public/notifications/notification_center.php';

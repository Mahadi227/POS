<?php
declare(strict_types=1);
require __DIR__ . '/includes/bootstrap.php';
if (!$isAdmin) {
    header('Location: notification_center.php');
    exit;
}
header('Location: analytics.php');
exit;

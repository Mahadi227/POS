<?php declare(strict_types=1);
require __DIR__ . '/../includes/bootstrap.php';
PlatformGuard::requireLogin('login.php');
header('Location: ' . plat_public_href('developers/openapi.php'));
exit;

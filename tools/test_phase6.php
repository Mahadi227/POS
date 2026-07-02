<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../includes/Platform/Services/PlatformStatusService.php';

$db = Database::getInstance()->getConnection();
SaaSPhase6Migrator::ensure($db);
$svc = new PlatformStatusService($db);
$status = $svc->getPublicStatus();
echo 'Components: ' . count($status['components']) . PHP_EOL;
echo 'Overall: ' . $status['overall'] . PHP_EOL;
echo 'OK' . PHP_EOL;

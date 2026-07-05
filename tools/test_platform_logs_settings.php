<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase14Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformLogsRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformSettingsRepository.php';

$db = Database::getInstance()->getConnection();
SaaSPhase14Migrator::ensure($db);

$logs = new PlatformLogsRepository($db);
$settings = new PlatformSettingsRepository($db);

echo 'logs dashboard: ' . json_encode($logs->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'settings dashboard stats: ' . json_encode($settings->dashboard()['stats'], JSON_THROW_ON_ERROR) . PHP_EOL;

$updated = $settings->updateMany(['lockout_threshold' => 6], 1);
echo 'settings update: ' . json_encode($updated, JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'done' . PHP_EOL;

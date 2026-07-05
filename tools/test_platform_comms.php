<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformNotificationsRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformEmailsRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformSmsRepository.php';

$db = Database::getInstance()->getConnection();
$notif = new PlatformNotificationsRepository($db);
$email = new PlatformEmailsRepository($db);
$sms = new PlatformSmsRepository($db);

echo "notifications dashboard: " . json_encode($notif->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo "emails dashboard: " . json_encode($email->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo "sms dashboard: " . json_encode($sms->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;

$id = $notif->createBroadcast([
    'title_en' => 'Smoke test broadcast',
    'message_en' => 'Hello tenants',
    'audience' => 'all',
], 1);
echo "broadcast created id={$id}" . PHP_EOL;
echo 'send=' . ($notif->sendBroadcast($id) ? 'ok' : 'fail') . PHP_EOL;
echo "done" . PHP_EOL;

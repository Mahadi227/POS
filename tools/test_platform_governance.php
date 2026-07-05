<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/Config/config.php';
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase13Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/../includes/Platform/Repositories/PlatformSecurityRepository.php';

$db = Database::getInstance()->getConnection();
SaaSPhase13Migrator::ensure($db);

$audit = new PlatformAuditRepository($db);
$security = new PlatformSecurityRepository($db);

$security->recordLoginAttempt('test@example.com', null, 'failed', '127.0.0.1', 'smoke-test');
$audit->log('platform.login_failed', null, null, ['email' => 'test@example.com'], '127.0.0.1');

echo 'audit dashboard: ' . json_encode($audit->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'security dashboard: ' . json_encode($security->dashboard(), JSON_THROW_ON_ERROR) . PHP_EOL;
echo 'audit logs count: ' . count($audit->listLogs(5)) . PHP_EOL;
echo 'done' . PHP_EOL;

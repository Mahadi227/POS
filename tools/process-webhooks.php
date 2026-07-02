<?php
declare(strict_types=1);

/**
 * Process pending webhook deliveries.
 * Usage: php tools/process-webhooks.php [--limit=50]
 */
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../includes/Platform/Repositories/WebhookRepository.php';
require_once __DIR__ . '/../includes/Platform/Services/WebhookDispatcherService.php';

$limit = 50;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--limit=')) {
        $limit = max(1, (int) substr($arg, 8));
    }
}

$db = Database::getInstance()->getConnection();
SaaSPhase6Migrator::ensure($db);

$svc = new WebhookDispatcherService($db, new WebhookRepository($db));
$stats = $svc->processPending($limit);

echo "Webhook worker\n";
echo str_repeat('-', 32) . "\n";
foreach ($stats as $k => $v) {
    echo ucfirst($k) . ": {$v}\n";
}
echo "Done.\n";

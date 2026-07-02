<?php
declare(strict_types=1);

/**
 * Transactional email cron — trial ending reminders.
 * Usage: php tools/transactional-cron.php
 */
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../includes/Platform/Services/TransactionalEmailService.php';

$db = Database::getInstance()->getConnection();
SaaSPhase6Migrator::ensure($db);

$svc = new TransactionalEmailService($db);
$result = $svc->processTrialReminders();

echo "Transactional email cron\n";
echo str_repeat('-', 32) . "\n";
echo 'Trial reminders sent: ' . ($result['sent'] ?? 0) . "\n";
echo "Done.\n";

<?php
// Reset notification schema migrator flag for CLI re-run
require __DIR__ . '/../includes/Database/Database.php';

$db = Database::getInstance()->getConnection();

// Drop queue/logs if partially created
foreach (['notification_logs', 'notification_queue'] as $t) {
    try { $db->exec("DROP TABLE IF EXISTS {$t}"); } catch (PDOException $e) {}
}

require __DIR__ . '/../includes/Notifications/NotificationSchemaMigrator.php';
NotificationSchemaMigrator::ensure($db);

echo "Done\n";
$tables = ['notifications', 'notification_queue', 'notification_logs', 'notification_templates'];
foreach ($tables as $t) {
    $exists = $db->query("SHOW TABLES LIKE '{$t}'")->fetch();
    echo "{$t}: " . ($exists ? 'OK' : 'MISSING') . "\n";
}

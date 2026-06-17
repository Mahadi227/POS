<?php
require __DIR__ . '/../includes/Database/Database.php';
require __DIR__ . '/../includes/Notifications/NotificationSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
NotificationSchemaMigrator::ensure($db);
echo "Notification migration OK\n";
echo 'Templates: ' . $db->query('SELECT COUNT(*) FROM notification_templates')->fetchColumn() . "\n";
echo 'Categories: ' . $db->query('SELECT COUNT(*) FROM notification_categories')->fetchColumn() . "\n";

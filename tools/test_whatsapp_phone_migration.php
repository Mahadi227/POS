<?php
require __DIR__ . '/../includes/Database/Database.php';
require __DIR__ . '/../includes/Notifications/NotificationSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
NotificationSchemaMigrator::ensure($db);

$stmt = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'notification_preferences'
       AND COLUMN_NAME = 'whatsapp_phone'"
);
echo $stmt->fetchColumn() ? "whatsapp_phone column OK\n" : "whatsapp_phone column MISSING\n";

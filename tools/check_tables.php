<?php
require_once __DIR__ . '/../includes/Database/Database.php';
$db = Database::getInstance()->getConnection();
foreach (['stores', 'user_stores'] as $t) {
    $exists = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$t'")->fetchColumn();
    echo "$t: " . ($exists ? 'yes' : 'no') . "\n";
}

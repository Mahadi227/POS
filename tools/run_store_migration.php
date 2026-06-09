<?php
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Database/StoreSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
StoreSchemaMigrator::ensure($db);

$cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
echo "OK - stores columns:\n" . implode(', ', $cols) . "\n";

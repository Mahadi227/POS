<?php
require_once __DIR__ . '/../includes/Database/Database.php';
$db = Database::getInstance()->getConnection();
$cols = $db->query("SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores' ORDER BY ORDINAL_POSITION")->fetchAll(PDO::FETCH_COLUMN);
echo implode("\n", $cols);

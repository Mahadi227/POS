<?php
require_once __DIR__ . '/../includes/Database/Database.php';
require_once __DIR__ . '/../includes/Database/StoreSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
StoreSchemaMigrator::ensure($db);

$stmt = $db->prepare('INSERT INTO stores (name, code, location, tax_rate, currency, is_active) VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute(['Test Branch', 'TST-99', 'Test City', 18, 'FCFA', 1]);
echo 'Created store id: ' . $db->lastInsertId() . "\n";
$db->exec('DELETE FROM stores WHERE code = "TST-99"');
echo "Cleaned up test row.\n";

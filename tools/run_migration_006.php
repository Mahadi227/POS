<?php
/**
 * Run migration 006 — inventory_ledger table.
 * Usage: php tools/run_migration_006.php
 */
require_once __DIR__ . '/../includes/Database/Database.php';

$db = Database::getInstance()->getConnection();
$sql = file_get_contents(__DIR__ . '/../includes/Database/migrations/006_inventory_ledger.sql');

foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
    if ($statement === '') continue;
    try {
        $db->exec($statement);
        echo "OK: " . substr($statement, 0, 60) . "...\n";
    } catch (PDOException $e) {
        echo "ERR: " . $e->getMessage() . "\n";
    }
}

$count = $db->query('SELECT COUNT(*) FROM inventory_ledger')->fetchColumn();
echo "inventory_ledger rows: $count\n";

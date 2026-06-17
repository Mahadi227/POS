<?php
require __DIR__ . '/../includes/Database/Database.php';
require __DIR__ . '/../includes/Auth/RbacSchemaMigrator.php';

$db = Database::getInstance()->getConnection();
RbacSchemaMigrator::ensure($db);
echo "Migration OK\n";

$roles = $db->query('SELECT id, name FROM roles ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
echo 'Roles: ' . count($roles) . "\n";
foreach ($roles as $r) {
    echo "  {$r['id']}: {$r['name']}\n";
}

$perms = (int) $db->query('SELECT COUNT(*) FROM permissions')->fetchColumn();
echo "Permissions: {$perms}\n";

$cols = $db->query(
    "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='users' ORDER BY ORDINAL_POSITION"
)->fetchAll(PDO::FETCH_COLUMN);
echo 'User columns: ' . implode(', ', $cols) . "\n";

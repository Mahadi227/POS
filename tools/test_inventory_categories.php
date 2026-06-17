<?php
error_reporting(E_ALL);
ini_set('display_errors', '1');

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'admin';
$_SESSION['store_id'] = 1;
$_SESSION['active_store_id'] = 1;

require __DIR__ . '/../includes/Config/config.php';
require __DIR__ . '/../includes/Database/Database.php';
require __DIR__ . '/../includes/Database/CategorySchemaMigrator.php';
require __DIR__ . '/../includes/Helpers/StoreScope.php';

$db = Database::getInstance()->getConnection();
CategorySchemaMigrator::ensure($db);

$stmt = $db->prepare(
    'SELECT 1 FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
);
$stmt->execute(['categories', 'store_id']);
echo 'store_id column: ' . ($stmt->fetchColumn() ? 'yes' : 'no') . "\n";

require __DIR__ . '/../includes/Middleware/AuthMiddleware.php';
require __DIR__ . '/../includes/Helpers/InventoryLedgerHelper.php';
require __DIR__ . '/../includes/Controllers/InventoryController.php';

$ctrl = new InventoryController();

foreach (['categories', 'stats', 'products'] as $action) {
    ob_start();
    try {
        $ctrl->handleRequest('GET', ['inventory', $action]);
        $out = ob_get_clean();
        $json = json_decode($out, true);
        echo "{$action}: " . ($json['status'] ?? 'raw') . "\n";
        if (($json['status'] ?? '') === 'error') {
            echo "  " . substr($out, 0, 300) . "\n";
        }
    } catch (Throwable $e) {
        ob_end_clean();
        echo "ERROR {$action}: " . $e->getMessage() . "\n";
    }
}

// simulate create product category check
ob_start();
try {
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $ctrl2 = new InventoryController();
    // findCategory via reflection
    $ref = new ReflectionClass($ctrl2);
    $m = $ref->getMethod('findCategory');
    $m->setAccessible(true);
    $cat = $m->invoke($ctrl2, 1);
    ob_end_clean();
    echo 'findCategory(1): ' . ($cat ? 'found id='.$cat['id'] : 'not found') . "\n";
} catch (Throwable $e) {
    ob_end_clean();
    echo 'findCategory ERROR: ' . $e->getMessage() . "\n";
}

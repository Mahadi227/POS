<?php
declare(strict_types=1);

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

$started = microtime(true);
$checks = ['app' => 'ok', 'database' => 'unknown'];

try {
    require_once __DIR__ . '/../includes/Database/Database.php';
    $db = Database::getInstance()->getConnection();
    $db->query('SELECT 1');
    $checks['database'] = 'ok';
} catch (Throwable $e) {
    $checks['database'] = 'error';
    http_response_code(503);
}

$healthy = $checks['database'] === 'ok';
if (!$healthy) {
    http_response_code(503);
}

echo json_encode([
    'status' => $healthy ? 'healthy' : 'unhealthy',
    'checks' => $checks,
    'version' => '1.0',
    'latency_ms' => (int) round((microtime(true) - $started) * 1000),
    'timestamp' => gmdate('c'),
], JSON_THROW_ON_ERROR);

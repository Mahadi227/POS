<?php
session_start();
$_SESSION['user_id'] = 1;
$_SESSION['role'] = 'Super Admin';
$_SESSION['name'] = 'Test';

$_SERVER['REQUEST_METHOD'] = 'POST';
$GLOBALS['test_body'] = json_encode([
    'name' => 'Succursale Test API',
    'code' => 'TST-' . time(),
    'location' => 'Ville Test',
    'tax_rate' => 18,
    'currency' => 'FCFA',
]);

// Simulate php://input
stream_wrapper_unregister('php');
stream_wrapper_register('php', 'TestInputStream');

class TestInputStream {
    public $context;
    private static $data;
    public static function setData($d) { self::$data = $d; }
    public function stream_open() { return true; }
    public function stream_read($count) {
        $ret = substr(self::$data, 0, $count);
        self::$data = substr(self::$data, $count);
        return $ret;
    }
    public function stream_eof() { return self::$data === ''; }
    public function stream_stat() { return []; }
}
TestInputStream::setData($GLOBALS['test_body']);

require_once __DIR__ . '/../includes/Controllers/StoresController.php';
$controller = new StoresController();
ob_start();
$controller->handleRequest('POST', ['stores']);
$out = ob_get_clean();
echo $out . "\n";

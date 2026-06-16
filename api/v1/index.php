<?php

/**
 * API v1 — routeur central avec session et protection par rôle.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Middleware/AuthMiddleware.php';

$request = isset($_GET['request']) ? explode('/', trim($_GET['request'], '/')) : [];
$resource = $request[0] ?? null;

/** Routes publiques (sans session) */
$publicResources = ['auth'];

if (!in_array($resource, $publicResources, true)) {
    AuthMiddleware::apiProtect();
}

switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/../../includes/Controllers/AuthController.php';
        (new AuthController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'sales':
        require_once __DIR__ . '/../../includes/Controllers/SalesController.php';
        (new SalesController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'cashier':
        require_once __DIR__ . '/../../includes/Controllers/CashierController.php';
        (new CashierController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'dashboard':
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin']);
        require_once __DIR__ . '/../../includes/Controllers/DashboardController.php';
        (new DashboardController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'reports':
        require_once __DIR__ . '/../../includes/Controllers/ReportsController.php';
        (new ReportsController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'inventory':
        if (isset($request[1]) && in_array($request[1], ['ledger', 'movements', 'analytics', 'reports', 'audit'], true)) {
            require_once __DIR__ . '/../../includes/Controllers/InventoryLedgerController.php';
            (new InventoryLedgerController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        } else {
            require_once __DIR__ . '/../../includes/Controllers/InventoryController.php';
            (new InventoryController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        }
        break;

    case 'stores':
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin', 'cashier']);
        require_once __DIR__ . '/../../includes/Controllers/StoresController.php';
        (new StoresController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'users':
        require_once __DIR__ . '/../../includes/Controllers/UsersController.php';
        (new UsersController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'sync':
        require_once __DIR__ . '/../../includes/Controllers/SyncController.php';
        (new SyncController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'manager':
        AuthMiddleware::apiProtect(['manager', 'admin', 'super_admin']);
        require_once __DIR__ . '/../../includes/Controllers/ManagerController.php';
        (new ManagerController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'cash-registers':
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin']);
        require_once __DIR__ . '/../../includes/Controllers/CashRegisterController.php';
        (new CashRegisterController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    case 'wms':
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin']);
        require_once __DIR__ . '/../../includes/Controllers/WmsController.php';
        (new WmsController())->handleRequest($_SERVER['REQUEST_METHOD'], $request);
        break;

    default:
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        break;
}

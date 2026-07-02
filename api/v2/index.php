<?php
declare(strict_types=1);

/**
 * API v2 — JWT + API keys, REST conventions, rate limiting.
 */
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Methods: OPTIONS,GET,POST,PUT,DELETE');
header('Access-Control-Max-Age: 3600');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-Tenant-ID, X-API-Key, Idempotency-Key');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
if ($origin !== '') {
    header('Access-Control-Allow-Origin: ' . $origin);
    header('Vary: Origin');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../../includes/Config/config.php';
require_once __DIR__ . '/../../includes/Config/session.php';
require_once __DIR__ . '/../../includes/Database/Database.php';
require_once __DIR__ . '/../../includes/Api/ApiProblem.php';
require_once __DIR__ . '/../../includes/Api/ApiV2Auth.php';

$request = isset($_GET['request']) ? explode('/', trim($_GET['request'], '/')) : [];
$resource = $request[0] ?? null;
$method = $_SERVER['REQUEST_METHOD'];

$publicResources = ['auth'];

if (!in_array($resource, $publicResources, true)) {
    $db = Database::getInstance()->getConnection();
    ApiV2Auth::authenticate($db);
}

switch ($resource) {
    case 'auth':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2AuthController.php';
        (new ApiV2AuthController())->handleRequest($method, $request);
        break;

    case 'me':
    case 'tenant':
    case 'usage':
    case 'subscription':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2TenantController.php';
        (new ApiV2TenantController())->handleRequest($method, $request);
        break;

    case 'stores':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2StoresController.php';
        (new ApiV2StoresController())->handleRequest($method, $request);
        break;

    case 'products':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2ProductsController.php';
        (new ApiV2ProductsController())->handleRequest($method, $request);
        break;

    case 'sales':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2SalesController.php';
        (new ApiV2SalesController())->handleRequest($method, $request);
        break;

    case 'inventory':
        require_once __DIR__ . '/../../includes/Controllers/ApiV2InventoryController.php';
        (new ApiV2InventoryController())->handleRequest($method, $request);
        break;

    default:
        ApiProblem::notFound('Endpoint not found');
        break;
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Wms/Services/WmsService.php';
require_once __DIR__ . '/../Wms/Services/WmsDashboardService.php';
require_once __DIR__ . '/../Wms/Services/WmsSyncMonitorService.php';
require_once __DIR__ . '/../Wms/Services/InventoryReportService.php';
require_once __DIR__ . '/../Wms/Services/WarehousePerformanceService.php';
require_once __DIR__ . '/../Wms/Services/InventoryValuationService.php';
require_once __DIR__ . '/../Wms/Services/DamageReportService.php';
require_once __DIR__ . '/../Wms/Services/ExpiryReportService.php';

class WmsController
{
    private WmsService $service;
    private WmsDashboardService $dashboard;
    private WmsSyncMonitorService $syncMonitor;

    public function __construct()
    {
        $this->service = new WmsService();
        $this->dashboard = new WmsDashboardService();
        $this->syncMonitor = new WmsSyncMonitorService();
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? 'dashboard';
        $sub = $path[2] ?? null;
        $id = null;
        if (isset($path[3]) && is_numeric($path[3])) {
            $id = (int) $path[3];
        } elseif (isset($path[2]) && is_numeric($path[2])) {
            $id = (int) $path[2];
            $sub = null;
        } elseif (isset($path[2]) && !is_numeric($path[2])) {
            $sub = $path[2];
            $id = isset($path[3]) ? (int) $path[3] : null;
        }

        if (!$this->canAccess()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        if ($method === 'GET') {
            $this->handleGet($action, $sub, $id);
            return;
        }
        if ($method === 'POST') {
            $this->handlePost($action, $sub, $id);
            return;
        }
        if ($method === 'PUT' && $action === 'warehouses' && $id) {
            $this->json($this->service->updateWarehouse($id, $this->body(), $this->userId()));
            return;
        }
        if ($method === 'DELETE' && $action === 'warehouses' && $id) {
            $this->json($this->service->deleteWarehouse($id, $this->userId()));
            return;
        }
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function canAccess(): bool
    {
        require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
        $role = WarehousePortalAuth::roleSlug();
        return in_array($role, [
            'super_admin', 'admin', 'manager',
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
            'warehouse_auditor', 'storekeeper',
        ], true);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function storeId(): ?int
    {
        return StoreScope::activeStoreId();
    }

    private function body(): array
    {
        return json_decode(file_get_contents('php://input'), true) ?: [];
    }

    private function json(array $result): void
    {
        if (($result['status'] ?? '') !== 'success') {
            http_response_code(400);
        }
        echo json_encode($result);
    }

    private function handleGet(string $action, ?string $sub, ?int $id): void
    {
        $storeId = $this->storeId();
        switch ($action) {
            case 'dashboard':
                echo json_encode(['status' => 'success', 'data' => $this->dashboard->dashboard($storeId)]);
                break;
            case 'analytics':
                echo json_encode(['status' => 'success', 'data' => $this->dashboard->analytics($storeId, $_GET['period'] ?? 'month')]);
                break;
            case 'warehouses':
                if ($id) {
                    $row = $this->service->getWarehouse($id);
                    echo json_encode($row ? ['status' => 'success', 'data' => $row] : ['status' => 'error', 'message' => 'Not found']);
                    break;
                }
                $scopeStore = $this->storeId();
                $filterStore = (int) ($_GET['store_id'] ?? 0) ?: null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $status = $_GET['status'] ?? null;
                $type = $_GET['type'] ?? null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->warehouseNetworkSummary($scopeStore),
                    'total' => $this->service->countWarehouses($scopeStore, $status, $search, $type, $filterStore),
                    'data' => $this->service->listWarehouses(
                        $scopeStore,
                        $status,
                        $search,
                        $type,
                        $filterStore,
                        $limit,
                        $offset
                    ),
                ]);
                break;
            case 'inventory':
                if ($id) {
                    $wh = (int) ($_GET['warehouse_id'] ?? 0);
                    $row = $this->service->getInventoryItem($wh, $id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $wh > 0 ? $this->service->inventorySummary($wh) : null,
                    'data' => $this->service->listInventory($wh, $_GET['q'] ?? null, $_GET['filter'] ?? null),
                ]);
                break;
            case 'products':
                if ($id) {
                    $row = $this->service->getProductCatalog($id, $storeId);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $categoryId = isset($_GET['category_id']) && $_GET['category_id'] !== ''
                    ? (int) $_GET['category_id'] : null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $filter = $_GET['filter'] ?? null;
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->productsSummary($wh, $storeId),
                    'categories' => $this->service->listProductCategories($storeId),
                    'total' => $this->service->countProducts($wh, $storeId, $search, $filter, $categoryId),
                    'data' => $this->service->listProducts($wh, $storeId, $search, $filter, $categoryId, $limit, $offset),
                ]);
                break;
            case 'stock-levels':
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $filter = $_GET['filter'] ?? null;
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->stockLevelsSummary($wh, $storeId),
                    'total' => $this->service->countStockLevels($wh, $storeId, $search, $filter),
                    'data' => $this->service->listStockLevels($wh, $storeId, $search, $filter, $limit, $offset),
                ]);
                break;
            case 'locations':
                $wh = (int) ($_GET['warehouse_id'] ?? 0);
                if ($wh <= 0) {
                    echo json_encode([
                        'status' => 'success',
                        'module_ready' => $this->service->moduleReady(),
                        'summary' => null,
                        'breakdown' => [],
                        'total' => 0,
                        'data' => [],
                    ]);
                    break;
                }
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $status = $_GET['status'] ?? 'all';
                $zone = $_GET['zone'] ?? 'all';
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->locationSummary($wh),
                    'breakdown' => $this->service->locationZoneBreakdown($wh),
                    'total' => $this->service->countLocations($wh, $search, $status, $zone),
                    'data' => $this->service->listLocations($wh, $search, $status, $zone, $limit, $offset),
                ]);
                break;
            case 'movements':
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $filters = [
                    'movement_type' => $_GET['type'] ?? 'all',
                    'from' => $_GET['from'] ?? null,
                    'to' => $_GET['to'] ?? null,
                    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
                ];
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->movementSummary($wh, $filters),
                    'breakdown' => $this->service->movementBreakdown($wh, $filters),
                    'chart' => [
                        'trend' => $this->service->movementTrend($wh, $filters, (int) ($_GET['days'] ?? 30)),
                    ],
                    'total' => $this->service->countMovements($wh, $filters),
                    'data' => $this->service->listMovements($wh, $filters, $limit, $offset),
                ]);
                break;
            case 'adjustments':
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $filters = [
                    'movement_type' => $_GET['type'] ?? 'all',
                    'from' => $_GET['from'] ?? null,
                    'to' => $_GET['to'] ?? null,
                    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
                ];
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->adjustmentSummary($wh, $filters),
                    'breakdown' => $this->service->adjustmentBreakdown($wh, $filters),
                    'total' => $this->service->countAdjustments($wh, $filters),
                    'data' => $this->service->listAdjustments($wh, $filters, $limit, $offset),
                ]);
                break;
            case 'store-network':
                if ($id) {
                    echo json_encode([
                        'status' => 'success',
                        'module_ready' => $this->service->moduleReady(),
                        'data' => $this->service->storeNetworkWarehouses($id),
                    ]);
                    break;
                }
                $scopeStore = (int) ($_GET['store_id'] ?? 0) ?: $this->storeId();
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $status = $_GET['status'] ?? 'all';
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->storeNetworkSummary($scopeStore),
                    'total' => $this->service->countStoreNetwork($scopeStore, $search, $status),
                    'data' => $this->service->listStoreNetwork($scopeStore, $search, $status, $limit, $offset),
                ]);
                break;
            case 'transfers':
                if ($id) {
                    $row = $this->service->getTransfer($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $storeId = (int) ($_GET['store_id'] ?? 0) ?: null;
                $direction = isset($_GET['direction']) ? trim((string) $_GET['direction']) : null;
                $transferType = isset($_GET['transfer_type']) ? trim((string) $_GET['transfer_type']) : null;
                $queue = isset($_GET['queue']) ? trim((string) $_GET['queue']) : null;
                $scope = isset($_GET['scope']) ? trim((string) $_GET['scope']) : null;
                $status = $_GET['status'] ?? null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $dateFrom = isset($_GET['date_from']) && $_GET['date_from'] !== '' ? trim((string) $_GET['date_from']) : null;
                $dateTo = isset($_GET['date_to']) && $_GET['date_to'] !== '' ? trim((string) $_GET['date_to']) : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                if ($direction === 'incoming' && (!$status || $status === 'all')) {
                    $status = 'incoming_active';
                }
                if ($direction === 'outgoing' && (!$status || $status === 'all')) {
                    $status = 'outgoing_active';
                }
                if ($transferType === 'branch_to_branch' && (!$status || $status === 'all')) {
                    $status = 'btr_active';
                }
                if ($queue === 'approval' && (!$status || $status === 'all')) {
                    $status = 'requested';
                }
                if ($scope === 'history' && (!$status || $status === 'all')) {
                    $status = 'history';
                }
                $summary = match (true) {
                    $scope === 'report' => $this->service->transferReportSummary(
                        $wh,
                        $search ?: null,
                        $dateFrom,
                        $dateTo,
                        $transferType ?: null,
                        $direction ?: null
                    ),
                    $scope === 'history' => $this->service->historyTransferSummary($wh, $search ?: null, $dateFrom, $dateTo, $transferType),
                    $queue === 'approval' => $this->service->approvalTransferSummary($wh, $search ?: null, $transferType),
                    $transferType === 'branch_to_branch' => $this->service->branchTransferSummary($storeId, $search ?: null),
                    $direction === 'incoming' => $this->service->incomingTransferSummary($wh, $search ?: null),
                    $direction === 'outgoing' => $this->service->outgoingTransferSummary($wh, $search ?: null),
                    default => $this->service->transferSummary($wh),
                };
                $breakdown = $queue === 'approval'
                    ? $this->service->approvalTransferTypeBreakdown($wh, $search ?: null, $transferType)
                    : $this->service->transferStatusBreakdown($wh, $search ?: null, $direction, $transferType, $storeId, $scope === 'history' ? 'history' : null, $dateFrom, $dateTo);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $summary,
                    'breakdown' => $breakdown,
                    'chart' => [
                        'trend' => $this->service->transferTrend(
                            $wh,
                            $search ?: null,
                            $dateFrom,
                            $dateTo,
                            $transferType ?: null,
                            $direction ?: null,
                            (int) ($_GET['days'] ?? 30)
                        ),
                    ],
                    'total' => $this->service->countTransfers($status, $wh, $search ?: null, $direction, $transferType, $storeId, $dateFrom, $dateTo),
                    'data' => $this->service->listTransfers($status, $wh, $search ?: null, $direction, $limit, $offset, $transferType, $storeId, $dateFrom, $dateTo),
                ]);
                break;
            case 'receipts':
                if ($id) {
                    $row = $this->service->getReceipt($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $status = $_GET['status'] ?? null;
                $scope = $_GET['scope'] ?? null;
                if ($scope === 'incoming' && (!$status || $status === 'all')) {
                    $status = 'incoming';
                }
                if ($scope === 'inspection' && (!$status || $status === 'all')) {
                    $status = 'inspection';
                }
                if ($scope === 'history' && (!$status || $status === 'all')) {
                    $status = 'history';
                }
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $dateFrom = !empty($_GET['date_from']) ? (string) $_GET['date_from'] : null;
                $dateTo = !empty($_GET['date_to']) ? (string) $_GET['date_to'] : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $summary = match ($scope) {
                    'incoming' => $this->service->incomingDeliverySummary($wh, $search),
                    'inspection' => $this->service->inspectionQueueSummary($wh, $search),
                    'history' => $this->service->historyReceiptSummary($wh, $search, $dateFrom, $dateTo),
                    default => $this->service->receiptSummary($wh, $status, $search),
                };
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $summary,
                    'summary_overview' => $this->service->receiptSummary($wh, null, $search),
                    'breakdown' => $this->service->receiptStatusBreakdown($wh, $search),
                    'chart' => [
                        'trend' => $this->service->receiptTrend($wh, $search, $dateFrom, $dateTo, (int) ($_GET['days'] ?? 30)),
                    ],
                    'total' => $this->service->countReceipts($wh, $status, $search, $dateFrom, $dateTo),
                    'data' => $this->service->listReceipts($wh, $status, $search, $limit, $offset, $dateFrom, $dateTo),
                ]);
                break;
            case 'purchase-orders':
                if ($id) {
                    $row = $this->service->getPurchaseOrder($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'module_ready' => $this->service->moduleReady(), 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $status = $_GET['status'] ?? null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->purchaseOrderSummary($wh, $status, $search),
                    'breakdown' => $this->service->purchaseOrderStatusBreakdown($wh, $search),
                    'total' => $this->service->countPurchaseOrders($wh, $status, $search),
                    'data' => $this->service->listPurchaseOrders($wh, $status, $search, $limit, $offset),
                ]);
                break;
            case 'dispatches':
                if ($id) {
                    $row = $this->service->getDispatch($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'module_ready' => $this->service->moduleReady(), 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $status = $_GET['status'] ?? null;
                $scope = $_GET['scope'] ?? null;
                if ($scope === 'history' && (!$status || $status === 'all')) {
                    $status = 'history';
                }
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $dateFrom = !empty($_GET['date_from']) ? (string) $_GET['date_from'] : null;
                $dateTo = !empty($_GET['date_to']) ? (string) $_GET['date_to'] : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 150)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $view = (string) ($_GET['view'] ?? '');
                $summary = match (true) {
                    $scope === 'report' => $this->service->dispatchReportSummary($wh, $search ?: null, $dateFrom, $dateTo),
                    $scope === 'history' => $this->service->dispatchHistorySummary($wh, $search ?: null, $dateFrom, $dateTo),
                    default => match ($view) {
                        'picking' => $this->service->dispatchPickingSummary($wh),
                        'packing' => $this->service->dispatchPackingSummary($wh),
                        'shipping' => $this->service->dispatchShippingSummary($wh),
                        'delivery' => $this->service->dispatchDeliverySummary($wh),
                        default => $this->service->dispatchSummary($wh),
                    },
                };
                $breakdownScope = match (true) {
                    $scope === 'history' => 'history',
                    $view === 'picking' => 'picking_active',
                    default => null,
                };
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $summary,
                    'breakdown' => $this->service->dispatchStatusBreakdown($wh, $search ?: null, $dateFrom, $dateTo, $breakdownScope),
                    'chart' => [
                        'trend' => $this->service->dispatchTrend($wh, $search ?: null, $dateFrom, $dateTo, (int) ($_GET['days'] ?? 30)),
                    ],
                    'total' => $this->service->countDispatches($wh, $status, $search ?: null, $dateFrom, $dateTo),
                    'data' => $this->service->listDispatches($wh, $status, $search ?: null, $limit, $offset, $dateFrom, $dateTo),
                ]);
                break;
            case 'requests':
                if ($id) {
                    $row = $this->service->getRequest($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $storeFilter = (int) ($_GET['store_id'] ?? 0) ?: null;
                $status = $_GET['status'] ?? null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->requestSummary($storeFilter, $wh),
                    'breakdown' => $this->service->requestStatusBreakdown($storeFilter, $wh, $search ?: null),
                    'total' => $this->service->countRequests($storeFilter, $status, $wh, $search ?: null),
                    'data' => $this->service->listRequests($storeFilter, $status, $wh, $search ?: null, $limit, $offset),
                ]);
                break;
            case 'batches':
                if ($id) {
                    $row = $this->service->getBatch($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $days = max(1, min(365, (int) ($_GET['days'] ?? 30)));
                $scope = $_GET['scope'] ?? null;
                $strategy = in_array($_GET['strategy'] ?? '', ['fifo', 'fefo'], true)
                    ? (string) $_GET['strategy']
                    : 'fefo';
                $status = $_GET['status'] ?? match ($scope) {
                    'expiry' => 'at_risk',
                    'fifo' => 'active',
                    default => null,
                };
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $summary = match ($scope) {
                    'expiry' => $this->service->expirySummary($wh, $days),
                    'serial' => $this->service->serialSummary($wh),
                    'fifo' => $this->service->fifoSummary($wh),
                    default => $this->service->batchSummary($wh),
                };
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $summary,
                    'strategy' => $scope === 'fifo' ? $strategy : null,
                    'breakdown' => match ($scope) {
                        'expiry' => $this->service->expiryBreakdown($wh, $days, $search ?: null),
                        'fifo' => $this->service->fifoStrategyBreakdown($wh, $search ?: null),
                        default => $this->service->batchStatusBreakdown($wh, $search ?: null, $days, $scope),
                    },
                    'total' => $this->service->countBatches($wh, $status, $search ?: null, $days, $scope),
                    'data' => $this->service->listBatches($wh, $status, $search ?: null, $days, $limit, $offset, $scope, $strategy),
                ]);
                break;
            case 'audits':
                if ($id) {
                    $row = $this->service->getAudit($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'module_ready' => $this->service->moduleReady(), 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $status = $_GET['status'] ?? null;
                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
                $auditType = $_GET['audit_type'] ?? null;
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 150)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->auditSummary($wh),
                    'breakdown' => $this->service->auditStatusBreakdown($wh, $search ?: null, $auditType),
                    'total' => $this->service->countAudits($wh, $status, $search ?: null, $auditType),
                    'data' => $this->service->listAudits($wh, $status, $search ?: null, $auditType, $limit, $offset),
                ]);
                break;
            case 'logs':
                if ($id) {
                    $row = $this->service->getLog($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        break;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $filters = [
                    'action' => $_GET['action'] ?? null,
                    'entity_type' => $_GET['entity_type'] ?? null,
                    'from' => $_GET['from'] ?? null,
                    'to' => $_GET['to'] ?? null,
                    'q' => isset($_GET['q']) ? trim((string) $_GET['q']) : null,
                ];
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->logSummary($wh, $filters),
                    'breakdown' => $this->service->logBreakdown($wh, $filters),
                    'actions' => $this->service->logActions($wh),
                    'total' => $this->service->countLogs($wh, $filters),
                    'data' => $this->service->listLogs($wh, $filters, $limit, $offset),
                ]);
                break;
            case 'notifications':
                echo json_encode(['status' => 'success', 'data' => $this->service->listNotifications((int) ($_GET['warehouse_id'] ?? 0) ?: null, $_GET['since'] ?? null)]);
                break;
            case 'inventory-report':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $reportService = new InventoryReportService();
                $tab = preg_replace('/[^a-z_]/', '', (string) ($_GET['tab'] ?? 'overview')) ?: 'overview';
                $filters = $reportService->parseFilters($_GET);
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $payload = $reportService->handleTab($tab, $filters, $limit, $offset);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $reportService->moduleReady(),
                    'tab' => $tab,
                    'filters' => $filters,
                    'alerts' => $tab === 'overview' ? $reportService->alerts($filters) : [],
                    'summary' => $payload['summary'] ?? null,
                    'charts' => $payload['charts'] ?? null,
                    'total' => $payload['total'] ?? 0,
                    'data' => $payload['data'] ?? [],
                ]);
                break;
            case 'warehouse-performance':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $perfService = new WarehousePerformanceService();
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $payload = $perfService->report($_GET, $limit, $offset);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $perfService->moduleReady(),
                    'filters' => $payload['filters'],
                    'summary' => $payload['summary'],
                    'charts' => $payload['charts'],
                    'total' => $payload['total'],
                    'data' => $payload['data'],
                ]);
                break;
            case 'inventory-valuation':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $valService = new InventoryValuationService();
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $payload = $valService->report($_GET, $limit, $offset);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $valService->moduleReady(),
                    'filters' => $payload['filters'],
                    'summary' => $payload['summary'],
                    'breakdown' => $payload['breakdown'],
                    'charts' => $payload['charts'],
                    'total' => $payload['total'],
                    'data' => $payload['data'],
                ]);
                break;
            case 'damage-report':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $dmgService = new DamageReportService();
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $payload = $dmgService->report($_GET, $limit, $offset);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $dmgService->moduleReady(),
                    'filters' => $payload['filters'],
                    'summary' => $payload['summary'],
                    'breakdown' => $payload['breakdown'],
                    'chart' => $payload['chart'],
                    'total' => $payload['total'],
                    'data' => $payload['data'],
                ]);
                break;
            case 'expiry-report':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $expService = new ExpiryReportService();
                $limit = min(200, max(1, (int) ($_GET['limit'] ?? 50)));
                $offset = max(0, (int) ($_GET['offset'] ?? 0));
                $payload = $expService->report($_GET, $limit, $offset);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $expService->moduleReady(),
                    'filters' => $payload['filters'],
                    'summary' => $payload['summary'],
                    'breakdown' => $payload['breakdown'],
                    'chart' => $payload['chart'],
                    'total' => $payload['total'],
                    'data' => $payload['data'],
                ]);
                break;
            case 'sync':
                $this->handleSyncGet($sub);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }

    private function handlePost(string $action, ?string $sub, ?int $id): void
    {
        $data = $this->body();
        $userId = $this->userId();

        switch ($action) {
            case 'warehouses':
                $this->json($this->service->createWarehouse($data, $userId));
                break;
            case 'locations':
                $this->json($this->service->createLocation($data, $userId));
                break;
            case 'transfers':
                if ($sub === 'approve' && $id) {
                    $this->json($this->service->approveTransfer($id, $userId));
                } elseif ($sub === 'complete' && $id) {
                    $this->json($this->service->completeTransfer($id, $userId));
                } elseif ($sub === 'reject' && $id) {
                    $this->json($this->service->rejectTransfer($id, $userId));
                } else {
                    $this->json($this->service->createTransfer($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'receipts':
                if ($sub === 'complete' && $id) {
                    $this->json($this->service->completeReceipt($id, $userId));
                } elseif ($sub === 'inspect' && $id) {
                    $this->json($this->service->updateReceiptStatus($id, 'inspecting', $userId));
                } elseif ($sub === 'accept' && $id) {
                    $this->json($this->service->updateReceiptStatus($id, 'accepted', $userId));
                } elseif ($sub === 'reject' && $id) {
                    $this->json($this->service->updateReceiptStatus($id, 'rejected', $userId));
                } elseif ($sub === 'inspection' && $id) {
                    $this->json($this->service->saveReceiptInspection($id, $data['items'] ?? [], $userId));
                } else {
                    $this->json($this->service->createReceipt($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'purchase-orders':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if ($sub === 'submit' && $id) {
                    $this->json($this->service->updatePurchaseOrderStatus($id, 'pending', $userId));
                } elseif ($sub === 'approve' && $id) {
                    if (!WarehousePortalAuth::canManage()) {
                        http_response_code(403);
                        echo json_encode(['status' => 'error', 'message' => 'Approval not permitted']);
                        break;
                    }
                    $this->json($this->service->updatePurchaseOrderStatus($id, 'approved', $userId));
                } elseif ($sub === 'cancel' && $id) {
                    $this->json($this->service->updatePurchaseOrderStatus($id, 'cancelled', $userId));
                } elseif ($sub === 'receive' && $id) {
                    if (WarehousePortalAuth::isReadOnly()) {
                        http_response_code(403);
                        echo json_encode(['status' => 'error', 'message' => 'Read-only access']);
                        break;
                    }
                    $this->json($this->service->createGrnFromPurchaseOrder($id, $userId));
                } else {
                    if (WarehousePortalAuth::isReadOnly()) {
                        http_response_code(403);
                        echo json_encode(['status' => 'error', 'message' => 'Read-only access']);
                        break;
                    }
                    $this->json($this->service->createPurchaseOrder($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'dispatches':
                if ($sub === 'dispatch' && $id) {
                    $this->json($this->service->dispatchOut($id, $userId));
                } elseif ($sub === 'status' && $id) {
                    require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                    if (WarehousePortalAuth::isReadOnly()) {
                        http_response_code(403);
                        echo json_encode(['status' => 'error', 'message' => 'Read-only access']);
                        break;
                    }
                    $this->json($this->service->updateDispatchStatus($id, (string) ($data['status'] ?? ''), $userId));
                } else {
                    $this->json($this->service->createDispatch($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'requests':
                if ($sub === 'approve' && $id) {
                    $this->json($this->service->approveRequest($id, $userId, $data['role'] ?? 'manager'));
                } elseif ($sub === 'reject' && $id) {
                    $this->json($this->service->rejectRequest($id, $userId));
                } else {
                    $this->json($this->service->createRequest($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'batches':
                if ($sub === 'status' && $id) {
                    $this->json($this->service->updateBatchStatus($id, (string) ($data['status'] ?? ''), $userId));
                } else {
                    $this->json($this->service->createBatch($data, $userId));
                }
                break;
            case 'audits':
                if ($sub === 'submit' && $id) {
                    $this->json($this->service->submitAudit($id, $userId));
                } elseif ($sub === 'approve' && $id) {
                    $this->json($this->service->approveAudit($id, $userId));
                } elseif ($sub === 'reject' && $id) {
                    $this->json($this->service->rejectAudit($id, $userId));
                } else {
                    $this->json($this->service->createAudit($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'adjustments':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (WarehousePortalAuth::isReadOnly()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Read-only access']);
                    break;
                }
                $this->json($this->service->createAdjustment($data, $userId));
                break;
            case 'sync':
                if ($sub === 'resolve' && $id) {
                    $this->json($this->syncMonitor->resolveItem(
                        $id,
                        (string) ($data['entity'] ?? ''),
                        (string) ($data['action'] ?? 'retry')
                    ));
                } else {
                    $this->json($this->service->syncOffline($data['items'] ?? [], $userId));
                }
                break;
            case 'inventory-report':
                require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';
                if (!WarehousePortalAuth::canReports()) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                    break;
                }
                $reportService = new InventoryReportService();
                if ($sub === 'audit' || $sub === 'schedule') {
                    $reportService->logAudit($userId, (int) ($data['warehouse_id'] ?? 0) ?: null, array_merge(
                        ['action' => $sub],
                        $data
                    ));
                    if ($sub === 'schedule') {
                        $_SESSION['wh_inv_report_schedule'] = $data;
                    }
                    echo json_encode(['status' => 'success', 'data' => $sub === 'schedule' ? ($data) : null]);
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }

    private function handleSyncGet(?string $sub): void
    {
        $storeId = $this->storeId();
        $warehouseId = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
        $ready = $this->syncMonitor->ready();

        switch ($sub) {
            case 'monitor':
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $ready,
                    'data' => $this->syncMonitor->dashboard($storeId),
                ]);
                break;
            case 'warehouses':
                echo json_encode(['status' => 'success', 'module_ready' => $ready, 'data' => $this->syncMonitor->listWarehouses($storeId)]);
                break;
            case 'pending':
                echo json_encode(['status' => 'success', 'module_ready' => $ready, 'data' => $this->syncMonitor->listItems($storeId, 'pending', $warehouseId)]);
                break;
            case 'conflicts':
                echo json_encode(['status' => 'success', 'module_ready' => $ready, 'data' => $this->syncMonitor->listItems($storeId, 'conflict', $warehouseId)]);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }
}

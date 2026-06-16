<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Wms/Services/WmsService.php';
require_once __DIR__ . '/../Wms/Services/WmsDashboardService.php';

class WmsController
{
    private WmsService $service;
    private WmsDashboardService $dashboard;

    public function __construct()
    {
        $this->service = new WmsService();
        $this->dashboard = new WmsDashboardService();
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
        $role = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
        return in_array($role, ['super_admin', 'admin', 'manager'], true);
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
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->warehouseSummary($storeId),
                    'data' => $this->service->listWarehouses(
                        $storeId,
                        $_GET['status'] ?? null,
                        isset($_GET['q']) ? trim((string) $_GET['q']) : null
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
            case 'locations':
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'data' => $this->service->listLocations((int) ($_GET['warehouse_id'] ?? 0)),
                ]);
                break;
            case 'movements':
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                $filters = [
                    'movement_type' => $_GET['type'] ?? 'all',
                    'from' => $_GET['from'] ?? null,
                    'to' => $_GET['to'] ?? null,
                    'q' => $_GET['q'] ?? null,
                ];
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->movementSummary($wh, $filters),
                    'breakdown' => $this->service->movementBreakdown($wh, $filters),
                    'data' => $this->service->listMovements($wh, $filters),
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
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->transferSummary($wh),
                    'data' => $this->service->listTransfers($_GET['status'] ?? null, $wh, $_GET['q'] ?? null),
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
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'data' => $this->service->listReceipts((int) ($_GET['warehouse_id'] ?? 0) ?: null, $_GET['status'] ?? null),
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
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->dispatchSummary($wh),
                    'data' => $this->service->listDispatches($wh, $_GET['status'] ?? null, $_GET['q'] ?? null),
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
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->requestSummary($storeFilter, $wh),
                    'data' => $this->service->listRequests($storeFilter, $_GET['status'] ?? null, $wh, $_GET['q'] ?? null),
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
                $summary = $scope === 'expiry'
                    ? $this->service->expirySummary($wh, $days)
                    : $this->service->batchSummary($wh);
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $summary,
                    'data' => $this->service->listBatches(
                        $wh,
                        $_GET['status'] ?? ($scope === 'expiry' ? 'at_risk' : null),
                        $_GET['q'] ?? null,
                        $days
                    ),
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
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $wh = (int) ($_GET['warehouse_id'] ?? 0) ?: null;
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->auditSummary($wh),
                    'data' => $this->service->listAudits(
                        $wh,
                        $_GET['status'] ?? null,
                        $_GET['q'] ?? null,
                        $_GET['audit_type'] ?? null
                    ),
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
                    'q' => $_GET['q'] ?? null,
                ];
                echo json_encode([
                    'status' => 'success',
                    'module_ready' => $this->service->moduleReady(),
                    'summary' => $this->service->logSummary($wh, $filters),
                    'breakdown' => $this->service->logBreakdown($wh, $filters),
                    'actions' => $this->service->logActions($wh),
                    'data' => $this->service->listLogs($wh, $filters),
                ]);
                break;
            case 'notifications':
                echo json_encode(['status' => 'success', 'data' => $this->service->listNotifications((int) ($_GET['warehouse_id'] ?? 0) ?: null, $_GET['since'] ?? null)]);
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
                } else {
                    $this->json($this->service->createReceipt($data, $data['items'] ?? [], $userId));
                }
                break;
            case 'dispatches':
                if ($sub === 'dispatch' && $id) {
                    $this->json($this->service->dispatchOut($id, $userId));
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
            case 'sync':
                $this->json($this->service->syncOffline($data['items'] ?? [], $userId));
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }
}

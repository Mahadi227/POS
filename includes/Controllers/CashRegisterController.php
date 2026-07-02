<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../CashRegister/Services/CashRegisterService.php';
require_once __DIR__ . '/../CashRegister/Services/CashRegisterDashboardService.php';

class CashRegisterController
{
    private CashRegisterService $service;
    private CashRegisterDashboardService $dashboard;

    public function __construct()
    {
        $this->service = new CashRegisterService();
        $this->dashboard = new CashRegisterDashboardService();
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
        if ($method === 'PUT' && $action === 'registers' && $id) {
            $this->handlePutRegister($id);
            return;
        }
        if ($method === 'DELETE' && $action === 'registers' && $id) {
            $this->handleDeleteRegister($id);
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

    private function handleGet(string $action, ?string $sub, ?int $id): void
    {
        $storeId = $this->storeId();

        switch ($action) {
            case 'dashboard':
                echo json_encode(['status' => 'success', 'data' => $this->dashboard->dashboard($storeId)]);
                break;
            case 'registers':
                if ($id) {
                    $row = $this->service->getRegister($id);
                    if (!$row) {
                        http_response_code(404);
                        echo json_encode(['status' => 'error', 'message' => 'Not found']);
                        return;
                    }
                    echo json_encode(['status' => 'success', 'data' => $row]);
                    break;
                }
                $status = $_GET['status'] ?? null;
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listRegisters($storeId, $status),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'sessions':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listSessions($storeId, [
                        'status' => $_GET['status'] ?? null,
                        'shift_type' => $_GET['shift_type'] ?? null,
                        'from' => $_GET['from'] ?? null,
                        'to' => $_GET['to'] ?? null,
                        'q' => $_GET['q'] ?? null,
                    ]),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'movements':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listMovements($storeId, [
                        'register_id' => $_GET['register_id'] ?? null,
                        'movement_type' => $_GET['type'] ?? 'all',
                        'from' => $_GET['from'] ?? null,
                        'to' => $_GET['to'] ?? null,
                        'q' => $_GET['q'] ?? null,
                    ]),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'reconciliation':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listReconciliations($storeId, $_GET),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'transfers':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listTransfers($storeId, [
                        'status' => $_GET['status'] ?? null,
                        'from' => $_GET['from'] ?? null,
                        'to' => $_GET['to'] ?? null,
                        'q' => $_GET['q'] ?? null,
                    ]),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'history':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->dashboard->history($storeId, $_GET['from'] ?? null, $_GET['to'] ?? null),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'analytics':
                $data = $this->dashboard->analytics($storeId, $_GET['period'] ?? 'month');
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'logs':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listLogs($storeId, $_GET),
                    'actions' => $this->service->listLogActions($storeId),
                    'module_ready' => $this->service->moduleReady(),
                ]);
                break;
            case 'notifications':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->listNotifications($storeId, $_GET['since'] ?? null),
                    'module_ready' => true,
                ]);
                break;
            case 'export':
                $this->handleExport($storeId);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }

    private function handlePost(string $action, ?string $sub, ?int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $userId = $this->userId();
        $storeId = $this->storeId();

        switch ($action) {
            case 'registers':
                if ($sub === 'open' && $id) {
                    $result = $this->service->openSession($id, $userId, $data);
                } elseif ($sub === 'close' && $id) {
                    $result = $this->service->closeSession($id, $userId, $data);
                } else {
                    $data['store_id'] = $data['store_id'] ?? $storeId;
                    $result = $this->service->createRegister($data, $userId);
                }
                break;
            case 'sessions':
                if ($sub === 'close' && $id) {
                    $result = $this->service->closeSession($id, $userId, $data);
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid session action']);
                    return;
                }
                break;
            case 'reconciliation':
                if ($sub === 'approve' && $id) {
                    $result = $this->service->reviewReconciliation($id, 'approved', $userId, $this->roleSlug(), $data['note'] ?? null);
                } elseif ($sub === 'reject' && $id) {
                    $result = $this->service->reviewReconciliation($id, 'rejected', $userId, $this->roleSlug(), $data['note'] ?? null);
                } else {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid reconciliation action']);
                    return;
                }
                break;
            case 'transfers':
                if ($sub === 'approve' && $id) {
                    $result = $this->service->approveTransfer($id, $userId);
                } elseif ($sub === 'complete' && $id) {
                    $result = $this->service->completeTransfer($id, $userId);
                } else {
                    $data['store_id'] = $data['store_id'] ?? $storeId;
                    $result = $this->service->createTransfer($data, $userId);
                }
                break;
            case 'sync':
                $result = $this->service->syncOfflineMovements($data['items'] ?? [], $userId);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
                return;
        }

        if (($result['status'] ?? '') !== 'success') {
            http_response_code(400);
        }
        echo json_encode($result);
    }

    private function handlePutRegister(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $result = $this->service->updateRegister($id, $data, $this->userId());
        if (($result['status'] ?? '') !== 'success') {
            http_response_code(400);
        }
        echo json_encode($result);
    }

    private function handleDeleteRegister(int $id): void
    {
        $result = $this->service->deleteRegister($id, $this->userId());
        if (($result['status'] ?? '') !== 'success') {
            http_response_code(400);
        }
        echo json_encode($result);
    }

    private function roleSlug(): string
    {
        $role = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
        return $role === 'manager' ? 'manager' : 'admin';
    }

    private function handleExport(?int $storeId): void
    {
        $type = $_GET['type'] ?? 'history';
        $format = strtolower($_GET['format'] ?? 'csv');
        $from = $_GET['from'] ?? null;
        $to = $_GET['to'] ?? null;

        if ($type === 'history') {
            $rows = $this->dashboard->history($storeId, $from, $to);
        } elseif ($type === 'movements') {
            $rows = $this->service->listMovements($storeId, [
                'movement_type' => $_GET['movement_type'] ?? 'all',
                'from' => $from,
                'to' => $to,
            ]);
        } else {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Unknown export type']);
            return;
        }

        if ($format === 'json') {
            header('Content-Type: application/json');
            echo json_encode(['status' => 'success', 'data' => $rows]);
            return;
        }

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="cash-register-' . $type . '-' . date('Y-m-d') . '.csv"');
        $out = fopen('php://output', 'w');
        if (!$rows) {
            fclose($out);
            return;
        }
        fputcsv($out, array_keys($rows[0]));
        foreach ($rows as $row) {
            fputcsv($out, array_values($row));
        }
        fclose($out);
    }
}

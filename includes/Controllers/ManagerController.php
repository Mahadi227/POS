<?php
/**
 * Manager supervision API — manager/*
 */
declare(strict_types=1);

require_once __DIR__ . '/../Manager/ManagerAuth.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Manager/Services/SupervisionService.php';
require_once __DIR__ . '/../Manager/Services/ApprovalService.php';
require_once __DIR__ . '/../Manager/Services/ShiftService.php';
require_once __DIR__ . '/../Manager/Services/AuditService.php';

class ManagerController
{
    private SupervisionService $supervision;
    private ApprovalService $approvals;
    private ShiftService $shifts;

    public function __construct()
    {
        ManagerAuth::requireManagerApi();
        $this->supervision = new SupervisionService();
        $this->approvals = new ApprovalService();
        $this->shifts = new ShiftService();
    }

    public function handleRequest(string $method, array $path): void
    {
        $domain = $path[1] ?? 'dashboard';
        $action = $path[2] ?? null;
        $id = isset($path[3]) ? (int) $path[3] : null;

        switch ($method) {
            case 'GET':
                $this->handleGet($domain, $action, $id);
                break;
            case 'POST':
                $this->handlePost($domain, $action, $id);
                break;
            default:
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
        }
    }

    private function storeId(): ?int
    {
        return StoreScope::activeStoreId();
    }

    private function handleGet(string $domain, ?string $action, ?int $id): void
    {
        switch ($domain) {
            case 'dashboard':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->supervision->dashboard($this->storeId()),
                ]);
                break;

            case 'supervision':
                if ($action === 'live') {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->supervision->liveRegisters($this->storeId()),
                    ]);
                    break;
                }
                if ($action === 'shifts') {
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->shifts->listOpen($this->storeId()),
                    ]);
                    break;
                }
                if ($action === 'team') {
                    $period = (string) ($_GET['period'] ?? 'today');
                    $from = isset($_GET['from']) ? (string) $_GET['from'] : null;
                    $to = isset($_GET['to']) ? (string) $_GET['to'] : null;
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->supervision->teamPerformance($this->storeId(), $period, $from, $to),
                    ]);
                    break;
                }
                $this->notFound();
                break;

            case 'approvals':
                $allowedTypes = ['return', 'discount', 'void', 'stock_adjustment'];
                $type = null;
                if (!empty($_GET['type']) && in_array((string) $_GET['type'], $allowedTypes, true)) {
                    $type = (string) $_GET['type'];
                } elseif ($action && in_array($action, $allowedTypes, true)) {
                    $type = $action;
                }
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->approvals->listPending($this->storeId(), $type),
                ]);
                break;

            case 'audit':
                echo json_encode([
                    'status' => 'success',
                    'data' => AuditService::trail($this->storeId(), [
                        'period' => (string) ($_GET['period'] ?? 'today'),
                        'from'   => isset($_GET['from']) ? (string) $_GET['from'] : null,
                        'to'     => isset($_GET['to']) ? (string) $_GET['to'] : null,
                        'filter' => (string) ($_GET['filter'] ?? 'all'),
                        'q'      => (string) ($_GET['q'] ?? ''),
                    ]),
                ]);
                break;

            case 'operations':
                if ($action === 'inventory') {
                    $filter = (string) ($_GET['filter'] ?? 'all');
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->supervision->inventoryAlerts($this->storeId(), $filter),
                    ]);
                    break;
                }
                if ($action === 'sales-review') {
                    $period = (string) ($_GET['period'] ?? 'today');
                    $from = isset($_GET['from']) ? (string) $_GET['from'] : null;
                    $to = isset($_GET['to']) ? (string) $_GET['to'] : null;
                    $filter = (string) ($_GET['filter'] ?? 'all');
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->supervision->salesReview(
                            $this->storeId(),
                            $period,
                            $from,
                            $to,
                            $filter
                        ),
                    ]);
                    break;
                }
                if ($action === 'cash-reconciliation') {
                    $filter = (string) ($_GET['filter'] ?? 'open');
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->shifts->cashReconciliation($this->storeId(), $filter),
                    ]);
                    break;
                }
                $this->notFound();
                break;

            case 'reports':
                if ($action === 'daily-summary') {
                    $date = isset($_GET['date']) ? (string) $_GET['date'] : null;
                    echo json_encode([
                        'status' => 'success',
                        'data' => $this->supervision->dailySummary($this->storeId(), $date),
                    ]);
                    break;
                }
                if ($action === 'audit-trail') {
                    echo json_encode([
                        'status' => 'success',
                        'data' => AuditService::trail($this->storeId(), [
                            'period' => (string) ($_GET['period'] ?? 'today'),
                            'from'   => isset($_GET['from']) ? (string) $_GET['from'] : null,
                            'to'     => isset($_GET['to']) ? (string) $_GET['to'] : null,
                            'filter' => (string) ($_GET['filter'] ?? 'all'),
                            'q'      => (string) ($_GET['q'] ?? ''),
                        ]),
                    ]);
                    break;
                }
                $this->notFound();
                break;

            default:
                $this->notFound();
        }
    }

    private function handlePost(string $domain, ?string $action, ?int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if ($domain === 'approvals' && $id) {
            $note = $data['note'] ?? null;
            $result = ($action === 'reject')
                ? $this->approvals->reject($id, $note)
                : $this->approvals->approve($id, $note);
            echo json_encode($result);
            return;
        }

        $this->notFound();
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Manager endpoint not found']);
    }
}

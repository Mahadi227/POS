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
                $this->notFound();
                break;

            case 'approvals':
                $type = $action && $action !== 'list' ? $action : null;
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->approvals->listPending($this->storeId(), $type),
                ]);
                break;

            case 'audit':
                echo json_encode([
                    'status' => 'success',
                    'data' => AuditService::recent($this->storeId()),
                ]);
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

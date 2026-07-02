<?php
declare(strict_types=1);

require_once __DIR__ . '/../Notifications/Services/NotificationService.php';
require_once __DIR__ . '/../Notifications/NotificationManager.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class NotificationController
{
    private NotificationService $service;
    private PDO $db;

    public function __construct()
    {
        $this->service = new NotificationService();
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest(string $method, array $path): void
    {
        if (!$this->service->isReady()) {
            http_response_code(503);
            echo json_encode(['status' => 'error', 'message' => 'Run migration 010_notifications.sql']);
            return;
        }

        $action = $path[1] ?? 'list';
        $sub = $path[2] ?? null;

        if ($action === 'sse' && $method === 'GET') {
            $this->streamSse();
            return;
        }

        if ($method === 'GET') {
            $this->handleGet($action, $sub);
            return;
        }
        if ($method === 'POST') {
            $this->handlePost($action, $sub);
            return;
        }

        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function handleGet(string $action, ?string $sub): void
    {
        $uid = $this->userId();

        switch ($action) {
            case 'list':
                $filters = [
                    'unread' => !empty($_GET['unread']),
                    'archived' => !empty($_GET['archived']),
                    'pinned' => !empty($_GET['pinned']),
                    'category' => $_GET['category'] ?? null,
                    'module' => $_GET['module'] ?? null,
                    'priority' => $_GET['priority'] ?? null,
                    'search' => $_GET['search'] ?? null,
                    'since' => $_GET['since'] ?? null,
                    'limit' => $_GET['limit'] ?? 50,
                    'offset' => $_GET['offset'] ?? 0,
                ];
                $warehouseId = (int) ($_GET['warehouse_id'] ?? $_SESSION['warehouse_id'] ?? 0);
                if ($warehouseId > 0 && $this->isWarehousePortalRole()) {
                    $filters['warehouse_id'] = $warehouseId;
                }
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->list($uid, $filters),
                    'unread_count' => $this->service->unreadCount($uid),
                ]);
                break;

            case 'unread-count':
                echo json_encode([
                    'status' => 'success',
                    'count' => $this->service->unreadCount($uid),
                ]);
                break;

            case 'preferences':
                echo json_encode(['status' => 'success', 'data' => $this->service->getPreferences($uid)]);
                break;

            case 'meta':
                echo json_encode(['status' => 'success', 'data' => $this->service->getMeta()]);
                break;

            case 'analytics':
                $this->requireAdmin();
                $storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : StoreScope::activeStoreId();
                echo json_encode(['status' => 'success', 'data' => $this->service->analytics($storeId)]);
                break;

            case 'logs':
                $this->requireAdmin();
                echo json_encode(['status' => 'success', 'data' => $this->service->logs($_GET)]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
        }
    }

    private function handlePost(string $action, ?string $sub): void
    {
        $uid = $this->userId();
        $data = $this->input();

        switch ($action) {
            case 'mark-read':
                $ids = array_map('intval', $data['ids'] ?? []);
                echo json_encode(['status' => 'success', 'updated' => $this->service->markRead($uid, $ids)]);
                break;

            case 'mark-all-read':
                echo json_encode(['status' => 'success', 'updated' => $this->service->markAllRead($uid)]);
                break;

            case 'archive':
                $ids = array_map('intval', $data['ids'] ?? []);
                $archive = ($data['archive'] ?? true) !== false;
                echo json_encode(['status' => 'success', 'updated' => $this->service->archive($uid, $ids, $archive)]);
                break;

            case 'pin':
                $id = (int) ($data['id'] ?? 0);
                $pinned = !empty($data['pinned']);
                echo json_encode(['status' => 'success', 'ok' => $this->service->pin($uid, $id, $pinned)]);
                break;

            case 'delete':
                $ids = array_map('intval', $data['ids'] ?? []);
                echo json_encode(['status' => 'success', 'deleted' => $this->service->delete($uid, $ids)]);
                break;

            case 'restore':
                $ids = array_map('intval', $data['ids'] ?? []);
                echo json_encode(['status' => 'success', 'restored' => $this->service->restore($uid, $ids)]);
                break;

            case 'preferences':
                try {
                    $this->service->savePreferences($uid, $data);
                    echo json_encode(['status' => 'success', 'message' => 'Preferences saved']);
                } catch (InvalidArgumentException $e) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
                }
                break;

            case 'sync':
                $local = $data['local'] ?? [];
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->service->syncOffline($uid, is_array($local) ? $local : []),
                ]);
                break;

            case 'send':
                $this->requireAdmin();
                $ids = NotificationManager::dispatch($data);
                echo json_encode(['status' => 'success', 'notification_ids' => $ids]);
                break;

            case 'process-queue':
                $this->requireAdmin();
                echo json_encode(['status' => 'success', 'data' => $this->service->processQueue()]);
                break;

            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
        }
    }

    private function streamSse(): void
    {
        $uid = $this->userId();
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        $lastCount = -1;
        $iterations = 0;
        while ($iterations < 30 && !connection_aborted()) {
            $count = $this->service->unreadCount($uid);
            if ($count !== $lastCount) {
                echo 'event: unread' . "\n";
                echo 'data: ' . json_encode(['count' => $count]) . "\n\n";
                $lastCount = $count;
            }
            if (ob_get_level() > 0) {
                ob_flush();
            }
            flush();
            sleep(2);
            $iterations++;
        }
    }

    private function requireAdmin(): void
    {
        $slug = RoleRedirect::slug($_SESSION['role'] ?? '');
        if (!in_array($slug, ['super_admin', 'admin'], true) && !PermissionService::isSuperAdmin()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Forbidden']);
            exit;
        }
    }

    private function isWarehousePortalRole(): bool
    {
        $slug = RoleRedirect::slug($_SESSION['role'] ?? '');
        return in_array($slug, [
            'warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer',
            'warehouse_auditor', 'storekeeper',
        ], true);
    }

    private function input(): array
    {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw ?: '{}', true);
        return is_array($data) ? $data : ($_POST ?: []);
    }
}

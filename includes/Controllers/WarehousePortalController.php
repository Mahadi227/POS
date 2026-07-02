<?php

declare(strict_types=1);



require_once __DIR__ . '/../Helpers/StoreScope.php';

require_once __DIR__ . '/../Helpers/RbacGuard.php';

require_once __DIR__ . '/../Helpers/WarehousePortalAuth.php';

require_once __DIR__ . '/../Wms/Services/WarehousePortalService.php';

require_once __DIR__ . '/../Wms/Services/WarehouseCalendarService.php';

require_once __DIR__ . '/../Wms/Repositories/WarehouseTaskRepository.php';

require_once __DIR__ . '/../Wms/Services/WmsService.php';

require_once __DIR__ . '/../Wms/WmsSchema.php';

require_once __DIR__ . '/../Warehouse/Services/WarehouseProfileService.php';
require_once __DIR__ . '/../Warehouse/Services/WarehouseSettingsService.php';
require_once __DIR__ . '/../Warehouse/Services/WarehouseHelpService.php';
require_once __DIR__ . '/../Warehouse/Services/WarehouseNotificationService.php';
require_once __DIR__ . '/../Database/Database.php';

class WarehousePortalController
{
    private WarehousePortalService $portal;

    private WarehouseCalendarService $calendar;

    private WmsService $wms;

    private WarehouseProfileService $profile;

    private WarehouseSettingsService $settings;

    private WarehouseHelpService $help;

    private WarehouseNotificationService $notifications;



    public function __construct()

    {

        $this->portal = new WarehousePortalService();

        $this->calendar = new WarehouseCalendarService();

        $this->wms = new WmsService();

        $this->profile = new WarehouseProfileService();

        $this->settings = new WarehouseSettingsService();

        $this->help = new WarehouseHelpService();

        $this->notifications = new WarehouseNotificationService();

    }



    public function handleRequest(string $method, array $path): void

    {

        if (!$this->canAccess()) {

            http_response_code(403);

            echo json_encode(['status' => 'error', 'message' => 'Access denied']);

            return;

        }



        $action = $path[1] ?? 'dashboard';

        if ($action === 'profile') {

            $this->handleProfile($method, $path);

            return;

        }



        if ($action === 'settings') {

            $this->handleSettings($method, $path);

            return;

        }



        if ($action === 'help') {

            $this->handleHelp($method, $path);

            return;

        }



        if ($action === 'notifications') {

            $this->handleNotifications($method, $path);

            return;

        }



        if ($method === 'GET') {

            $this->handleGet($action);

            return;

        }

        http_response_code(405);

        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

    }



    private function canAccess(): bool

    {

        $role = WarehousePortalAuth::roleSlug();

        $allowed = array_merge(

            ['warehouse_manager', 'inventory_officer', 'receiving_officer', 'dispatch_officer', 'warehouse_auditor', 'storekeeper'],

            ['admin', 'manager', 'super_admin']

        );

        return in_array($role, $allowed, true);

    }



    private function userId(): int

    {

        return (int) ($_SESSION['user_id'] ?? 0);

    }



    private function verifyCsrf(?array $data = null): bool

    {

        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($data['csrf_token'] ?? '');

        return is_string($token) && $token !== '' && function_exists('verify_csrf_token') && verify_csrf_token($token);

    }

    /** Resolve warehouse context for settings (session, query, or first available for admins). */
    private function resolveWarehouseId(): int
    {
        $warehouseId = (int) ($_GET['warehouse_id'] ?? $_SESSION['warehouse_id'] ?? 0);
        if ($warehouseId > 0) {
            return $warehouseId;
        }
        if (!WarehousePortalAuth::canManage()
            && !in_array(WarehousePortalAuth::roleSlug(), ['super_admin', 'admin'], true)) {
            return 0;
        }
        try {
            $db = Database::getInstance()->getConnection();
            if (!WmsSchema::ready()) {
                return 0;
            }
            $id = (int) $db->query(
                'SELECT id FROM warehouses WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 1'
            )->fetchColumn();
            return $id > 0 ? $id : 0;
        } catch (Throwable) {
            return 0;
        }
    }



    private function handleProfile(string $method, array $path): void

    {

        $sub = $path[2] ?? null;

        $uid = $this->userId();

        if ($uid <= 0) {

            http_response_code(401);

            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);

            return;

        }



        $targetId = (int) ($_GET['user_id'] ?? $uid);

        if ($targetId <= 0) {

            $targetId = $uid;

        }



        try {

            if ($method === 'GET' && $sub === 'login-history') {

                $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));

                $offset = max(0, (int) ($_GET['offset'] ?? 0));

                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;

                $payload = $this->profile->loginHistory($uid, $targetId, $search ?: null, $limit, $offset);

                echo json_encode(['status' => 'success', 'module_ready' => $this->profile->moduleReady(), ...$payload]);

                return;

            }



            if ($method === 'GET' && $sub === 'activities') {

                $limit = min(100, max(1, (int) ($_GET['limit'] ?? 20)));

                $offset = max(0, (int) ($_GET['offset'] ?? 0));

                $payload = $this->profile->activities($uid, $targetId, $limit, $offset);

                echo json_encode(['status' => 'success', 'module_ready' => $this->profile->moduleReady(), ...$payload]);

                return;

            }



            if ($method === 'GET' && $sub === null) {

                echo json_encode([

                    'status' => 'success',

                    'module_ready' => $this->profile->moduleReady(),

                    'data' => $this->profile->getProfile($uid, $targetId),

                ]);

                return;

            }



            if ($method === 'POST' && $sub === 'avatar') {

                if (!$this->verifyCsrf()) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $url = $this->profile->uploadAvatar($uid, $_FILES['avatar'] ?? []);

                echo json_encode(['status' => 'success', 'message' => 'Avatar updated', 'avatar_url' => $url]);

                return;

            }



            if ($method === 'POST' && $sub === 'password') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $this->profile->changePassword($uid, $data);

                echo json_encode(['status' => 'success', 'message' => 'Password updated']);

                return;

            }



            if ($method === 'POST' && $sub === 'preferences') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $this->profile->savePreferences($uid, $data);

                echo json_encode(['status' => 'success', 'message' => 'Preferences saved']);

                return;

            }



            if ($method === 'POST' && $sub === 'notifications') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $this->profile->saveNotificationPreferences($uid, $data);

                echo json_encode(['status' => 'success', 'message' => 'Notification preferences saved']);

                return;

            }



            if ($method === 'POST' && $sub === 'logout-devices') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $this->profile->logoutOtherDevices($uid);

                echo json_encode(['status' => 'success', 'message' => 'Other sessions revoked']);

                return;

            }



            if ($method === 'POST' && $sub === null) {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $result = $this->profile->updateProfile($uid, $targetId, $data);

                echo json_encode(['status' => 'success', 'message' => 'Profile updated', 'data' => $result]);

                return;

            }



            if ($method === 'DELETE' && $sub === 'avatar') {

                if (!$this->verifyCsrf($_GET)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $this->profile->deleteAvatar($uid);

                echo json_encode(['status' => 'success', 'message' => 'Avatar removed']);

                return;

            }



            http_response_code(405);

            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

        } catch (InvalidArgumentException $e) {

            http_response_code(400);

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        } catch (RuntimeException $e) {

            $code = str_contains(strtolower($e->getMessage()), 'denied') ? 403 : 404;

            http_response_code($code);

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        } catch (Throwable $e) {

            error_log('WarehouseProfile: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode(['status' => 'error', 'message' => 'Server error']);

        }

    }



    private function handleSettings(string $method, array $path): void

    {

        $sub = $path[2] ?? null;

        $uid = $this->userId();

        if ($uid <= 0) {

            http_response_code(401);

            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);

            return;

        }



        $warehouseId = $this->resolveWarehouseId();

        if ($warehouseId <= 0) {

            http_response_code(400);

            echo json_encode(['status' => 'error', 'message' => 'Warehouse ID required']);

            return;

        }



        $userName = $_SESSION['name'] ?? null;



        try {

            if ($method === 'GET' && $sub === 'audit') {

                $limit = min(100, max(1, (int) ($_GET['limit'] ?? 25)));

                $offset = max(0, (int) ($_GET['offset'] ?? 0));

                $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;

                $payload = $this->settings->auditLog($uid, $warehouseId, $search ?: null, $limit, $offset);

                echo json_encode(['status' => 'success', 'module_ready' => $this->settings->moduleReady(), ...$payload]);

                return;

            }



            if ($method === 'GET' && $sub === null) {

                echo json_encode([

                    'status' => 'success',

                    'module_ready' => $this->settings->moduleReady(),

                    'data' => $this->settings->load($uid, $warehouseId),

                ]);

                return;

            }



            if ($method === 'POST' && $sub === 'validate') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                $result = $this->settings->validateSettings($data);

                echo json_encode(['status' => 'success', ...$result]);

                return;

            }



            if ($method === 'POST' && $sub === 'reset') {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $section = isset($data['section']) ? trim((string) $data['section']) : null;

                $result = $this->settings->reset($uid, $warehouseId, $section ?: null, $userName);

                echo json_encode(['status' => 'success', 'message' => 'Settings reset', 'data' => $result]);

                return;

            }



            if ($method === 'POST' && $sub === null) {

                $data = json_decode(file_get_contents('php://input'), true) ?: [];

                if (!$this->verifyCsrf($data)) {

                    http_response_code(403);

                    echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                    return;

                }

                $result = $this->settings->save($uid, $warehouseId, $data, $userName);

                echo json_encode(['status' => 'success', 'message' => 'Settings saved', 'data' => $result]);

                return;

            }



            http_response_code(405);

            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

        } catch (InvalidArgumentException $e) {

            http_response_code(400);

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        } catch (RuntimeException $e) {

            $code = str_contains(strtolower($e->getMessage()), 'denied') ? 403 : 404;

            http_response_code($code);

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        } catch (Throwable $e) {

            error_log('WarehouseSettings: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode(['status' => 'error', 'message' => 'Server error']);

        }

    }



    private function handleHelp(string $method, array $path): void

    {

        $sub = $path[2] ?? null;

        $uid = $this->userId();

        if ($uid <= 0) {

            http_response_code(401);

            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);

            return;

        }



        $warehouseId = (int) ($_GET['warehouse_id'] ?? $_SESSION['warehouse_id'] ?? 0) ?: null;



        try {

            if ($method === 'GET' && $sub === 'search') {

                $q = trim((string) ($_GET['q'] ?? ''));

                echo json_encode([

                    'status' => 'success',

                    'module_ready' => $this->help->moduleReady(),

                    'data' => $this->help->search($q),

                ]);

                return;

            }



            if ($method === 'GET' && $sub === 'article') {

                $slug = trim((string) ($_GET['slug'] ?? ''));

                $article = $this->help->article($slug);

                if (!$article) {

                    http_response_code(404);

                    echo json_encode(['status' => 'error', 'message' => 'Article not found']);

                    return;

                }

                echo json_encode(['status' => 'success', 'data' => $article]);

                return;

            }



            if ($method === 'GET' && $sub === 'manual') {

                $slug = trim((string) ($_GET['slug'] ?? ''));

                $manual = $this->help->manualHtml($slug);

                if (!$manual) {

                    http_response_code(404);

                    echo json_encode(['status' => 'error', 'message' => 'Manual not found']);

                    return;

                }

                echo json_encode(['status' => 'success', 'data' => $manual]);

                return;

            }



            if ($method === 'GET' && $sub === null) {

                echo json_encode([

                    'status' => 'success',

                    'module_ready' => $this->help->moduleReady(),

                    'data' => $this->help->hub($uid, $warehouseId),

                ]);

                return;

            }



            if ($method === 'POST' && $sub === 'ticket') {

                if (!$this->verifyCsrf($_POST)) {

                    $data = json_decode(file_get_contents('php://input'), true) ?: [];

                    if (!$this->verifyCsrf($data)) {

                        http_response_code(403);

                        echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token']);

                        return;

                    }

                    $input = $data;

                } else {

                    $input = $_POST;

                }

                $file = $_FILES['attachment'] ?? null;

                $result = $this->help->createTicket($uid, $input, $file);

                echo json_encode(['status' => 'success', 'message' => 'Ticket created', 'data' => $result]);

                return;

            }



            http_response_code(405);

            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

        } catch (InvalidArgumentException $e) {

            http_response_code(400);

            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);

        } catch (Throwable $e) {

            error_log('WarehouseHelp: ' . $e->getMessage());

            http_response_code(500);

            echo json_encode(['status' => 'error', 'message' => 'Server error']);

        }

    }



    private function warehouseId(): ?int

    {

        $id = (int) ($_SESSION['warehouse_id'] ?? 0);

        if ($id > 0) {

            RbacGuard::assertWarehouseAccess($id);

            return $id;

        }

        $wh = (int) ($_GET['warehouse_id'] ?? 0);

        if ($wh > 0) {

            RbacGuard::assertWarehouseAccess($wh);

            return $wh;

        }

        return null;

    }



    private function handleNotifications(string $method, array $path): void

    {

        $sub = $path[2] ?? 'list';

        $uid = $this->userId();

        if ($uid <= 0) {

            http_response_code(401);

            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);

            return;

        }



        if (!$this->notifications->isReady()) {

            http_response_code(503);

            echo json_encode(['status' => 'error', 'message' => 'Run migration 010_notifications.sql']);

            return;

        }



        $warehouseId = $this->warehouseId();



        if ($method === 'GET') {

            switch ($sub) {

                case 'list':

                    $payload = $this->notifications->list($uid, $warehouseId, $_GET);

                    echo json_encode([

                        'status' => 'success',

                        'data' => $payload['items'],

                        'total' => $payload['total'],

                        'unread_count' => $payload['unread_count'],

                        'stats' => $payload['stats'],

                        'warehouse_id' => $payload['warehouse_id'],

                        'scope' => $payload['scope'],

                    ]);

                    break;



                case 'unread-count':

                    echo json_encode([

                        'status' => 'success',

                        'count' => $this->notifications->unreadCount($uid, $warehouseId),

                    ]);

                    break;



                case 'meta':

                    echo json_encode(['status' => 'success', 'data' => $this->notifications->meta()]);

                    break;



                default:

                    http_response_code(404);

                    echo json_encode(['status' => 'error', 'message' => 'Not found']);

            }

            return;

        }



        if ($method === 'POST') {

            $raw = file_get_contents('php://input');

            $data = json_decode($raw ?: '{}', true);

            $data = is_array($data) ? $data : [];



            switch ($sub) {

                case 'mark-read':

                    $ids = array_map('intval', $data['ids'] ?? []);

                    echo json_encode(['status' => 'success', 'updated' => $this->notifications->markRead($uid, $ids)]);

                    break;



                case 'mark-all-read':

                    echo json_encode(['status' => 'success', 'updated' => $this->notifications->markAllRead($uid, $warehouseId)]);

                    break;



                case 'archive':

                    $ids = array_map('intval', $data['ids'] ?? []);

                    $archive = ($data['archive'] ?? true) !== false;

                    echo json_encode(['status' => 'success', 'updated' => $this->notifications->archive($uid, $ids, $archive)]);

                    break;



                case 'pin':

                    $id = (int) ($data['id'] ?? 0);

                    $pinned = !empty($data['pinned']);

                    echo json_encode(['status' => 'success', 'ok' => $this->notifications->pin($uid, $id, $pinned)]);

                    break;



                default:

                    http_response_code(404);

                    echo json_encode(['status' => 'error', 'message' => 'Not found']);

            }

            return;

        }



        http_response_code(405);

        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);

    }



    private function handleGet(string $action): void

    {

        $storeId = StoreScope::activeStoreId();

        $warehouseId = $this->warehouseId();

        $userId = $this->userId();



        switch ($action) {

            case 'dashboard':

                $period = (string) ($_GET['period'] ?? 'week');

                $from = trim((string) ($_GET['from'] ?? ''));

                $to = trim((string) ($_GET['to'] ?? ''));

                $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;

                $to = preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;

                echo json_encode([

                    'status' => 'success',

                    'data' => $this->portal->portalDashboard($storeId, $warehouseId, $userId, $period, $from, $to),

                    'module_ready' => WmsSchema::ready(),

                ]);

                break;

            case 'calendar':

                $year = (int) ($_GET['year'] ?? date('Y'));

                $month = (int) ($_GET['month'] ?? date('n'));

                $types = isset($_GET['types']) ? array_filter(explode(',', (string) $_GET['types'])) : [];

                if ($warehouseId) {

                    (new WarehouseTaskRepository())->seedDailyTasks($warehouseId);

                }

                echo json_encode([

                    'status' => 'success',

                    'data' => $this->calendar->month($warehouseId, $storeId, $year, $month, $types),

                    'module_ready' => WmsSchema::ready(),

                ]);

                break;

            case 'search':

                echo json_encode([

                    'status' => 'success',

                    'data' => $this->portal->globalSearch($warehouseId, (string) ($_GET['q'] ?? '')),

                ]);

                break;

            case 'permissions':

                echo json_encode([

                    'status' => 'success',

                    'data' => [

                        'role' => WarehousePortalAuth::roleSlug(),

                        'manage' => WarehousePortalAuth::canManage(),

                        'receive' => WarehousePortalAuth::canReceive(),

                        'dispatch' => WarehousePortalAuth::canDispatch(),

                        'inventory' => WarehousePortalAuth::canInventory(),

                        'transfer' => WarehousePortalAuth::canTransfer(),

                        'reports' => WarehousePortalAuth::canReports(),

                        'read_only' => WarehousePortalAuth::isReadOnly(),

                    ],

                ]);

                break;

            case 'module':

                $module = $_GET['module'] ?? '';

                $endpoint = $_GET['endpoint'] ?? '';

                echo json_encode($this->proxyModule($module, $endpoint, $storeId, $warehouseId));

                break;

            default:

                http_response_code(404);

                echo json_encode(['status' => 'error', 'message' => 'Not found']);

        }

    }



    private function proxyModule(string $module, string $endpoint, ?int $storeId, ?int $warehouseId): array

    {

        if (!WarehousePortalAuth::canModule($module)) {

            return ['status' => 'error', 'message' => 'Module access denied'];

        }

        $wh = $warehouseId ?: (int) ($_GET['warehouse_id'] ?? 0);

        return match ($endpoint) {

            'inventory' => ['status' => 'success', 'data' => $this->wms->listInventory($wh, $_GET['q'] ?? null, $_GET['filter'] ?? null)],

            'receipts' => ['status' => 'success', 'data' => $this->wms->listReceipts($wh, $_GET['status'] ?? null)],

            'dispatches' => ['status' => 'success', 'data' => $this->wms->listDispatches($wh, $_GET['status'] ?? null, $_GET['q'] ?? null)],

            'transfers' => ['status' => 'success', 'data' => $this->wms->listTransfers($_GET['status'] ?? null, $wh ?: null, $_GET['q'] ?? null)],

            'movements' => ['status' => 'success', 'data' => $this->wms->listMovements($wh ?: null, [

                'movement_type' => $_GET['type'] ?? 'all',

                'from' => $_GET['from'] ?? null,

                'to' => $_GET['to'] ?? null,

                'q' => $_GET['q'] ?? null,

            ])],

            'batches' => ['status' => 'success', 'data' => $this->wms->listBatches($wh ?: null, $_GET['status'] ?? null, $_GET['q'] ?? null, (int) ($_GET['days'] ?? 30))],

            default => ['status' => 'error', 'message' => 'Unknown endpoint'],

        };

    }

}



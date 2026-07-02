<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Repositories/WebhookRepository.php';
require_once __DIR__ . '/../Platform/Services/WebhookDispatcherService.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

final class WebhookController
{
    private PDO $db;
    private WebhookRepository $webhooks;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase6Migrator::ensure($this->db);
        $this->webhooks = new WebhookRepository($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'GET' && $action === 'events') {
            echo json_encode(['status' => 'success', 'data' => WebhookDispatcherService::SAAS_EVENTS]);
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'super_admin']);
        $tenantId = TenantScope::id();

        if (!$this->hasApiAccess($tenantId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Webhooks require API access (Enterprise plan)']);
            return;
        }

        if ($action === 'endpoints' || $action === '') {
            $this->handleEndpoints($method, $path, $tenantId);
            return;
        }
        if ($action === 'deliveries' && $method === 'GET') {
            echo json_encode(['status' => 'success', 'data' => $this->webhooks->listDeliveries($tenantId)]);
            return;
        }
        if ($action === 'test' && $method === 'POST') {
            $this->sendTest($tenantId);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleEndpoints(string $method, array $path, int $tenantId): void
    {
        $id = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;

        if ($method === 'GET' && $id <= 0) {
            echo json_encode(['status' => 'success', 'data' => $this->webhooks->listEndpoints($tenantId)]);
            return;
        }
        if ($method === 'POST' && $id <= 0) {
            $this->createEndpoint($tenantId);
            return;
        }
        if ($method === 'PUT' && $id > 0) {
            $this->updateEndpoint($tenantId, $id);
            return;
        }
        if ($method === 'DELETE' && $id > 0) {
            $ok = $this->webhooks->deleteEndpoint($tenantId, $id);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function createEndpoint(int $tenantId): void
    {
        $data = $this->jsonBody();
        $url = filter_var(trim($data['url'] ?? ''), FILTER_VALIDATE_URL);
        $events = is_array($data['events'] ?? null) ? $data['events'] : [];
        if (!$url || !$events) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'url and events required']);
            return;
        }
        $created = $this->webhooks->createEndpoint($tenantId, $url, $events, $data['description'] ?? null);
        echo json_encode(['status' => 'success', 'data' => $created]);
    }

    private function updateEndpoint(int $tenantId, int $id): void
    {
        $data = $this->jsonBody();
        $ok = $this->webhooks->updateEndpoint($tenantId, $id, $data);
        echo json_encode(['status' => $ok ? 'success' : 'error']);
    }

    private function sendTest(int $tenantId): void
    {
        $count = WebhookDispatcherService::dispatch($this->db, $tenantId, 'tenant.provisioned', [
            'test' => true,
            'tenant_id' => $tenantId,
            'message' => 'Webhook test event',
        ]);
        echo json_encode(['status' => 'success', 'queued' => $count]);
    }

    private function hasApiAccess(int $tenantId): bool
    {
        if ($tenantId === 1) {
            return true;
        }
        $ent = new EntitlementService($this->db, new SubscriptionRepository($this->db));
        return $ent->hasModule($tenantId, 'api_access');
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : ($_POST ?: []);
    }
}

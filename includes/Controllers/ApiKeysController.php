<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase7Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Services/ApiKeyService.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Repositories/ApiKeyRepository.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

final class ApiKeysController
{
    private PDO $db;
    private ApiKeyService $keys;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase7Migrator::ensure($this->db);
        $this->keys = new ApiKeyService($this->db, new ApiKeyRepository($this->db));
    }

    public function handleRequest(string $method, array $path): void
    {
        if ($method === 'GET' && ($path[1] ?? '') === 'scopes') {
            echo json_encode(['status' => 'success', 'data' => ApiKeyService::SCOPES]);
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'super_admin']);
        TenantScope::loadFromSession($this->db);
        $tenantId = TenantScope::id();

        if (!$this->hasApiAccess($tenantId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'API keys require API access (Business+ plan)']);
            return;
        }

        $id = isset($path[1]) && ctype_digit((string) $path[1]) ? (int) $path[1] : 0;

        if ($method === 'GET' && $id <= 0) {
            echo json_encode(['status' => 'success', 'data' => $this->keys->list($tenantId)]);
            return;
        }
        if ($method === 'POST' && $id <= 0) {
            $this->create($tenantId);
            return;
        }
        if ($method === 'DELETE' && $id > 0) {
            $ok = $this->keys->revoke($tenantId, $id);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function create(int $tenantId): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        $name = trim((string) ($data['name'] ?? 'API Key'));
        $scopes = is_array($data['scopes'] ?? null) ? $data['scopes'] : ['tenant:read'];

        try {
            $created = $this->keys->create($tenantId, $name, $scopes, (int) ($_SESSION['user_id'] ?? 0) ?: null);
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'id' => $created['id'],
                    'prefix' => $created['prefix'],
                    'scopes' => $created['scopes'],
                    'raw_key' => $created['raw_key'],
                ],
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function hasApiAccess(int $tenantId): bool
    {
        if ($tenantId === 1) {
            return true;
        }
        $ent = new EntitlementService($this->db, new SubscriptionRepository($this->db));
        return $ent->hasModule($tenantId, 'api_access');
    }
}

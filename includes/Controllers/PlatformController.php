<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase3Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../Platform/PlatformSessionAuth.php';
require_once __DIR__ . '/../Platform/Repositories/TenantRepository.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/../Platform/Services/PlatformDashboardService.php';
require_once __DIR__ . '/../Platform/Services/PlatformTenantService.php';
require_once __DIR__ . '/../Platform/Services/PlatformImpersonationService.php';
require_once __DIR__ . '/../Platform/Services/UsageMeteringService.php';
require_once __DIR__ . '/../Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/PlatformStatusService.php';
require_once __DIR__ . '/../Platform/Services/WebhookDispatcherService.php';

final class PlatformController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase2Migrator::ensure($this->db);
        SaaSPhase3Migrator::ensure($this->db);
        SaaSPhase4Migrator::ensure($this->db);
        SaaSPhase5Migrator::ensure($this->db);
        SaaSPhase6Migrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $this->login();
            return;
        }
        if ($method === 'POST' && $action === 'logout') {
            $this->logout();
            return;
        }

        if (!PlatformSessionAuth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        if ($method === 'GET' && ($action === '' || $action === 'dashboard')) {
            $this->dashboard();
            return;
        }
        if ($method === 'GET' && $action === 'plans') {
            $this->plans();
            return;
        }
        if ($action === 'tenants') {
            $this->handleTenants($method, $path);
            return;
        }
        if ($action === 'incidents') {
            $this->handleIncidents($method, $path);
            return;
        }
        if ($method === 'GET' && $action === 'status') {
            $svc = new PlatformStatusService($this->db);
            echo json_encode(['status' => 'success', 'data' => $svc->getPublicStatus()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleTenants(string $method, array $path): void
    {
        $tenantId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && $tenantId <= 0) {
            $this->tenants();
            return;
        }
        if ($method === 'GET' && $tenantId > 0 && $subAction === '') {
            $this->tenantDetail($tenantId);
            return;
        }

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tenant id required']);
            return;
        }

        $body = $this->jsonBody();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $platformUserId = PlatformSessionAuth::userId();
        $service = $this->tenantService();
        $impersonation = new PlatformImpersonationService($this->db, new PlatformAuditRepository($this->db));

        try {
            if ($method === 'POST' && $subAction === 'status') {
                $status = trim((string) ($body['status'] ?? ''));
                $service->updateStatus($tenantId, $status, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Status updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'trial') {
                $days = (int) ($body['days'] ?? 14);
                $newEnd = $service->extendTrial($tenantId, $days, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'trial_ends_at' => $newEnd]);
                return;
            }
            if ($method === 'POST' && $subAction === 'plan') {
                $planCode = trim((string) ($body['plan_code'] ?? ''));
                $service->changePlan($tenantId, $planCode, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Plan updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'modules') {
                $overrides = is_array($body['overrides'] ?? null) ? $body['overrides'] : [];
                $parsed = [];
                foreach ($overrides as $k => $v) {
                    if ($v === 'inherit' || $v === null) {
                        $parsed[$k] = null;
                    } else {
                        $parsed[$k] = (bool) $v;
                    }
                }
                $service->setModuleOverrides($tenantId, $parsed, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Modules updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'feature-flags') {
                $flags = is_array($body['flags'] ?? null) ? $body['flags'] : [];
                $parsed = [];
                foreach ($flags as $k => $v) {
                    $parsed[$k] = (bool) $v;
                }
                $service->setFeatureFlags($tenantId, $parsed, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Feature flags updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'impersonate') {
                $result = $impersonation->impersonate($tenantId, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'data' => $result]);
                return;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function login(): void
    {
        $data = $this->jsonBody();
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($data['password'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, name, role, is_active
             FROM platform_users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) ($user['is_active'] ?? 0) || !password_verify($password, $user['password_hash'])) {
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        PlatformSessionAuth::establish($user);
        $this->db->prepare('UPDATE platform_users SET last_login = NOW() WHERE id = ?')
            ->execute([(int) $user['id']]);

        echo json_encode([
            'status' => 'success',
            'redirect' => 'index.php',
            'user' => [
                'name' => $user['name'],
                'role' => $user['role'],
            ],
        ]);
    }

    private function logout(): void
    {
        PlatformSessionAuth::clear();
        echo json_encode(['status' => 'success']);
    }

    private function dashboard(): void
    {
        $service = new PlatformDashboardService($this->db, new TenantRepository($this->db));
        $summary = $service->summary();
        $summary['schema_version'] = SaaSPhase3Migrator::VERSION;
        echo json_encode(['status' => 'success', 'data' => $summary]);
    }

    private function plans(): void
    {
        $repo = new SubscriptionRepository($this->db);
        echo json_encode(['status' => 'success', 'data' => $repo->listActivePlans()]);
    }

    private function tenants(): void
    {
        $repo = new TenantRepository($this->db);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;

        echo json_encode([
            'status' => 'success',
            'data' => $repo->listTenants($perPage, $offset, $search ?: null, $status ?: null),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'q' => $search, 'status' => $status],
        ]);
    }

    private function tenantDetail(int $tenantId): void
    {
        $detail = $this->tenantService()->getDetail($tenantId);
        if (!$detail) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tenant not found']);
            return;
        }
        echo json_encode(['status' => 'success', 'data' => $detail]);
    }

    private function handleIncidents(string $method, array $path): void
    {
        $statusSvc = new PlatformStatusService($this->db);
        $id = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && ($path[2] ?? '') === 'components') {
            echo json_encode(['status' => 'success', 'data' => $statusSvc->listComponents()]);
            return;
        }

        if ($method === 'PUT' && ($path[2] ?? '') === 'components' && isset($path[3])) {
            $body = $this->jsonBody();
            $statusSvc->updateComponentStatus((string) $path[3], trim((string) ($body['status'] ?? 'operational')));
            echo json_encode(['status' => 'success']);
            return;
        }

        if ($method === 'GET' && $id <= 0) {
            echo json_encode(['status' => 'success', 'data' => $statusSvc->listRecentIncidents()]);
            return;
        }

        if ($method === 'POST' && $id <= 0) {
            $body = $this->jsonBody();
            $incidentId = $statusSvc->createIncident($body, PlatformSessionAuth::userId());
            echo json_encode(['status' => 'success', 'id' => $incidentId]);
            return;
        }

        if ($method === 'POST' && $id > 0 && $subAction === 'resolve') {
            $ok = $statusSvc->resolveIncident($id);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function tenantService(): PlatformTenantService
    {
        $subs = new SubscriptionRepository($this->db);
        $ent = new EntitlementService($this->db, $subs);
        return new PlatformTenantService(
            $this->db,
            new TenantRepository($this->db),
            $subs,
            new PlatformAuditRepository($this->db),
            $ent,
            new UsageMeteringService($this->db, new UsageMeteringRepository($this->db), $ent),
        );
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return $_POST ?: [];
    }
}

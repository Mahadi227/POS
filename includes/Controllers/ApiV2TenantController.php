<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/UsageMeteringService.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';

final class ApiV2TenantController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $resource = $path[0] ?? '';

        if ($method === 'GET' && $resource === 'me') {
            ApiV2Auth::authenticate($this->db);
            $this->me();
            return;
        }

        ApiV2Auth::requireScope($this->db, 'tenant:read');

        if ($method === 'GET' && $resource === 'tenant') {
            $this->tenant();
            return;
        }
        if ($method === 'GET' && $resource === 'usage') {
            $this->usage();
            return;
        }
        if ($method === 'GET' && $resource === 'subscription') {
            $this->subscription();
            return;
        }

        ApiProblem::notFound();
    }

    private function me(): void
    {
        $ctx = ApiV2Auth::authenticate($this->db);
        ApiV2Auth::jsonSuccess([
            'user_id' => $ctx['user_id'],
            'tenant_id' => $ctx['tenant_id'],
            'role' => $ctx['role'],
            'email' => $ctx['email'],
            'permissions' => $ctx['permissions'],
            'scopes' => $ctx['scopes'],
            'store_id' => $ctx['store_id'],
            'auth_type' => $ctx['auth_type'],
        ]);
    }

    private function tenant(): void
    {
        $tenantId = TenantScope::id();
        $stmt = $this->db->prepare(
            'SELECT id, uuid, slug, name, status, trial_ends_at, created_at FROM tenants WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ApiProblem::notFound('Tenant not found');
            return;
        }
        ApiV2Auth::jsonSuccess($row);
    }

    private function usage(): void
    {
        $tenantId = TenantScope::id();
        $svc = new UsageMeteringService(
            $this->db,
            new UsageMeteringRepository($this->db),
            new EntitlementService($this->db, new SubscriptionRepository($this->db)),
        );
        ApiV2Auth::jsonSuccess($svc->getReport($tenantId));
    }

    private function subscription(): void
    {
        $tenantId = TenantScope::id();
        $ent = new EntitlementService($this->db, new SubscriptionRepository($this->db));
        ApiV2Auth::jsonSuccess($ent->getSubscriptionSummary($tenantId));
    }
}

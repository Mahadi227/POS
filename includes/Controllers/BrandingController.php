<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/TenantDomainRepository.php';
require_once __DIR__ . '/../Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/TenantBrandingService.php';
require_once __DIR__ . '/../Platform/Services/UsageMeteringService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

final class BrandingController
{
    private PDO $db;
    private TenantBrandingService $branding;
    private UsageMeteringService $metering;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase2Migrator::ensure($this->db);
        SaaSPhase4Migrator::ensure($this->db);

        $subs = new SubscriptionRepository($this->db);
        $ent = new EntitlementService($this->db, $subs);
        $this->branding = new TenantBrandingService($this->db, $ent, new TenantDomainRepository($this->db));
        $this->metering = new UsageMeteringService($this->db, new UsageMeteringRepository($this->db), $ent);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'GET' && $action === 'public') {
            $this->publicBranding();
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'super_admin']);
        $tenantId = TenantScope::id();

        switch ($action) {
            case '':
            case 'settings':
                if ($method === 'GET') {
                    $this->settings($tenantId);
                } elseif ($method === 'PUT' || $method === 'POST') {
                    $this->saveSettings($tenantId);
                } else {
                    $this->notFound();
                }
                break;
            case 'logo':
                if ($method === 'POST') {
                    $this->upload($tenantId, 'logo');
                } else {
                    $this->notFound();
                }
                break;
            case 'favicon':
                if ($method === 'POST') {
                    $this->upload($tenantId, 'favicon');
                } else {
                    $this->notFound();
                }
                break;
            case 'usage':
                if ($method === 'GET') {
                    $this->usage($tenantId);
                } else {
                    $this->notFound();
                }
                break;
            default:
                $this->notFound();
        }
    }

    private function publicBranding(): void
    {
        $slug = trim((string) ($_GET['tenant'] ?? $_GET['slug'] ?? ''));
        if ($slug === '') {
            echo json_encode(['status' => 'success', 'data' => $this->branding->getBranding(1)]);
            return;
        }
        $tenant = TenantScope::resolveBySlug($this->db, $slug);
        if (!$tenant) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tenant not found']);
            return;
        }
        echo json_encode([
            'status' => 'success',
            'data' => $this->branding->getBranding((int) $tenant['id']),
        ]);
    }

    private function settings(int $tenantId): void
    {
        echo json_encode(['status' => 'success', 'data' => $this->branding->getBranding($tenantId)]);
    }

    private function saveSettings(int $tenantId): void
    {
        try {
            $data = $this->jsonBody();
            $saved = $this->branding->saveSettings($tenantId, $data);
            echo json_encode(['status' => 'success', 'data' => $saved]);
        } catch (RuntimeException $e) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function upload(int $tenantId, string $type): void
    {
        $field = $type === 'favicon' ? 'favicon' : 'logo';
        if (empty($_FILES[$field])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'No file uploaded']);
            return;
        }
        try {
            $data = $this->branding->uploadLogo($tenantId, $_FILES[$field], $type);
            echo json_encode(['status' => 'success', 'data' => $data]);
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function usage(int $tenantId): void
    {
        $this->metering->syncTenant($tenantId);
        echo json_encode(['status' => 'success', 'data' => $this->metering->getReport($tenantId)]);
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

    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
}

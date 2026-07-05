<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/SaaSPhase15Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase16Migrator.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Ecommerce/Repositories/EcommerceAdminRepository.php';
require_once __DIR__ . '/../Ecommerce/Repositories/EcommerceCatalogRepository.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommerceOrderService.php';
require_once __DIR__ . '/../Ecommerce/Services/EcommercePaystackService.php';

final class EcommerceAdminController
{
    private PDO $db;
    private EcommerceAdminRepository $repo;
    private EcommerceCatalogRepository $catalog;
    private EcommerceOrderService $orders;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        SaaSPhase15Migrator::ensure($this->db);
        SaaSPhase16Migrator::ensure($this->db);
        $this->repo = new EcommerceAdminRepository($this->db);
        $this->catalog = new EcommerceCatalogRepository($this->db);
        $this->orders = new EcommerceOrderService($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        header('Content-Type: application/json; charset=utf-8');

        if (!$this->canAccess()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        $tenantId = TenantScope::id();
        if (!$this->hasEcommerce($tenantId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'code' => 'ecommerce_not_enabled', 'message' => 'E-commerce module not enabled']);
            return;
        }

        $storeId = $this->resolveStoreId($tenantId);
        $action = $path[1] ?? 'dashboard';
        $sub = $path[2] ?? null;
        $id = isset($path[3]) && is_numeric($path[3]) ? (int) $path[3] : (isset($path[2]) && is_numeric($path[2]) ? (int) $path[2] : null);

        try {
            if ($method === 'GET') {
                $this->handleGet($action, $sub, $id, $tenantId, $storeId);
                return;
            }
            if ($method === 'POST') {
                $this->handlePost($action, $sub, $id, $tenantId, $storeId);
                return;
            }
            if ($method === 'DELETE') {
                $this->handleDelete($action, $id, $tenantId);
                return;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Server error']);
            error_log('EcommerceAdminController: ' . $e->getMessage());
            return;
        }

        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function handleGet(string $action, ?string $sub, ?int $id, int $tenantId, int $storeId): void
    {
        match ($action) {
            'dashboard' => $this->ok($this->repo->dashboardStats($tenantId, $storeId)),
            'products' => $this->ok($this->repo->listProducts($storeId, [
                'q' => trim($_GET['q'] ?? ''),
                'online' => $_GET['online'] ?? '',
            ], min(100, max(1, (int) ($_GET['limit'] ?? 50))), max(0, (int) ($_GET['offset'] ?? 0)))),
            'orders' => $id
                ? (($order = $this->repo->getWebOrder($id, $tenantId, $storeId))
                    ? $this->ok(['order' => $order])
                    : $this->notFound())
                : $this->ok($this->repo->listWebOrders($tenantId, $storeId, min(100, max(1, (int) ($_GET['limit'] ?? 30))), max(0, (int) ($_GET['offset'] ?? 0)))),
            'brands' => $this->ok(['items' => $this->repo->listBrands($tenantId)]),
            'blog' => $this->ok(['items' => $this->repo->listBlogPosts($tenantId)]),
            'customers' => $id
                ? (($customer = $this->repo->getStorefrontAccount($tenantId, $id))
                    ? $this->ok(['customer' => $customer])
                    : $this->notFound())
                : $this->ok(['items' => $this->repo->listStorefrontAccounts($tenantId)]),
            'settings' => $this->ok([
                'settings' => $this->repo->getSettingsForApi($tenantId),
                'stores' => $this->repo->listStores($tenantId),
                'paystack_currencies' => EcommercePaystackService::supportedCurrencies(),
            ]),
            default => $this->notFound(),
        };
    }

    private function handlePost(string $action, ?string $sub, ?int $id, int $tenantId, int $storeId): void
    {
        $data = $this->body();

        if ($action === 'products' && $sub === 'toggle-online' && $id) {
            $online = !empty($data['online']);
            if (!$this->repo->setProductOnline($id, $storeId, $online)) {
                $this->notFound();
                return;
            }
            $this->ok(['ok' => true, 'online' => $online]);
            return;
        }

        if ($action === 'products' && $sub === 'slug' && $id) {
            $slug = EcommerceAdminRepository::slugify((string) ($data['slug'] ?? ''));
            if (!$this->repo->updateProductSlug($id, $storeId, $slug)) {
                $this->notFound();
                return;
            }
            $this->ok(['ok' => true, 'slug' => $slug]);
            return;
        }

        if ($action === 'brands') {
            $brandId = $id ?: (isset($data['id']) ? (int) $data['id'] : null);
            $savedId = $this->repo->saveBrand($tenantId, $data, $brandId ?: null);
            $this->ok(['ok' => true, 'id' => $savedId]);
            return;
        }

        if ($action === 'blog') {
            $postId = $id ?: (isset($data['id']) ? (int) $data['id'] : null);
            $savedId = $this->repo->saveBlogPost($tenantId, $data, $postId ?: null);
            $this->ok(['ok' => true, 'id' => $savedId]);
            return;
        }

        if ($action === 'customers') {
            $this->requireManageEcom();
            $customerId = $id ?: (isset($data['id']) ? (int) $data['id'] : null);
            $savedId = $this->repo->saveStorefrontAccount($tenantId, $data, $customerId ?: null);
            $this->ok(['ok' => true, 'id' => $savedId]);
            return;
        }

        if ($action === 'settings') {
            $this->repo->saveSettings($tenantId, $data);
            $this->ok(['ok' => true, 'settings' => $this->repo->getSettingsForApi($tenantId)]);
            return;
        }

        if ($action === 'orders' && $sub === 'accept' && $id) {
            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId <= 0) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Access denied']);
                return;
            }
            $result = $this->orders->acceptOrder($id, $tenantId, $storeId, $userId);
            $this->ok($result);
            return;
        }

        $this->notFound();
    }

    private function handleDelete(string $action, ?int $id, int $tenantId): void
    {
        if (!$id) {
            $this->notFound();
            return;
        }

        $ok = match ($action) {
            'brands' => $this->repo->deleteBrand($tenantId, $id),
            'blog' => $this->repo->deleteBlogPost($tenantId, $id),
            'customers' => $this->canManageEcom() && $this->repo->deleteStorefrontAccount($tenantId, $id),
            default => false,
        };

        if (!$ok) {
            $this->notFound();
            return;
        }
        $this->ok(['ok' => true]);
    }

    private function canAccess(): bool
    {
        $role = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
        return in_array($role, ['super_admin', 'admin', 'manager'], true);
    }

    private function canManageEcom(): bool
    {
        $role = strtolower(str_replace(' ', '_', $_SESSION['role_slug'] ?? $_SESSION['role'] ?? ''));
        return in_array($role, ['super_admin', 'admin'], true);
    }

    private function requireManageEcom(): void
    {
        if (!$this->canManageEcom()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            exit;
        }
    }

    private function hasEcommerce(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return true;
        }
        try {
            $svc = new EntitlementService($this->db, new SubscriptionRepository($this->db));
            return $svc->hasModule($tenantId, 'ecommerce');
        } catch (Throwable) {
            return true;
        }
    }

    private function resolveStoreId(int $tenantId): int
    {
        return $this->repo->resolveStoreId($tenantId);
    }

    /** @return array<string, mixed> */
    private function body(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return $_POST;
    }

    /** @param array<string, mixed> $data */
    private function ok(array $data): void
    {
        echo json_encode(array_merge(['status' => 'ok'], $data), JSON_UNESCAPED_UNICODE);
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
}

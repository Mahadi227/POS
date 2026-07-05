<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Services/TenantProvisioningService.php';
require_once __DIR__ . '/../Platform/Services/EmailVerificationService.php';
require_once __DIR__ . '/../Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../Platform/Services/WebhookDispatcherService.php';
require_once __DIR__ . '/../Platform/Services/TransactionalEmailService.php';
require_once __DIR__ . '/../Auth/SessionAuth.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Platform/TenantScope.php';

final class TenantSignupController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase2Migrator::ensure($this->db);
        SaaSPhase5Migrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? 'register';

        if ($method === 'GET' && $action === 'plans') {
            $this->plans();
            return;
        }
        if ($method === 'POST' && ($action === 'register' || $action === '')) {
            $this->register();
            return;
        }
        if ($method === 'GET' && $action === 'check-slug') {
            $this->checkSlug();
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function plans(): void
    {
        $repo = new SubscriptionRepository($this->db);
        $plans = array_map(static function (array $p) {
            $p['modules'] = json_decode($p['modules_json'] ?? '{}', true) ?: [];
            unset($p['modules_json']);
            return $p;
        }, $repo->listActivePlans());

        echo json_encode(['status' => 'success', 'data' => $plans]);
    }

    private function checkSlug(): void
    {
        $slug = trim($_GET['slug'] ?? '');
        $slug = strtolower(preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '');
        $slug = trim($slug, '-');
        if ($slug === '') {
            echo json_encode(['status' => 'error', 'available' => false]);
            return;
        }
        $stmt = $this->db->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        echo json_encode(['status' => 'success', 'available' => !$stmt->fetchColumn(), 'slug' => $slug]);
    }

    private function register(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;

        if (!empty($data['csrf_token']) && function_exists('verify_csrf_token') && !verify_csrf_token($data['csrf_token'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Invalid CSRF token.']);
            return;
        }

        $password = (string) ($data['password'] ?? '');
        $passwordConfirm = (string) ($data['password_confirm'] ?? $data['password_confirmation'] ?? '');
        if ($passwordConfirm !== '' && $password !== $passwordConfirm) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $this->passwordMismatchMessage()]);
            return;
        }

        try {
            $service = new TenantProvisioningService($this->db, new SubscriptionRepository($this->db));
            $result = $service->provision([
                'org_name' => $data['org_name'] ?? '',
                'slug' => $data['slug'] ?? '',
                'admin_name' => $data['admin_name'] ?? $data['name'] ?? '',
                'admin_email' => $data['admin_email'] ?? $data['email'] ?? '',
                'password' => $data['password'] ?? '',
                'plan_code' => $data['plan_code'] ?? 'starter',
                'country_code' => $data['country_code'] ?? 'SN',
                'currency' => $data['currency'] ?? 'XOF',
                'store_name' => $data['store_name'] ?? null,
            ]);

            $tenantRow = TenantScope::resolveBySlug($this->db, $result['slug']);
            if ($tenantRow) {
                TenantScope::set((int) $tenantRow['id'], $tenantRow);
            }

            $stmt = $this->db->prepare(
                'SELECT u.id, u.name, u.full_name, u.email, u.store_id, u.branch_id, u.warehouse_id, u.language, u.role_id,
                        r.name AS role_name'
                . ($this->hasColumn('users', 'tenant_id') ? ', u.tenant_id' : '') .
                ' FROM users u INNER JOIN roles r ON r.id = u.role_id WHERE u.id = ? LIMIT 1'
            );
            $stmt->execute([$result['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $permissions = (new PermissionService($this->db))->loadForUser((int) $user['id'], (int) $user['role_id']);
                SessionAuth::establish($user, $permissions);
                if ($tenantRow) {
                    TenantScope::set((int) $tenantRow['id'], $tenantRow);
                }

                $this->initOnboarding((int) $result['tenant_id']);
                $lang = $_SESSION['lang'] ?? 'en';
                (new EmailVerificationService($this->db))->createAndSend(
                    (int) $user['id'],
                    $user['email'],
                    $user['name'] ?? '',
                    $lang,
                );

                SaaSPhase6Migrator::ensure($this->db);
                WebhookDispatcherService::dispatch($this->db, (int) $result['tenant_id'], 'tenant.provisioned', [
                    'tenant_id' => (int) $result['tenant_id'],
                    'slug' => $result['slug'],
                    'plan_code' => $result['plan_code'],
                    'trial_ends_at' => $result['trial_ends_at'],
                    'store_id' => (int) $result['store_id'],
                ]);
                (new TransactionalEmailService($this->db))->sendWelcome(
                    (int) $result['tenant_id'],
                    (int) $result['user_id'],
                );
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Organization created successfully.',
                'tenant' => [
                    'id' => $result['tenant_id'],
                    'slug' => $result['slug'],
                    'trial_ends_at' => $result['trial_ends_at'],
                    'plan_code' => $result['plan_code'],
                ],
                'redirect' => 'verify-email.php',
            ]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'status' => 'error',
                'message' => defined('APP_DEBUG') && APP_DEBUG ? $e->getMessage() : 'Registration failed.',
            ]);
        }
    }

    private function passwordMismatchMessage(): string
    {
        $lang = $_SESSION['lang'] ?? 'en';

        return $lang === 'fr'
            ? 'Les mots de passe ne correspondent pas.'
            : 'Passwords do not match.';
    }

    private function initOnboarding(int $tenantId): void
    {
        if (!$this->tableExists('tenant_onboarding')) {
            return;
        }
        $this->db->prepare('DELETE FROM tenant_onboarding WHERE tenant_id = ?')->execute([$tenantId]);
        $this->db->prepare('INSERT INTO tenant_onboarding (tenant_id, current_step) VALUES (?, 1)')->execute([$tenantId]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

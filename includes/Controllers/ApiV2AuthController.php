<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Auth/JwtService.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Api/ApiProblem.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';

final class ApiV2AuthController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'POST' && $action === 'token') {
            $this->token();
            return;
        }
        if ($method === 'POST' && $action === 'refresh') {
            $this->refresh();
            return;
        }
        if ($method === 'GET' && $action === 'me') {
            $this->legacyMe();
            return;
        }

        ApiProblem::notFound();
    }

    private function legacyMe(): void
    {
        $ctx = ApiV2Auth::authenticate($this->db);
        ApiV2Auth::jsonSuccess([
            'user_id' => $ctx['user_id'],
            'tenant_id' => $ctx['tenant_id'],
            'role' => $ctx['role'],
            'email' => $ctx['email'],
            'permissions' => $ctx['permissions'],
            'store_id' => $ctx['store_id'],
        ]);
    }

    private function token(): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($data['password'] ?? '');
        $tenantSlug = trim($data['tenant_slug'] ?? $data['tenant'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            ApiProblem::send(422, 'Validation Error', 'Invalid email or password');
            return;
        }

        $tenant = null;
        if (TenantScope::isReady($this->db)) {
            if ($tenantSlug !== '') {
                $tenant = TenantScope::resolveBySlug($this->db, $tenantSlug);
            } else {
                $tenant = TenantScope::resolveDefault($this->db);
            }
            if (!$tenant) {
                ApiProblem::notFound('Tenant not found');
                return;
            }
            if (($tenant['status'] ?? '') === 'suspended') {
                ApiProblem::forbidden('Tenant suspended');
                return;
            }
        }

        $sql = '
            SELECT u.id, u.name, u.full_name, u.email, u.password_hash, u.is_active, u.status,
                   u.store_id, u.branch_id, u.warehouse_id, u.role_id,
                   r.name AS role_name';
        if (TenantScope::isReady($this->db) && $this->hasColumn('users', 'tenant_id')) {
            $sql .= ', u.tenant_id';
        }
        $sql .= '
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.deleted_at IS NULL';
        $params = [$email];
        if ($tenant && $this->hasColumn('users', 'tenant_id')) {
            $sql .= ' AND u.tenant_id = ?';
            $params[] = (int) $tenant['id'];
        }
        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            ApiProblem::unauthorized('Invalid credentials');
            return;
        }

        $active = ($user['status'] ?? 'active') === 'active'
            || ((int) ($user['is_active'] ?? 0) === 1 && ($user['status'] ?? '') !== 'inactive');
        if (!$active) {
            ApiProblem::forbidden('Account inactive');
            return;
        }

        $permissions = (new PermissionService($this->db))->loadForUser((int) $user['id'], (int) $user['role_id']);
        $roleSlug = RoleRedirect::slug($user['role_name'] ?? '');
        $tenantId = (int) ($user['tenant_id'] ?? $tenant['id'] ?? 1);

        $claims = [
            'sub' => (string) $user['id'],
            'tenant_id' => $tenantId,
            'tenant_slug' => $tenant['slug'] ?? 'legacy',
            'role' => $roleSlug,
            'email' => $user['email'],
            'permissions' => $permissions,
            'store_id' => (int) ($user['store_id'] ?? 0) ?: null,
        ];

        echo json_encode([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => JwtService::encode($claims, 3600),
                'expires_in' => 3600,
                'user' => [
                    'id' => (int) $user['id'],
                    'name' => $user['name'] ?? $user['full_name'],
                    'email' => $user['email'],
                    'role' => $roleSlug,
                    'tenant_id' => $tenantId,
                ],
            ],
        ], JSON_UNESCAPED_UNICODE);
    }

    private function refresh(): void
    {
        $token = JwtService::bearerFromRequest();
        if (!$token) {
            ApiProblem::unauthorized('Bearer token required');
            return;
        }

        $claims = JwtService::decode($token);
        if (!$claims) {
            ApiProblem::unauthorized('Invalid or expired token');
            return;
        }

        unset($claims['iat'], $claims['exp'], $claims['iss']);
        $newToken = JwtService::encode($claims, 3600);

        echo json_encode([
            'data' => [
                'token_type' => 'Bearer',
                'access_token' => $newToken,
                'expires_in' => 3600,
            ],
        ], JSON_UNESCAPED_UNICODE);
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

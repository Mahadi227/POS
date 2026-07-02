<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Auth/SessionAuth.php';
require_once __DIR__ . '/../../Auth/PermissionService.php';
require_once __DIR__ . '/../../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/../PlatformSessionAuth.php';

final class PlatformImpersonationService
{
    private PDO $db;
    private PlatformAuditRepository $audit;

    public function __construct(PDO $db, PlatformAuditRepository $audit)
    {
        $this->db = $db;
        $this->audit = $audit;
    }

    public static function isImpersonating(): bool
    {
        return !empty($_SESSION['impersonating']);
    }

    public function impersonate(int $tenantId, int $platformUserId, ?string $ip = null): array
    {
        $tenant = $this->getTenant($tenantId);
        if (!$tenant) {
            throw new RuntimeException('Tenant not found');
        }
        if (($tenant['status'] ?? '') === 'cancelled') {
            throw new RuntimeException('Cannot impersonate cancelled tenant');
        }

        $user = $this->findTenantAdmin($tenantId);
        if (!$user) {
            throw new RuntimeException('No admin user found for tenant');
        }

        $permissions = (new PermissionService($this->db))->loadForUser(
            (int) $user['id'],
            (int) $user['role_id'],
        );

        SessionAuth::establish($user, $permissions);

        $_SESSION['impersonating'] = true;
        $_SESSION['impersonated_by'] = $platformUserId;
        $_SESSION['impersonated_tenant_id'] = $tenantId;
        $_SESSION['impersonated_tenant_name'] = $tenant['name'] ?? '';
        $_SESSION['impersonation_started'] = time();

        $this->audit->log('tenant.impersonate_start', $platformUserId, $tenantId, [
            'user_id' => (int) $user['id'],
            'user_email' => $user['email'] ?? '',
        ], $ip);

        return [
            'redirect' => '../../admin/index.php',
            'user_name' => $user['name'] ?? '',
            'tenant_name' => $tenant['name'] ?? '',
        ];
    }

    public function exitImpersonation(int $platformUserId, ?string $ip = null): void
    {
        $tenantId = (int) ($_SESSION['impersonated_tenant_id'] ?? 0);

        if ($tenantId > 0) {
            $this->audit->log('tenant.impersonate_end', $platformUserId, $tenantId, null, $ip);
        }

        $keys = [
            'user_id', 'role_id', 'name', 'full_name', 'email', 'role', 'role_slug',
            'permissions', 'workspace', 'store_id', 'branch_id', 'warehouse_id',
            'active_store_id', 'login_time', 'last_activity', 'tenant_id', 'tenant_slug',
            'impersonating', 'impersonated_by', 'impersonated_tenant_id',
            'impersonated_tenant_name', 'impersonation_started',
        ];
        foreach ($keys as $key) {
            unset($_SESSION[$key]);
        }
    }

    private function findTenantAdmin(int $tenantId): ?array
    {
        $hasTenant = $this->hasColumn('users', 'tenant_id');
        $sql = "
            SELECT u.id, u.name, u.full_name, u.email, u.is_active, u.status,
                   u.store_id, u.branch_id, u.warehouse_id, u.language, u.role_id,
                   r.name AS role_name";
        if ($hasTenant) {
            $sql .= ', u.tenant_id';
        }
        $sql .= "
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.deleted_at IS NULL
              AND (u.status = 'active' OR u.is_active = 1)
              AND r.name IN ('super_admin', 'admin')";
        $params = [];
        if ($hasTenant) {
            $sql .= ' AND u.tenant_id = ?';
            $params[] = $tenantId;
        }
        $sql .= " ORDER BY FIELD(r.name, 'super_admin', 'admin'), u.id ASC LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function getTenant(int $tenantId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, slug, name, status FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

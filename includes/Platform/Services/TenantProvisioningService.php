<?php
declare(strict_types=1);

require_once __DIR__ . '/../TenantScope.php';
require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Repositories/TenantDomainRepository.php';

final class TenantProvisioningService
{
    private const TRIAL_DAYS = 14;

    private PDO $db;
    private SubscriptionRepository $subscriptions;

    public function __construct(PDO $db, SubscriptionRepository $subscriptions)
    {
        $this->db = $db;
        $this->subscriptions = $subscriptions;
    }

    /**
     * @param array{
     *   org_name: string,
     *   slug?: string,
     *   admin_name: string,
     *   admin_email: string,
     *   password: string,
     *   plan_code?: string,
     *   country_code?: string,
     *   currency?: string,
     *   store_name?: string
     * } $input
     * @return array{tenant_id: int, slug: string, store_id: int, user_id: int}
     */
    public function provision(array $input): array
    {
        $orgName = trim($input['org_name'] ?? '');
        $adminName = trim($input['admin_name'] ?? '');
        $email = filter_var(trim($input['admin_email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = $input['password'] ?? '';
        $planCode = trim($input['plan_code'] ?? 'starter') ?: 'starter';
        $country = strtoupper(substr(trim($input['country_code'] ?? 'SN'), 0, 2)) ?: 'SN';
        $currency = strtoupper(trim($input['currency'] ?? 'XOF')) ?: 'XOF';
        $storeName = trim($input['store_name'] ?? '') ?: ($orgName . ' — Main');

        if ($orgName === '' || $adminName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new InvalidArgumentException('Invalid organization or admin details.');
        }
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        $slug = $this->normalizeSlug($input['slug'] ?? $orgName);
        if ($slug === '') {
            throw new InvalidArgumentException('Invalid organization slug.');
        }

        if ($this->slugExists($slug)) {
            throw new InvalidArgumentException('Organization slug already taken.');
        }
        if ($this->emailExistsInTenantScope($email)) {
            throw new InvalidArgumentException('Email already registered.');
        }

        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Invalid subscription plan.');
        }

        $roleId = $this->resolveTenantOwnerRoleId();
        if (!$roleId) {
            throw new RuntimeException('Super Admin role not found.');
        }

        $this->db->beginTransaction();
        try {
            $uuid = $this->uuid4();
            $trialEnd = date('Y-m-d H:i:s', strtotime('+' . self::TRIAL_DAYS . ' days'));

            $tenantCols = ['uuid', 'slug', 'name', 'country_code', 'default_currency', 'status'];
            $tenantVals = [$uuid, $slug, $orgName, $country, $currency, 'trial'];
            if ($this->hasColumn('tenants', 'trial_ends_at')) {
                $tenantCols[] = 'trial_ends_at';
                $tenantVals[] = $trialEnd;
            }
            if ($this->hasColumn('tenants', 'plan_id')) {
                $tenantCols[] = 'plan_id';
                $tenantVals[] = (int) $plan['id'];
            }
            $ph = implode(',', array_fill(0, count($tenantVals), '?'));
            $this->db->prepare(
                'INSERT INTO tenants (' . implode(', ', $tenantCols) . ') VALUES (' . $ph . ')'
            )->execute($tenantVals);
            $tenantId = (int) $this->db->lastInsertId();

            $this->subscriptions->createSubscription(
                $tenantId,
                (int) $plan['id'],
                'trial',
                date('Y-m-d', strtotime('+' . self::TRIAL_DAYS . ' days'))
            );

            $storeId = $this->createStore($tenantId, $storeName, $currency, $country);
            $userId = $this->createAdminUser($tenantId, $adminName, $email, $password, $roleId, $storeId);

            (new TenantDomainRepository($this->db))->registerSubdomain($tenantId, $slug);

            $this->db->commit();

            return [
                'tenant_id' => $tenantId,
                'slug' => $slug,
                'store_id' => $storeId,
                'user_id' => $userId,
                'trial_ends_at' => $trialEnd,
                'plan_code' => $planCode,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    private function createStore(int $tenantId, string $name, string $currency, string $country): int
    {
        $code = strtoupper(substr(preg_replace('/[^a-z0-9]/i', '', $name) ?: 'MAIN', 0, 8));
        $cols = ['name', 'location', 'currency', 'is_active'];
        $vals = [$name, $country, $currency, 1];
        $sql = 'INSERT INTO stores (name, location, currency, is_active';
        if ($this->hasColumn('stores', 'tenant_id')) {
            $sql .= ', tenant_id';
            $vals[] = $tenantId;
        }
        if ($this->hasColumn('stores', 'code')) {
            $sql .= ', code';
            $vals[] = $code;
        }
        if ($this->hasColumn('stores', 'tax_rate')) {
            $sql .= ', tax_rate';
            $vals[] = 18;
        }
        $sql .= ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')';
        $this->db->prepare($sql)->execute($vals);
        return (int) $this->db->lastInsertId();
    }

    private function createAdminUser(
        int $tenantId,
        string $name,
        string $email,
        string $password,
        int $roleId,
        int $storeId,
    ): int {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        $pinHash = password_hash('1234', PASSWORD_DEFAULT);

        $cols = ['name', 'email', 'password_hash', 'pin_hash', 'role_id', 'store_id', 'is_active', 'status'];
        $vals = [$name, $email, $hash, $pinHash, $roleId, $storeId, 1, 'active'];

        $sql = 'INSERT INTO users (' . implode(', ', $cols);
        if ($this->hasColumn('users', 'tenant_id')) {
            $sql .= ', tenant_id';
            $vals[] = $tenantId;
        }
        $sql .= ') VALUES (' . implode(',', array_fill(0, count($vals), '?')) . ')';
        $this->db->prepare($sql)->execute($vals);
        $userId = (int) $this->db->lastInsertId();

        if ($this->tableExists('user_stores')) {
            $this->db->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)')
                ->execute([$userId, $storeId]);
        }

        return $userId;
    }

    /** Role assigned to the organization owner at signup. */
    private function resolveTenantOwnerRoleId(): ?int
    {
        foreach (['Super Admin', 'super_admin', 'SuperAdmin'] as $candidate) {
            $id = $this->resolveRoleId($candidate);
            if ($id !== null) {
                return $id;
            }
        }

        return null;
    }

    private function resolveRoleId(string $roleName): ?int
    {
        $stmt = $this->db->prepare('SELECT id FROM roles WHERE name = ? ORDER BY id ASC LIMIT 1');
        $stmt->execute([$roleName]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }

    private function slugExists(string $slug): bool
    {
        $stmt = $this->db->prepare('SELECT 1 FROM tenants WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        return (bool) $stmt->fetchColumn();
    }

    private function emailExistsInTenantScope(string $email): bool
    {
        if ($this->hasColumn('users', 'tenant_id')) {
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? AND deleted_at IS NULL LIMIT 1');
        } else {
            $stmt = $this->db->prepare('SELECT 1 FROM users WHERE email = ? LIMIT 1');
        }
        $stmt->execute([$email]);
        return (bool) $stmt->fetchColumn();
    }

    private function normalizeSlug(string $raw): string
    {
        $slug = strtolower(trim($raw));
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
        return trim($slug, '-');
    }

    private function uuid4(): string
    {
        $data = random_bytes(16);
        $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
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

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

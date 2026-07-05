<?php
declare(strict_types=1);

final class TenantRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listTenants(int $limit = 100, int $offset = 0, ?string $search = null, ?string $status = null): array
    {
        $where = ['t.deleted_at IS NULL'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 't.status = ?';
            $params[] = $status;
        }

        $planJoin = '';
        $planSelect = '';
        if ($this->hasColumn('tenants', 'plan_id') && $this->tableExists('subscription_plans')) {
            $planJoin = ' LEFT JOIN subscription_plans sp ON sp.id = t.plan_id';
            $planSelect = ', sp.code AS plan_code, sp.name AS plan_name';
        }
        if ($this->hasColumn('tenants', 'trial_ends_at')) {
            $planSelect .= ', t.trial_ends_at';
        }

        $sql = 'SELECT t.id, t.uuid, t.slug, t.name, t.status, t.default_currency, t.created_at' . $planSelect . ',
                    (SELECT COUNT(*) FROM stores s WHERE s.tenant_id = t.id AND s.deleted_at IS NULL) AS store_count,
                    (SELECT COUNT(*) FROM users u WHERE u.tenant_id = t.id AND u.deleted_at IS NULL) AS user_count
             FROM tenants t' . $planJoin . '
             WHERE ' . implode(' AND ', $where) . '
             ORDER BY t.created_at DESC
             LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countByStatus(): array
    {
        $rows = $this->db->query(
            'SELECT status, COUNT(*) AS cnt FROM tenants WHERE deleted_at IS NULL GROUP BY status'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $out = ['trial' => 0, 'active' => 0, 'suspended' => 0, 'cancelled' => 0, 'total' => 0];
        foreach ($rows as $row) {
            $status = $row['status'] ?? '';
            $cnt = (int) ($row['cnt'] ?? 0);
            if (isset($out[$status])) {
                $out[$status] = $cnt;
            }
            $out['total'] += $cnt;
        }
        return $out;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, uuid, slug, name, status, default_currency, country_code, created_at
             FROM tenants WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Active organizations shown on the public login workspace picker.
     *
     * @return array<int, array{slug: string, name: string, status: string}>
     */
    public function listLoginOptions(int $limit = 200): array
    {
        if (!$this->tableExists('tenants')) {
            return [];
        }

        $stmt = $this->db->prepare(
            "SELECT slug, name, status
             FROM tenants
             WHERE deleted_at IS NULL AND status IN ('active', 'trial')
             ORDER BY name ASC
             LIMIT ?"
        );
        $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
        $stmt->execute();

        return array_map(static fn (array $row): array => [
            'slug' => (string) ($row['slug'] ?? ''),
            'name' => (string) ($row['name'] ?? ''),
            'status' => (string) ($row['status'] ?? 'active'),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findDetailById(int $id): ?array
    {
        $extra = '';
        if ($this->hasColumn('tenants', 'trial_ends_at')) {
            $extra .= ', t.trial_ends_at';
        }
        if ($this->hasColumn('tenants', 'plan_id')) {
            $extra .= ', t.plan_id';
        }
        if ($this->hasColumn('tenants', 'settings_json')) {
            $extra .= ', t.settings_json';
        }

        $stmt = $this->db->prepare(
            "SELECT t.id, t.uuid, t.slug, t.name, t.status, t.default_currency, t.country_code,
                    t.timezone, t.created_at, t.updated_at{$extra}
             FROM tenants t WHERE t.id = ? AND t.deleted_at IS NULL LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, bool> */
    public function getModuleOverrides(int $tenantId): array
    {
        if (!$this->tableExists('tenant_module_overrides')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT module_key, enabled FROM tenant_module_overrides WHERE tenant_id = ?'
        );
        $stmt->execute([$tenantId]);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $out[$row['module_key']] = (bool) $row['enabled'];
        }
        return $out;
    }

    public function listBillingEvents(int $tenantId, int $limit = 20): array
    {
        if (!$this->tableExists('billing_events')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, type, amount, currency, external_id, metadata_json, created_at
             FROM billing_events WHERE tenant_id = ? ORDER BY id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listStores(int $tenantId, int $limit = 10): array
    {
        if (!$this->hasColumn('stores', 'tenant_id')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, name, code, currency, is_active, created_at
             FROM stores WHERE tenant_id = ? AND deleted_at IS NULL
             ORDER BY id ASC LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function getFeatureFlags(int $tenantId): array
    {
        if (!$this->tableExists('feature_flags')) {
            return [];
        }
        $sql = 'SELECT ff.key_name, ff.description, ff.default_enabled,
                       COALESCE(tff.enabled, ff.default_enabled) AS enabled,
                       (tff.tenant_id IS NOT NULL) AS is_override
                FROM feature_flags ff
                LEFT JOIN tenant_feature_flags tff
                  ON tff.key_name = ff.key_name AND tff.tenant_id = ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

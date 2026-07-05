<?php
declare(strict_types=1);

final class PlatformIntegrationRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        $this->syncDerivedConnections();

        return [
            'stats' => $this->stats(),
            'providers' => $this->listProviders(null, null, null),
            'recent' => $this->listConnections(25, 0, null, null, null),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        $stats = [
            'providers' => 0,
            'enabled' => 0,
            'connections' => 0,
            'connected' => 0,
            'tenants' => 0,
        ];

        if ($this->tableExists('platform_integration_providers')) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'enabled' THEN 1 ELSE 0 END) AS enabled
                 FROM platform_integration_providers"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['providers'] = (int) ($row['total'] ?? 0);
            $stats['enabled'] = (int) ($row['enabled'] ?? 0);
        }

        if ($this->tableExists('tenant_integrations')) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'connected' THEN 1 ELSE 0 END) AS connected,
                        COUNT(DISTINCT tenant_id) AS tenants
                 FROM tenant_integrations"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['connections'] = (int) ($row['total'] ?? 0);
            $stats['connected'] = (int) ($row['connected'] ?? 0);
            $stats['tenants'] = (int) ($row['tenants'] ?? 0);
        }

        return $stats;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listProviders(?string $search = null, ?string $category = null, ?string $status = null): array
    {
        if (!$this->tableExists('platform_integration_providers')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(p.name LIKE ? OR p.slug LIKE ? OR p.short_description LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($category !== null && $category !== '') {
            $where[] = 'p.category = ?';
            $params[] = $category;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'p.status = ?';
            $params[] = $status;
        }

        $connJoin = $this->tableExists('tenant_integrations')
            ? 'LEFT JOIN (
                SELECT provider_id,
                       COUNT(*) AS connection_count,
                       SUM(CASE WHEN status = \'connected\' THEN 1 ELSE 0 END) AS active_count
                FROM tenant_integrations
                GROUP BY provider_id
            ) c ON c.provider_id = p.id'
            : 'LEFT JOIN (SELECT NULL AS provider_id, 0 AS connection_count, 0 AS active_count) c ON 1=0';

        $sql = 'SELECT p.id, p.slug, p.name, p.short_description, p.category, p.icon, p.brand_color,
                       p.status, p.is_official, p.docs_url, p.sort_order,
                       COALESCE(c.connection_count, 0) AS connection_count,
                       COALESCE(c.active_count, 0) AS active_count
                FROM platform_integration_providers p
                ' . $connJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY p.sort_order ASC, p.name ASC';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $p) {
            $stmt->bindValue($i + 1, $p);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['is_official'] = (int) ($row['is_official'] ?? 0);
            $row['connection_count'] = (int) ($row['connection_count'] ?? 0);
            $row['active_count'] = (int) ($row['active_count'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listConnections(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
        ?string $category = null
    ): array {
        if (!$this->tableExists('tenant_integrations')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR p.name LIKE ? OR p.slug LIKE ? OR ti.external_ref LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'ti.status = ?';
            $params[] = $status;
        }
        if ($category !== null && $category !== '') {
            $where[] = 'p.category = ?';
            $params[] = $category;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'INNER JOIN tenants t ON t.id = ti.tenant_id AND t.deleted_at IS NULL'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';

        $sql = 'SELECT ti.id, ti.tenant_id, ti.provider_id, ti.status, ti.external_ref, ti.notes,
                       ti.last_sync_at, ti.error_message, ti.created_at, ti.updated_at,
                       t.name AS tenant_name, t.slug AS tenant_slug,
                       p.slug AS provider_slug, p.name AS provider_name, p.category AS provider_category,
                       p.icon AS provider_icon, p.brand_color AS provider_color
                FROM tenant_integrations ti
                INNER JOIN platform_integration_providers p ON p.id = ti.provider_id
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY ti.updated_at DESC, ti.id DESC
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

    public function findProviderById(int $id): ?array
    {
        if (!$this->tableExists('platform_integration_providers')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM platform_integration_providers WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function setProviderStatus(int $id, string $status): bool
    {
        if (!$this->tableExists('platform_integration_providers')) {
            return false;
        }
        if (!in_array($status, ['enabled', 'disabled'], true)) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE platform_integration_providers SET status = ? WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function setConnectionStatus(int $id, string $status): bool
    {
        if (!$this->tableExists('tenant_integrations')) {
            return false;
        }
        if (!in_array($status, ['connected', 'disconnected', 'pending', 'error'], true)) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE tenant_integrations SET status = ?, updated_at = NOW(), last_sync_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$status, $id]);
        return $stmt->rowCount() > 0;
    }

    public function findConnectionById(int $id): ?array
    {
        if (!$this->tableExists('tenant_integrations')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT ti.*, p.slug AS provider_slug, p.name AS provider_name
             FROM tenant_integrations ti
             INNER JOIN platform_integration_providers p ON p.id = ti.provider_id
             WHERE ti.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function syncDerivedConnections(): void
    {
        if (!$this->tableExists('tenant_integrations') || !$this->tableExists('platform_integration_providers')) {
            return;
        }

        $providerIds = [];
        $stmt = $this->db->query('SELECT id, slug FROM platform_integration_providers');
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $providerIds[(string) $row['slug']] = (int) $row['id'];
        }

        $upsert = $this->db->prepare(
            'INSERT INTO tenant_integrations (tenant_id, provider_id, status, external_ref, last_sync_at)
             VALUES (?, ?, ?, ?, NOW())
             ON DUPLICATE KEY UPDATE
                status = IF(status = \'disconnected\', status, VALUES(status)),
                external_ref = COALESCE(VALUES(external_ref), external_ref),
                last_sync_at = NOW(),
                updated_at = NOW()'
        );

        if ($this->tableExists('webhook_endpoints') && isset($providerIds['webhooks'])) {
            $rows = $this->db->query(
                'SELECT tenant_id, COUNT(*) AS cnt FROM webhook_endpoints GROUP BY tenant_id'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $upsert->execute([
                    (int) $r['tenant_id'],
                    $providerIds['webhooks'],
                    'connected',
                    'webhooks:' . (int) $r['cnt'],
                ]);
            }
        }

        if ($this->tableExists('tenant_api_keys') && isset($providerIds['api_v2'])) {
            $rows = $this->db->query(
                'SELECT tenant_id, COUNT(*) AS cnt FROM tenant_api_keys WHERE revoked_at IS NULL GROUP BY tenant_id'
            )->fetchAll(PDO::FETCH_ASSOC) ?: [];
            foreach ($rows as $r) {
                $upsert->execute([
                    (int) $r['tenant_id'],
                    $providerIds['api_v2'],
                    'connected',
                    'keys:' . (int) $r['cnt'],
                ]);
            }
        }

        if ($this->tableExists('tenant_subscriptions')) {
            $hasStripe = $this->hasColumn('tenant_subscriptions', 'stripe_customer_id');
            $hasProvider = $this->hasColumn('tenant_subscriptions', 'payment_provider');

            if ($hasStripe && isset($providerIds['stripe'])) {
                $rows = $this->db->query(
                    "SELECT tenant_id, stripe_customer_id FROM tenant_subscriptions
                     WHERE stripe_customer_id IS NOT NULL AND stripe_customer_id != ''"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $upsert->execute([
                        (int) $r['tenant_id'],
                        $providerIds['stripe'],
                        'connected',
                        (string) ($r['stripe_customer_id'] ?? ''),
                    ]);
                }
            }

            if ($hasProvider && isset($providerIds['mtn_momo'])) {
                $rows = $this->db->query(
                    "SELECT tenant_id, payment_provider FROM tenant_subscriptions
                     WHERE payment_provider = 'mobile_money'"
                )->fetchAll(PDO::FETCH_ASSOC) ?: [];
                foreach ($rows as $r) {
                    $upsert->execute([
                        (int) $r['tenant_id'],
                        $providerIds['mtn_momo'],
                        'connected',
                        'mobile_money',
                    ]);
                }
            }
        }
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
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

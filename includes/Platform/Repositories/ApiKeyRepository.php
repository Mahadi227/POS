<?php
declare(strict_types=1);

final class ApiKeyRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listKeys(int $tenantId): array
    {
        if (!$this->tableExists('tenant_api_keys')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, name, key_prefix, scopes_json, created_by, last_used_at, revoked_at, created_at
             FROM tenant_api_keys WHERE tenant_id = ? ORDER BY id DESC'
        );
        $stmt->execute([$tenantId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['scopes'] = json_decode($row['scopes_json'] ?? '[]', true) ?: [];
            unset($row['scopes_json']);
            $row['is_active'] = empty($row['revoked_at']);
        }
        return $rows;
    }

    /** @param string[] $scopes */
    public function create(int $tenantId, string $name, string $prefix, string $hash, array $scopes, ?int $createdBy): int
    {
        $this->db->prepare(
            'INSERT INTO tenant_api_keys (tenant_id, name, key_prefix, key_hash, scopes_json, created_by)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $tenantId,
            $name,
            $prefix,
            $hash,
            json_encode(array_values($scopes), JSON_UNESCAPED_UNICODE),
            $createdBy,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function findByHash(string $hash): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, tenant_id, name, key_prefix, scopes_json, revoked_at
             FROM tenant_api_keys WHERE key_hash = ? LIMIT 1'
        );
        $stmt->execute([$hash]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || !empty($row['revoked_at'])) {
            return null;
        }
        $row['scopes'] = json_decode($row['scopes_json'] ?? '[]', true) ?: [];
        return $row;
    }

    public function touchLastUsed(int $id): void
    {
        $this->db->prepare('UPDATE tenant_api_keys SET last_used_at = NOW() WHERE id = ?')->execute([$id]);
    }

    public function revoke(int $tenantId, int $id): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE tenant_api_keys SET revoked_at = NOW() WHERE id = ? AND tenant_id = ? AND revoked_at IS NULL'
        );
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
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

<?php
declare(strict_types=1);

final class TenantDomainRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listForTenant(int $tenantId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, hostname, kind, is_verified, created_at
             FROM tenant_domains WHERE tenant_id = ? ORDER BY kind ASC, id ASC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function addCustomDomain(int $tenantId, string $hostname): int
    {
        $hostname = strtolower(trim($hostname));
        $stmt = $this->db->prepare(
            'INSERT INTO tenant_domains (tenant_id, hostname, kind, is_verified) VALUES (?, ?, ?, 0)'
        );
        $stmt->execute([$tenantId, $hostname, 'custom']);
        return (int) $this->db->lastInsertId();
    }

    public function registerSubdomain(int $tenantId, string $slug): void
    {
        if (!$this->tableExists()) {
            return;
        }
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO tenant_domains (tenant_id, hostname, kind, is_verified) VALUES (?, ?, ?, 1)'
        );
        $stmt->execute([$tenantId, $slug, 'subdomain']);
    }

    public function verifyDomain(int $tenantId, int $domainId): void
    {
        $this->db->prepare(
            'UPDATE tenant_domains SET is_verified = 1 WHERE id = ? AND tenant_id = ?'
        )->execute([$domainId, $tenantId]);
    }

    private function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['tenant_domains']);
        return (bool) $stmt->fetchColumn();
    }
}

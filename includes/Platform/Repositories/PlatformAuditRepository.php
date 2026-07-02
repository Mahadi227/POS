<?php
declare(strict_types=1);

final class PlatformAuditRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function log(
        string $action,
        ?int $platformUserId = null,
        ?int $tenantId = null,
        ?array $details = null,
        ?string $ip = null,
    ): void {
        if (!$this->tableExists()) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO platform_audit_log (platform_user_id, tenant_id, action, details_json, ip_address)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $platformUserId,
            $tenantId,
            $action,
            $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $ip,
        ]);
    }

    public function listForTenant(int $tenantId, int $limit = 30): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT pal.id, pal.action, pal.details_json, pal.ip_address, pal.created_at,
                    pu.name AS platform_user_name, pu.email AS platform_user_email
             FROM platform_audit_log pal
             LEFT JOIN platform_users pu ON pu.id = pal.platform_user_id
             WHERE pal.tenant_id = ?
             ORDER BY pal.id DESC
             LIMIT ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['platform_audit_log']);
        return (bool) $stmt->fetchColumn();
    }
}

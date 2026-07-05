<?php
declare(strict_types=1);

final class LicenseRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listLicenses(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
        ?string $licenseType = null
    ): array {
        if (!$this->tableExists('tenant_licenses')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(tl.key_prefix LIKE ? OR t.name LIKE ? OR t.slug LIKE ? OR tl.plan_code LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'tl.status = ?';
            $params[] = $status;
        }
        if ($licenseType !== null && $licenseType !== '') {
            $where[] = 'tl.license_type = ?';
            $params[] = $licenseType;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = tl.tenant_id'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';

        $sql = 'SELECT tl.id, tl.tenant_id, tl.key_prefix, tl.license_type, tl.status,
                       tl.plan_code, tl.max_seats, tl.notes, tl.expires_at, tl.revoked_at, tl.created_at,
                       t.name AS tenant_name, t.slug AS tenant_slug
                FROM tenant_licenses tl
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY tl.id DESC
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

    /** @return array<string, int> */
    public function licenseStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'expiring' => 0,
            'revoked' => 0,
        ];

        if (!$this->tableExists('tenant_licenses')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                    SUM(CASE WHEN status = 'revoked' THEN 1 ELSE 0 END) AS revoked,
                    SUM(CASE WHEN status = 'active' AND expires_at IS NOT NULL
                             AND expires_at <= DATE_ADD(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS expiring
             FROM tenant_licenses"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['active'] = (int) ($row['active'] ?? 0);
        $stats['revoked'] = (int) ($row['revoked'] ?? 0);
        $stats['expiring'] = (int) ($row['expiring'] ?? 0);

        return $stats;
    }

    public function create(
        ?int $tenantId,
        string $prefix,
        string $hash,
        string $licenseType,
        ?string $planCode,
        ?int $maxSeats,
        ?string $notes,
        ?int $issuedBy,
        ?string $expiresAt
    ): int {
        $this->db->prepare(
            'INSERT INTO tenant_licenses
             (tenant_id, license_key_hash, key_prefix, license_type, plan_code, max_seats, notes, issued_by, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([
            $tenantId,
            $hash,
            $prefix,
            $licenseType,
            $planCode ?: null,
            $maxSeats,
            $notes ?: null,
            $issuedBy,
            $expiresAt ?: null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists('tenant_licenses')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM tenant_licenses WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function revoke(int $id): bool
    {
        if (!$this->tableExists('tenant_licenses')) {
            return false;
        }
        $stmt = $this->db->prepare(
            "UPDATE tenant_licenses SET status = 'revoked', revoked_at = NOW() WHERE id = ? AND status = 'active'"
        );
        $stmt->execute([$id]);
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

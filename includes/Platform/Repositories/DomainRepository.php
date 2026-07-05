<?php
declare(strict_types=1);

final class DomainRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listDomains(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $kind = null,
        ?string $verified = null
    ): array {
        if (!$this->tableExists('tenant_domains')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(td.hostname LIKE ? OR t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($kind !== null && $kind !== '') {
            $where[] = 'td.kind = ?';
            $params[] = $kind;
        }
        if ($verified === 'yes') {
            $where[] = 'td.is_verified = 1';
        } elseif ($verified === 'no') {
            $where[] = 'td.is_verified = 0';
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'INNER JOIN tenants t ON t.id = td.tenant_id AND t.deleted_at IS NULL'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';

        $sql = 'SELECT td.id, td.tenant_id, td.hostname, td.kind, td.is_verified, td.created_at,
                       t.name AS tenant_name, t.slug AS tenant_slug
                FROM tenant_domains td
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY td.id DESC
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
    public function domainStats(): array
    {
        $stats = [
            'total' => 0,
            'subdomain' => 0,
            'custom' => 0,
            'pending' => 0,
            'verified' => 0,
        ];

        if (!$this->tableExists('tenant_domains')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN kind = 'subdomain' THEN 1 ELSE 0 END) AS subdomain,
                    SUM(CASE WHEN kind = 'custom' THEN 1 ELSE 0 END) AS custom,
                    SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) AS verified
             FROM tenant_domains"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['subdomain'] = (int) ($row['subdomain'] ?? 0);
        $stats['custom'] = (int) ($row['custom'] ?? 0);
        $stats['pending'] = (int) ($row['pending'] ?? 0);
        $stats['verified'] = (int) ($row['verified'] ?? 0);

        return $stats;
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists('tenant_domains')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM tenant_domains WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function verify(int $domainId): bool
    {
        if (!$this->tableExists('tenant_domains')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE tenant_domains SET is_verified = 1 WHERE id = ? AND is_verified = 0'
        );
        $stmt->execute([$domainId]);
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

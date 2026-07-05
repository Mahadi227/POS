<?php
declare(strict_types=1);

final class BillingRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listEvents(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $type = null
    ): array {
        if (!$this->tableExists('billing_events')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ? OR be.external_id LIKE ? OR CAST(be.id AS CHAR) LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($type !== null && $type !== '') {
            $where[] = 'be.type = ?';
            $params[] = $type;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'INNER JOIN tenants t ON t.id = be.tenant_id AND t.deleted_at IS NULL'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug, NULL AS deleted_at) t ON 1=0';

        $sql = 'SELECT be.id, be.tenant_id, be.type, be.amount, be.currency, be.external_id,
                       be.metadata_json, be.created_at,
                       t.name AS tenant_name, t.slug AS tenant_slug
                FROM billing_events be
                ' . $tenantJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY be.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $meta = json_decode((string) ($row['metadata_json'] ?? '{}'), true);
            $row['metadata'] = is_array($meta) ? $meta : [];
            unset($row['metadata_json']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, int|float|string> */
    public function billingStats(): array
    {
        $stats = [
            'total' => 0,
            'payments' => 0,
            'collected' => 0.0,
            'failed' => 0,
            'refunds' => 0,
            'currency' => 'EUR',
        ];

        if (!$this->tableExists('billing_events')) {
            return $stats;
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) AS payments,
                    SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) AS collected,
                    SUM(CASE WHEN type = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN type = 'refund' THEN 1 ELSE 0 END) AS refunds
             FROM billing_events"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($row['total'] ?? 0);
        $stats['payments'] = (int) ($row['payments'] ?? 0);
        $stats['collected'] = (float) ($row['collected'] ?? 0);
        $stats['failed'] = (int) ($row['failed'] ?? 0);
        $stats['refunds'] = (int) ($row['refunds'] ?? 0);

        $cur = $this->db->query(
            "SELECT currency FROM billing_events WHERE type = 'payment' ORDER BY id DESC LIMIT 1"
        )->fetchColumn();
        if (is_string($cur) && $cur !== '') {
            $stats['currency'] = $cur;
        }

        return $stats;
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

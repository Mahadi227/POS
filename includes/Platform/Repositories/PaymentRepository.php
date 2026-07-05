<?php
declare(strict_types=1);

final class PaymentRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<int, array<string, mixed>> */
    public function listPayments(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
        ?string $provider = null
    ): array {
        $parts = [];
        $params = [];

        if ($this->tableExists('billing_events')) {
            $gwWhere = ["be.type IN ('payment', 'refund', 'failed')"];
            if ($status === 'confirmed') {
                $gwWhere[] = "be.type = 'payment'";
            } elseif ($status === 'failed') {
                $gwWhere[] = "be.type = 'failed'";
            } elseif ($status === 'refund') {
                $gwWhere[] = "be.type = 'refund'";
            } elseif ($status === 'pending') {
                $gwWhere[] = '1=0';
            }
            if ($provider === 'stripe') {
                $gwWhere[] = "(be.metadata_json LIKE '%\"stripe\"%' OR be.metadata_json LIKE '%stripe%')";
            } elseif ($provider === 'paystack') {
                $gwWhere[] = "be.metadata_json LIKE '%paystack%'";
            } elseif ($provider === 'mobile_money') {
                $gwWhere[] = "be.metadata_json LIKE '%mobile_money%'";
            } elseif ($provider !== null && $provider !== '' && !in_array($provider, ['stripe', 'paystack', 'mobile_money'], true)) {
                $gwWhere[] = '1=0';
            }

            $parts[] = 'SELECT be.id AS src_id, \'gateway\' AS source, be.tenant_id,
                CASE be.type WHEN \'payment\' THEN \'confirmed\' WHEN \'failed\' THEN \'failed\'
                     WHEN \'refund\' THEN \'refund\' ELSE be.type END AS payment_status,
                be.amount, be.currency, COALESCE(be.external_id, \'\') AS reference,
                be.metadata_json, NULL AS plan_code, NULL AS mm_provider, be.created_at
                FROM billing_events be WHERE ' . implode(' AND ', $gwWhere);
        }

        if ($this->tableExists('mobile_money_payments')) {
            $mmWhere = ['1=1'];
            if ($status === 'confirmed') {
                $mmWhere[] = "mm.status = 'confirmed'";
            } elseif ($status === 'pending') {
                $mmWhere[] = "mm.status = 'pending'";
            } elseif ($status === 'failed') {
                $mmWhere[] = "mm.status = 'rejected'";
            } elseif ($status === 'refund') {
                $mmWhere[] = '1=0';
            }
            if ($provider === 'mobile_money') {
                // all mobile money rows
            } elseif (in_array($provider, ['orange', 'mtn', 'wave', 'moov', 'other'], true)) {
                $mmWhere[] = 'mm.provider = ?';
                $params[] = $provider;
            } elseif ($provider !== null && $provider !== '' && !in_array($provider, ['stripe', 'paystack'], true)) {
                if ($provider !== 'mobile_money') {
                    $mmWhere[] = '1=0';
                }
            } elseif ($provider === 'stripe' || $provider === 'paystack') {
                $mmWhere[] = '1=0';
            }

            $parts[] = 'SELECT mm.id AS src_id, \'mobile_money\' AS source, mm.tenant_id,
                CASE mm.status WHEN \'confirmed\' THEN \'confirmed\' WHEN \'pending\' THEN \'pending\'
                     WHEN \'rejected\' THEN \'failed\' ELSE mm.status END AS payment_status,
                mm.amount, mm.currency, mm.reference, NULL AS metadata_json,
                mm.plan_code, mm.provider AS mm_provider, mm.created_at
                FROM mobile_money_payments mm WHERE ' . implode(' AND ', $mmWhere);
        }

        if (!$parts) {
            return [];
        }

        $union = implode(' UNION ALL ', $parts);
        $outerWhere = [];
        $outerParams = $params;

        if ($search !== null && $search !== '') {
            $outerWhere[] = '(p.reference LIKE ? OR p.plan_code LIKE ? OR t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $outerParams[] = $like;
            $outerParams[] = $like;
            $outerParams[] = $like;
            $outerParams[] = $like;
        }

        $tenantJoin = $this->tableExists('tenants')
            ? 'LEFT JOIN tenants t ON t.id = p.tenant_id AND t.deleted_at IS NULL'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name, NULL AS slug) t ON 1=0';

        $sql = 'SELECT p.src_id, p.source, p.tenant_id, p.payment_status, p.amount, p.currency,
                       p.reference, p.metadata_json, p.plan_code, p.mm_provider, p.created_at,
                       t.name AS tenant_name, t.slug AS tenant_slug
                FROM (' . $union . ') p
                ' . $tenantJoin;

        if ($outerWhere) {
            $sql .= ' WHERE ' . implode(' AND ', $outerWhere);
        }

        $sql .= ' ORDER BY p.created_at DESC LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($outerParams as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['provider'] = $this->resolveProvider($row);
            $meta = json_decode((string) ($row['metadata_json'] ?? '{}'), true);
            $row['metadata'] = is_array($meta) ? $meta : [];
            if (!$row['plan_code'] && !empty($row['metadata']['plan_code'])) {
                $row['plan_code'] = $row['metadata']['plan_code'];
            }
            unset($row['metadata_json']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, int|float|string> */
    public function paymentStats(): array
    {
        $stats = [
            'total' => 0,
            'confirmed' => 0,
            'pending' => 0,
            'failed' => 0,
            'collected' => 0.0,
            'currency' => 'EUR',
        ];

        if ($this->tableExists('billing_events')) {
            $gw = $this->db->query(
                "SELECT
                    SUM(CASE WHEN type IN ('payment','refund','failed') THEN 1 ELSE 0 END) AS total,
                    SUM(CASE WHEN type = 'payment' THEN 1 ELSE 0 END) AS confirmed,
                    SUM(CASE WHEN type = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN type = 'payment' THEN amount ELSE 0 END) AS collected
                 FROM billing_events"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total'] += (int) ($gw['total'] ?? 0);
            $stats['confirmed'] += (int) ($gw['confirmed'] ?? 0);
            $stats['failed'] += (int) ($gw['failed'] ?? 0);
            $stats['collected'] += (float) ($gw['collected'] ?? 0);

            $cur = $this->db->query(
                "SELECT currency FROM billing_events WHERE type = 'payment' ORDER BY id DESC LIMIT 1"
            )->fetchColumn();
            if (is_string($cur) && $cur !== '') {
                $stats['currency'] = $cur;
            }
        }

        if ($this->tableExists('mobile_money_payments')) {
            $mm = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'confirmed' THEN 1 ELSE 0 END) AS confirmed,
                        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                        SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) AS failed,
                        SUM(CASE WHEN status = 'confirmed' THEN amount ELSE 0 END) AS collected
                 FROM mobile_money_payments"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total'] += (int) ($mm['total'] ?? 0);
            $stats['confirmed'] += (int) ($mm['confirmed'] ?? 0);
            $stats['pending'] += (int) ($mm['pending'] ?? 0);
            $stats['failed'] += (int) ($mm['failed'] ?? 0);
            $stats['collected'] += (float) ($mm['collected'] ?? 0);

            if ($stats['currency'] === 'EUR') {
                $mmCur = $this->db->query(
                    "SELECT currency FROM mobile_money_payments WHERE status = 'confirmed' ORDER BY id DESC LIMIT 1"
                )->fetchColumn();
                if (is_string($mmCur) && $mmCur !== '') {
                    $stats['currency'] = $mmCur;
                }
            }
        }

        return $stats;
    }

    public function findMobileMoney(int $id): ?array
    {
        if (!$this->tableExists('mobile_money_payments')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM mobile_money_payments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @param array<string, mixed> $row */
    private function resolveProvider(array $row): string
    {
        if (($row['source'] ?? '') === 'mobile_money') {
            $sub = $row['mm_provider'] ?? '';
            return $sub ? 'mobile_' . $sub : 'mobile_money';
        }
        $meta = json_decode((string) ($row['metadata_json'] ?? '{}'), true);
        if (is_array($meta)) {
            if (!empty($meta['provider'])) {
                return (string) $meta['provider'];
            }
            if (!empty($meta['stripe'])) {
                return 'stripe';
            }
            if (!empty($meta['paystack'])) {
                return 'paystack';
            }
            if (!empty($meta['mobile_money'])) {
                return 'mobile_money';
            }
        }
        return 'manual';
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

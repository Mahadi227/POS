<?php
declare(strict_types=1);

final class SubscriptionRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function listActivePlans(): array
    {
        if (!$this->tableExists('subscription_plans')) {
            return [];
        }
        return $this->db->query(
            'SELECT id, code, name, max_stores, max_users, modules_json, price_monthly, currency
             FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listPlansCatalog(): array
    {
        if (!$this->tableExists('subscription_plans')) {
            return [];
        }

        $hasSubs = $this->tableExists('tenant_subscriptions');
        $subSelect = $hasSubs
            ? ', (SELECT COUNT(DISTINCT ts.tenant_id)
                 FROM tenant_subscriptions ts
                 INNER JOIN (
                     SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
                 ) latest ON latest.max_id = ts.id
                 WHERE ts.plan_id = sp.id) AS subscriber_count'
            : ', 0 AS subscriber_count';

        $rows = $this->db->query(
            'SELECT sp.id, sp.code, sp.name, sp.max_stores, sp.max_users, sp.modules_json,
                    sp.price_monthly, sp.currency, sp.is_active' . $subSelect . '
             FROM subscription_plans sp
             ORDER BY sp.is_active DESC, sp.price_monthly ASC, sp.name ASC'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['subscriber_count'] = (int) ($row['subscriber_count'] ?? 0);
            $row['is_active'] = (int) ($row['is_active'] ?? 0);
            $modules = json_decode((string) ($row['modules_json'] ?? '{}'), true);
            $row['modules'] = is_array($modules) ? $modules : [];
            unset($row['modules_json']);
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, int|float> */
    public function planStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'inactive' => 0,
            'subscribers' => 0,
            'mrr' => 0.0,
        ];

        if (!$this->tableExists('subscription_plans')) {
            return $stats;
        }

        $planRow = $this->db->query(
            'SELECT COUNT(*) AS total,
                    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) AS active
             FROM subscription_plans'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['total'] = (int) ($planRow['total'] ?? 0);
        $stats['active'] = (int) ($planRow['active'] ?? 0);
        $stats['inactive'] = max(0, $stats['total'] - $stats['active']);

        if (!$this->tableExists('tenant_subscriptions')) {
            return $stats;
        }

        $subRow = $this->db->query(
            'SELECT COUNT(DISTINCT ts.tenant_id) AS subscribers
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id'
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        $stats['subscribers'] = (int) ($subRow['subscribers'] ?? 0);

        $mrrRow = $this->db->query(
            "SELECT COALESCE(SUM(sp.price_monthly), 0) AS mrr
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id
             INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
             WHERE ts.status = 'active'"
        )->fetch(PDO::FETCH_ASSOC);

        $stats['mrr'] = (float) ($mrrRow['mrr'] ?? 0);
        return $stats;
    }

    public function findPlanByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, max_stores, max_users, modules_json, price_monthly, currency, is_active
             FROM subscription_plans WHERE code = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findPlanById(int $id): ?array
    {
        if ($id <= 0 || !$this->tableExists('subscription_plans')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, code, name, max_stores, max_users, modules_json, price_monthly, currency, is_active
             FROM subscription_plans WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * @param array<string, mixed> $fields
     */
    public function updatePlan(int $id, array $fields): ?array
    {
        $existing = $this->findPlanById($id);
        if ($existing === null) {
            return null;
        }

        $sets = [];
        $params = [];

        if (array_key_exists('name', $fields)) {
            $name = trim((string) $fields['name']);
            if ($name !== '') {
                $sets[] = 'name = ?';
                $params[] = $name;
            }
        }
        if (array_key_exists('price_monthly', $fields)) {
            $sets[] = 'price_monthly = ?';
            $params[] = max(0, (float) $fields['price_monthly']);
        }
        if (array_key_exists('currency', $fields)) {
            $currency = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', (string) $fields['currency']) ?? '', 0, 3));
            if (strlen($currency) === 3) {
                $sets[] = 'currency = ?';
                $params[] = $currency;
            }
        }
        if (array_key_exists('max_stores', $fields)) {
            $stores = $fields['max_stores'];
            $sets[] = 'max_stores = ?';
            $params[] = ($stores === null || $stores === '') ? null : max(0, (int) $stores);
        }
        if (array_key_exists('max_users', $fields)) {
            $users = $fields['max_users'];
            $sets[] = 'max_users = ?';
            $params[] = ($users === null || $users === '') ? null : max(0, (int) $users);
        }
        if (array_key_exists('is_active', $fields)) {
            $sets[] = 'is_active = ?';
            $params[] = !empty($fields['is_active']) ? 1 : 0;
        }

        if ($sets === []) {
            return $existing;
        }

        $params[] = $id;
        $sql = 'UPDATE subscription_plans SET ' . implode(', ', $sets) . ' WHERE id = ?';
        $this->db->prepare($sql)->execute($params);

        return $this->findPlanById($id);
    }

    public function getActiveSubscription(int $tenantId): ?array
    {
        if (!$this->tableExists('tenant_subscriptions')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT ts.*, sp.code AS plan_code, sp.name AS plan_name, sp.modules_json, sp.max_stores, sp.max_users,
                    sp.price_monthly, sp.currency
             FROM tenant_subscriptions ts
             INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
             WHERE ts.tenant_id = ?
             ORDER BY ts.id DESC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function createSubscription(int $tenantId, int $planId, string $status = 'trial', ?string $periodEnd = null): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO tenant_subscriptions (tenant_id, plan_id, status, current_period_start, current_period_end)
             VALUES (?, ?, ?, CURDATE(), ?)'
        );
        $stmt->execute([$tenantId, $planId, $status, $periodEnd]);
        return (int) $this->db->lastInsertId();
    }

    public function updateSubscriptionStatus(int $tenantId, string $status): void
    {
        $this->db->prepare(
            'UPDATE tenant_subscriptions SET status = ? WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
        )->execute([$status, $tenantId]);
    }

    public function changePlan(int $tenantId, int $planId): void
    {
        $sub = $this->getActiveSubscription($tenantId);
        if ($sub) {
            $this->db->prepare(
                'UPDATE tenant_subscriptions SET plan_id = ? WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
            )->execute([$planId, $tenantId]);
        } else {
            $this->createSubscription($tenantId, $planId, 'active');
        }
    }

    public function setStripeIds(int $tenantId, ?string $customerId, ?string $subscriptionId): void
    {
        if (!$this->hasColumn('tenant_subscriptions', 'stripe_customer_id')) {
            return;
        }
        $this->db->prepare(
            'UPDATE tenant_subscriptions SET stripe_customer_id = ?, stripe_subscription_id = ?
             WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
        )->execute([$customerId, $subscriptionId, $tenantId]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listSubscriptions(
        int $limit = 100,
        int $offset = 0,
        ?string $search = null,
        ?string $subscriptionStatus = null,
        ?string $planCode = null
    ): array {
        if (!$this->tableExists('tenant_subscriptions') || !$this->tableExists('tenants')) {
            return [];
        }

        $where = ['t.deleted_at IS NULL'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(t.name LIKE ? OR t.slug LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($subscriptionStatus !== null && $subscriptionStatus !== '') {
            $where[] = 'ts.status = ?';
            $params[] = $subscriptionStatus;
        }
        if ($planCode !== null && $planCode !== '') {
            $where[] = 'sp.code = ?';
            $params[] = $planCode;
        }

        $providerSelect = $this->hasColumn('tenant_subscriptions', 'payment_provider')
            ? ', ts.payment_provider'
            : '';
        $trialSelect = $this->hasColumn('tenants', 'trial_ends_at') ? ', t.trial_ends_at' : '';

        $sql = 'SELECT t.id AS tenant_id, t.name, t.slug, t.status AS tenant_status,
                       ts.id AS subscription_id, ts.status AS subscription_status,
                       ts.current_period_start, ts.current_period_end,
                       sp.code AS plan_code, sp.name AS plan_name,
                       sp.price_monthly, sp.currency' . $providerSelect . $trialSelect . '
                FROM tenants t
                LEFT JOIN (
                    SELECT tenant_id, MAX(id) AS max_id
                    FROM tenant_subscriptions
                    GROUP BY tenant_id
                ) latest ON latest.tenant_id = t.id
                LEFT JOIN tenant_subscriptions ts ON ts.id = latest.max_id
                LEFT JOIN subscription_plans sp ON sp.id = ts.plan_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY t.name ASC
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

    /** @return array<string, int|float> */
    public function subscriptionStats(): array
    {
        $stats = [
            'total' => 0,
            'active' => 0,
            'trial' => 0,
            'past_due' => 0,
            'cancelled' => 0,
            'mrr' => 0.0,
        ];

        if (!$this->tableExists('tenant_subscriptions')) {
            return $stats;
        }

        $rows = $this->db->query(
            'SELECT ts.status, COUNT(*) AS cnt
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id
             GROUP BY ts.status'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? '');
            $cnt = (int) ($row['cnt'] ?? 0);
            $stats['total'] += $cnt;
            if (isset($stats[$status])) {
                $stats[$status] = $cnt;
            }
        }

        $mrrRow = $this->db->query(
            "SELECT COALESCE(SUM(sp.price_monthly), 0) AS mrr
             FROM tenant_subscriptions ts
             INNER JOIN (
                 SELECT tenant_id, MAX(id) AS max_id FROM tenant_subscriptions GROUP BY tenant_id
             ) latest ON latest.max_id = ts.id
             INNER JOIN subscription_plans sp ON sp.id = ts.plan_id
             WHERE ts.status = 'active'"
        )->fetch(PDO::FETCH_ASSOC);

        $stats['mrr'] = (float) ($mrrRow['mrr'] ?? 0);
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

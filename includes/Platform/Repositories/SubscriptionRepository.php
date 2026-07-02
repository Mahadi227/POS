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

    public function findPlanByCode(string $code): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, code, name, max_stores, max_users, modules_json, price_monthly, currency
             FROM subscription_plans WHERE code = ? AND is_active = 1 LIMIT 1'
        );
        $stmt->execute([$code]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
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

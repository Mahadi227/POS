<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/EntitlementService.php';

final class MobileMoneyBillingService
{
    private PDO $db;
    private SubscriptionRepository $subscriptions;
    private EntitlementService $entitlements;

    public function __construct(PDO $db, SubscriptionRepository $subscriptions, EntitlementService $entitlements)
    {
        $this->db = $db;
        $this->subscriptions = $subscriptions;
        $this->entitlements = $entitlements;
    }

    /**
     * @return array{mode: string, reference: string, message: string, auto_confirmed?: bool}
     */
    public function requestPayment(
        int $tenantId,
        string $planCode,
        string $provider,
        string $phone,
    ): array {
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found');
        }

        $provider = $this->normalizeProvider($provider);
        $phone = preg_replace('/\D+/', '', $phone) ?? '';
        if ($phone === '') {
            throw new InvalidArgumentException('Phone number required');
        }

        $reference = 'MM-' . strtoupper(bin2hex(random_bytes(4))) . '-' . time();
        $amount = (float) $plan['price_monthly'];
        $currency = $plan['currency'] ?? 'XOF';

        if (!$this->tableExists('mobile_money_payments')) {
            throw new RuntimeException('Mobile money not available');
        }

        $this->db->prepare(
            'INSERT INTO mobile_money_payments (tenant_id, plan_code, provider, phone, amount, currency, reference, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        )->execute([$tenantId, $planCode, $provider, $phone, $amount, $currency, $reference, 'pending']);

        $autoConfirm = !(defined('PAYSTACK_SECRET_KEY') && PAYSTACK_SECRET_KEY !== '')
            && !(defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== '');

        if ($autoConfirm && defined('APP_DEBUG') && APP_DEBUG) {
            $this->confirmPayment($reference, $tenantId);
            return [
                'mode' => 'mobile_money_demo',
                'reference' => $reference,
                'message' => 'Payment auto-confirmed (demo mode).',
                'auto_confirmed' => true,
            ];
        }

        return [
            'mode' => 'mobile_money',
            'reference' => $reference,
            'message' => 'Send ' . $amount . ' ' . $currency . ' via ' . $provider . ' to complete upgrade. Reference: ' . $reference,
        ];
    }

    public function confirmPayment(string $reference, int $tenantId): bool
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM mobile_money_payments WHERE reference = ? AND tenant_id = ? LIMIT 1'
        );
        $stmt->execute([$reference, $tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row || ($row['status'] ?? '') !== 'pending') {
            return false;
        }

        $plan = $this->subscriptions->findPlanByCode($row['plan_code']);
        if (!$plan) {
            return false;
        }

        $this->subscriptions->changePlan($tenantId, (int) $plan['id']);
        $this->subscriptions->updateSubscriptionStatus($tenantId, 'active');

        if ($this->hasColumn('tenant_subscriptions', 'payment_provider')) {
            $this->db->prepare(
                'UPDATE tenant_subscriptions SET payment_provider = ? WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
            )->execute(['mobile_money', $tenantId]);
        }

        $this->db->prepare(
            'UPDATE mobile_money_payments SET status = ?, confirmed_at = NOW() WHERE reference = ?'
        )->execute(['confirmed', $reference]);

        $this->logBillingEvent($tenantId, (float) $row['amount'], $row['currency'], $reference, $row['plan_code']);

        return true;
    }

    private function logBillingEvent(int $tenantId, float $amount, string $currency, string $ref, string $planCode): void
    {
        if (!$this->tableExists('billing_events')) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO billing_events (tenant_id, type, amount, currency, external_id, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $tenantId,
            'payment',
            $amount,
            $currency,
            $ref,
            json_encode(['plan_code' => $planCode, 'provider' => 'mobile_money'], JSON_UNESCAPED_UNICODE),
        ]);
    }

    private function normalizeProvider(string $provider): string
    {
        $p = strtolower(trim($provider));
        $allowed = ['orange', 'mtn', 'wave', 'moov', 'other'];
        return in_array($p, $allowed, true) ? $p : 'wave';
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

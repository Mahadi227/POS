<?php
declare(strict_types=1);

require_once __DIR__ . '/StripeService.php';
require_once __DIR__ . '/PaystackService.php';
require_once __DIR__ . '/MobileMoneyBillingService.php';
require_once __DIR__ . '/EntitlementService.php';
require_once __DIR__ . '/../Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../TenantScope.php';
require_once __DIR__ . '/WebhookDispatcherService.php';
require_once __DIR__ . '/TransactionalEmailService.php';
require_once __DIR__ . '/../SaaSPhase6Migrator.php';

final class BillingService
{
    private PDO $db;
    private SubscriptionRepository $subscriptions;
    private EntitlementService $entitlements;
    private StripeService $stripe;
    private PaystackService $paystack;
    private MobileMoneyBillingService $mobileMoney;

    public function __construct(
        PDO $db,
        SubscriptionRepository $subscriptions,
        EntitlementService $entitlements,
        StripeService $stripe,
        ?PaystackService $paystack = null,
        ?MobileMoneyBillingService $mobileMoney = null,
    ) {
        $this->db = $db;
        $this->subscriptions = $subscriptions;
        $this->entitlements = $entitlements;
        $this->stripe = $stripe;
        $this->paystack = $paystack ?? new PaystackService();
        $this->mobileMoney = $mobileMoney ?? new MobileMoneyBillingService($db, $subscriptions, $entitlements);
    }

    public function listPlans(): array
    {
        return $this->subscriptions->listActivePlans();
    }

    public function subscriptionSummary(int $tenantId): array
    {
        return $this->entitlements->getSubscriptionSummary($tenantId);
    }

    /**
     * Create Stripe Checkout for plan upgrade.
     * @return array{mode: string, url?: string, session_id?: string, message?: string}
     */
    public function createCheckout(
        int $tenantId,
        string $planCode,
        string $successUrl,
        string $cancelUrl,
        string $provider = 'stripe',
        ?string $mobilePhone = null,
        ?string $mobileProvider = null,
    ): array {
        $provider = strtolower(trim($provider));

        if ($provider === 'mobile_money') {
            return $this->mobileMoney->requestPayment(
                $tenantId,
                $planCode,
                $mobileProvider ?? 'wave',
                $mobilePhone ?? '',
            );
        }

        if ($provider === 'paystack' && $this->paystack->isConfigured()) {
            return $this->createPaystackCheckout($tenantId, $planCode, $successUrl);
        }

        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found.');
        }

        if (!$this->stripe->isConfigured()) {
            return $this->simulateCheckout($tenantId, $planCode, $successUrl);
        }

        $tenant = $this->getTenant($tenantId);
        $amountCents = (int) round((float) $plan['price_monthly'] * 100);

        $session = $this->stripe->createCheckoutSession([
            'mode' => 'subscription',
            'success_url' => $successUrl . (str_contains($successUrl, '?') ? '&' : '?') . 'session_id={CHECKOUT_SESSION_ID}',
            'cancel_url' => $cancelUrl,
            'client_reference_id' => (string) $tenantId,
            'metadata' => [
                'tenant_id' => (string) $tenantId,
                'plan_code' => $planCode,
            ],
            'line_items' => [[
                'price_data' => [
                    'currency' => strtolower($plan['currency'] ?? 'eur'),
                    'product_data' => [
                        'name' => 'RetailPOS ' . ($plan['name'] ?? $planCode),
                    ],
                    'unit_amount' => max($amountCents, 100),
                    'recurring' => ['interval' => 'month'],
                ],
                'quantity' => 1,
            ]],
            'customer_email' => $tenant['billing_email'] ?? null,
        ]);

        $this->logEvent($tenantId, 'checkout', 0, $plan['currency'] ?? 'EUR', $session['id'] ?? null, [
            'plan_code' => $planCode,
        ]);

        return [
            'mode' => 'stripe',
            'url' => $session['url'] ?? null,
            'session_id' => $session['id'] ?? null,
        ];
    }

    /** Dev/demo mode when Stripe keys absent */
    private function simulateCheckout(int $tenantId, string $planCode, string $successUrl): array
    {
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found.');
        }

        $this->activatePlan($tenantId, (int) $plan['id'], 'active');
        $this->logEvent($tenantId, 'payment', (float) $plan['price_monthly'], $plan['currency'] ?? 'EUR', 'sim_' . time(), [
            'plan_code' => $planCode,
            'simulated' => true,
        ]);

        return [
            'mode' => 'simulated',
            'message' => 'Plan activated (Stripe not configured — demo mode).',
            'url' => $successUrl,
        ];
    }

    public function handleStripeWebhook(string $payload, ?string $sigHeader): bool
    {
        if (!$this->stripe->isConfigured()) {
            return false;
        }

        $secret = defined('STRIPE_WEBHOOK_SECRET') ? STRIPE_WEBHOOK_SECRET : '';
        if ($secret !== '' && $sigHeader) {
            if (!$this->verifyStripeSignature($payload, $sigHeader, $secret)) {
                throw new RuntimeException('Invalid webhook signature.');
            }
        }

        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }

        $type = $event['type'] ?? '';
        $object = $event['data']['object'] ?? [];

        if ($type === 'checkout.session.completed') {
            $tenantId = (int) ($object['metadata']['tenant_id'] ?? $object['client_reference_id'] ?? 0);
            $planCode = (string) ($object['metadata']['plan_code'] ?? '');
            if ($tenantId > 0 && $planCode !== '') {
                $plan = $this->subscriptions->findPlanByCode($planCode);
                if ($plan) {
                    $this->activatePlan($tenantId, (int) $plan['id'], 'active');
                    $this->subscriptions->setStripeIds(
                        $tenantId,
                        $object['customer'] ?? null,
                        is_string($object['subscription'] ?? null) ? $object['subscription'] : null
                    );
                    $this->db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
                    $this->logEvent($tenantId, 'payment', 0, 'EUR', $object['id'] ?? null, ['stripe' => true]);
                }
            }
        }

        if ($type === 'invoice.payment_failed') {
            $customerId = $object['customer'] ?? null;
            if ($customerId) {
                $this->markPastDueByCustomer((string) $customerId);
            }
        }

        return true;
    }

    public function completeCheckoutSession(int $tenantId, string $sessionId): bool
    {
        if (!$this->stripe->isConfigured() || $sessionId === '') {
            return false;
        }
        $session = $this->stripe->retrieveSession($sessionId);
        if (($session['payment_status'] ?? '') !== 'paid') {
            return false;
        }
        $refTenant = (int) ($session['metadata']['tenant_id'] ?? $session['client_reference_id'] ?? 0);
        if ($refTenant !== $tenantId) {
            return false;
        }
        $planCode = (string) ($session['metadata']['plan_code'] ?? '');
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            return false;
        }
        $this->activatePlan($tenantId, (int) $plan['id'], 'active');
        $this->db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
        return true;
    }

    public function handlePaystackWebhook(string $payload): bool
    {
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            return false;
        }
        $eventData = $event['data'] ?? [];
        if (($event['event'] ?? '') !== 'charge.success') {
            return true;
        }

        $tenantId = (int) ($eventData['metadata']['tenant_id'] ?? 0);
        $planCode = (string) ($eventData['metadata']['plan_code'] ?? '');
        $reference = (string) ($eventData['reference'] ?? '');

        if ($tenantId > 0 && $planCode !== '') {
            $plan = $this->subscriptions->findPlanByCode($planCode);
            if ($plan) {
                $this->activatePlan($tenantId, (int) $plan['id'], 'active');
                if ($this->hasColumn('tenant_subscriptions', 'payment_provider')) {
                    $this->db->prepare(
                        'UPDATE tenant_subscriptions SET payment_provider = ? WHERE tenant_id = ? ORDER BY id DESC LIMIT 1'
                    )->execute(['paystack', $tenantId]);
                }
                $this->db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
                $amount = ((int) ($eventData['amount'] ?? 0)) / 100;
                $currency = strtoupper((string) ($eventData['currency'] ?? 'NGN'));
                $this->logEvent($tenantId, 'payment', $amount, $currency, $reference, ['paystack' => true]);
            }
        }

        return true;
    }

    public function completePaystackReference(int $tenantId, string $reference): bool
    {
        if (!$this->paystack->isConfigured() || $reference === '') {
            return false;
        }
        $data = $this->paystack->verifyTransaction($reference);
        if (($data['status'] ?? '') !== 'success') {
            return false;
        }
        $refTenant = (int) ($data['metadata']['tenant_id'] ?? 0);
        if ($refTenant !== $tenantId) {
            return false;
        }
        $planCode = (string) ($data['metadata']['plan_code'] ?? '');
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            return false;
        }
        $this->activatePlan($tenantId, (int) $plan['id'], 'active');
        $this->db->prepare("UPDATE tenants SET status = 'active' WHERE id = ?")->execute([$tenantId]);
        return true;
    }

    public function confirmMobileMoney(int $tenantId, string $reference): bool
    {
        return $this->mobileMoney->confirmPayment($reference, $tenantId);
    }

    private function createPaystackCheckout(int $tenantId, string $planCode, string $callbackUrl): array
    {
        $plan = $this->subscriptions->findPlanByCode($planCode);
        if (!$plan) {
            throw new InvalidArgumentException('Plan not found.');
        }

        $tenant = $this->getTenant($tenantId);
        $amountKobo = (int) round((float) $plan['price_monthly'] * 100);
        $email = $this->getTenantBillingEmail($tenantId);

        $init = $this->paystack->initializeTransaction([
            'email' => $email ?: 'billing@retailpos.local',
            'amount' => max($amountKobo, 100),
            'currency' => strtoupper($plan['currency'] ?? 'NGN'),
            'callback_url' => $callbackUrl,
            'metadata' => [
                'tenant_id' => (string) $tenantId,
                'plan_code' => $planCode,
                'tenant_slug' => $tenant['slug'] ?? '',
            ],
        ]);

        $this->logEvent($tenantId, 'checkout', 0, $plan['currency'] ?? 'NGN', $init['reference'] ?? null, [
            'plan_code' => $planCode,
            'provider' => 'paystack',
        ]);

        return [
            'mode' => 'paystack',
            'url' => $init['authorization_url'] ?? null,
            'reference' => $init['reference'] ?? null,
        ];
    }

    private function getTenantBillingEmail(int $tenantId): ?string
    {
        if (!$this->hasColumn('users', 'tenant_id')) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT u.email FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND r.name IN ('super_admin','admin') AND u.deleted_at IS NULL
             ORDER BY u.id ASC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        $email = $stmt->fetchColumn();
        return $email ? (string) $email : null;
    }

    private function activatePlan(int $tenantId, int $planId, string $status): void
    {
        $existing = $this->subscriptions->getActiveSubscription($tenantId);
        if ($existing) {
            $this->db->prepare(
                'UPDATE tenant_subscriptions SET plan_id = ?, status = ?, current_period_start = CURDATE(),
                 current_period_end = DATE_ADD(CURDATE(), INTERVAL 1 MONTH) WHERE id = ?'
            )->execute([$planId, $status, (int) $existing['id']]);
        } else {
            $this->subscriptions->createSubscription($tenantId, $planId, $status, date('Y-m-d', strtotime('+1 month')));
        }
        if ($this->hasColumn('tenants', 'plan_id')) {
            $this->db->prepare('UPDATE tenants SET plan_id = ? WHERE id = ?')->execute([$planId, $tenantId]);
        }

        $planCode = null;
        $stmt = $this->db->prepare('SELECT code FROM subscription_plans WHERE id = ? LIMIT 1');
        $stmt->execute([$planId]);
        $code = $stmt->fetchColumn();
        if ($code) {
            $planCode = (string) $code;
        }

        WebhookDispatcherService::dispatch($this->db, $tenantId, 'subscription.updated', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'plan_code' => $planCode,
            'status' => $status,
        ]);
        WebhookDispatcherService::dispatch($this->db, $tenantId, 'payment.received', [
            'tenant_id' => $tenantId,
            'plan_id' => $planId,
            'plan_code' => $planCode,
            'status' => $status,
        ]);
    }

    private function markPastDueByCustomer(string $customerId): void
    {
        if (!$this->hasColumn('tenant_subscriptions', 'stripe_customer_id')) {
            return;
        }
        $stmt = $this->db->prepare(
            'SELECT tenant_id FROM tenant_subscriptions WHERE stripe_customer_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$customerId]);
        $tenantId = (int) $stmt->fetchColumn();
        if ($tenantId > 0) {
            $this->subscriptions->updateSubscriptionStatus($tenantId, 'past_due');
            $this->db->prepare("UPDATE tenants SET status = 'suspended' WHERE id = ?")->execute([$tenantId]);
            try {
                SaaSPhase6Migrator::ensure($this->db);
                (new TransactionalEmailService($this->db))->sendPaymentFailed($tenantId);
            } catch (Throwable $e) {
                error_log('Payment failed notification: ' . $e->getMessage());
            }
        }
    }

    private function getTenant(int $tenantId): array
    {
        $stmt = $this->db->prepare('SELECT id, slug, name FROM tenants WHERE id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function logEvent(int $tenantId, string $type, float $amount, string $currency, ?string $externalId, array $meta = []): void
    {
        if (!$this->tableExists('billing_events')) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO billing_events (tenant_id, type, amount, currency, external_id, metadata_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $tenantId,
            $type,
            $amount,
            $currency,
            $externalId,
            $meta ? json_encode($meta) : null,
        ]);
    }

    private function verifyStripeSignature(string $payload, string $sigHeader, string $secret): bool
    {
        $parts = [];
        foreach (explode(',', $sigHeader) as $item) {
            [$k, $v] = array_pad(explode('=', trim($item), 2), 2, null);
            if ($k && $v) {
                $parts[$k] = $v;
            }
        }
        $timestamp = $parts['t'] ?? '';
        $signature = $parts['v1'] ?? '';
        if ($timestamp === '' || $signature === '') {
            return false;
        }
        $signed = hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
        return hash_equals($signed, $signature);
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

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/BillingService.php';
require_once __DIR__ . '/../Platform/Services/StripeService.php';
require_once __DIR__ . '/../Platform/Services/PaystackService.php';
require_once __DIR__ . '/../Platform/Services/MobileMoneyBillingService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

final class BillingController
{
    private PDO $db;
    private BillingService $billing;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase2Migrator::ensure($this->db);
        SaaSPhase5Migrator::ensure($this->db);
        $subs = new SubscriptionRepository($this->db);
        $ent = new EntitlementService($this->db, $subs);
        $this->billing = new BillingService(
            $this->db,
            $subs,
            $ent,
            new StripeService(),
            new PaystackService(),
            new MobileMoneyBillingService($this->db, $subs, $ent),
        );
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'POST' && $action === 'webhook') {
            $this->webhook();
            return;
        }
        if ($method === 'POST' && $action === 'paystack-webhook') {
            $this->paystackWebhook();
            return;
        }

        if ($action === 'plans' && $method === 'GET') {
            echo json_encode(['status' => 'success', 'data' => $this->billing->listPlans()]);
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'super_admin', 'manager']);
        $tenantId = TenantScope::id();

        switch ($action) {
            case 'subscription':
            case '':
                if ($method === 'GET') {
                    $this->subscription($tenantId);
                }
                break;
            case 'checkout':
                if ($method === 'POST') {
                    $this->checkout($tenantId);
                }
                break;
            case 'complete':
                if ($method === 'POST') {
                    $this->complete($tenantId);
                }
                break;
            case 'mobile-money':
                if ($method === 'POST') {
                    $this->mobileMoney($tenantId);
                }
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
        }
    }

    private function subscription(int $tenantId): void
    {
        echo json_encode([
            'status' => 'success',
            'data' => $this->billing->subscriptionSummary($tenantId),
            'plans' => $this->billing->listPlans(),
            'providers' => $this->paymentProviders(),
        ]);
    }

    private function checkout(int $tenantId): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        $planCode = trim($data['plan_code'] ?? '');
        $provider = trim($data['provider'] ?? 'stripe');
        $success = trim($data['success_url'] ?? '');
        $cancel = trim($data['cancel_url'] ?? '');

        if ($planCode === '') {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => 'plan_code required']);
            return;
        }

        try {
            $result = $this->billing->createCheckout(
                $tenantId,
                $planCode,
                $success ?: (defined('APP_URL') ? APP_URL . '/public/billing.php?success=1' : '/public/billing.php?success=1'),
                $cancel ?: (defined('APP_URL') ? APP_URL . '/public/billing.php' : '/public/billing.php'),
                $provider,
                trim($data['phone'] ?? ''),
                trim($data['mobile_provider'] ?? 'wave'),
            );
            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function complete(int $tenantId): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        $sessionId = trim($data['session_id'] ?? $_GET['session_id'] ?? '');
        $reference = trim($data['reference'] ?? $_GET['reference'] ?? '');
        $provider = trim($data['provider'] ?? 'stripe');

        $ok = false;
        if ($provider === 'paystack' && $reference !== '') {
            $ok = $this->billing->completePaystackReference($tenantId, $reference);
        } elseif ($provider === 'mobile_money' && $reference !== '') {
            $ok = $this->billing->confirmMobileMoney($tenantId, $reference);
        } else {
            $ok = $this->billing->completeCheckoutSession($tenantId, $sessionId);
        }

        echo json_encode(['status' => $ok ? 'success' : 'error', 'data' => $this->billing->subscriptionSummary($tenantId)]);
    }

    private function mobileMoney(int $tenantId): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        try {
            $result = $this->billing->createCheckout(
                $tenantId,
                trim($data['plan_code'] ?? ''),
                '',
                '',
                'mobile_money',
                trim($data['phone'] ?? ''),
                trim($data['provider'] ?? 'wave'),
            );
            echo json_encode(['status' => 'success', 'data' => $result]);
        } catch (Throwable $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function webhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
        try {
            $this->billing->handleStripeWebhook($payload, $sig);
            echo json_encode(['received' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function paystackWebhook(): void
    {
        $payload = file_get_contents('php://input') ?: '';
        try {
            $this->billing->handlePaystackWebhook($payload);
            echo json_encode(['received' => true]);
        } catch (Throwable $e) {
            http_response_code(400);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    private function paymentProviders(): array
    {
        return [
            'stripe' => defined('STRIPE_SECRET_KEY') && STRIPE_SECRET_KEY !== '',
            'paystack' => defined('PAYSTACK_SECRET_KEY') && PAYSTACK_SECRET_KEY !== '',
            'mobile_money' => true,
        ];
    }
}

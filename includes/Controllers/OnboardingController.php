<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/Services/OnboardingService.php';
require_once __DIR__ . '/../Platform/Services/EmailVerificationService.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';

final class OnboardingController
{
    private PDO $db;
    private OnboardingService $onboarding;
    private EmailVerificationService $emailVerification;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase5Migrator::ensure($this->db);
        $this->onboarding = new OnboardingService($this->db);
        $this->emailVerification = new EmailVerificationService($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'GET' && $action === 'verify') {
            $this->verifyEmail();
            return;
        }

        if ($method === 'POST' && $action === 'resend-verification') {
            AuthMiddleware::apiProtect(['admin', 'super_admin', 'manager', 'cashier']);
            $this->resendVerification();
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'super_admin']);
        $tenantId = TenantScope::id();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        switch ($action) {
            case '':
            case 'state':
                if ($method === 'GET') {
                    echo json_encode(['status' => 'success', 'data' => $this->onboarding->getState($tenantId)]);
                }
                break;
            case 'step':
                if ($method === 'POST') {
                    $this->saveStep($tenantId, $userId);
                } else {
                    $this->notFound();
                }
                break;
            case 'skip':
                if ($method === 'POST') {
                    $this->onboarding->skipToEnd($tenantId);
                    echo json_encode(['status' => 'success', 'data' => $this->onboarding->getState($tenantId)]);
                } else {
                    $this->notFound();
                }
                break;
            default:
                $this->notFound();
        }
    }

    private function verifyEmail(): void
    {
        $token = trim($_GET['token'] ?? '');
        $result = $this->emailVerification->verify($token);
        if (!$result) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid or expired token']);
            return;
        }
        echo json_encode(['status' => 'success', 'message' => 'Email verified', 'data' => $result]);
    }

    private function resendVerification(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $lang = $_SESSION['lang'] ?? 'en';
        $ok = $this->emailVerification->resend($userId, $lang);
        $payload = ['status' => $ok ? 'success' : 'error'];
        if ($ok && defined('APP_DEBUG') && APP_DEBUG) {
            $devUrl = $this->emailVerification->getPendingVerifyUrl($userId);
            if ($devUrl) {
                $payload['dev_verify_url'] = $devUrl;
            }
        }
        echo json_encode($payload);
    }

    private function saveStep(int $tenantId, int $userId): void
    {
        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: $_POST;
        $step = (int) ($data['step'] ?? 0);
        $payload = is_array($data['data'] ?? null) ? $data['data'] : $data;

        try {
            $state = $this->onboarding->saveStep($tenantId, $step, $payload, $userId);
            echo json_encode(['status' => 'success', 'data' => $state]);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function notFound(): void
    {
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }
}

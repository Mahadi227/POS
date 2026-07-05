<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase2Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase3Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase4Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase5Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase6Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase8Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase9Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase10Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase11Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase12Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase13Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase14Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase16Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase17Migrator.php';
require_once __DIR__ . '/../Platform/SaaSPhase18Migrator.php';
require_once __DIR__ . '/../Platform/PlatformSessionAuth.php';
require_once __DIR__ . '/../Platform/Repositories/TenantRepository.php';
require_once __DIR__ . '/../Platform/Repositories/SubscriptionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/LicenseRepository.php';
require_once __DIR__ . '/../Platform/Repositories/BillingRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PaymentRepository.php';
require_once __DIR__ . '/../Platform/Repositories/ModuleRepository.php';
require_once __DIR__ . '/../Platform/Repositories/MarketplaceRepository.php';
require_once __DIR__ . '/../Platform/Repositories/DomainRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformUserRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformRoleRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformPermissionRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformAnalyticsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformReportsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformSupportRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformKnowledgeRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformNotificationsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformEmailsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformSmsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformSecurityRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformLogsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformSettingsRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformAuditRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformBackupRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformIntegrationRepository.php';
require_once __DIR__ . '/../Platform/Repositories/PlatformUpdateRepository.php';
require_once __DIR__ . '/../Platform/Services/PlatformBackupService.php';
require_once __DIR__ . '/../Platform/Services/PlatformDashboardService.php';
require_once __DIR__ . '/../Platform/Services/PlatformTenantService.php';
require_once __DIR__ . '/../Platform/Services/PlatformImpersonationService.php';
require_once __DIR__ . '/../Platform/Services/UsageMeteringService.php';
require_once __DIR__ . '/../Platform/Repositories/UsageMeteringRepository.php';
require_once __DIR__ . '/../Platform/Services/EntitlementService.php';
require_once __DIR__ . '/../Platform/Services/PlatformStatusService.php';
require_once __DIR__ . '/../Platform/Services/PlatformLicenseService.php';
require_once __DIR__ . '/../Platform/Services/MobileMoneyBillingService.php';
require_once __DIR__ . '/../Platform/Services/WebhookDispatcherService.php';

final class PlatformController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase2Migrator::ensure($this->db);
        SaaSPhase3Migrator::ensure($this->db);
        SaaSPhase4Migrator::ensure($this->db);
        SaaSPhase5Migrator::ensure($this->db);
        SaaSPhase6Migrator::ensure($this->db);
        SaaSPhase8Migrator::ensure($this->db);
        SaaSPhase9Migrator::ensure($this->db);
        SaaSPhase10Migrator::ensure($this->db);
        SaaSPhase11Migrator::ensure($this->db);
        SaaSPhase12Migrator::ensure($this->db);
        SaaSPhase13Migrator::ensure($this->db);
        SaaSPhase14Migrator::ensure($this->db);
        SaaSPhase16Migrator::ensure($this->db);
        SaaSPhase17Migrator::ensure($this->db);
        SaaSPhase18Migrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'POST' && $action === 'login') {
            $this->login();
            return;
        }
        if ($method === 'POST' && $action === 'logout') {
            $this->logout();
            return;
        }

        if (!PlatformSessionAuth::isLoggedIn()) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        if ($method === 'GET' && ($action === '' || $action === 'dashboard')) {
            $this->dashboard();
            return;
        }
        if ($action === 'plans') {
            $this->handlePlans($method, $path);
            return;
        }
        if ($action === 'subscriptions') {
            $this->handleSubscriptions($method, $path);
            return;
        }
        if ($action === 'licenses') {
            $this->handleLicenses($method, $path);
            return;
        }
        if ($action === 'billing') {
            $this->handleBilling($method, $path);
            return;
        }
        if ($action === 'payments') {
            $this->handlePayments($method, $path);
            return;
        }
        if ($action === 'modules') {
            $this->handleModules($method, $path);
            return;
        }
        if ($action === 'marketplace') {
            $this->handleMarketplace($method, $path);
            return;
        }
        if ($action === 'domains') {
            $this->handleDomains($method, $path);
            return;
        }
        if ($action === 'users') {
            $this->handleUsers($method, $path);
            return;
        }
        if ($action === 'roles') {
            $this->handleRoles($method, $path);
            return;
        }
        if ($action === 'permissions') {
            $this->handlePermissions($method, $path);
            return;
        }
        if ($action === 'analytics') {
            $this->handleAnalytics($method, $path);
            return;
        }
        if ($action === 'reports') {
            $this->handleReports($method, $path);
            return;
        }
        if ($action === 'support') {
            $this->handleSupport($method, $path);
            return;
        }
        if ($action === 'knowledge') {
            $this->handleKnowledge($method, $path);
            return;
        }
        if ($action === 'notifications') {
            $this->handleNotifications($method, $path);
            return;
        }
        if ($action === 'emails') {
            $this->handleEmails($method, $path);
            return;
        }
        if ($action === 'sms') {
            $this->handleSms($method, $path);
            return;
        }
        if ($action === 'audit') {
            $this->handleAudit($method, $path);
            return;
        }
        if ($action === 'security') {
            $this->handleSecurity($method, $path);
            return;
        }
        if ($action === 'backups') {
            $this->handleBackups($method, $path);
            return;
        }
        if ($action === 'integrations') {
            $this->handleIntegrations($method, $path);
            return;
        }
        if ($action === 'updates') {
            $this->handleUpdates($method, $path);
            return;
        }
        if ($action === 'logs') {
            $this->handleLogs($method, $path);
            return;
        }
        if ($action === 'settings') {
            $this->handleSettings($method, $path);
            return;
        }
        if ($action === 'profile') {
            $this->handleProfile($method, $path);
            return;
        }
        if ($action === 'tenants') {
            $this->handleTenants($method, $path);
            return;
        }
        if ($action === 'incidents') {
            $this->handleIncidents($method, $path);
            return;
        }
        if ($method === 'GET' && $action === 'status') {
            $svc = new PlatformStatusService($this->db);
            echo json_encode(['status' => 'success', 'data' => $svc->getPublicStatus()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleProfile(string $method, array $path): void
    {
        $repo = new PlatformUserRepository($this->db);
        $audit = new PlatformAuditRepository($this->db);
        $userId = PlatformSessionAuth::userId();
        $sub = $path[2] ?? '';
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
            return;
        }

        if ($method === 'GET') {
            $user = $repo->findById($userId);
            if (!$user) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'User not found']);
                return;
            }
            $loginTime = (int) ($_SESSION['platform_login_time'] ?? 0);
            $user['session_started_at'] = $loginTime > 0 ? date('c', $loginTime) : null;
            $user['recent_activity'] = $audit->listForPlatformUser($userId, 12);
            echo json_encode(['status' => 'success', 'data' => $user]);
            return;
        }

        $body = $this->jsonBody();

        if ($method === 'PUT' && ($sub === '' || $sub === 'me')) {
            $name = trim((string) ($body['name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));

            if ($name === '' || strlen($name) < 2) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Name is required']);
                return;
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Valid email is required']);
                return;
            }
            if ($repo->emailTakenByOther($userId, $email)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
                return;
            }

            $existing = $repo->findById($userId);
            if ($existing
                && trim((string) $existing['name']) === $name
                && strtolower(trim((string) $existing['email'])) === $email
            ) {
                echo json_encode(['status' => 'success', 'data' => $existing]);
                return;
            }

            if (!$repo->updateProfile($userId, $name, $email)) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Update failed']);
                return;
            }

            $updated = $repo->findById($userId);
            if ($updated) {
                PlatformSessionAuth::establish(array_merge($updated, [
                    'password_hash' => $repo->getPasswordHash($userId) ?? '',
                ]));
            }

            $audit->log('platform.profile_update', $userId, null, ['email' => $email], $ip);
            echo json_encode(['status' => 'success', 'data' => $updated]);
            return;
        }

        if ($method === 'POST' && $sub === 'password') {
            $current = (string) ($body['current_password'] ?? '');
            $next = (string) ($body['new_password'] ?? '');
            $confirm = (string) ($body['confirm_password'] ?? '');

            if (strlen($next) < 8) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
                return;
            }
            if ($next !== $confirm) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Passwords do not match']);
                return;
            }

            $hash = $repo->getPasswordHash($userId);
            if (!$hash || !password_verify($current, $hash)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect']);
                return;
            }

            if (!$repo->updatePassword($userId, password_hash($next, PASSWORD_DEFAULT))) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Password update failed']);
                return;
            }

            $audit->log('platform.password_change', $userId, null, null, $ip);
            echo json_encode(['status' => 'success', 'message' => 'Password updated']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleTenants(string $method, array $path): void
    {
        $tenantId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && $tenantId <= 0) {
            $this->tenants();
            return;
        }
        if ($method === 'GET' && $tenantId > 0 && $subAction === '') {
            $this->tenantDetail($tenantId);
            return;
        }

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tenant id required']);
            return;
        }

        $body = $this->jsonBody();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $platformUserId = PlatformSessionAuth::userId();
        $service = $this->tenantService();
        $impersonation = new PlatformImpersonationService($this->db, new PlatformAuditRepository($this->db));

        try {
            if ($method === 'POST' && $subAction === 'status') {
                $status = trim((string) ($body['status'] ?? ''));
                $service->updateStatus($tenantId, $status, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Status updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'trial') {
                $days = (int) ($body['days'] ?? 14);
                $newEnd = $service->extendTrial($tenantId, $days, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'trial_ends_at' => $newEnd]);
                return;
            }
            if ($method === 'POST' && $subAction === 'plan') {
                $planCode = trim((string) ($body['plan_code'] ?? ''));
                $service->changePlan($tenantId, $planCode, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Plan updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'modules') {
                $overrides = is_array($body['overrides'] ?? null) ? $body['overrides'] : [];
                $parsed = [];
                foreach ($overrides as $k => $v) {
                    if ($v === 'inherit' || $v === null) {
                        $parsed[$k] = null;
                    } else {
                        $parsed[$k] = (bool) $v;
                    }
                }
                $service->setModuleOverrides($tenantId, $parsed, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Modules updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'feature-flags') {
                $flags = is_array($body['flags'] ?? null) ? $body['flags'] : [];
                $parsed = [];
                foreach ($flags as $k => $v) {
                    $parsed[$k] = (bool) $v;
                }
                $service->setFeatureFlags($tenantId, $parsed, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Feature flags updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'impersonate') {
                $result = $impersonation->impersonate($tenantId, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'data' => $result]);
                return;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function login(): void
    {
        $data = $this->jsonBody();
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($data['password'] ?? '');
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? (string) $_SERVER['HTTP_USER_AGENT'] : null;

        $security = new PlatformSecurityRepository($this->db);
        $audit = new PlatformAuditRepository($this->db);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            $security->recordLoginAttempt($email ?: 'invalid', null, 'failed', $ip, $userAgent);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        if ($security->isLocked($email, $ip)) {
            $security->recordLoginAttempt($email, null, 'locked', $ip, $userAgent);
            echo json_encode(['status' => 'error', 'message' => 'Account temporarily locked. Try again later.']);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT id, email, password_hash, name, role, is_active
             FROM platform_users WHERE email = ? LIMIT 1'
        );
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !(int) ($user['is_active'] ?? 0) || !password_verify($password, $user['password_hash'])) {
            $security->recordLoginAttempt($email, isset($user['id']) ? (int) $user['id'] : null, 'failed', $ip, $userAgent);
            $audit->log('platform.login_failed', null, null, ['email' => $email], $ip);
            echo json_encode(['status' => 'error', 'message' => 'Invalid credentials']);
            return;
        }

        PlatformSessionAuth::establish($user);
        $userId = (int) $user['id'];
        $this->db->prepare('UPDATE platform_users SET last_login = NOW() WHERE id = ?')
            ->execute([$userId]);

        $security->recordLoginAttempt($email, $userId, 'success', $ip, $userAgent);
        $audit->log('platform.login_success', $userId, null, null, $ip);

        echo json_encode([
            'status' => 'success',
            'redirect' => 'index.php',
            'user' => [
                'name' => $user['name'],
                'role' => $user['role'],
            ],
        ]);
    }

    private function logout(): void
    {
        $userId = PlatformSessionAuth::userId();
        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
        if ($userId) {
            $audit = new PlatformAuditRepository($this->db);
            $audit->log('platform.logout', $userId, null, null, $ip);
        }
        PlatformSessionAuth::clear();
        echo json_encode(['status' => 'success']);
    }

    private function dashboard(): void
    {
        $service = new PlatformDashboardService($this->db, new TenantRepository($this->db));
        $summary = $service->summary();
        $summary['schema_version'] = SaaSPhase3Migrator::VERSION;
        echo json_encode(['status' => 'success', 'data' => $summary]);
    }

    private function handlePlans(string $method, array $path): void
    {
        $repo = new SubscriptionRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->planStats()]);
            return;
        }
        if ($method === 'GET' && $sub === 'catalog') {
            echo json_encode(['status' => 'success', 'data' => $repo->listPlansCatalog()]);
            return;
        }
        if ($method === 'GET' && $sub === '') {
            echo json_encode(['status' => 'success', 'data' => $repo->listActivePlans()]);
            return;
        }
        if ($method === 'PUT' && ctype_digit((string) $sub)) {
            $planId = (int) $sub;
            $body = $this->jsonBody();
            $existing = $repo->findPlanById($planId);
            if ($existing === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Plan not found']);
                return;
            }

            $updated = $repo->updatePlan($planId, [
                'name' => $body['name'] ?? null,
                'price_monthly' => $body['price_monthly'] ?? null,
                'currency' => $body['currency'] ?? null,
                'max_stores' => array_key_exists('max_stores', $body) ? $body['max_stores'] : null,
                'max_users' => array_key_exists('max_users', $body) ? $body['max_users'] : null,
                'is_active' => $body['is_active'] ?? null,
            ]);

            if ($updated === null) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Update failed']);
                return;
            }

            $audit = new PlatformAuditRepository($this->db);
            $audit->log('plan.update', PlatformSessionAuth::userId(), null, [
                'plan_id' => $planId,
                'code' => $updated['code'] ?? null,
                'price_monthly' => $updated['price_monthly'] ?? null,
                'currency' => $updated['currency'] ?? null,
            ], $_SERVER['REMOTE_ADDR'] ?? null);

            $modules = json_decode((string) ($updated['modules_json'] ?? '{}'), true);
            $updated['modules'] = is_array($modules) ? $modules : [];
            unset($updated['modules_json']);

            echo json_encode(['status' => 'success', 'data' => $updated]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleLicenses(string $method, array $path): void
    {
        $repo = new LicenseRepository($this->db);
        $service = new PlatformLicenseService($this->db, $repo);
        $licenseId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->licenseStats()]);
            return;
        }

        if ($method === 'GET' && $licenseId <= 0) {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $type = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listLicenses($perPage, $offset, $search ?: null, $status ?: null, $type ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'status' => $status,
                    'type' => $type,
                ],
            ]);
            return;
        }

        $body = $this->jsonBody();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $platformUserId = PlatformSessionAuth::userId();
        $audit = new PlatformAuditRepository($this->db);

        try {
            if ($method === 'POST' && $licenseId <= 0) {
                $tenantId = isset($body['tenant_id']) ? (int) $body['tenant_id'] : null;
                if ($tenantId !== null && $tenantId <= 0) {
                    $tenantId = null;
                }
                $result = $service->issue(
                    $tenantId,
                    trim((string) ($body['license_type'] ?? 'cloud')),
                    isset($body['plan_code']) ? trim((string) $body['plan_code']) : null,
                    isset($body['max_seats']) ? (int) $body['max_seats'] : null,
                    isset($body['notes']) ? trim((string) $body['notes']) : null,
                    $platformUserId,
                    isset($body['expires_at']) ? trim((string) $body['expires_at']) : null,
                );
                $audit->log('license.issue', $platformUserId, $tenantId, [
                    'license_id' => $result['id'],
                    'prefix' => $result['prefix'],
                    'type' => $body['license_type'] ?? 'cloud',
                ], $ip);
                echo json_encode(['status' => 'success', 'data' => $result]);
                return;
            }

            if ($method === 'POST' && $licenseId > 0 && $subAction === 'revoke') {
                $row = $repo->findById($licenseId);
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'License not found']);
                    return;
                }
                if (!$service->revoke($licenseId)) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'License cannot be revoked']);
                    return;
                }
                $audit->log('license.revoke', $platformUserId, isset($row['tenant_id']) ? (int) $row['tenant_id'] : null, [
                    'license_id' => $licenseId,
                    'prefix' => $row['key_prefix'] ?? null,
                ], $ip);
                echo json_encode(['status' => 'success', 'message' => 'License revoked']);
                return;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleBilling(string $method, array $path): void
    {
        $repo = new BillingRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->billingStats()]);
            return;
        }

        if ($method === 'GET' && $sub === '') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $type = isset($_GET['type']) ? trim((string) $_GET['type']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listEvents($perPage, $offset, $search ?: null, $type ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'type' => $type,
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handlePayments(string $method, array $path): void
    {
        $repo = new PaymentRepository($this->db);
        $sub = $path[2] ?? '';
        $mmId = isset($path[3]) && ctype_digit((string) $path[3]) ? (int) $path[3] : 0;
        $subAction = $path[4] ?? ($path[3] ?? '');

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->paymentStats()]);
            return;
        }

        if ($method === 'GET' && $sub === '') {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $provider = isset($_GET['provider']) ? trim((string) $_GET['provider']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listPayments($perPage, $offset, $search ?: null, $status ?: null, $provider ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'status' => $status,
                    'provider' => $provider,
                ],
            ]);
            return;
        }

        if ($method === 'POST' && $sub === 'mobile-money' && $mmId > 0 && $subAction === 'confirm') {
            $row = $repo->findMobileMoney($mmId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Payment not found']);
                return;
            }
            if (($row['status'] ?? '') !== 'pending') {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Payment is not pending']);
                return;
            }

            $tenantId = (int) ($row['tenant_id'] ?? 0);
            $reference = (string) ($row['reference'] ?? '');
            $subs = new SubscriptionRepository($this->db);
            $mmSvc = new MobileMoneyBillingService($this->db, $subs, new EntitlementService($this->db, $subs));
            $ok = $mmSvc->confirmPayment($reference, $tenantId);

            if (!$ok) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Could not confirm payment']);
                return;
            }

            $audit = new PlatformAuditRepository($this->db);
            $audit->log('payment.mobile_money_confirm', PlatformSessionAuth::userId(), $tenantId, [
                'payment_id' => $mmId,
                'reference' => $reference,
            ], $_SERVER['REMOTE_ADDR'] ?? null);

            echo json_encode(['status' => 'success', 'message' => 'Payment confirmed']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleModules(string $method, array $path): void
    {
        $repo = new ModuleRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->moduleStats()]);
            return;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'catalog')) {
            echo json_encode(['status' => 'success', 'data' => $repo->catalog()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleMarketplace(string $method, array $path): void
    {
        $repo = new MarketplaceRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->marketplaceStats()]);
            return;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'catalog')) {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $category = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listApps($perPage, $offset, $search ?: null, $category ?: null, $status ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'category' => $category,
                    'status' => $status,
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleDomains(string $method, array $path): void
    {
        $repo = new DomainRepository($this->db);
        $sub = $path[2] ?? '';
        $domainId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->domainStats()]);
            return;
        }

        if ($method === 'GET' && $domainId <= 0) {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $kind = isset($_GET['kind']) ? trim((string) $_GET['kind']) : null;
            $verified = isset($_GET['verified']) ? trim((string) $_GET['verified']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listDomains($perPage, $offset, $search ?: null, $kind ?: null, $verified ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'kind' => $kind,
                    'verified' => $verified,
                ],
            ]);
            return;
        }

        if ($method === 'POST' && $domainId > 0 && $subAction === 'verify') {
            $row = $repo->findById($domainId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Domain not found']);
                return;
            }
            if (!$repo->verify($domainId)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Domain already verified']);
                return;
            }

            $audit = new PlatformAuditRepository($this->db);
            $audit->log('domain.verify', PlatformSessionAuth::userId(), (int) ($row['tenant_id'] ?? 0), [
                'domain_id' => $domainId,
                'hostname' => $row['hostname'] ?? null,
            ], $_SERVER['REMOTE_ADDR'] ?? null);

            echo json_encode(['status' => 'success', 'message' => 'Domain verified']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleUsers(string $method, array $path): void
    {
        $repo = new PlatformUserRepository($this->db);
        $sub = $path[2] ?? '';
        $userId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $currentUserId = PlatformSessionAuth::userId();
        $audit = new PlatformAuditRepository($this->db);
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->userStats()]);
            return;
        }

        if ($method === 'GET' && $userId <= 0) {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 50)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $role = isset($_GET['role']) ? trim((string) $_GET['role']) : null;
            $active = isset($_GET['active']) ? trim((string) $_GET['active']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listUsers($perPage, $offset, $search ?: null, $role ?: null, $active ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'role' => $role,
                    'active' => $active,
                    'current_user_id' => $currentUserId,
                ],
            ]);
            return;
        }

        $body = $this->jsonBody();

        if ($method === 'POST' && $userId <= 0) {
            $name = trim((string) ($body['name'] ?? ''));
            $email = strtolower(trim((string) ($body['email'] ?? '')));
            $password = (string) ($body['password'] ?? '');
            $role = trim((string) ($body['role'] ?? 'platform_admin'));

            if ($name === '' || strlen($name) < 2) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Name is required']);
                return;
            }
            if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Valid email is required']);
                return;
            }
            if (strlen($password) < 8) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Password must be at least 8 characters']);
                return;
            }
            if (!in_array($role, ['platform_admin', 'support'], true)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Invalid role']);
                return;
            }
            if ($repo->findByEmail($email)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Email already in use']);
                return;
            }

            $hash = password_hash($password, PASSWORD_DEFAULT);
            $newId = $repo->create($name, $email, $hash, $role);
            $audit->log('platform_user.create', $currentUserId, null, [
                'user_id' => $newId,
                'email' => $email,
                'role' => $role,
            ], $ip);

            echo json_encode([
                'status' => 'success',
                'data' => $repo->findById($newId),
            ]);
            return;
        }

        if ($method === 'POST' && $userId > 0 && $subAction === 'toggle-active') {
            $row = $repo->findById($userId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'User not found']);
                return;
            }

            $isActive = (int) ($row['is_active'] ?? 0) === 1;
            $nextActive = !$isActive;

            if (!$nextActive && $userId === $currentUserId) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Cannot deactivate your own account']);
                return;
            }
            if (!$nextActive && ($row['role'] ?? '') === 'platform_admin' && $repo->countActiveAdmins() <= 1) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Cannot deactivate the last platform admin']);
                return;
            }

            if (!$repo->setActive($userId, $nextActive)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Status unchanged']);
                return;
            }

            $audit->log(
                $nextActive ? 'platform_user.activate' : 'platform_user.deactivate',
                $currentUserId,
                null,
                ['user_id' => $userId, 'email' => $row['email'] ?? null],
                $ip
            );

            echo json_encode([
                'status' => 'success',
                'data' => $repo->findById($userId),
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleRoles(string $method, array $path): void
    {
        $repo = new PlatformRoleRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->roleStats()]);
            return;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'catalog')) {
            echo json_encode(['status' => 'success', 'data' => $repo->catalog()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handlePermissions(string $method, array $path): void
    {
        $repo = new PlatformPermissionRepository();
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->permissionStats()]);
            return;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'catalog')) {
            echo json_encode(['status' => 'success', 'data' => $repo->catalog()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleAnalytics(string $method, array $path): void
    {
        $repo = new PlatformAnalyticsRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && $sub === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->stats()]);
            return;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'overview')) {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'stats' => $repo->stats(),
                    'overview' => $repo->overview(),
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleReports(string $method, array $path): void
    {
        $repo = new PlatformReportsRepository($this->db);
        $reportKey = $path[2] ?? '';
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && ($reportKey === '' || $reportKey === 'catalog')) {
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'stats' => $repo->reportStats(),
                    'reports' => $repo->catalog(),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $reportKey !== '' && $subAction === 'export') {
            $csv = $repo->exportCsv($reportKey);
            if ($csv === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Report not found']);
                return;
            }
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $reportKey . '-' . date('Y-m-d') . '.csv"');
            echo $csv;
            return;
        }

        if ($method === 'GET' && $reportKey !== '' && ($subAction === '' || $subAction === 'preview')) {
            $preview = $repo->preview($reportKey, 25);
            if ($preview === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Report not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $preview]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleSupport(string $method, array $path): void
    {
        $repo = new PlatformSupportRepository($this->db);
        $sub = $path[2] ?? '';
        $ticketId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $platformUserId = PlatformSessionAuth::userId();

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'tickets') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $priority = isset($_GET['priority']) ? trim((string) $_GET['priority']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'tickets' => $repo->listTickets($limit, $offset, $search, $status, $priority),
                    'total' => $repo->countTickets($search, $status, $priority),
                    'meta' => $repo->ticketsPage(),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $ticketId > 0 && $subAction === '') {
            $ticket = $repo->findTicket($ticketId);
            if ($ticket === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Ticket not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $ticket]);
            return;
        }

        if ($method === 'POST' && $sub === 'tickets') {
            $body = $this->jsonBody();
            $id = $repo->createTicket($body, $platformUserId);
            echo json_encode(['status' => 'success', 'id' => $id]);
            return;
        }

        if ($method === 'POST' && $ticketId > 0 && $subAction === 'status') {
            $body = $this->jsonBody();
            $ok = $repo->updateStatus($ticketId, trim((string) ($body['status'] ?? '')));
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        if ($method === 'POST' && $ticketId > 0 && $subAction === 'assign') {
            $body = $this->jsonBody();
            $assignee = isset($body['assigned_to']) ? (int) $body['assigned_to'] : 0;
            $ok = $repo->assignTicket($ticketId, $assignee > 0 ? $assignee : null);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        if ($method === 'POST' && $ticketId > 0 && $subAction === 'reply') {
            $body = $this->jsonBody();
            $message = trim((string) ($body['message'] ?? ''));
            $internal = !empty($body['is_internal']);
            $id = $repo->addReply($ticketId, $message, $platformUserId, $internal);
            echo json_encode(['status' => 'success', 'id' => $id]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleKnowledge(string $method, array $path): void
    {
        $repo = new PlatformKnowledgeRepository($this->db);
        $sub = $path[2] ?? '';
        $articleId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $platformUserId = PlatformSessionAuth::userId();

        if ($method === 'GET' && ($sub === '' || $sub === 'catalog')) {
            echo json_encode(['status' => 'success', 'data' => $repo->catalog()]);
            return;
        }

        if ($method === 'GET' && $sub === 'articles') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $category = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
            $audience = isset($_GET['audience']) ? trim((string) $_GET['audience']) : null;
            $published = isset($_GET['published']) ? trim((string) $_GET['published']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'articles' => $repo->listArticles($limit, $offset, $search, $category, $audience, $published),
                    'total' => $repo->countArticles($search, $category, $audience, $published),
                    'categories' => $repo->listCategories(),
                    'stats' => $repo->stats(),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $articleId > 0 && $subAction === '') {
            $article = $repo->findArticle($articleId);
            if ($article === null) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Article not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $article]);
            return;
        }

        if ($method === 'POST' && $sub === 'articles') {
            $body = $this->jsonBody();
            $id = $repo->createArticle($body, $platformUserId);
            echo json_encode(['status' => 'success', 'id' => $id]);
            return;
        }

        if ($method === 'PUT' && $articleId > 0 && $subAction === '') {
            $body = $this->jsonBody();
            $ok = $repo->updateArticle($articleId, $body);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        if ($method === 'POST' && $articleId > 0 && $subAction === 'publish') {
            $body = $this->jsonBody();
            $ok = $repo->setPublished($articleId, !empty($body['is_published']));
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleNotifications(string $method, array $path): void
    {
        $repo = new PlatformNotificationsRepository($this->db);
        $sub = $path[2] ?? '';
        $broadcastId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $platformUserId = PlatformSessionAuth::userId();

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'POST' && $sub === 'broadcasts') {
            $body = $this->jsonBody();
            $id = $repo->createBroadcast($body, $platformUserId);
            echo json_encode(['status' => 'success', 'id' => $id]);
            return;
        }

        if ($method === 'POST' && $broadcastId > 0 && $subAction === 'send') {
            $ok = $repo->sendBroadcast($broadcastId);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleEmails(string $method, array $path): void
    {
        $repo = new PlatformEmailsRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'logs') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $template = isset($_GET['template']) ? trim((string) $_GET['template']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'logs' => $repo->listLogs($limit, 0, $search, $template),
                    'stats' => $repo->stats(),
                    'templates' => PlatformEmailsRepository::TEMPLATES,
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleSms(string $method, array $path): void
    {
        $repo = new PlatformSmsRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'logs') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $template = isset($_GET['template']) ? trim((string) $_GET['template']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'logs' => $repo->listLogs($limit, 0, $search, $template),
                    'stats' => $repo->stats(),
                    'templates' => PlatformSmsRepository::TEMPLATES,
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleAudit(string $method, array $path): void
    {
        $repo = new PlatformAuditRepository($this->db);
        $sub = $path[2] ?? '';
        $logId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'logs') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $action = isset($_GET['action']) ? trim((string) $_GET['action']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'logs' => $repo->listLogs($limit, 0, $search, $action),
                    'stats' => $repo->stats(),
                    'actions' => $repo->knownActions(),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $logId > 0) {
            $row = $repo->findById($logId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $row]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleSecurity(string $method, array $path): void
    {
        $repo = new PlatformSecurityRepository($this->db);
        $sub = $path[2] ?? '';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'login-attempts') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'attempts' => $repo->listLoginAttempts($limit, 0, $search, $status),
                    'stats' => $repo->stats(),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $sub === 'events') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $severity = isset($_GET['severity']) ? trim((string) $_GET['severity']) : null;
            $eventType = isset($_GET['event_type']) ? trim((string) $_GET['event_type']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'events' => $repo->listEvents($limit, 0, $search, $severity, $eventType),
                    'stats' => $repo->stats(),
                ],
            ]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleBackups(string $method, array $path): void
    {
        $repo = new PlatformBackupRepository($this->db);
        $audit = new PlatformAuditRepository($this->db);
        $sub = $path[2] ?? '';
        $backupId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $userId = PlatformSessionAuth::userId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $isAdmin = ($_SESSION['platform_role'] ?? '') === 'platform_admin';

        if ($method === 'GET' && $backupId > 0 && $subAction === 'download') {
            $meta = $repo->findFileMeta($backupId);
            if (!$meta || ($meta['status'] ?? '') !== 'completed') {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Backup file not found']);
                return;
            }
            $filePath = (string) ($meta['file_path'] ?? '');
            if ($filePath === '' || !is_file($filePath)) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Backup file missing on disk']);
                return;
            }
            $ext = pathinfo($filePath, PATHINFO_EXTENSION) ?: 'sql';
            $filename = preg_replace('/[^a-zA-Z0-9._-]+/', '_', (string) ($meta['label'] ?? 'backup')) . '.' . $ext;
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Length: ' . filesize($filePath));
            readfile($filePath);
            exit;
        }

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'list') {
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $scope = isset($_GET['scope']) ? trim((string) $_GET['scope']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            echo json_encode([
                'status' => 'success',
                'data' => $repo->list($limit, $offset, $status ?: null, $scope ?: null),
                'stats' => $repo->stats(),
            ]);
            return;
        }

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Platform admin only']);
            return;
        }

        if ($method === 'POST' && $sub === '') {
            $body = $this->jsonBody();
            $scope = trim((string) ($body['scope'] ?? 'full'));
            if (!in_array($scope, ['full', 'schema', 'tenant'], true)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Invalid backup scope']);
                return;
            }

            $tenantId = isset($body['tenant_id']) && $body['tenant_id'] !== ''
                ? (int) $body['tenant_id'] : null;
            if ($scope === 'tenant' && ($tenantId === null || $tenantId <= 0)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Tenant required for tenant backup']);
                return;
            }
            if ($scope !== 'tenant') {
                $tenantId = null;
            }

            $label = trim((string) ($body['label'] ?? ''));
            if ($label === '') {
                $label = ucfirst($scope) . ' backup ' . date('Y-m-d H:i');
            }

            $id = $repo->create($label, $scope, $tenantId, $userId);
            $ext = $scope === 'tenant' ? 'json' : 'sql';
            $filename = sprintf('backup_%d_%s.%s', $id, date('Ymd_His'), $ext);
            $destPath = PlatformBackupService::storageDir() . DIRECTORY_SEPARATOR . $filename;

            $service = new PlatformBackupService($this->db);
            $result = $service->run($id, $scope, $tenantId, $destPath);

            if (!$result['ok']) {
                $audit->log('platform.backup_failed', $userId, $tenantId, [
                    'backup_id' => $id,
                    'scope' => $scope,
                    'error' => $result['error'],
                ], $ip);
                http_response_code(500);
                echo json_encode([
                    'status' => 'error',
                    'message' => $result['error'] ?? 'Backup failed',
                    'data' => $repo->findById($id),
                ]);
                return;
            }

            $audit->log('platform.backup_completed', $userId, $tenantId, [
                'backup_id' => $id,
                'scope' => $scope,
                'size_bytes' => $result['size'],
            ], $ip);

            echo json_encode(['status' => 'success', 'data' => $repo->findById($id)]);
            return;
        }

        if ($method === 'DELETE' && $backupId > 0) {
            if (!$repo->delete($backupId)) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Backup not found']);
                return;
            }
            $audit->log('platform.backup_deleted', $userId, null, ['backup_id' => $backupId], $ip);
            echo json_encode(['status' => 'success']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleIntegrations(string $method, array $path): void
    {
        $repo = new PlatformIntegrationRepository($this->db);
        $audit = new PlatformAuditRepository($this->db);
        $sub = $path[2] ?? '';
        $itemId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $userId = PlatformSessionAuth::userId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $isAdmin = ($_SESSION['platform_role'] ?? '') === 'platform_admin';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'providers') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $category = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            echo json_encode([
                'status' => 'success',
                'data' => $repo->listProviders($search ?: null, $category ?: null, $status ?: null),
                'stats' => $repo->stats(),
            ]);
            return;
        }

        if ($method === 'GET' && $sub === 'connections') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $category = isset($_GET['category']) ? trim((string) $_GET['category']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            echo json_encode([
                'status' => 'success',
                'data' => $repo->listConnections($limit, $offset, $search ?: null, $status ?: null, $category ?: null),
                'stats' => $repo->stats(),
            ]);
            return;
        }

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Platform admin only']);
            return;
        }

        if ($method === 'PUT' && $itemId > 0 && $subAction === 'status') {
            $body = $this->jsonBody();
            $type = trim((string) ($body['type'] ?? 'connection'));
            $status = trim((string) ($body['status'] ?? ''));

            if ($type === 'provider') {
                $row = $repo->findProviderById($itemId);
                if (!$row) {
                    http_response_code(404);
                    echo json_encode(['status' => 'error', 'message' => 'Provider not found']);
                    return;
                }
                if (!$repo->setProviderStatus($itemId, $status)) {
                    http_response_code(422);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid provider status']);
                    return;
                }
                $audit->log('platform.integration_provider_status', $userId, null, [
                    'provider_id' => $itemId,
                    'slug' => $row['slug'] ?? null,
                    'status' => $status,
                ], $ip);
                echo json_encode(['status' => 'success', 'data' => $repo->findProviderById($itemId)]);
                return;
            }

            $row = $repo->findConnectionById($itemId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Connection not found']);
                return;
            }
            if (!$repo->setConnectionStatus($itemId, $status)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Invalid connection status']);
                return;
            }
            $audit->log('platform.integration_connection_status', $userId, (int) ($row['tenant_id'] ?? 0), [
                'connection_id' => $itemId,
                'provider' => $row['provider_slug'] ?? null,
                'status' => $status,
            ], $ip);
            echo json_encode(['status' => 'success', 'data' => $repo->findConnectionById($itemId)]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleUpdates(string $method, array $path): void
    {
        $repo = new PlatformUpdateRepository($this->db);
        $audit = new PlatformAuditRepository($this->db);
        $sub = $path[2] ?? '';
        $itemId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $userId = PlatformSessionAuth::userId();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $isAdmin = ($_SESSION['platform_role'] ?? '') === 'platform_admin';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'migrations') {
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => $repo->listMigrations($limit),
                'stats' => $repo->stats(),
            ]);
            return;
        }

        if ($method === 'GET' && $itemId > 0 && $subAction === '') {
            $row = $repo->findById($itemId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Release not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $row]);
            return;
        }

        if ($method === 'GET' && $sub === 'list') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;
            $type = isset($_GET['type']) ? trim((string) $_GET['type']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            $offset = max(0, (int) ($_GET['offset'] ?? 0));
            echo json_encode([
                'status' => 'success',
                'data' => $repo->listReleases($limit, $offset, $search ?: null, $status ?: null, $type ?: null),
                'stats' => $repo->stats(),
                'current_version' => $repo->currentVersion(),
            ]);
            return;
        }

        if (!$isAdmin) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Platform admin only']);
            return;
        }

        if ($method === 'POST' && $sub === '') {
            $body = $this->jsonBody();
            $version = trim((string) ($body['version'] ?? ''));
            $title = trim((string) ($body['title'] ?? ''));
            if ($version === '' || $title === '') {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Version and title are required']);
                return;
            }
            $id = $repo->create($body, $userId);
            if ($id <= 0) {
                http_response_code(500);
                echo json_encode(['status' => 'error', 'message' => 'Could not create release']);
                return;
            }
            $audit->log('platform.release_created', $userId, null, [
                'release_id' => $id,
                'version' => $version,
            ], $ip);
            echo json_encode(['status' => 'success', 'data' => $repo->findById($id)]);
            return;
        }

        if ($method === 'PUT' && $itemId > 0 && $subAction === '') {
            $body = $this->jsonBody();
            if (!$repo->update($itemId, $body)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Release not found or already published']);
                return;
            }
            $audit->log('platform.release_updated', $userId, null, ['release_id' => $itemId], $ip);
            echo json_encode(['status' => 'success', 'data' => $repo->findById($itemId)]);
            return;
        }

        if ($method === 'POST' && $itemId > 0 && $subAction === 'publish') {
            $row = $repo->findById($itemId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Release not found']);
                return;
            }
            if (!$repo->publish($itemId, $userId)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Release cannot be published']);
                return;
            }
            $audit->log('platform.release_published', $userId, null, [
                'release_id' => $itemId,
                'version' => $row['version'] ?? null,
            ], $ip);
            echo json_encode(['status' => 'success', 'data' => $repo->findById($itemId)]);
            return;
        }

        if ($method === 'DELETE' && $itemId > 0) {
            $row = $repo->findById($itemId);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Release not found']);
                return;
            }
            if (!$repo->delete($itemId)) {
                http_response_code(422);
                echo json_encode(['status' => 'error', 'message' => 'Only draft releases can be deleted']);
                return;
            }
            $audit->log('platform.release_deleted', $userId, null, [
                'release_id' => $itemId,
                'version' => $row['version'] ?? null,
            ], $ip);
            echo json_encode(['status' => 'success']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleLogs(string $method, array $path): void
    {
        $repo = new PlatformLogsRepository($this->db);
        $sub = $path[2] ?? '';
        $ref = isset($path[2]) && str_contains((string) $path[2], ':') ? (string) $path[2] : '';

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'GET' && $sub === 'entries') {
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $channel = isset($_GET['channel']) ? trim((string) $_GET['channel']) : null;
            $level = isset($_GET['level']) ? trim((string) $_GET['level']) : null;
            $limit = min(100, max(1, (int) ($_GET['per_page'] ?? 50)));
            echo json_encode([
                'status' => 'success',
                'data' => [
                    'entries' => $repo->listEntries($limit, $channel, $level, $search),
                    'stats' => $repo->stats(),
                    'channels' => array_keys(PlatformLogsRepository::CHANNELS),
                ],
            ]);
            return;
        }

        if ($method === 'GET' && $ref !== '') {
            $row = $repo->findEntry($ref);
            if (!$row) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Not found']);
                return;
            }
            echo json_encode(['status' => 'success', 'data' => $row]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleSettings(string $method, array $path): void
    {
        $repo = new PlatformSettingsRepository($this->db);
        $sub = $path[2] ?? '';
        $platformUserId = PlatformSessionAuth::userId();

        if ($method === 'GET' && ($sub === '' || $sub === 'dashboard')) {
            echo json_encode(['status' => 'success', 'data' => $repo->dashboard()]);
            return;
        }

        if ($method === 'PUT' && $sub === 'values') {
            $body = $this->jsonBody();
            $values = is_array($body['settings'] ?? null) ? $body['settings'] : [];
            $updated = $repo->updateMany($values, $platformUserId);
            $audit = new PlatformAuditRepository($this->db);
            $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
            $audit->log('platform.settings_update', $platformUserId, null, ['keys' => array_keys($updated)], $ip);
            echo json_encode(['status' => 'success', 'data' => ['updated' => $updated]]);
            return;
        }

        if ($method === 'PUT' && $sub === 'feature-flags') {
            $body = $this->jsonBody();
            $flags = is_array($body['flags'] ?? null) ? $body['flags'] : [];
            $count = $repo->updateFeatureFlags($flags);
            $audit = new PlatformAuditRepository($this->db);
            $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : null;
            $audit->log('platform.feature_flags_update', $platformUserId, null, ['count' => $count], $ip);
            echo json_encode(['status' => 'success', 'data' => ['updated' => $count]]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function handleSubscriptions(string $method, array $path): void
    {
        $tenantId = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';
        $repo = new SubscriptionRepository($this->db);

        if ($method === 'GET' && ($path[2] ?? '') === 'stats') {
            echo json_encode(['status' => 'success', 'data' => $repo->subscriptionStats()]);
            return;
        }

        if ($method === 'GET' && $tenantId <= 0) {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
            $offset = ($page - 1) * $perPage;
            $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
            $subStatus = isset($_GET['sub_status']) ? trim((string) $_GET['sub_status']) : null;
            $planCode = isset($_GET['plan']) ? trim((string) $_GET['plan']) : null;

            echo json_encode([
                'status' => 'success',
                'data' => $repo->listSubscriptions($perPage, $offset, $search ?: null, $subStatus ?: null, $planCode ?: null),
                'meta' => [
                    'page' => $page,
                    'per_page' => $perPage,
                    'q' => $search,
                    'sub_status' => $subStatus,
                    'plan' => $planCode,
                ],
            ]);
            return;
        }

        if ($tenantId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Tenant id required']);
            return;
        }

        $body = $this->jsonBody();
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $platformUserId = PlatformSessionAuth::userId();
        $service = $this->tenantService();

        try {
            if ($method === 'POST' && $subAction === 'plan') {
                $planCode = trim((string) ($body['plan_code'] ?? ''));
                $service->changePlan($tenantId, $planCode, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Plan updated']);
                return;
            }
            if ($method === 'POST' && $subAction === 'status') {
                $status = trim((string) ($body['status'] ?? ''));
                $service->updateStatus($tenantId, $status, $platformUserId, $ip);
                echo json_encode(['status' => 'success', 'message' => 'Subscription status updated']);
                return;
            }
        } catch (InvalidArgumentException $e) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        } catch (RuntimeException $e) {
            http_response_code(422);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function tenants(): void
    {
        $repo = new TenantRepository($this->db);
        $page = max(1, (int) ($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int) ($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;
        $search = isset($_GET['q']) ? trim((string) $_GET['q']) : null;
        $status = isset($_GET['status']) ? trim((string) $_GET['status']) : null;

        echo json_encode([
            'status' => 'success',
            'data' => $repo->listTenants($perPage, $offset, $search ?: null, $status ?: null),
            'meta' => ['page' => $page, 'per_page' => $perPage, 'q' => $search, 'status' => $status],
        ]);
    }

    private function tenantDetail(int $tenantId): void
    {
        $detail = $this->tenantService()->getDetail($tenantId);
        if (!$detail) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Tenant not found']);
            return;
        }
        echo json_encode(['status' => 'success', 'data' => $detail]);
    }

    private function handleIncidents(string $method, array $path): void
    {
        $statusSvc = new PlatformStatusService($this->db);
        $id = isset($path[2]) && ctype_digit((string) $path[2]) ? (int) $path[2] : 0;
        $subAction = $path[3] ?? '';

        if ($method === 'GET' && ($path[2] ?? '') === 'components') {
            echo json_encode(['status' => 'success', 'data' => $statusSvc->listComponents()]);
            return;
        }

        if ($method === 'PUT' && ($path[2] ?? '') === 'components' && isset($path[3])) {
            $body = $this->jsonBody();
            $statusSvc->updateComponentStatus((string) $path[3], trim((string) ($body['status'] ?? 'operational')));
            echo json_encode(['status' => 'success']);
            return;
        }

        if ($method === 'GET' && $id <= 0) {
            echo json_encode(['status' => 'success', 'data' => $statusSvc->listRecentIncidents()]);
            return;
        }

        if ($method === 'POST' && $id <= 0) {
            $body = $this->jsonBody();
            $incidentId = $statusSvc->createIncident($body, PlatformSessionAuth::userId());
            echo json_encode(['status' => 'success', 'id' => $incidentId]);
            return;
        }

        if ($method === 'POST' && $id > 0 && $subAction === 'resolve') {
            $ok = $statusSvc->resolveIncident($id);
            echo json_encode(['status' => $ok ? 'success' : 'error']);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Not found']);
    }

    private function tenantService(): PlatformTenantService
    {
        $subs = new SubscriptionRepository($this->db);
        $ent = new EntitlementService($this->db, $subs);
        return new PlatformTenantService(
            $this->db,
            new TenantRepository($this->db),
            $subs,
            new PlatformAuditRepository($this->db),
            $ent,
            new UsageMeteringService($this->db, new UsageMeteringRepository($this->db), $ent),
        );
    }

    /** @return array<string, mixed> */
    private function jsonBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
        return $_POST ?: [];
    }
}

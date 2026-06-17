<?php
// includes/Controllers/AuthController.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Helpers/MailHelper.php';
require_once __DIR__ . '/../Auth/RbacSchemaMigrator.php';
require_once __DIR__ . '/../Auth/SessionAuth.php';
require_once __DIR__ . '/../Auth/PermissionService.php';
require_once __DIR__ . '/../Auth/RoleRedirect.php';
require_once __DIR__ . '/../Auth/RememberMeService.php';
require_once __DIR__ . '/../Auth/AuditLogger.php';
require_once __DIR__ . '/../Notifications/NotificationManager.php';
if (!defined('I18N_SKIP_BROWSER_LANG')) {
    define('I18N_SKIP_BROWSER_LANG', true);
}
require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

class AuthController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        RbacSchemaMigrator::ensure($this->db);
    }

    public function handleRequest($method, $path) {
        $action = isset($path[1]) ? $path[1] : null;

        if ($method === 'POST') {
            // CSRF Check for all POST requests
            $data = json_decode(file_get_contents("php://input"), true);
            if (!$data && !empty($_POST)) $data = $_POST;

            if ($action !== 'logout' && (!isset($data['csrf_token']) || !verify_csrf_token($data['csrf_token']))) {
                http_response_code(403);
                echo json_encode(["status" => "error", "message" => "Invalid CSRF token."]);
                return;
            }

            switch ($action) {
                case 'login':
                    $this->login($data);
                    break;
                case 'register':
                    $this->register($data);
                    break;
                case 'logout':
                    $this->logout();
                    break;
                case 'forgot-password':
                    $this->forgotPassword($data);
                    break;
                case 'reset-password':
                    $this->resetPassword($data);
                    break;
                default:
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "Auth endpoint not found"]);
            }
        }
    }

    private function login($data) {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        $password = trim($data['password'] ?? '');
        $remember = !empty($data['remember']);
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
            return;
        }

        // Rate limiting (IP + email)
        if ($this->isIpLocked($email, $ip)) {
            http_response_code(429);
            echo json_encode([
                'status' => 'error',
                'message' => 'Account temporarily locked due to multiple failed attempts. Please try again later.',
            ]);
            return;
        }

        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.full_name, u.email, u.password_hash, u.is_active, u.status,
                   u.store_id, u.branch_id, u.warehouse_id, u.language, u.role_id,
                   u.failed_login_attempts, u.locked_until,
                   r.name AS role_name
            FROM users u
            INNER JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.deleted_at IS NULL
            LIMIT 1
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->handleFailedLogin($email, $ip, $user ? (int) $user['id'] : null);
            echo json_encode(['status' => 'error', 'message' => 'Invalid email or password.']);
            return;
        }

        if ($this->isUserLocked($user)) {
            http_response_code(423);
            echo json_encode([
                'status' => 'error',
                'message' => 'Account is locked. Contact administrator or try again later.',
            ]);
            AuditLogger::log((int) $user['id'], 'login_locked', 'failed');
            return;
        }

        $active = ($user['status'] ?? 'active') === 'active' || ((int) ($user['is_active'] ?? 0) === 1 && ($user['status'] ?? '') !== 'inactive');
        if (!$active) {
            echo json_encode(['status' => 'error', 'message' => 'Account is inactive. Contact administrator.']);
            AuditLogger::log((int) $user['id'], 'login_inactive', 'failed');
            return;
        }

        $this->clearFailedAttempts($email, $ip, (int) $user['id']);

        $permissions = (new PermissionService($this->db))->loadForUser((int) $user['id'], (int) $user['role_id']);
        SessionAuth::establish($user, $permissions);

        if ($remember) {
            RememberMeService::issue((int) $user['id']);
        }

        $this->db->prepare('UPDATE users SET last_login = NOW(), last_activity = NOW() WHERE id = ?')
            ->execute([(int) $user['id']]);

        AuditLogger::log((int) $user['id'], 'login', 'success');
        $this->logLoginActivity((int) $user['id'], $ip, 'success');

        $redirect = RoleRedirect::apiPath($user['role_name']);

        echo json_encode([
            'status' => 'success',
            'message' => 'Login successful',
            'redirect' => $redirect,
            'workspace' => RoleRedirect::workspaceForRole(RoleRedirect::slug($user['role_name'])),
            'permissions' => $permissions,
        ]);
    }

    private function isIpLocked(string $email, string $ip): bool
    {
        $stmt = $this->db->prepare(
            'SELECT locked_until FROM failed_login_attempts WHERE email = ? AND ip_address = ? LIMIT 1'
        );
        $stmt->execute([$email, $ip]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['locked_until']) && strtotime($row['locked_until']) > time()) {
            return true;
        }
        return false;
    }

    private function isUserLocked(array $user): bool
    {
        if (($user['status'] ?? '') === 'locked') {
            return true;
        }
        if (!empty($user['locked_until']) && strtotime($user['locked_until']) > time()) {
            return true;
        }
        return false;
    }

    private function clearFailedAttempts(string $email, string $ip, int $userId): void
    {
        $this->db->prepare('DELETE FROM failed_login_attempts WHERE email = ? AND ip_address = ?')
            ->execute([$email, $ip]);
        $this->db->prepare(
            'UPDATE users SET failed_login_attempts = 0, locked_until = NULL WHERE id = ?'
        )->execute([$userId]);
    }

    private function register($data) {
        $name = htmlspecialchars(trim($data['name']));
        $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
        $password = $data['password'];
        $confirm = $data['password_confirmation'];

        if ($password !== $confirm) {
            echo json_encode(["status" => "error", "message" => "Passwords do not match."]);
            return;
        }

        if (strlen($password) < 8) {
            echo json_encode(["status" => "error", "message" => "Password must be at least 8 characters long."]);
            return;
        }

        // Check duplicates
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            echo json_encode(["status" => "error", "message" => "Email already registered."]);
            return;
        }

        // Get default 'Cashier' role ID
        $stmt = $this->db->prepare("SELECT id FROM roles WHERE name = 'cashier'");
        $stmt->execute();
        $role = $stmt->fetch();
        $roleId = $role ? $role['id'] : 3;

        $hash = password_hash($password, PASSWORD_BCRYPT);
        $pinHash = password_hash("1234", PASSWORD_BCRYPT); // Default PIN

        try {
            $stmt = $this->db->prepare("INSERT INTO users (name, email, password_hash, pin_hash, role_id, is_active) VALUES (?, ?, ?, ?, ?, 0)");
            $stmt->execute([$name, $email, $hash, $pinHash, $roleId]);
            echo json_encode(["status" => "success", "message" => "Registration successful. Awaiting admin activation."]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error"]);
        }
    }

    private function logout() {
        $uid = (int) ($_SESSION['user_id'] ?? 0);
        if ($uid > 0) {
            AuditLogger::log($uid, 'logout', 'success');
            RememberMeService::revoke($uid);
        }
        SessionAuth::clear();
        echo json_encode(['status' => 'success', 'redirect' => '/public/login.php']);
    }

    private function forgotPassword(array $data): void
    {
        $email = filter_var(trim($data['email'] ?? ''), FILTER_SANITIZE_EMAIL);
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['status' => 'error', 'message' => __t('invalid_email', 'auth')]);
            return;
        }

        $successMsg = __t('forgot_success', 'auth');
        $lang = defined('ACTIVE_LANG') ? ACTIVE_LANG : ($_SESSION['lang'] ?? 'en');

        try {
            $stmt = $this->db->prepare(
                'SELECT id, name, email FROM users WHERE email = ? AND deleted_at IS NULL AND is_active = 1 LIMIT 1'
            );
            $stmt->execute([$email]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user) {
                $userLang = $lang;
                try {
                    $langStmt = $this->db->prepare('SELECT language FROM users WHERE id = ? LIMIT 1');
                    if ($langStmt->execute([(int) $user['id']])) {
                        $row = $langStmt->fetch(PDO::FETCH_ASSOC);
                        if (!empty($row['language']) && in_array($row['language'], ['en', 'fr'], true)) {
                            $userLang = $row['language'];
                        }
                    }
                } catch (PDOException $e) {
                    // language column may not exist yet
                }

                $countStmt = $this->db->prepare(
                    'SELECT COUNT(*) FROM password_resets WHERE user_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
                );
                $countStmt->execute([(int) $user['id']]);
                if ((int) $countStmt->fetchColumn() < 3) {
                    $rawToken = bin2hex(random_bytes(32));
                    $tokenHash = hash('sha256', $rawToken);
                    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

                    $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([(int) $user['id']]);
                    $insert = $this->db->prepare(
                        'INSERT INTO password_resets (user_id, token, expires_at) VALUES (?, ?, ?)'
                    );
                    $insert->execute([(int) $user['id'], $tokenHash, $expiresAt]);

                    $resetUrl = app_base_url() . '/public/reset-password.php?token=' . urlencode($rawToken);
                    $this->sendPasswordResetEmail($user, $resetUrl, $userLang);
                }
            }
        } catch (PDOException $e) {
            error_log('AuthController forgotPassword: ' . $e->getMessage());
        }

        echo json_encode(['status' => 'success', 'message' => $successMsg]);
    }

    private function resetPassword(array $data): void
    {
        $token = trim($data['token'] ?? '');
        $password = $data['password'] ?? '';
        $confirm = $data['password_confirmation'] ?? '';

        if ($token === '') {
            echo json_encode(['status' => 'error', 'message' => __t('reset_token_missing', 'auth')]);
            return;
        }

        if ($password !== $confirm) {
            echo json_encode(['status' => 'error', 'message' => __t('password_mismatch', 'auth')]);
            return;
        }

        if (strlen($password) < 8) {
            echo json_encode(['status' => 'error', 'message' => __t('password_min_length', 'auth')]);
            return;
        }

        try {
            $tokenHash = hash('sha256', $token);
            $stmt = $this->db->prepare(
                'SELECT user_id FROM password_resets WHERE token = ? AND expires_at > NOW() LIMIT 1'
            );
            $stmt->execute([$tokenHash]);
            $reset = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$reset) {
                echo json_encode(['status' => 'error', 'message' => __t('reset_invalid_token', 'auth')]);
                return;
            }

            $userId = (int) $reset['user_id'];
            $hash = password_hash($password, PASSWORD_BCRYPT);

            $this->db->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$hash, $userId]);
            $this->db->prepare('DELETE FROM password_resets WHERE user_id = ?')->execute([$userId]);

            AuditLogger::log($userId, 'password_reset', 'success');

            echo json_encode([
                'status' => 'success',
                'message' => __t('reset_success', 'auth'),
                'redirect' => '../public/login.php',
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => __t('error_generic', 'auth')]);
        }
    }

    private function sendPasswordResetEmail(array $user, string $resetUrl, string $lang): void
    {
        require_once __DIR__ . '/../../languages/TranslationService.php';

        $name = htmlspecialchars($user['name'] ?? '', ENT_QUOTES, 'UTF-8');
        $subject = TranslationService::get('email_reset_subject', 'auth', $lang);
        $greeting = sprintf(TranslationService::get('email_reset_greeting', 'auth', $lang), $name);
        $bodyText = TranslationService::get('email_reset_body', 'auth', $lang);
        $buttonLabel = TranslationService::get('email_reset_button', 'auth', $lang);
        $ignoreText = TranslationService::get('email_reset_ignore', 'auth', $lang);

        $html = '<!DOCTYPE html><html><body style="font-family:Inter,sans-serif;color:#0f172a;line-height:1.6;">'
            . '<p>' . $greeting . '</p>'
            . '<p>' . htmlspecialchars($bodyText, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="' . htmlspecialchars($resetUrl, ENT_QUOTES, 'UTF-8') . '" '
            . 'style="display:inline-block;padding:12px 24px;background:#2563eb;color:#fff;text-decoration:none;border-radius:8px;font-weight:600;">'
            . htmlspecialchars($buttonLabel, ENT_QUOTES, 'UTF-8') . '</a></p>'
            . '<p style="color:#64748b;font-size:14px;">' . htmlspecialchars($ignoreText, ENT_QUOTES, 'UTF-8') . '</p>'
            . '</body></html>';

        send_app_email($user['email'], $subject, $html);
    }

    private function handleFailedLogin($email, $ip, ?int $userId = null) {
        $stmt = $this->db->prepare('SELECT attempts FROM failed_login_attempts WHERE email = ? AND ip_address = ?');
        $stmt->execute([$email, $ip]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);

        $attempts = $record ? ((int) $record['attempts'] + 1) : 1;
        $lockedUntil = $attempts >= 5 ? date('Y-m-d H:i:s', strtotime('+15 minutes')) : null;

        if ($record) {
            $stmt = $this->db->prepare(
                'UPDATE failed_login_attempts SET attempts = ?, locked_until = ? WHERE email = ? AND ip_address = ?'
            );
            $stmt->execute([$attempts, $lockedUntil, $email, $ip]);
        } else {
            $stmt = $this->db->prepare(
                'INSERT INTO failed_login_attempts (ip_address, email, attempts, locked_until) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$ip, $email, $attempts, $lockedUntil]);
        }

        if ($userId) {
            $userLock = $attempts >= 5 ? $lockedUntil : null;
            $this->db->prepare(
                'UPDATE users SET failed_login_attempts = ?, locked_until = ? WHERE id = ?'
            )->execute([$attempts, $userLock, $userId]);
            AuditLogger::log($userId, 'login_failed', 'failed', 'user', $userId);
            $this->logLoginActivity($userId, $ip, 'failed');
            if ($attempts >= 5) {
                NotificationManager::notifyUser($userId, [
                    'template' => 'users.account_locked',
                    'category' => 'security',
                    'module' => 'users',
                    'severity' => 'critical',
                    'channels' => ['in_app', 'email'],
                ]);
            } else {
                NotificationManager::notifyUser($userId, [
                    'template' => 'users.login_failed',
                    'category' => 'security',
                    'module' => 'users',
                    'severity' => 'warning',
                    'channels' => ['in_app'],
                ]);
            }
        } else {
            AuditLogger::log(null, 'login_failed', 'failed', 'email', null, ['email' => $email]);
        }
    }

    private function logLoginActivity(int $userId, string $ip, string $status): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO login_activity (user_id, ip_address, user_agent, status) VALUES (?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $ip, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500), $status]);
        } catch (PDOException $e) {
            // optional table
        }
    }
}

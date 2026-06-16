<?php
// includes/Controllers/AuthController.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Config/session.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Helpers/MailHelper.php';
if (!defined('I18N_SKIP_BROWSER_LANG')) {
    define('I18N_SKIP_BROWSER_LANG', true);
}
require_once __DIR__ . '/../../languages/LanguageMiddleware.php';
require_once __DIR__ . '/../../languages/helpers.php';

class AuthController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
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
        $email = filter_var(trim($data['email']), FILTER_SANITIZE_EMAIL);
        $password = trim($data['password']);
        $ip = $_SERVER['REMOTE_ADDR'];

        // 1. Check Rate Limiting (Account Lockout)
        $stmt = $this->db->prepare("SELECT attempts, locked_until FROM failed_login_attempts WHERE email = ? AND ip_address = ?");
        $stmt->execute([$email, $ip]);
        $attemptRecord = $stmt->fetch();

        if ($attemptRecord && $attemptRecord['locked_until']) {
            if (strtotime($attemptRecord['locked_until']) > time()) {
                http_response_code(429);
                echo json_encode(["status" => "error", "message" => "Account temporarily locked due to multiple failed attempts. Please try again later."]);
                return;
            } else {
                // Lock expired, reset attempts
                $stmt = $this->db->prepare("DELETE FROM failed_login_attempts WHERE email = ? AND ip_address = ?");
                $stmt->execute([$email, $ip]);
            }
        }

        // 2. Fetch User & Role
        $stmt = $this->db->prepare("
            SELECT u.id, u.name, u.password_hash, u.is_active, u.store_id, r.name as role_name 
            FROM users u
            JOIN roles r ON u.role_id = r.id
            WHERE u.email = ? AND u.deleted_at IS NULL
        ");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, $user['password_hash'])) {
            if (!$user['is_active']) {
                echo json_encode(["status" => "error", "message" => "Account is inactive. Contact administrator."]);
                return;
            }

            // Success - Reset Failed Attempts
            $stmt = $this->db->prepare("DELETE FROM failed_login_attempts WHERE email = ? AND ip_address = ?");
            $stmt->execute([$email, $ip]);

            // Set Secure Session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['name'] = $user['name'];
            $_SESSION['email'] = $email;
            $_SESSION['role'] = $user['role_name'];
            $roleSlug = strtolower(str_replace(' ', '_', $user['role_name']));
            $storeId = $user['store_id'] ? (int) $user['store_id'] : null;

            $_SESSION['store_id'] = $storeId;
            if ($roleSlug === 'super_admin') {
                $_SESSION['active_store_id'] = $storeId;
                if (!$storeId) {
                    $first = $this->db->query(
                        'SELECT id FROM stores WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1'
                    )->fetchColumn();
                    if ($first) {
                        $_SESSION['active_store_id'] = null;
                    }
                }
            } else {
                $_SESSION['active_store_id'] = $storeId;
            }
            $_SESSION['last_activity'] = time();

            $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?')->execute([$user['id']]);
            $this->logActivity((int) $user['id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', 'login_success', 'success');
            $this->logLoginActivity((int) $user['id'], $ip, 'success');

            // Determine redirect URL
            $redirect = $this->getDashboardUrl($user['role_name']);

            echo json_encode(["status" => "success", "message" => "Login successful", "redirect" => $redirect]);

        } else {
            // Failed Login
            $this->handleFailedLogin($email, $ip);
            if ($user) {
                $this->logActivity((int) $user['id'], $ip, $_SERVER['HTTP_USER_AGENT'] ?? '', 'login_failed', 'failed');
                $this->logLoginActivity((int) $user['id'], $ip, 'failed');
            }
            echo json_encode(["status" => "error", "message" => "Invalid email or password."]);
        }
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
            $this->logActivity($uid, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', 'logout', 'success');
        }
        session_unset();
        session_destroy();
        echo json_encode(["status" => "success", "redirect" => "/public/login.php"]);
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

            $this->logActivity($userId, $_SERVER['REMOTE_ADDR'] ?? '', $_SERVER['HTTP_USER_AGENT'] ?? '', 'password_reset', 'success');

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

    private function handleFailedLogin($email, $ip) {
        $stmt = $this->db->prepare("SELECT attempts FROM failed_login_attempts WHERE email = ? AND ip_address = ?");
        $stmt->execute([$email, $ip]);
        $record = $stmt->fetch();

        if ($record) {
            $attempts = $record['attempts'] + 1;
            $lockedUntil = null;
            if ($attempts >= 5) { // Lock out after 5 attempts
                $lockedUntil = date('Y-m-d H:i:s', strtotime('+15 minutes'));
            }
            $stmt = $this->db->prepare("UPDATE failed_login_attempts SET attempts = ?, locked_until = ? WHERE email = ? AND ip_address = ?");
            $stmt->execute([$attempts, $lockedUntil, $email, $ip]);
        } else {
            $stmt = $this->db->prepare("INSERT INTO failed_login_attempts (ip_address, email, attempts) VALUES (?, ?, 1)");
            $stmt->execute([$ip, $email]);
        }
    }

    private function logActivity(int $userId, string $ip, string $agent, string $action, string $status): void
    {
        try {
            $stmt = $this->db->prepare(
                'INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent, status)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $stmt->execute([$userId, $action, $ip, substr($agent, 0, 500), $status]);
        } catch (PDOException $e) {
            error_log('AuthController logActivity: ' . $e->getMessage());
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
            // table optionnelle
        }
    }

    private function getDashboardUrl($role) {
        switch (strtolower($role)) {
            case 'super admin': return '../public/admin/index.php';
            case 'admin': return '../public/admin/index.php';
            case 'manager': return '../public/manager/index.php';
            case 'cashier': return '../public/cashier/dashboard.php';
            default: return '../public/cashier/dashboard.php';
        }
    }
}

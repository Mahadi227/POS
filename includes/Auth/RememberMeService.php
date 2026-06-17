<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/RbacSchemaMigrator.php';
require_once __DIR__ . '/PermissionService.php';
require_once __DIR__ . '/SessionAuth.php';
require_once __DIR__ . '/AuditLogger.php';

/**
 * Persistent login via secure cookie (30 days).
 */
class RememberMeService
{
    private const COOKIE = 'retailpos_remember';
    private const DAYS = 30;

    public static function issue(int $userId): void
    {
        $selector = bin2hex(random_bytes(12));
        $validator = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $validator);
        $expires = date('Y-m-d H:i:s', strtotime('+' . self::DAYS . ' days'));

        try {
            $db = Database::getInstance()->getConnection();
            RbacSchemaMigrator::ensure($db);
            $db->prepare('UPDATE users SET remember_token = ? WHERE id = ?')->execute([$selector . ':' . $tokenHash, $userId]);
            setcookie(
                self::COOKIE,
                $selector . ':' . $validator,
                [
                    'expires' => time() + (self::DAYS * 86400),
                    'path' => '/',
                    'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on',
                    'httponly' => true,
                    'samesite' => 'Lax',
                ]
            );
        } catch (PDOException $e) {
            error_log('RememberMeService::issue ' . $e->getMessage());
        }
    }

    public static function attempt(): bool
    {
        $raw = $_COOKIE[self::COOKIE] ?? '';
        if (!str_contains($raw, ':')) {
            return false;
        }
        [$selector, $validator] = explode(':', $raw, 2);
        if ($selector === '' || $validator === '') {
            return false;
        }

        try {
            $db = Database::getInstance()->getConnection();
            $stmt = $db->prepare(
                "SELECT u.*, r.id AS role_id, r.name AS role_name
                 FROM users u
                 INNER JOIN roles r ON r.id = u.role_id
                 WHERE u.remember_token LIKE ? AND u.deleted_at IS NULL
                   AND (u.status = 'active' OR (u.status IS NULL AND u.is_active = 1))
                 LIMIT 1"
            );
            $stmt->execute([$selector . ':%']);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$user || !hash_equals(substr((string) $user['remember_token'], strlen($selector) + 1), hash('sha256', $validator))) {
                return false;
            }

            $perms = (new PermissionService($db))->loadForUser((int) $user['id'], (int) $user['role_id']);
            SessionAuth::establish($user, $perms);
            AuditLogger::log((int) $user['id'], 'remember_me_login', 'success');
            return true;
        } catch (PDOException $e) {
            error_log('RememberMeService::attempt ' . $e->getMessage());
            return false;
        }
    }

    public static function revoke(int $userId): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $db->prepare('UPDATE users SET remember_token = NULL WHERE id = ?')->execute([$userId]);
        } catch (PDOException $e) {
        }
        setcookie(self::COOKIE, '', time() - 3600, '/');
    }
}

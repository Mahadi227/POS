<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';

/**
 * Security and RBAC audit logging.
 */
class AuditLogger
{
    public static function log(
        ?int $userId,
        string $action,
        string $status = 'success',
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $parsed = self::parseUserAgent($ua);

        try {
            $db = Database::getInstance()->getConnection();
            if (self::tableExists($db, 'security_audit_logs')) {
                $stmt = $db->prepare(
                    'INSERT INTO security_audit_logs
                        (user_id, action, entity_type, entity_id, details, ip_address, user_agent, browser, os_name, device_type, status)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $stmt->execute([
                    $userId,
                    $action,
                    $entityType,
                    $entityId,
                    $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
                    $_SERVER['REMOTE_ADDR'] ?? null,
                    substr($ua, 0, 500),
                    $parsed['browser'],
                    $parsed['os'],
                    $parsed['device'],
                    $status,
                ]);
            }

            if ($userId) {
                $legacy = $db->prepare(
                    'INSERT INTO user_activity_logs (user_id, action, ip_address, user_agent, status)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $legacy->execute([$userId, $action, $_SERVER['REMOTE_ADDR'] ?? '', substr($ua, 0, 500), $status]);
            }
        } catch (PDOException $e) {
            error_log('AuditLogger: ' . $e->getMessage());
        }
    }

    private static function parseUserAgent(string $ua): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'desktop';

        if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $ua)) {
            $device = 'tablet';
        }
        if (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $ua)) {
            $browser = 'Edge';
        }
        if (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/i', $ua)) {
            $os = 'iOS';
        }

        return compact('browser', 'os', 'device');
    }

    private static function tableExists(PDO $db, string $table): bool
    {
        $stmt = $db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/PlatformSettingsRepository.php';

final class PlatformSecurityRepository
{
    private const LOCKOUT_THRESHOLD = 5;
    private const LOCKOUT_WINDOW_MINUTES = 15;

    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'recent_attempts' => $this->listLoginAttempts(10, 0),
            'recent_events' => $this->listEvents(10, 0),
        ];
    }

    /** @return array<string, int> */
    public function stats(): array
    {
        if (!$this->tableExists('platform_login_attempts')) {
            return [
                'failed_today' => 0,
                'failed_total' => 0,
                'success_today' => 0,
                'events_today' => 0,
                'events_total' => 0,
                'active_users' => 0,
            ];
        }

        $failedToday = (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_login_attempts
             WHERE status IN ('failed','locked') AND DATE(created_at) = CURDATE()"
        )->fetchColumn();

        $failedTotal = (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_login_attempts WHERE status IN ('failed','locked')"
        )->fetchColumn();

        $successToday = (int) $this->db->query(
            "SELECT COUNT(*) FROM platform_login_attempts
             WHERE status = 'success' AND DATE(created_at) = CURDATE()"
        )->fetchColumn();

        $eventsToday = 0;
        $eventsTotal = 0;
        if ($this->tableExists('platform_security_events')) {
            $eventsToday = (int) $this->db->query(
                'SELECT COUNT(*) FROM platform_security_events WHERE DATE(created_at) = CURDATE()'
            )->fetchColumn();
            $eventsTotal = (int) $this->db->query(
                'SELECT COUNT(*) FROM platform_security_events'
            )->fetchColumn();
        }

        $activeUsers = 0;
        if ($this->tableExists('platform_users')) {
            $activeUsers = (int) $this->db->query(
                'SELECT COUNT(*) FROM platform_users WHERE is_active = 1'
            )->fetchColumn();
        }

        return [
            'failed_today' => $failedToday,
            'failed_total' => $failedTotal,
            'success_today' => $successToday,
            'events_today' => $eventsToday,
            'events_total' => $eventsTotal,
            'active_users' => $activeUsers,
        ];
    }

    public function isLocked(string $email, ?string $ip): bool
    {
        if (!$this->tableExists('platform_login_attempts')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM platform_login_attempts
             WHERE email = ? AND status = 'failed'
               AND created_at >= DATE_SUB(NOW(), INTERVAL ? MINUTE)"
        );
        $stmt->execute([$email, $this->lockoutWindowMinutes()]);
        return (int) $stmt->fetchColumn() >= $this->lockoutThreshold();
    }

    public function recordLoginAttempt(
        string $email,
        ?int $platformUserId,
        string $status,
        ?string $ip = null,
        ?string $userAgent = null,
    ): void {
        if (!$this->tableExists('platform_login_attempts')) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO platform_login_attempts (email, platform_user_id, status, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?)'
        )->execute([
            $email,
            $platformUserId,
            $status,
            $ip,
            $userAgent !== null ? substr($userAgent, 0, 500) : null,
        ]);

        if ($status === 'failed' && $this->isLocked($email, $ip)) {
            $this->recordEvent('login.lockout', 'high', $platformUserId, $email, $ip, [
                'threshold' => $this->lockoutThreshold(),
                'window_minutes' => $this->lockoutWindowMinutes(),
            ]);
        }
    }

    public function recordEvent(
        string $eventType,
        string $severity,
        ?int $platformUserId = null,
        ?string $email = null,
        ?string $ip = null,
        ?array $details = null,
    ): void {
        if (!$this->tableExists('platform_security_events')) {
            return;
        }

        $this->db->prepare(
            'INSERT INTO platform_security_events
                (event_type, severity, platform_user_id, email, ip_address, details_json)
             VALUES (?, ?, ?, ?, ?, ?)'
        )->execute([
            $eventType,
            $severity,
            $platformUserId,
            $email,
            $ip,
            $details !== null ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listLoginAttempts(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
    ): array {
        if (!$this->tableExists('platform_login_attempts')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pla.email LIKE ? OR pla.ip_address LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'pla.status = ?';
            $params[] = $status;
        }

        $sql = 'SELECT pla.id, pla.email, pla.platform_user_id, pla.status, pla.ip_address,
                       pla.user_agent, pla.created_at,
                       pu.name AS platform_user_name
                FROM platform_login_attempts pla
                LEFT JOIN platform_users pu ON pu.id = pla.platform_user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pla.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<int, array<string, mixed>> */
    public function listEvents(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $severity = null,
        ?string $eventType = null,
    ): array {
        if (!$this->tableExists('platform_security_events')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(pse.event_type LIKE ? OR pse.email LIKE ? OR pse.ip_address LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($severity !== null && $severity !== '') {
            $where[] = 'pse.severity = ?';
            $params[] = $severity;
        }
        if ($eventType !== null && $eventType !== '') {
            $where[] = 'pse.event_type = ?';
            $params[] = $eventType;
        }

        $sql = 'SELECT pse.id, pse.event_type, pse.severity, pse.email, pse.ip_address,
                       pse.details_json, pse.created_at,
                       pu.name AS platform_user_name
                FROM platform_security_events pse
                LEFT JOIN platform_users pu ON pu.id = pse.platform_user_id
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pse.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        foreach ($params as $i => $val) {
            $stmt->bindValue($i + 1, $val);
        }
        $stmt->bindValue(count($params) + 1, $limit, PDO::PARAM_INT);
        $stmt->bindValue(count($params) + 2, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function lockoutThreshold(): int
    {
        if ($this->tableExists('platform_settings')) {
            return (new PlatformSettingsRepository($this->db))->getInt('lockout_threshold', self::LOCKOUT_THRESHOLD);
        }

        return self::LOCKOUT_THRESHOLD;
    }

    private function lockoutWindowMinutes(): int
    {
        if ($this->tableExists('platform_settings')) {
            return (new PlatformSettingsRepository($this->db))->getInt('lockout_window_minutes', self::LOCKOUT_WINDOW_MINUTES);
        }

        return self::LOCKOUT_WINDOW_MINUTES;
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

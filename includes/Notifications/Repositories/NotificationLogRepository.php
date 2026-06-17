<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class NotificationLogRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function log(
        ?int $notificationId,
        ?int $userId,
        string $action,
        string $status = 'success',
        ?string $channel = null,
        ?array $details = null
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO notification_logs
                (notification_id, user_id, channel_slug, action, status, details, ip_address, user_agent)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $notificationId,
            $userId,
            $channel,
            $action,
            $status,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
        ]);
    }

    public function list(array $filters = [], int $limit = 100): array
    {
        $where = ['1=1'];
        $params = [];
        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = ?';
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['action'])) {
            $where[] = 'action = ?';
            $params[] = $filters['action'];
        }
        $sql = 'SELECT * FROM notification_logs WHERE ' . implode(' AND ', $where)
            . ' ORDER BY created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

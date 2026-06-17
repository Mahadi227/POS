<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class NotificationQueueRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function enqueue(array $row): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notification_queue
                (notification_id, user_id, channel_slug, recipient, subject, body, payload, scheduled_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['notification_id'] ?? null,
            $row['user_id'],
            $row['channel_slug'],
            $row['recipient'] ?? null,
            $row['subject'] ?? null,
            $row['body'],
            isset($row['payload']) ? json_encode($row['payload'], JSON_UNESCAPED_UNICODE) : null,
            $row['scheduled_at'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function fetchPending(int $limit = 50): array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM notification_queue
             WHERE status = 'pending' AND attempts < max_attempts
               AND (scheduled_at IS NULL OR scheduled_at <= NOW())
             ORDER BY created_at ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function markSent(int $id): void
    {
        $this->db->prepare(
            "UPDATE notification_queue SET status = 'sent', sent_at = NOW() WHERE id = ?"
        )->execute([$id]);
    }

    public function markFailed(int $id, string $error): void
    {
        $this->db->prepare(
            "UPDATE notification_queue SET status = 'failed', attempts = attempts + 1, error_message = ? WHERE id = ?"
        )->execute([$error, $id]);
    }
}

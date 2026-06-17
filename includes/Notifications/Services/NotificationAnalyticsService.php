<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class NotificationAnalyticsService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function dashboard(?int $storeId = null): array
    {
        $storeFilter = $storeId ? ' AND store_id = ' . (int) $storeId : '';

        $total = (int) $this->db->query(
            "SELECT COUNT(*) FROM notifications WHERE deleted_at IS NULL{$storeFilter}"
        )->fetchColumn();

        $unread = (int) $this->db->query(
            "SELECT COUNT(*) FROM notifications WHERE is_read = 0 AND is_archived = 0 AND deleted_at IS NULL{$storeFilter}"
        )->fetchColumn();

        $critical = (int) $this->db->query(
            "SELECT COUNT(*) FROM notifications WHERE severity IN ('critical','error') AND is_read = 0
             AND deleted_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY){$storeFilter}"
        )->fetchColumn();

        $failed = (int) $this->db->query(
            "SELECT COUNT(*) FROM notification_queue WHERE status = 'failed'"
        )->fetchColumn();

        $byCategory = $this->db->query(
            "SELECT category_slug, COUNT(*) AS cnt FROM notifications
             WHERE deleted_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 30 DAY){$storeFilter}
             GROUP BY category_slug ORDER BY cnt DESC LIMIT 10"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $byDay = $this->db->query(
            "SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM notifications
             WHERE deleted_at IS NULL AND created_at > DATE_SUB(NOW(), INTERVAL 14 DAY){$storeFilter}
             GROUP BY DATE(created_at) ORDER BY day"
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'total_sent' => $total,
            'unread' => $unread,
            'critical_alerts' => $critical,
            'failed_deliveries' => $failed,
            'by_category' => $byCategory,
            'by_day' => $byDay,
        ];
    }
}

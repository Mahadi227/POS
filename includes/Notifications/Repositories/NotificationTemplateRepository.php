<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class NotificationTemplateRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM notification_templates WHERE slug = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listAll(): array
    {
        return $this->db->query(
            'SELECT t.*, c.slug AS category_slug, c.module
             FROM notification_templates t
             LEFT JOIN notification_categories c ON c.id = t.category_id
             ORDER BY t.slug'
        )->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listCategories(): array
    {
        return $this->db->query('SELECT * FROM notification_categories ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listTypes(): array
    {
        return $this->db->query('SELECT * FROM notification_types ORDER BY sort_order')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listChannels(): array
    {
        return $this->db->query('SELECT * FROM notification_channels WHERE is_active = 1')->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

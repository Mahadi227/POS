<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';

class ShiftRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM cashier_shifts LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    public function listOpen(?int $storeId): array
    {
        if (!$this->tableExists()) {
            return [];
        }
        $sql = "SELECT s.*, u.name AS cashier_name
                FROM cashier_shifts s
                JOIN users u ON u.id = s.user_id
                WHERE s.status = 'open'";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY s.opened_at DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

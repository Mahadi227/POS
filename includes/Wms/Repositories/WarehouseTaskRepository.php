<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseTaskRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableReady(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM warehouse_tasks LIMIT 1');
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function list(?int $warehouseId, array $filters = [], int $limit = 50): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        $sql = 'SELECT t.*, u.name AS assigned_name, c.name AS created_name
                FROM warehouse_tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                LEFT JOIN users c ON c.id = t.created_by
                WHERE 1=1';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND t.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if (!empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND t.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['type'])) {
            $sql .= ' AND t.task_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['due'])) {
            $sql .= ' AND t.due_date = CURDATE()';
        }
        if (!empty($filters['assigned_to'])) {
            $sql .= ' AND t.assigned_to = ?';
            $params[] = (int) $filters['assigned_to'];
        }
        $sql .= ' ORDER BY FIELD(t.priority, "urgent","high","normal","low"), t.due_date ASC, t.id DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function summary(?int $warehouseId): array
    {
        if (!$this->tableReady()) {
            return ['pending' => 0, 'in_progress' => 0, 'due_today' => 0, 'completed_today' => 0];
        }
        $sql = "SELECT
                    SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN status IN ('pending','in_progress') AND due_date = CURDATE() THEN 1 ELSE 0 END) AS due_today,
                    SUM(CASE WHEN status = 'completed' AND DATE(completed_at) = CURDATE() THEN 1 ELSE 0 END) AS completed_today
                FROM warehouse_tasks WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'due_today' => (int) ($row['due_today'] ?? 0),
            'completed_today' => (int) ($row['completed_today'] ?? 0),
        ];
    }

    public function seedDailyTasks(?int $warehouseId): void
    {
        if (!$this->tableReady() || !$warehouseId) {
            return;
        }
        $today = date('Y-m-d');
        $checks = [
            ['receiving', 'Daily receiving queue', 'receiving'],
            ['dispatch', 'Daily dispatch queue', 'dispatch'],
            ['transfer', 'Pending transfer approvals', 'transfer'],
            ['inventory_count', 'Cycle count review', 'inventory_count'],
        ];
        foreach ($checks as [$type, $title, $refType]) {
            $stmt = $this->db->prepare(
                "SELECT id FROM warehouse_tasks WHERE warehouse_id = ? AND task_type = ? AND due_date = ? AND status IN ('pending','in_progress') LIMIT 1"
            );
            $stmt->execute([$warehouseId, $type, $today]);
            if ($stmt->fetchColumn()) {
                continue;
            }
            $ins = $this->db->prepare(
                'INSERT INTO warehouse_tasks (warehouse_id, task_type, title, reference_type, due_date, priority, status)
                 VALUES (?, ?, ?, ?, ?, "normal", "pending")'
            );
            $ins->execute([$warehouseId, $type, $title, $refType, $today]);
        }
    }
}

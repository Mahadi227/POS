<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseLogRepository
{
    public static function log(
        string $action,
        ?int $warehouseId,
        ?int $userId,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        if (!WmsSchema::ready()) {
            return;
        }
        $stmt = Database::getInstance()->getConnection()->prepare(
            "INSERT INTO warehouse_logs (warehouse_id, user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $warehouseId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function list(?int $warehouseId = null, array $filters = [], int $limit = 200): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT l.*, u.name AS user_name, w.name AS warehouse_name
                FROM warehouse_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN warehouses w ON w.id = l.warehouse_id
                WHERE 1=1 {$where}
                ORDER BY l.created_at DESC
                LIMIT " . (int) $limit;
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = Database::getInstance()->getConnection()->prepare(
            "SELECT l.*, u.name AS user_name, w.name AS warehouse_name
             FROM warehouse_logs l
             LEFT JOIN users u ON u.id = l.user_id
             LEFT JOIN warehouses w ON w.id = l.warehouse_id
             WHERE l.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function summary(?int $warehouseId = null, array $filters = []): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'today' => 0, 'users' => 0, 'entities' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN DATE(l.created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                       COUNT(DISTINCT l.user_id) AS users,
                       COUNT(DISTINCT CONCAT(COALESCE(l.entity_type, ''), ':', COALESCE(l.entity_id, 0))) AS entities
                FROM warehouse_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN warehouses w ON w.id = l.warehouse_id
                WHERE 1=1 {$where}";
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'users' => (int) ($row['users'] ?? 0),
            'entities' => (int) ($row['entities'] ?? 0),
        ];
    }

    public function breakdownByAction(?int $warehouseId = null, array $filters = [], int $limit = 12): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT l.action, COUNT(*) AS event_count
                FROM warehouse_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN warehouses w ON w.id = l.warehouse_id
                WHERE 1=1 {$where}
                GROUP BY l.action
                ORDER BY event_count DESC
                LIMIT " . (int) $limit;
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function distinctActions(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = 'SELECT DISTINCT action FROM warehouse_logs WHERE action IS NOT NULL AND action != \'\'';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $sql .= ' ORDER BY action ASC';
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'action');
    }

    /** @return array{0: string, 1: array} */
    private function filterClause(?int $warehouseId, array $filters): array
    {
        $sql = '';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND l.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $sql .= ' AND l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['entity_type']) && $filters['entity_type'] !== 'all') {
            $sql .= ' AND l.entity_type = ?';
            $params[] = $filters['entity_type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND l.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND l.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (l.action LIKE ? OR l.entity_type LIKE ? OR u.name LIKE ?
                      OR w.name LIKE ? OR l.ip_address LIKE ? OR CAST(l.entity_id AS CHAR) LIKE ?
                      OR l.details LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, array_fill(0, 7, $like));
        }
        return [$sql, $params];
    }

    public function listNotifications(?int $warehouseId, ?string $since = null, int $limit = 40): array
    {
        $actions = [
            'low_stock', 'transfer_approved', 'transfer_rejected', 'transfer_received',
            'damaged_stock', 'expired_product', 'incoming_delivery', 'purchase_received', 'warehouse_full',
        ];
        $placeholders = implode(',', array_fill(0, count($actions), '?'));
        $sql = "SELECT l.*, u.name AS user_name, w.name AS warehouse_name
                FROM warehouse_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN warehouses w ON w.id = l.warehouse_id
                WHERE l.action IN ($placeholders)";
        $params = $actions;
        if ($warehouseId) {
            $sql .= ' AND l.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($since) {
            $sql .= ' AND l.created_at > ?';
            $params[] = $since;
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . (int) $limit;
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

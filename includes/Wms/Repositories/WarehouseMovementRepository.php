<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseMovementRepository
{
    /** @var list<string> */
    public const ADJUSTMENT_TYPES = ['adjustment', 'manual', 'damaged', 'expired', 'lost'];

    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function record(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_stock_movements
                (warehouse_id, product_id, batch_id, movement_type, quantity, balance_after,
                 unit_cost, stock_value, reference_type, reference_id, notes, created_by, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['warehouse_id'],
            (int) $data['product_id'],
            !empty($data['batch_id']) ? (int) $data['batch_id'] : null,
            (string) $data['movement_type'],
            (int) ($data['quantity'] ?? 0),
            (int) ($data['balance_after'] ?? 0),
            round((float) ($data['unit_cost'] ?? 0), 4),
            round((float) ($data['stock_value'] ?? 0), 4),
            $data['reference_type'] ?? null,
            !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
            $data['notes'] ?? null,
            !empty($data['created_by']) ? (int) $data['created_by'] : null,
            (string) ($data['sync_status'] ?? 'synced'),
            $data['local_uuid'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function list(?int $warehouseId, array $filters = [], int $limit = 200, int $offset = 0): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT m.*, p.name AS product_name, p.sku, w.name AS warehouse_name, u.name AS created_by_name
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1 {$where}
                ORDER BY m.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(?int $warehouseId, array $filters = []): int
    {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT COUNT(*)
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function summary(?int $warehouseId, array $filters = []): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'stock_in' => 0, 'stock_out' => 0, 'net_qty' => 0, 'total_value' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT COUNT(*) AS total,
                       COALESCE(SUM(CASE WHEN m.quantity > 0 THEN m.quantity ELSE 0 END), 0) AS stock_in,
                       COALESCE(SUM(CASE WHEN m.quantity < 0 THEN ABS(m.quantity) ELSE 0 END), 0) AS stock_out,
                       COALESCE(SUM(m.quantity), 0) AS net_qty,
                       COALESCE(SUM(ABS(m.stock_value)), 0) AS total_value
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'stock_in' => (int) ($row['stock_in'] ?? 0),
            'stock_out' => (int) ($row['stock_out'] ?? 0),
            'net_qty' => (int) ($row['net_qty'] ?? 0),
            'total_value' => round((float) ($row['total_value'] ?? 0), 2),
        ];
    }

    public function breakdownByType(?int $warehouseId, array $filters = []): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $sql = "SELECT m.movement_type,
                       COUNT(*) AS movement_count,
                       COALESCE(SUM(m.quantity), 0) AS net_qty,
                       COALESCE(SUM(ABS(m.quantity)), 0) AS abs_qty,
                       COALESCE(SUM(ABS(m.stock_value)), 0) AS total_value
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1 {$where}
                GROUP BY m.movement_type
                ORDER BY movement_count DESC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function movementTrend(?int $warehouseId, array $filters = [], int $days = 30): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $days = max(7, min(90, $days));
        [$where, $params] = $this->filterClause($warehouseId, $filters);
        $where .= " AND m.created_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        $sql = "SELECT DATE(m.created_at) AS d,
                       COALESCE(SUM(CASE WHEN m.quantity > 0 THEN m.quantity ELSE 0 END), 0) AS stock_in,
                       COALESCE(SUM(CASE WHEN m.quantity < 0 THEN ABS(m.quantity) ELSE 0 END), 0) AS stock_out,
                       COUNT(*) AS movement_count
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1 {$where}
                GROUP BY DATE(m.created_at)
                ORDER BY d ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{0: string, 1: array} */
    private function filterClause(?int $warehouseId, array $filters): array
    {
        $sql = '';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND m.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if (!empty($filters['scope']) && $filters['scope'] === 'adjustments') {
            if (!empty($filters['movement_type']) && $filters['movement_type'] !== 'all') {
                $sql .= ' AND m.movement_type = ?';
                $params[] = $filters['movement_type'];
            } else {
                $placeholders = implode(',', array_fill(0, count(self::ADJUSTMENT_TYPES), '?'));
                $sql .= " AND m.movement_type IN ({$placeholders})";
                $params = array_merge($params, self::ADJUSTMENT_TYPES);
            }
        } elseif (!empty($filters['movement_type']) && $filters['movement_type'] !== 'all') {
            $sql .= ' AND m.movement_type = ?';
            $params[] = $filters['movement_type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND m.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND m.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }
        if (!empty($filters['product_id'])) {
            $sql .= ' AND m.product_id = ?';
            $params[] = (int) $filters['product_id'];
        }
        if (!empty($filters['q'])) {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR w.name LIKE ? OR m.notes LIKE ?
                      OR m.movement_type LIKE ? OR u.name LIKE ?)';
            $like = '%' . $filters['q'] . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }
        return [$sql, $params];
    }
}

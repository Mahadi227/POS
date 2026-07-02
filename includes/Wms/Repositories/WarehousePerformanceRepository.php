<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';
require_once __DIR__ . '/InventoryReportRepository.php';

class WarehousePerformanceRepository
{
    private PDO $db;
    private InventoryReportRepository $inventory;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->inventory = new InventoryReportRepository($this->db);
    }

    public function summary(array $filters): array
    {
        if (!WmsSchema::ready()) {
            return $this->emptySummary();
        }
        $mov = $this->inventory->movementSummary($filters);
        $dash = $this->inventory->dashboardSummary($filters);
        $perf = $this->inventory->performance($filters);
        [$whWhere, $whParams] = $this->warehouseScopeClause($filters);

        $sql = "SELECT COUNT(*) FROM warehouses w WHERE w.deleted_at IS NULL {$whWhere}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($whParams);
        $warehouseCount = (int) $stmt->fetchColumn();

        $expiring = $this->countExpiringSoon($filters, 30);

        return array_merge([
            'movements' => (int) ($mov['total'] ?? 0),
            'stock_in' => (int) ($mov['stock_in'] ?? 0),
            'stock_out' => (int) ($mov['stock_out'] ?? 0),
            'movement_value' => round((float) ($mov['total_value'] ?? 0), 2),
            'inventory_value' => round((float) ($dash['inventory_value'] ?? 0), 2),
            'low_stock' => (int) ($dash['low_stock'] ?? 0),
            'out_of_stock' => (int) ($dash['out_of_stock'] ?? 0),
            'capacity_used_pct' => (float) ($dash['capacity_used_pct'] ?? 0),
            'expiring_soon' => $expiring,
            'warehouse_count' => $warehouseCount,
            'total_products' => (int) ($dash['total_products'] ?? 0),
        ], $perf);
    }

    public function countRows(array $filters): int
    {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$whWhere, $whParams] = $this->warehouseScopeClause($filters);
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (w.name LIKE ? OR w.warehouse_code LIKE ?)';
            $whParams[] = '%' . $search . '%';
            $whParams[] = '%' . $search . '%';
        }
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM warehouses w WHERE w.deleted_at IS NULL {$whWhere}{$searchSql}"
        );
        $stmt->execute($whParams);
        return (int) $stmt->fetchColumn();
    }

    public function listRows(array $filters, int $limit = 50, int $offset = 0): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        [$dateFrom, $dateTo] = $this->dateRange($filters);
        [$whWhere, $whParams] = $this->warehouseScopeClause($filters);
        $search = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $searchSql = '';
        if ($search !== '') {
            $searchSql = ' AND (w.name LIKE ? OR w.warehouse_code LIKE ?)';
            $whParams[] = '%' . $search . '%';
            $whParams[] = '%' . $search . '%';
        }

        $params = array_merge(
            [$dateFrom, $dateTo, $dateFrom, $dateTo, $dateFrom, $dateTo],
            $whParams
        );

        $sql = "SELECT w.id, w.name AS warehouse_name, w.warehouse_code, w.warehouse_type, w.status,
                       w.capacity_units,
                       COALESCE(SUM(wi.quantity), 0) AS total_units,
                       COALESCE(SUM(wi.stock_value), 0) AS stock_value,
                       COUNT(DISTINCT wi.product_id) AS product_count,
                       SUM(CASE WHEN wi.quantity > 0 AND wi.quantity <= wi.reorder_level THEN 1 ELSE 0 END) AS low_stock,
                       SUM(CASE WHEN wi.quantity = 0 THEN 1 ELSE 0 END) AS out_of_stock,
                       (SELECT COUNT(*) FROM warehouse_stock_movements m
                        WHERE m.warehouse_id = w.id AND DATE(m.created_at) >= ? AND DATE(m.created_at) <= ? AND m.quantity > 0) AS stock_in,
                       (SELECT COUNT(*) FROM warehouse_stock_movements m
                        WHERE m.warehouse_id = w.id AND DATE(m.created_at) >= ? AND DATE(m.created_at) <= ? AND m.quantity < 0) AS stock_out,
                       (SELECT COALESCE(SUM(ABS(m.stock_value)), 0) FROM warehouse_stock_movements m
                        WHERE m.warehouse_id = w.id AND DATE(m.created_at) >= ? AND DATE(m.created_at) <= ?) AS movement_value
                FROM warehouses w
                LEFT JOIN warehouse_inventory wi ON wi.warehouse_id = w.id
                WHERE w.deleted_at IS NULL {$whWhere}{$searchSql}
                GROUP BY w.id, w.name, w.warehouse_code, w.warehouse_type, w.status, w.capacity_units
                ORDER BY stock_value DESC, w.name ASC
                LIMIT {$limit} OFFSET {$offset}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $cap = (int) ($row['capacity_units'] ?? 0);
            $units = (int) ($row['total_units'] ?? 0);
            $row['capacity_used_pct'] = $cap > 0 ? round(min(100, $units * 100 / $cap), 1) : 0;
            $row['stock_value'] = round((float) ($row['stock_value'] ?? 0), 2);
            $row['movement_value'] = round((float) ($row['movement_value'] ?? 0), 2);
            $invVal = (float) ($row['stock_value'] ?? 0);
            $movVal = (float) ($row['movement_value'] ?? 0);
            $row['turnover'] = $invVal > 0 ? round($movVal / $invVal, 2) : 0;
        }
        unset($row);

        return $rows;
    }

    public function charts(array $filters): array
    {
        return $this->inventory->charts($filters);
    }

    private function countExpiringSoon(array $filters, int $days): int
    {
        $sql = "SELECT COUNT(*) FROM batch_tracking b
                INNER JOIN warehouses w ON w.id = b.warehouse_id
                WHERE b.status = 'active' AND b.expiry_date IS NOT NULL
                  AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                  AND b.quantity > 0";
        $params = [$days];
        if (!empty($filters['warehouse_id'])) {
            $sql .= ' AND b.warehouse_id = ?';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['store_id'])) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = (int) $filters['store_id'];
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{0: string, 1: string} */
    private function dateRange(array $filters): array
    {
        $from = !empty($filters['date_from']) ? (string) $filters['date_from'] : date('Y-m-d', strtotime('-30 days'));
        $to = !empty($filters['date_to']) ? (string) $filters['date_to'] : date('Y-m-d');
        return [$from, $to];
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function warehouseScopeClause(array $filters): array
    {
        $sql = '';
        $params = [];
        if (!empty($filters['warehouse_id'])) {
            $sql .= ' AND w.id = ?';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['store_id'])) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = (int) $filters['store_id'];
        }
        return [$sql, $params];
    }

    private function emptySummary(): array
    {
        return [
            'movements' => 0, 'stock_in' => 0, 'stock_out' => 0, 'movement_value' => 0,
            'inventory_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0, 'capacity_used_pct' => 0,
            'expiring_soon' => 0, 'warehouse_count' => 0, 'total_products' => 0,
            'inventory_accuracy' => 0, 'receiving_efficiency' => 0, 'dispatch_efficiency' => 0,
            'transfer_success_rate' => 0, 'avg_inventory_age' => 0, 'warehouse_utilization' => 0,
            'inventory_turnover' => 0,
        ];
    }
}

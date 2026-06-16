<?php
declare(strict_types=1);

require_once __DIR__ . '/../WmsSchema.php';
require_once __DIR__ . '/../Repositories/WarehouseRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseInventoryRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseTransferRepository.php';
require_once __DIR__ . '/../Repositories/GoodsReceiptRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseDispatchRepository.php';
require_once __DIR__ . '/../Repositories/BatchTrackingRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseLogRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseMovementRepository.php';
require_once __DIR__ . '/../../Database/Database.php';

class WmsDashboardService
{
    private PDO $db;
    private WarehouseRepository $warehouses;
    private WarehouseInventoryRepository $inventory;
    private WarehouseTransferRepository $transfers;
    private GoodsReceiptRepository $receipts;
    private WarehouseDispatchRepository $dispatches;
    private BatchTrackingRepository $batches;
    private WarehouseLogRepository $logs;
    private WarehouseMovementRepository $movements;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->warehouses = new WarehouseRepository($this->db);
        $this->inventory = new WarehouseInventoryRepository($this->db);
        $this->transfers = new WarehouseTransferRepository($this->db);
        $this->receipts = new GoodsReceiptRepository($this->db);
        $this->dispatches = new WarehouseDispatchRepository($this->db);
        $this->batches = new BatchTrackingRepository($this->db);
        $this->logs = new WarehouseLogRepository();
        $this->movements = new WarehouseMovementRepository($this->db);
    }

    public function dashboard(?int $storeId): array
    {
        if (!WmsSchema::ready()) {
            return ['module_ready' => false, 'summary' => [], 'charts' => [], 'recent_activities' => []];
        }

        $summary = $this->warehouses->countSummary($storeId);
        $de = $this->inventory->countDamagedExpired();
        $productCount = $this->countProducts($storeId);

        return [
            'module_ready' => true,
            'summary' => array_merge($summary, [
                'total_products' => $productCount,
                'incoming_shipments' => $this->receipts->countIncoming(),
                'outgoing_shipments' => $this->dispatches->countOutgoing(),
                'pending_transfers' => $this->transfers->countPending(),
                'low_stock_alerts' => $this->inventory->countLowStock(),
                'damaged_products' => $de['damaged'],
                'expired_products' => $de['expired'],
                'expiring_soon' => $this->batches->countExpiringSoon(30),
            ]),
            'warehouse_status' => $this->warehouseStatus($storeId),
            'collection_chart' => $this->movementChart($storeId, 7),
            'capacity_chart' => $this->capacityChart($storeId),
            'recent_activities' => $this->logs->list(null, [], 15),
        ];
    }

    public function analytics(?int $storeId, string $period = 'month'): array
    {
        if (!WmsSchema::ready()) {
            return ['module_ready' => false, 'charts' => []];
        }
        $days = $period === 'week' ? 7 : ($period === 'year' ? 365 : 30);
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        return [
            'module_ready' => true,
            'inventory_trends' => $this->trendByType($from),
            'warehouse_comparison' => $this->warehouseComparison($storeId),
            'top_moving' => $this->topMoving($from, $storeId),
            'expiry_trends' => $this->expiryTrends(),
        ];
    }

    private function countProducts(?int $storeId): int
    {
        if ($storeId) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(DISTINCT wi.product_id) FROM warehouse_inventory wi
                 INNER JOIN warehouses w ON w.id = wi.warehouse_id
                 WHERE w.store_id = ? OR w.store_id IS NULL'
            );
            $stmt->execute([$storeId]);
            return (int) $stmt->fetchColumn();
        }
        return (int) $this->db->query('SELECT COUNT(DISTINCT product_id) FROM warehouse_inventory')->fetchColumn();
    }

    private function warehouseStatus(?int $storeId): array
    {
        $list = $this->warehouses->list($storeId, 'active');
        return array_map(function (array $w) {
            $cap = (int) ($w['capacity_units'] ?? 0);
            $units = (int) ($w['total_units'] ?? 0);
            $usage = $cap > 0 ? round(min(100, ($units / $cap) * 100), 1) : 0;
            return [
                'id' => $w['id'],
                'name' => $w['name'],
                'code' => $w['warehouse_code'],
                'type' => $w['warehouse_type'],
                'stock_value' => round((float) ($w['stock_value'] ?? 0), 2),
                'capacity_usage' => $usage,
                'status' => $w['status'],
            ];
        }, $list);
    }

    private function movementChart(?int $storeId, int $days): array
    {
        $labels = [];
        $incoming = [];
        $outgoing = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('d/m', strtotime($d));
            $incoming[] = $this->sumMovement($d, ['receipt_in', 'purchase', 'transfer_in'], $storeId);
            $outgoing[] = $this->sumMovement($d, ['dispatch_out', 'transfer_out', 'sale'], $storeId);
        }
        return ['labels' => $labels, 'incoming' => $incoming, 'outgoing' => $outgoing];
    }

    private function sumMovement(string $date, array $types, ?int $storeId): float
    {
        $ph = implode(',', array_fill(0, count($types), '?'));
        $sql = "SELECT COALESCE(SUM(ABS(m.quantity) * m.unit_cost), 0) FROM warehouse_stock_movements m";
        if ($storeId) {
            $sql .= ' INNER JOIN warehouses w ON w.id = m.warehouse_id WHERE DATE(m.created_at) = ? AND m.movement_type IN (' . $ph . ') AND (w.store_id = ? OR w.store_id IS NULL)';
            $params = array_merge([$date], $types, [$storeId]);
        } else {
            $sql .= ' WHERE DATE(m.created_at) = ? AND m.movement_type IN (' . $ph . ')';
            $params = array_merge([$date], $types);
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    private function capacityChart(?int $storeId): array
    {
        $status = $this->warehouseStatus($storeId);
        return [
            'labels' => array_column($status, 'name'),
            'usage' => array_column($status, 'capacity_usage'),
            'values' => array_column($status, 'stock_value'),
        ];
    }

    private function trendByType(string $from, ?int $storeId = null): array
    {
        $sql = "SELECT m.movement_type, COALESCE(SUM(ABS(m.quantity)), 0) AS total
                FROM warehouse_stock_movements m
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                WHERE m.created_at >= ?";
        $params = [$from];
        if ($storeId) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY m.movement_type ORDER BY total DESC LIMIT 10';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return [
            'labels' => array_column($rows, 'movement_type'),
            'values' => array_map('intval', array_column($rows, 'total')),
        ];
    }

    private function warehouseComparison(?int $storeId): array
    {
        $list = $this->warehouses->list($storeId);
        return [
            'labels' => array_column($list, 'name'),
            'values' => array_map(fn ($w) => round((float) ($w['stock_value'] ?? 0), 2), $list),
        ];
    }

    private function topMoving(string $from, ?int $storeId): array
    {
        $sql = "SELECT p.name, SUM(ABS(m.quantity)) AS total
                FROM warehouse_stock_movements m
                INNER JOIN products p ON p.id = m.product_id WHERE m.created_at >= ?";
        $params = [$from];
        if ($storeId) {
            $sql .= ' AND m.warehouse_id IN (SELECT id FROM warehouses WHERE store_id = ? OR store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY p.id ORDER BY total DESC LIMIT 8';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['labels' => array_column($rows, 'name'), 'values' => array_map('intval', array_column($rows, 'total'))];
    }

    private function expiryTrends(?int $storeId = null): array
    {
        $sql = "SELECT DATE_FORMAT(b.expiry_date, '%Y-%m') AS ym, COUNT(*) AS cnt
                FROM batch_tracking b
                INNER JOIN warehouses w ON w.id = b.warehouse_id
                WHERE b.expiry_date IS NOT NULL AND b.expiry_date >= CURDATE()";
        $params = [];
        if ($storeId) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY ym ORDER BY ym LIMIT 6';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return ['labels' => array_column($rows, 'ym'), 'values' => array_map('intval', array_column($rows, 'cnt'))];
    }
}

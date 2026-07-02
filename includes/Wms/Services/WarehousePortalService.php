<?php
declare(strict_types=1);

require_once __DIR__ . '/WmsDashboardService.php';
require_once __DIR__ . '/../Repositories/WarehouseRequestRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseTaskRepository.php';
require_once __DIR__ . '/../../Notifications/Repositories/NotificationRepository.php';
require_once __DIR__ . '/../../Helpers/WarehousePortalAuth.php';
require_once __DIR__ . '/../../Helpers/CurrencyHelper.php';
require_once __DIR__ . '/../../Database/Database.php';

class WarehousePortalService
{
    private WmsDashboardService $dashboard;
    private WarehouseTaskRepository $tasks;
    private WarehouseRequestRepository $requests;
    private WarehouseRepository $warehouses;
    private NotificationRepository $notifications;

    public function __construct()
    {
        $this->dashboard = new WmsDashboardService();
        $this->tasks = new WarehouseTaskRepository();
        $this->requests = new WarehouseRequestRepository();
        $this->warehouses = new WarehouseRepository();
        $this->notifications = new NotificationRepository();
    }

    public function portalDashboard(
        ?int $storeId,
        ?int $warehouseId,
        int $userId,
        string $period = 'week',
        ?string $from = null,
        ?string $to = null
    ): array {
        if ($warehouseId) {
            $this->tasks->seedDailyTasks($warehouseId);
        }

        $from = $from !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) ? $from : null;
        $to = $to !== null && preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) ? $to : null;

        $chartDays = match ($period) {
            'month' => 30,
            'year' => 90,
            'all' => 365,
            default => 7,
        };

        $base = ($from && $to)
            ? $this->dashboard->dashboard($storeId, $chartDays, $from, $to)
            : $this->dashboard->dashboard($storeId, $chartDays);
        if (!($base['module_ready'] ?? false)) {
            return ['module_ready' => false];
        }

        $summary = $base['summary'] ?? [];
        [$rangeFrom, $rangeTo] = $this->resolveDateRange($period, $from, $to, $base);
        $todayReceiving = $this->countToday($warehouseId, 'goods_receipts', 'received_at', 'warehouse_id');
        $todayDispatch = $this->countToday($warehouseId, 'warehouse_dispatches', 'created_at', 'from_warehouse_id');
        $todayTransfers = $this->countTodayTransfers($warehouseId);
        $periodReceiving = $this->countInRange($warehouseId, 'goods_receipts', 'received_at', 'warehouse_id', $rangeFrom, $rangeTo);
        $periodDispatch = $this->countInRange($warehouseId, 'warehouse_dispatches', 'created_at', 'from_warehouse_id', $rangeFrom, $rangeTo);
        $periodTransfers = $this->countTransfersInRange($warehouseId, $rangeFrom, $rangeTo);
        $pendingApprovals = $this->countPendingApprovals($warehouseId);
        $pendingDeliveries = (int) ($summary['incoming_shipments'] ?? 0);
        $capacity = $this->averageCapacity($base['warehouse_status'] ?? []);
        $currencyBreakdown = $this->warehouses->inventoryValueByStore($storeId);
        $currencies = array_values(array_unique(array_column($currencyBreakdown, 'currency')));
        $primaryCurrency = $storeId && $currencyBreakdown
            ? ($currencyBreakdown[0]['currency'] ?? 'FCFA')
            : ($currencies[0] ?? 'FCFA');
        if ($storeId) {
            $db = Database::getInstance()->getConnection();
            $ctx = CurrencyHelper::portalContext($db, $storeId, false);
            $primaryCurrency = $ctx['currency'];
        }

        $chartMeta = $base['collection_chart'] ?? [];

        return [
            'module_ready' => true,
            'period' => $period,
            'chart_days' => (int) ($base['chart_days'] ?? $chartMeta['days'] ?? $chartDays),
            'date_from' => $base['date_from'] ?? $chartMeta['date_from'] ?? null,
            'date_to' => $base['date_to'] ?? $chartMeta['date_to'] ?? null,
            'currency' => $primaryCurrency,
            'is_multi_currency' => count($currencies) > 1,
            'currency_breakdown' => $currencyBreakdown,
            'kpis' => [
                'today_receiving' => $todayReceiving,
                'today_dispatch' => $todayDispatch,
                'today_transfers' => $todayTransfers,
                'period_receiving' => $periodReceiving,
                'period_dispatch' => $periodDispatch,
                'period_transfers' => $periodTransfers,
                'pending_requests' => (int) (($this->requests->summary($storeId, $warehouseId)['pending'] ?? 0)),
                'pending_deliveries' => $pendingDeliveries,
                'pending_approvals' => $pendingApprovals,
                'low_stock' => (int) ($summary['low_stock_alerts'] ?? 0),
                'out_of_stock' => $this->countOutOfStock($warehouseId),
                'damaged_products' => (int) ($summary['damaged_products'] ?? 0),
                'expired_products' => (int) ($summary['expired_products'] ?? 0),
                'warehouse_capacity' => $capacity,
                'inventory_value' => round((float) ($summary['total_value'] ?? $summary['total_inventory_value'] ?? 0), 2),
                'total_warehouses' => (int) ($summary['total'] ?? 0),
                'active_warehouses' => (int) ($summary['active'] ?? 0),
                'total_products' => (int) ($summary['total_products'] ?? 0),
                'pending_transfers' => (int) ($summary['pending_transfers'] ?? 0),
                'incoming_shipments' => (int) ($summary['incoming_shipments'] ?? 0),
                'outgoing_shipments' => (int) ($summary['outgoing_shipments'] ?? 0),
                'expiring_soon' => (int) ($summary['expiring_soon'] ?? 0),
            ],
            'summary' => $summary,
            'warehouse_status' => $base['warehouse_status'] ?? [],
            'charts' => [
                'movements' => $base['collection_chart'] ?? [],
                'capacity' => $base['capacity_chart'] ?? [],
            ],
            'recent_activities' => $base['recent_activities'] ?? [],
            'recent_notifications' => $this->recentNotifications($userId, $warehouseId),
            'tasks' => $this->tasks->list($warehouseId, ['due' => true], 12),
            'task_summary' => $this->tasks->summary($warehouseId),
            'quick_actions' => $this->quickActions(),
            'permissions' => [
                'manage' => WarehousePortalAuth::canManage(),
                'receive' => WarehousePortalAuth::canReceive(),
                'dispatch' => WarehousePortalAuth::canDispatch(),
                'inventory' => WarehousePortalAuth::canInventory(),
                'transfer' => WarehousePortalAuth::canTransfer(),
                'reports' => WarehousePortalAuth::canReports(),
                'read_only' => WarehousePortalAuth::isReadOnly(),
            ],
        ];
    }

    public function globalSearch(?int $warehouseId, string $q): array
    {
        $q = trim($q);
        if ($q === '') {
            return ['products' => [], 'batches' => [], 'transfers' => [], 'purchase_orders' => []];
        }
        $db = Database::getInstance()->getConnection();
        $like = '%' . $q . '%';

        $products = [];
        $sql = 'SELECT p.id, p.name, p.sku, p.barcode, wi.quantity, w.name AS warehouse_name
                FROM products p
                LEFT JOIN warehouse_inventory wi ON wi.product_id = p.id
                LEFT JOIN warehouses w ON w.id = wi.warehouse_id
                WHERE p.deleted_at IS NULL AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
        $params = [$like, $like, $like];
        if ($warehouseId) {
            $sql .= ' AND (wi.warehouse_id = ? OR wi.warehouse_id IS NULL)';
            $params[] = $warehouseId;
        }
        $sql .= ' LIMIT 15';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $batches = [];
        try {
            $bsql = 'SELECT b.id, b.batch_number, b.serial_number, b.expiry_date, p.name AS product_name
                     FROM batch_tracking b
                     INNER JOIN products p ON p.id = b.product_id
                     WHERE b.batch_number LIKE ? OR b.serial_number LIKE ? OR b.barcode LIKE ?';
            $bparams = [$like, $like, $like];
            if ($warehouseId) {
                $bsql .= ' AND b.warehouse_id = ?';
                $bparams[] = $warehouseId;
            }
            $bsql .= ' LIMIT 10';
            $bstmt = $db->prepare($bsql);
            $bstmt->execute($bparams);
            $batches = $bstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

        $transfers = [];
        try {
            $tsql = 'SELECT id, transfer_number, status FROM warehouse_transfers WHERE transfer_number LIKE ?';
            $tparams = [$like];
            if ($warehouseId) {
                $tsql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
                $tparams[] = $warehouseId;
                $tparams[] = $warehouseId;
            }
            $tsql .= ' LIMIT 10';
            $tstmt = $db->prepare($tsql);
            $tstmt->execute($tparams);
            $transfers = $tstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

        $pos = [];
        try {
            $psql = 'SELECT id, po_number, status FROM purchase_orders WHERE po_number LIKE ?';
            $pparams = [$like];
            if ($warehouseId) {
                $psql .= ' AND warehouse_id = ?';
                $pparams[] = $warehouseId;
            }
            $psql .= ' LIMIT 10';
            $pstmt = $db->prepare($psql);
            $pstmt->execute($pparams);
            $pos = $pstmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
        }

        return compact('products', 'batches', 'transfers', 'purchase_orders');
    }

    private function quickActions(): array
    {
        $actions = [];
        if (WarehousePortalAuth::canReceive()) {
            $actions[] = ['id' => 'receive', 'label' => 'wh_action_receive', 'icon' => 'move_to_inbox', 'href' => 'receiving/receive_stock.php'];
        }
        if (WarehousePortalAuth::canDispatch()) {
            $actions[] = ['id' => 'dispatch', 'label' => 'wh_action_dispatch', 'icon' => 'local_shipping', 'href' => 'dispatch/dispatch_orders.php'];
        }
        if (WarehousePortalAuth::canTransfer()) {
            $actions[] = ['id' => 'transfer', 'label' => 'wh_action_transfer', 'icon' => 'sync_alt', 'href' => 'transfers/transfer_requests.php'];
        }
        if (WarehousePortalAuth::canInventory()) {
            $actions[] = ['id' => 'scan', 'label' => 'wh_action_scan', 'icon' => 'qr_code_scanner', 'href' => 'inventory/barcode_scanner.php'];
            $actions[] = ['id' => 'count', 'label' => 'wh_action_count', 'icon' => 'fact_check', 'href' => 'inventory/stock_count.php'];
        }
        return $actions;
    }

    private function resolveDateRange(string $period, ?string $from, ?string $to, array $base): array
    {
        if ($from && $to) {
            return $from <= $to ? [$from, $to] : [$to, $from];
        }
        $chartMeta = $base['collection_chart'] ?? [];
        $dateFrom = $base['date_from'] ?? ($chartMeta['date_from'] ?? null);
        $dateTo = $base['date_to'] ?? ($chartMeta['date_to'] ?? null);
        if ($dateFrom && $dateTo) {
            return $dateFrom <= $dateTo ? [$dateFrom, $dateTo] : [$dateTo, $dateFrom];
        }
        $chartDays = match ($period) {
            'month' => 30,
            'year' => 90,
            'all' => 365,
            default => 7,
        };
        $dateTo = date('Y-m-d');
        $dateFrom = date('Y-m-d', strtotime('-' . ($chartDays - 1) . ' days'));
        return [$dateFrom, $dateTo];
    }

    private function countToday(?int $warehouseId, string $table, string $dateCol, string $warehouseCol = 'warehouse_id'): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) FROM {$table} WHERE DATE({$dateCol}) = CURDATE()";
            $params = [];
            if ($warehouseId) {
                $sql .= " AND {$warehouseCol} = ?";
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countInRange(
        ?int $warehouseId,
        string $table,
        string $dateCol,
        string $warehouseCol,
        string $from,
        string $to
    ): int {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) FROM {$table} WHERE DATE({$dateCol}) BETWEEN ? AND ?";
            $params = [$from, $to];
            if ($warehouseId) {
                $sql .= " AND {$warehouseCol} = ?";
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countTransfersInRange(?int $warehouseId, string $from, string $to): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = 'SELECT COUNT(*) FROM warehouse_transfers WHERE DATE(updated_at) BETWEEN ? AND ?';
            $params = [$from, $to];
            if ($warehouseId) {
                $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
                $params[] = $warehouseId;
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countTodayTransfers(?int $warehouseId): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) FROM warehouse_transfers WHERE DATE(updated_at) = CURDATE()";
            $params = [];
            if ($warehouseId) {
                $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
                $params[] = $warehouseId;
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countPendingApprovals(?int $warehouseId): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = "SELECT COUNT(*) FROM warehouse_transfers WHERE status IN ('requested','approved','picking')";
            $params = [];
            if ($warehouseId) {
                $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
                $params[] = $warehouseId;
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function countOutOfStock(?int $warehouseId): int
    {
        try {
            $db = Database::getInstance()->getConnection();
            $sql = 'SELECT COUNT(*) FROM warehouse_inventory WHERE quantity <= 0';
            $params = [];
            if ($warehouseId) {
                $sql .= ' AND warehouse_id = ?';
                $params[] = $warehouseId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        } catch (Throwable) {
            return 0;
        }
    }

    private function averageCapacity(array $warehouses): float
    {
        if (!$warehouses) {
            return 0;
        }
        $sum = array_sum(array_column($warehouses, 'capacity_usage'));
        return round($sum / count($warehouses), 1);
    }

    private function recentNotifications(int $userId, ?int $warehouseId): array
    {
        try {
            return $this->notifications->listForUser($userId, [
                'warehouse_id' => $warehouseId,
                'warehouse_scope' => true,
            ], 8);
        } catch (Throwable) {
            return [];
        }
    }
}

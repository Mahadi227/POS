<?php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/../Config/config.php';

class InventoryLedgerController
{
    private $db;
    private static $ledgerReady = false;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest($method, $path)
    {
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin', 'cashier', 'staff']);

        $resource = $path[1] ?? null;

        switch ($method) {
            case 'GET':
                if ($resource === 'ledger') {
                    return $this->getLedgerEntries();
                }
                if ($resource === 'movements') {
                    return $this->getMovements();
                }
                if ($resource === 'analytics') {
                    return $this->getAnalytics();
                }
                if ($resource === 'reports') {
                    return $this->getReports();
                }
                if ($resource === 'audit') {
                    return $this->getAuditTrail();
                }
                if ($resource === 'expired') {
                    return $this->getExpiredProducts();
                }
                return $this->notFound();
            case 'POST':
                if ($resource === 'ledger') {
                    return $this->createLedgerEntry();
                }
                if ($resource === 'movements') {
                    return $this->createMovement();
                }
                return $this->notFound();
            default:
                return $this->methodNotAllowed();
        }
    }

    /**
     * Ensure inventory_ledger exists (auto-applies migration 006 when missing).
     */
    private function ensureLedgerTable(): bool
    {
        if (self::$ledgerReady) {
            return true;
        }

        try {
            $this->db->query('SELECT 1 FROM inventory_ledger LIMIT 1');
            self::$ledgerReady = true;
            return true;
        } catch (PDOException $e) {
            // Table missing — create it
        }

        $migration = __DIR__ . '/../Database/migrations/006_inventory_ledger.sql';
        if (!is_file($migration)) {
            return false;
        }

        try {
            $sql = file_get_contents($migration);
            foreach (array_filter(array_map('trim', explode(';', $sql))) as $statement) {
                if ($statement !== '') {
                    $this->db->exec($statement);
                }
            }
            self::$ledgerReady = true;
            return true;
        } catch (PDOException $e) {
            error_log('InventoryLedgerController: ensureLedgerTable failed — ' . $e->getMessage());
            return false;
        }
    }

    private function getLedgerEntries()
    {
        $params = $this->requestParams();

        try {
            // inventory_logs is the source of truth — avoids stale backfilled ledger rows.
            $rows = $this->fetchLogLedgerRows($params);
            $rows = $this->appendLedgerOnlyEntries($rows, $params);
            $rows = $this->enrichStockColumns($rows);
            $rows = InventoryLedgerHelper::sortRowsByDateDesc($rows);
            $rows = array_slice($rows, 0, 250);

            $this->response(['status' => 'success', 'data' => $rows, 'source' => 'inventory_logs']);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getLedgerEntries — ' . $e->getMessage());
            $this->getLedgerFromLogs($params);
        }
    }

    /**
     * Rare ledger rows with no matching inventory_log (keep for completeness).
     */
    private function appendLedgerOnlyEntries(array $logRows, array $params): array
    {
        if (!$this->ensureLedgerTable()) {
            return $logRows;
        }

        $existingLogIds = [];
        foreach ($logRows as $row) {
            if (($row['reference_type'] ?? '') === 'inventory_log' && ($row['reference_id'] ?? '') !== '') {
                $existingLogIds[(int) $row['reference_id']] = true;
            }
            if (!empty($row['id']) && ($row['reference_type'] ?? '') === 'inventory_log') {
                $existingLogIds[(int) $row['id']] = true;
            }
        }

        $sql = 'SELECT l.*, p.name AS product_name, p.sku, p.barcode, u.name AS user_name, s.name AS store_name
                FROM inventory_ledger l
                LEFT JOIN products p ON l.product_id = p.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN stores s ON l.store_id = s.id
                WHERE (l.reference_type IS NULL OR l.reference_type <> \'inventory_log\')';
        $values = [];

        if (!empty($params['product_id'])) {
            $sql .= ' AND l.product_id = ?';
            $values[] = (int) $params['product_id'];
        }
        if (!empty($params['store_id'])) {
            $sql .= ' AND l.store_id = ?';
            $values[] = (int) $params['store_id'];
        }
        if (!empty($params['type']) || !empty($params['movement_type'])) {
            $type = $params['movement_type'] ?? $params['type'];
            $sql .= ' AND l.movement_type = ?';
            $values[] = $type;
        }
        if (!empty($params['date_from'])) {
            $sql .= ' AND l.movement_date >= ?';
            $values[] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $dateTo = $params['date_to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo .= ' 23:59:59';
            }
            $sql .= ' AND l.movement_date <= ?';
            $values[] = $dateTo;
        }

        $sql .= ' ORDER BY l.movement_date DESC LIMIT 50';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $ledgerOnly = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_merge($logRows, $ledgerOnly);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::appendLedgerOnlyEntries — ' . $e->getMessage());
            return $logRows;
        }
    }

    private function enrichStockColumns(array $rows): array
    {
        if (empty($rows)) {
            return $rows;
        }

        $productIds = InventoryLedgerHelper::productIdsFromRows($rows);
        $snapshots = InventoryLedgerHelper::computeStockSnapshots($this->db, $productIds);

        return InventoryLedgerHelper::applyStockSnapshots($rows, $snapshots);
    }

    /**
     * Build ledger-shaped rows from inventory_logs (stock columns enriched separately).
     */
    private function fetchLogLedgerRows(array $params, array $excludeLogIds = []): array
    {
        $reasonMap = [
            'sale'       => 'sale',
            'restock'    => 'adjustment',
            'damage'     => 'damaged',
            'correction' => 'adjustment',
            'transfer'   => 'transfer_out',
        ];

        $sql = 'SELECT il.id, il.product_id, il.store_id, il.user_id, il.change_amount, il.reason, il.created_at,
                       p.name AS product_name, p.sku, p.barcode, p.cost, p.price,
                       u.name AS user_name, s.name AS store_name
                FROM inventory_logs il
                INNER JOIN products p ON p.id = il.product_id
                LEFT JOIN users u ON u.id = il.user_id
                LEFT JOIN stores s ON s.id = il.store_id
                WHERE 1=1';
        $values = [];

        if (!empty($excludeLogIds)) {
            $placeholders = implode(',', array_fill(0, count($excludeLogIds), '?'));
            $sql .= " AND il.id NOT IN ($placeholders)";
            foreach ($excludeLogIds as $logId) {
                $values[] = (int) $logId;
            }
        }

        if (!empty($params['product_id'])) {
            $sql .= ' AND il.product_id = ?';
            $values[] = (int) $params['product_id'];
        }
        if (!empty($params['store_id'])) {
            $sql .= ' AND il.store_id = ?';
            $values[] = (int) $params['store_id'];
        }
        if (!empty($params['type']) || !empty($params['movement_type'])) {
            $type = $params['movement_type'] ?? $params['type'];
            $reason = array_search($type, $reasonMap, true);
            if ($reason !== false) {
                $sql .= ' AND il.reason = ?';
                $values[] = $reason;
            } elseif ($type === 'adjustment') {
                $sql .= " AND il.reason IN ('restock', 'correction')";
            } elseif ($type === 'damaged') {
                $sql .= " AND il.reason = 'damage'";
            } elseif ($type === 'expired') {
                $sql .= ' AND 1=0';
            } elseif ($type === 'manual_edit') {
                $sql .= " AND il.reason IN ('restock', 'correction')";
            } elseif (in_array($type, ['transfer_in', 'transfer_out', 'transfer'], true)) {
                $sql .= " AND il.reason = 'transfer'";
            }
        }
        if (!empty($params['q'])) {
            $query = '%' . $params['q'] . '%';
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR u.name LIKE ? OR s.name LIKE ?)';
            array_push($values, $query, $query, $query, $query, $query);
        }
        if (!empty($params['date_from'])) {
            $sql .= ' AND il.created_at >= ?';
            $values[] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $dateTo = $params['date_to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo .= ' 23:59:59';
            }
            $sql .= ' AND il.created_at <= ?';
            $values[] = $dateTo;
        }

        $sql .= ' ORDER BY il.created_at DESC LIMIT 500';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return array_map(static function ($il) use ($reasonMap) {
                $cost = (float) ($il['cost'] ?? 0);
                $price = (float) ($il['price'] ?? 0);
                $change = (int) $il['change_amount'];
                $movementType = $reasonMap[$il['reason']] ?? 'adjustment';

                return [
                    'id'              => $il['id'],
                    'product_id'      => $il['product_id'],
                    'store_id'        => $il['store_id'],
                    'user_id'         => $il['user_id'],
                    'movement_type'   => $movementType,
                    'reference_id'    => (string) $il['id'],
                    'reference_type'  => 'inventory_log',
                    'opening_stock'   => 0,
                    'stock_in'        => max(0, $change),
                    'stock_out'       => max(0, -$change),
                    'current_stock'   => 0,
                    'purchase_price'  => $cost,
                    'selling_price'   => $price,
                    'opening_stock_value' => 0,
                    'stock_out_value' => 0,
                    'current_stock_value' => 0,
                    'estimated_profit' => 0,
                    'notes'           => 'From inventory_logs #' . $il['id'],
                    'movement_date'   => $il['created_at'],
                    'product_name'    => $il['product_name'],
                    'sku'             => $il['sku'],
                    'barcode'         => $il['barcode'],
                    'user_name'       => $il['user_name'],
                    'store_name'      => $il['store_name'],
                ];
            }, $logs);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::fetchLogLedgerRows — ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Fallback: build ledger-shaped rows from inventory_logs when ledger table is unavailable.
     */
    private function getLedgerFromLogs(array $params)
    {
        try {
            $rows = $this->fetchLogLedgerRows($params);
            $rows = $this->enrichStockColumns($rows);
            $rows = InventoryLedgerHelper::sortRowsByDateDesc($rows);
            $rows = array_slice($rows, 0, 250);
            $this->response(['status' => 'success', 'data' => $rows, 'source' => 'inventory_logs']);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getLedgerFromLogs — ' . $e->getMessage());
            $this->error('Unable to load ledger entries');
        }
    }

    private function getMovements()
    {
        $params = $this->requestParams();

        $sql = 'SELECT m.*, p.name AS product_name, p.sku, s.name AS from_store, t.name AS to_store
                FROM stock_movements m
                LEFT JOIN products p ON m.product_id = p.id
                LEFT JOIN stores s ON m.from_store_id = s.id
                LEFT JOIN stores t ON m.to_store_id = t.id
                WHERE 1=1';
        $values = [];

        if (!empty($params['store_id'])) {
            $sid = (int) $params['store_id'];
            $sql .= ' AND (m.from_store_id = ? OR m.to_store_id = ?)';
            array_push($values, $sid, $sid);
        }

        if (!empty($params['status'])) {
            $sql .= ' AND m.status = ?';
            $values[] = $params['status'];
        }

        if (!empty($params['q'])) {
            $query = '%' . $params['q'] . '%';
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR s.name LIKE ? OR t.name LIKE ?)';
            array_push($values, $query, $query, $query, $query);
        }

        if (!empty($params['date_from'])) {
            $sql .= ' AND m.created_at >= ?';
            $values[] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $dateTo = $params['date_to'];
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
                $dateTo .= ' 23:59:59';
            }
            $sql .= ' AND m.created_at <= ?';
            $values[] = $dateTo;
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT 250';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getMovements — ' . $e->getMessage());
            $this->error('Unable to load stock movements');
        }
    }

    private function getAnalytics()
    {
        $params = $this->requestParams();
        $period = $params['period'] ?? 'month';
        if (!in_array($period, ['today', 'week', 'month', '90d', 'all'], true)) {
            $period = 'month';
        }

        [$from, $to] = $this->periodBounds($period);
        $dateFilter = $period === 'all' ? '' : ' AND l.movement_date >= ? AND l.movement_date <= ?';
        $dateParams = $period === 'all' ? [] : [$from, $to];

        $inventoryStats = $this->analyticsInventoryStats();
        $data = [
            'period'          => $period,
            'from'            => $from,
            'to'              => $to,
            'stats'           => array_merge([
                'total_movements' => 0,
                'total_in'        => 0,
                'total_out'       => 0,
                'estimated_profit'=> 0.0,
            ], $inventoryStats),
            'movement_by_type'=> [],
            'daily_trend'     => ['labels' => [], 'stock_in' => [], 'stock_out' => [], 'movements' => []],
            'top_products'    => [],
            'stock_status'    => [
                'in_stock'    => max(0, ($inventoryStats['total_products'] ?? 0) - ($inventoryStats['low_stock'] ?? 0) - ($inventoryStats['out_of_stock'] ?? 0)),
                'low_stock'   => $inventoryStats['low_stock'] ?? 0,
                'out_of_stock'=> $inventoryStats['out_of_stock'] ?? 0,
            ],
        ];

        if (!$this->ensureLedgerTable()) {
            $fallback = $this->analyticsFromLogs($period, $from, $to);
            $data['stats'] = array_merge($data['stats'], $fallback['stats']);
            $data['movement_by_type'] = $fallback['movement_by_type'];
            $data['daily_trend'] = $fallback['daily_trend'];
            $data['top_products'] = $fallback['top_products'];
            $this->response(['status' => 'success', 'data' => $data, 'source' => 'inventory_logs']);
            return;
        }

        try {
            [$lScope, $lParams] = StoreScope::sqlFilter($this->db, 'store_id', 'l');

            $stmt = $this->db->prepare(
                "SELECT l.movement_type, COUNT(*) AS count,
                        COALESCE(SUM(l.stock_in), 0) AS total_in,
                        COALESCE(SUM(l.stock_out), 0) AS total_out,
                        COALESCE(SUM(l.estimated_profit), 0) AS profit_value
                 FROM inventory_ledger l
                 WHERE 1=1{$lScope}{$dateFilter}
                 GROUP BY l.movement_type
                 ORDER BY count DESC"
            );
            $stmt->execute(array_merge($lParams, $dateParams));
            $data['movement_by_type'] = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $aggStmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_movements,
                        COALESCE(SUM(l.stock_in), 0) AS total_in,
                        COALESCE(SUM(l.stock_out), 0) AS total_out,
                        COALESCE(SUM(l.estimated_profit), 0) AS estimated_profit
                 FROM inventory_ledger l
                 WHERE 1=1{$lScope}{$dateFilter}"
            );
            $aggStmt->execute(array_merge($lParams, $dateParams));
            $agg = $aggStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $data['stats']['total_movements'] = (int) ($agg['total_movements'] ?? 0);
            $data['stats']['total_in'] = (int) ($agg['total_in'] ?? 0);
            $data['stats']['total_out'] = (int) ($agg['total_out'] ?? 0);
            $data['stats']['estimated_profit'] = (float) ($agg['estimated_profit'] ?? 0);

            $data['daily_trend'] = $this->analyticsDailyTrend($lScope, $lParams, $period, $from, $to);

            $topStmt = $this->db->prepare(
                "SELECT p.name, COALESCE(SUM(l.stock_out), 0) AS sold_units,
                        COALESCE(SUM(l.stock_out_value), 0) AS sold_value,
                        COALESCE(SUM(l.estimated_profit), 0) AS profit_value
                 FROM inventory_ledger l
                 LEFT JOIN products p ON l.product_id = p.id
                 WHERE l.movement_type = 'sale'{$lScope}{$dateFilter}
                 GROUP BY p.id, p.name
                 HAVING sold_units > 0
                 ORDER BY sold_units DESC
                 LIMIT 10"
            );
            $topStmt->execute(array_merge($lParams, $dateParams));
            $data['top_products'] = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            $this->response(['status' => 'success', 'data' => $data]);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getAnalytics — ' . $e->getMessage());
            $this->error('Unable to load analytics');
        }
    }

    /** @return array<string, int|float> */
    private function analyticsInventoryStats(): array
    {
        [$pScope, $pParams] = StoreScope::sqlFilter($this->db, 'store_id', 'p');
        $productWhere = $this->hasColumn('products', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';
        $stats = [
            'total_products'  => 0,
            'out_of_stock'    => 0,
            'low_stock'       => 0,
            'inventory_value' => 0.0,
            'total_units'     => 0,
        ];

        try {
            $stmt = $this->db->prepare("SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope}");
            $stmt->execute($pParams);
            $stats['total_products'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope} AND p.stock_quantity <= 0"
            );
            $stmt->execute($pParams);
            $stats['out_of_stock'] = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare(
                "SELECT COUNT(p.id) FROM products p
                 WHERE {$productWhere}{$pScope}
                 AND p.stock_quantity > 0 AND p.stock_quantity <= COALESCE(p.min_stock_level, 5)"
            );
            $stmt->execute($pParams);
            $stats['low_stock'] = (int) $stmt->fetchColumn();

            $priceCol = $this->hasColumn('products', 'price') ? 'COALESCE(p.price, 0)' : '0';
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(p.stock_quantity * {$priceCol}), 0), COALESCE(SUM(p.stock_quantity), 0)
                 FROM products p WHERE {$productWhere}{$pScope}"
            );
            $stmt->execute($pParams);
            $row = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
            $stats['inventory_value'] = (float) $row[0];
            $stats['total_units'] = (int) $row[1];
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::analyticsInventoryStats — ' . $e->getMessage());
        }

        return $stats;
    }

    /** @return array<string, mixed> */
    private function analyticsDailyTrend(string $lScope, array $lParams, string $period, string $from, string $to): array
    {
        $labels = [];
        $stockIn = [];
        $stockOut = [];
        $movements = [];

        if ($period === 'all') {
            return compact('labels', 'stockIn', 'stockOut', 'movements');
        }

        $start = new DateTime(substr($from, 0, 10));
        $end = new DateTime(substr($to, 0, 10));
        $interval = new DateInterval('P1D');
        $range = new DatePeriod($start, $interval, $end->modify('+1 day'));

        $sql = "SELECT COALESCE(SUM(l.stock_in), 0) AS total_in,
                       COALESCE(SUM(l.stock_out), 0) AS total_out,
                       COUNT(l.id) AS cnt
                FROM inventory_ledger l
                WHERE 1=1{$lScope} AND DATE(l.movement_date) = ?";
        $stmt = $this->db->prepare($sql);

        foreach ($range as $day) {
            $d = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $stmt->execute(array_merge($lParams, [$d]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['total_in' => 0, 'total_out' => 0, 'cnt' => 0];
            $stockIn[] = (int) $row['total_in'];
            $stockOut[] = (int) $row['total_out'];
            $movements[] = (int) $row['cnt'];
        }

        return [
            'labels'    => $labels,
            'stock_in'  => $stockIn,
            'stock_out' => $stockOut,
            'movements' => $movements,
        ];
    }

    /** @return array<string, mixed> */
    private function analyticsFromLogs(string $period, string $from, string $to): array
    {
        [$scope, $scopeParams] = StoreScope::sqlFilter($this->db, 'store_id', 'il');
        $dateFilter = $period === 'all' ? '' : ' AND il.created_at >= ? AND il.created_at <= ?';
        $dateParams = $period === 'all' ? [] : [$from, $to];

        $stats = ['total_movements' => 0, 'total_in' => 0, 'total_out' => 0, 'estimated_profit' => 0.0];
        $movementByType = [];
        $dailyTrend = ['labels' => [], 'stock_in' => [], 'stock_out' => [], 'movements' => []];
        $topProducts = [];

        try {
            $aggStmt = $this->db->prepare(
                "SELECT COUNT(*) AS total_movements,
                        COALESCE(SUM(CASE WHEN il.change_amount > 0 THEN il.change_amount ELSE 0 END), 0) AS total_in,
                        COALESCE(SUM(CASE WHEN il.change_amount < 0 THEN ABS(il.change_amount) ELSE 0 END), 0) AS total_out
                 FROM inventory_logs il
                 WHERE 1=1{$scope}{$dateFilter}"
            );
            $aggStmt->execute(array_merge($scopeParams, $dateParams));
            $agg = $aggStmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total_movements'] = (int) ($agg['total_movements'] ?? 0);
            $stats['total_in'] = (int) ($agg['total_in'] ?? 0);
            $stats['total_out'] = (int) ($agg['total_out'] ?? 0);

            $typeStmt = $this->db->prepare(
                "SELECT il.reason AS movement_type, COUNT(*) AS count,
                        COALESCE(SUM(CASE WHEN il.change_amount > 0 THEN il.change_amount ELSE 0 END), 0) AS total_in,
                        COALESCE(SUM(CASE WHEN il.change_amount < 0 THEN ABS(il.change_amount) ELSE 0 END), 0) AS total_out
                 FROM inventory_logs il
                 WHERE 1=1{$scope}{$dateFilter}
                 GROUP BY il.reason
                 ORDER BY count DESC"
            );
            $typeStmt->execute(array_merge($scopeParams, $dateParams));
            $movementByType = $typeStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

            if ($period !== 'all') {
                $start = new DateTime(substr($from, 0, 10));
                $end = new DateTime(substr($to, 0, 10));
                $range = new DatePeriod($start, new DateInterval('P1D'), $end->modify('+1 day'));
                $dayStmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(CASE WHEN il.change_amount > 0 THEN il.change_amount ELSE 0 END), 0) AS total_in,
                            COALESCE(SUM(CASE WHEN il.change_amount < 0 THEN ABS(il.change_amount) ELSE 0 END), 0) AS total_out,
                            COUNT(*) AS cnt
                     FROM inventory_logs il
                     WHERE 1=1{$scope} AND DATE(il.created_at) = ?"
                );
                foreach ($range as $day) {
                    $d = $day->format('Y-m-d');
                    $dailyTrend['labels'][] = $day->format('d/m');
                    $dayStmt->execute(array_merge($scopeParams, [$d]));
                    $row = $dayStmt->fetch(PDO::FETCH_ASSOC) ?: ['total_in' => 0, 'total_out' => 0, 'cnt' => 0];
                    $dailyTrend['stock_in'][] = (int) $row['total_in'];
                    $dailyTrend['stock_out'][] = (int) $row['total_out'];
                    $dailyTrend['movements'][] = (int) $row['cnt'];
                }
            }

            $topStmt = $this->db->prepare(
                "SELECT p.name, SUM(ABS(il.change_amount)) AS sold_units
                 FROM inventory_logs il
                 INNER JOIN products p ON p.id = il.product_id
                 WHERE il.reason = 'sale'{$scope}{$dateFilter}
                 GROUP BY p.id, p.name
                 ORDER BY sold_units DESC
                 LIMIT 10"
            );
            $topStmt->execute(array_merge($scopeParams, $dateParams));
            $topProducts = array_map(static function ($r) {
                return [
                    'name'         => $r['name'],
                    'sold_units'   => (int) ($r['sold_units'] ?? 0),
                    'sold_value'   => 0,
                    'profit_value' => 0,
                ];
            }, $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::analyticsFromLogs — ' . $e->getMessage());
        }

        return [
            'stats'            => $stats,
            'movement_by_type' => $movementByType,
            'daily_trend'      => $dailyTrend,
            'top_products'     => $topProducts,
        ];
    }

    private function getReports()
    {
        $params = $this->requestParams();
        $period = $params['period'] ?? 'month';
        if (!in_array($period, ['today', 'week', 'month', '90d', 'all'], true)) {
            $period = 'month';
        }

        [$from, $to, $periodLabel] = $this->periodBounds($period);
        [$pScope, $pParams] = StoreScope::sqlFilter($this->db, 'store_id', 'p');
        $productWhere = $this->hasColumn('products', 'deleted_at') ? 'p.deleted_at IS NULL' : '1=1';

        $totalProducts = 0;
        $outOfStock = 0;
        $lowStock = 0;
        $costValue = 0.0;
        $retailValue = 0.0;
        $totalUnits = 0;

        try {
            $stmt = $this->db->prepare("SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope}");
            $stmt->execute($pParams);
            $totalProducts = (int) $stmt->fetchColumn();

            if ($this->hasColumn('products', 'stock_quantity')) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope} AND p.stock_quantity <= 0"
                );
                $stmt->execute($pParams);
                $outOfStock = (int) $stmt->fetchColumn();

                if ($this->hasColumn('products', 'min_stock_level')) {
                    $stmt = $this->db->prepare(
                        "SELECT COUNT(p.id) FROM products p
                         WHERE {$productWhere}{$pScope}
                         AND p.stock_quantity > 0 AND p.stock_quantity <= COALESCE(p.min_stock_level, 5)"
                    );
                    $stmt->execute($pParams);
                    $lowStock = (int) $stmt->fetchColumn();
                }

                $costCol = $this->hasColumn('products', 'cost') ? 'COALESCE(p.cost, 0)' : '0';
                $priceCol = $this->hasColumn('products', 'price') ? 'COALESCE(p.price, 0)' : '0';
                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(p.stock_quantity * {$costCol}), 0),
                            COALESCE(SUM(p.stock_quantity * {$priceCol}), 0),
                            COALESCE(SUM(p.stock_quantity), 0)
                     FROM products p WHERE {$productWhere}{$pScope}"
                );
                $stmt->execute($pParams);
                $invRow = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0, 0];
                $costValue = (float) $invRow[0];
                $retailValue = (float) $invRow[1];
                $totalUnits = (int) $invRow[2];
            }
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getReports summary — ' . $e->getMessage());
        }

        $categoryBreakdown = [];
        try {
            $catSql = "SELECT COALESCE(c.name, 'Uncategorized') AS name,
                              COUNT(p.id) AS product_count,
                              COALESCE(SUM(p.stock_quantity), 0) AS units,
                              COALESCE(SUM(p.stock_quantity * COALESCE(p.cost, 0)), 0) AS cost_value,
                              COALESCE(SUM(p.stock_quantity * COALESCE(p.price, 0)), 0) AS retail_value
                       FROM products p
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE {$productWhere}{$pScope}
                       GROUP BY COALESCE(c.name, 'Uncategorized')
                       ORDER BY cost_value DESC";
            $catStmt = $this->db->prepare($catSql);
            $catStmt->execute($pParams);
            $categoryBreakdown = $catStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getReports categories — ' . $e->getMessage());
        }

        $lowStockProducts = [];
        try {
            $lowSql = "SELECT p.id, p.name, p.sku, p.stock_quantity, COALESCE(p.min_stock_level, 5) AS min_stock_level,
                              COALESCE(p.cost, 0) AS cost, COALESCE(p.price, 0) AS price,
                              COALESCE(c.name, 'Uncategorized') AS category_name
                       FROM products p
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE {$productWhere}{$pScope}
                       AND (p.stock_quantity <= 0 OR p.stock_quantity <= COALESCE(p.min_stock_level, 5))
                       ORDER BY p.stock_quantity ASC, p.name ASC
                       LIMIT 100";
            $lowStmt = $this->db->prepare($lowSql);
            $lowStmt->execute($pParams);
            $lowStockProducts = $lowStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getReports low stock — ' . $e->getMessage());
        }

        $topMoving = [];
        if ($period !== 'all') {
            try {
                [$saleScope, $saleParams] = StoreScope::sqlFilter($this->db, 'store_id', 's');
                $salesWhere = "s.status = 'completed'";
                if ($this->hasColumn('sales', 'deleted_at')) {
                    $salesWhere .= ' AND s.deleted_at IS NULL';
                }
                $moveSql = "SELECT p.id, p.name, p.sku, SUM(si.quantity) AS qty_sold,
                                   SUM(si.subtotal) AS revenue
                            FROM sale_items si
                            INNER JOIN sales s ON si.sale_id = s.id
                            INNER JOIN products p ON si.product_id = p.id
                            WHERE {$salesWhere}{$saleScope}
                            AND s.created_at >= ? AND s.created_at <= ?
                            GROUP BY p.id, p.name, p.sku
                            ORDER BY qty_sold DESC
                            LIMIT 15";
                $moveStmt = $this->db->prepare($moveSql);
                $moveStmt->execute(array_merge($saleParams, [$from, $to]));
                $topMoving = $moveStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                error_log('InventoryLedgerController::getReports top moving — ' . $e->getMessage());
            }
        }

        $ledgerSummary = ['total_in' => 0, 'total_out' => 0, 'entries' => 0];
        if ($period !== 'all' && $this->ensureLedgerTable()) {
            try {
                [$lScope, $lParams] = StoreScope::sqlFilter($this->db, 'store_id', 'l');
                $ledgerSql = "SELECT COALESCE(SUM(l.stock_in), 0), COALESCE(SUM(l.stock_out), 0), COUNT(l.id)
                              FROM inventory_ledger l
                              WHERE 1=1{$lScope}
                              AND l.movement_date >= ? AND l.movement_date <= ?";
                $ledgerStmt = $this->db->prepare($ledgerSql);
                $ledgerStmt->execute(array_merge($lParams, [$from, $to]));
                $ledgerRow = $ledgerStmt->fetch(PDO::FETCH_NUM) ?: [0, 0, 0];
                $ledgerSummary = [
                    'total_in'  => (int) $ledgerRow[0],
                    'total_out' => (int) $ledgerRow[1],
                    'entries'   => (int) $ledgerRow[2],
                ];
            } catch (PDOException $e) {
                error_log('InventoryLedgerController::getReports ledger — ' . $e->getMessage());
            }
        }

        $inStock = max(0, $totalProducts - $lowStock - $outOfStock);

        $this->response([
            'status' => 'success',
            'data'   => [
                'period'       => $period,
                'period_label' => $periodLabel,
                'from'         => $from,
                'to'           => $to,
                'summary'      => [
                    'total_products' => $totalProducts,
                    'out_of_stock'   => $outOfStock,
                    'low_stock'      => $lowStock,
                    'in_stock'       => $inStock,
                    'cost_value'     => $costValue,
                    'retail_value'   => $retailValue,
                    'total_units'    => $totalUnits,
                ],
                'stock_status' => [
                    'in_stock'    => $inStock,
                    'low_stock'   => $lowStock,
                    'out_of_stock' => $outOfStock,
                ],
                'category_breakdown' => $categoryBreakdown,
                'low_stock_products' => $lowStockProducts,
                'top_moving'         => $topMoving,
                'ledger_summary'     => $ledgerSummary,
            ],
        ]);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function periodBounds(string $period): array
    {
        if ($period === 'all') {
            return ['1970-01-01 00:00:00', date('Y-m-d 23:59:59'), 'All time'];
        }

        $to = date('Y-m-d 23:59:59');
        switch ($period) {
            case 'today':
                $from = date('Y-m-d 00:00:00');
                $label = 'Today';
                break;
            case 'week':
                $from = date('Y-m-d 00:00:00', strtotime('-6 days'));
                $label = '7 days';
                break;
            case '90d':
                $from = date('Y-m-d 00:00:00', strtotime('-89 days'));
                $label = '90 days';
                break;
            case 'month':
            default:
                $from = date('Y-m-d 00:00:00', strtotime('-29 days'));
                $label = '30 days';
                break;
        }

        return [$from, $to, $label];
    }

    private function hasColumn(string $table, string $column): bool
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            return (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            return false;
        }
    }

    private function getAuditTrail()
    {
        try {
            $this->db->query('SELECT 1 FROM inventory_audit_trail LIMIT 1');
        } catch (PDOException $e) {
            $this->response(['status' => 'success', 'data' => []]);
            return;
        }

        try {
            $stmt = $this->db->prepare('SELECT a.*, u.name AS user_name FROM inventory_audit_trail a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 200');
            $stmt->execute();
            $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getAuditTrail — ' . $e->getMessage());
            $this->error('Unable to load audit trail');
        }
    }

    private function createLedgerEntry()
    {
        if (!$this->ensureLedgerTable()) {
            $this->error('Ledger table is not available', 503);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true);
        if (empty($data['product_id']) || empty($data['movement_type'])) {
            $this->error('Product and movement type are required', 400);
            return;
        }

        $openingStock = (int) ($data['opening_stock'] ?? 0);
        $stockIn = (int) ($data['stock_in'] ?? 0);
        $stockOut = (int) ($data['stock_out'] ?? 0);
        $currentStock = (int) ($data['current_stock'] ?? ($openingStock + $stockIn - $stockOut));
        $purchasePrice = (float) ($data['purchase_price'] ?? 0);
        $sellingPrice = (float) ($data['selling_price'] ?? 0);
        $openingValue = $openingStock * $purchasePrice;
        $stockOutValue = $stockOut * $sellingPrice;
        $currentValue = $currentStock * $purchasePrice;
        $estimatedProfit = ($sellingPrice - $purchasePrice) * $stockOut;

        try {
            $stmt = $this->db->prepare('INSERT INTO inventory_ledger (product_id, store_id, user_id, movement_type, reference_id, reference_type, opening_stock, stock_in, stock_out, current_stock, purchase_price, selling_price, opening_stock_value, stock_out_value, current_stock_value, estimated_profit, notes, movement_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $data['product_id'],
                $data['store_id'] ?? ($_SESSION['store_id'] ?? 1),
                $data['user_id'] ?? ($_SESSION['user_id'] ?? 1),
                $data['movement_type'],
                $data['reference_id'] ?? null,
                $data['reference_type'] ?? null,
                $openingStock,
                $stockIn,
                $stockOut,
                $currentStock,
                $purchasePrice,
                $sellingPrice,
                $openingValue,
                $stockOutValue,
                $currentValue,
                $estimatedProfit,
                $data['notes'] ?? null,
                $data['movement_date'] ?? date('Y-m-d H:i:s'),
            ]);

            $this->response(['status' => 'success', 'message' => 'Ledger entry created', 'id' => $this->db->lastInsertId()]);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::createLedgerEntry — ' . $e->getMessage());
            $this->error('Unable to create ledger entry');
        }
    }

    private function createMovement()
    {
        $this->response(['status' => 'success', 'message' => 'Stock movement endpoint ready']);
    }

    /**
     * Products that have an expiry date set (expiry_date not empty).
     */
    private function getExpiredProducts()
    {
        $params = $this->requestParams();

        try {
            $rows = $this->fetchExpiredProductRows($params);

            usort($rows, static function ($a, $b) {
                $dateA = strtotime($a['expiry_date'] ?? '0');
                $dateB = strtotime($b['expiry_date'] ?? '0');
                if ($dateA === $dateB) {
                    return strcmp($a['product_name'] ?? '', $b['product_name'] ?? '');
                }

                return $dateA <=> $dateB;
            });

            $this->response(['status' => 'success', 'data' => array_slice($rows, 0, 500)]);
        } catch (PDOException $e) {
            error_log('InventoryLedgerController::getExpiredProducts — ' . $e->getMessage());
            $this->error('Unable to load expired products');
        }
    }

    private function fetchExpiredProductRows(array $params): array
    {
        $sql = 'SELECT p.id AS product_id, p.name AS product_name, p.sku, p.barcode,
                       p.stock_quantity, p.cost, p.price, p.expiry_date, p.store_id,
                       s.name AS store_name, c.name AS category_name,
                       DATEDIFF(p.expiry_date, CURDATE()) AS days_until_expiry
                FROM products p
                LEFT JOIN stores s ON s.id = p.store_id
                LEFT JOIN categories c ON c.id = p.category_id
                WHERE p.deleted_at IS NULL
                  AND p.expiry_date IS NOT NULL
                  AND p.expiry_date <> \'\'
                  AND p.expiry_date > \'1000-01-01\'
                  AND p.stock_quantity > 0';
        $values = [];

        [$storeSql, $storeParams] = StoreScope::sqlFilter($this->db, 'store_id', 'p');
        $sql .= $storeSql;
        $values = array_merge($values, $storeParams);

        if (!empty($params['store_id'])) {
            $sql .= ' AND p.store_id = ?';
            $values[] = (int) $params['store_id'];
        }

        if (!empty($params['date_from'])) {
            $sql .= ' AND p.expiry_date >= ?';
            $values[] = $params['date_from'];
        }
        if (!empty($params['date_to'])) {
            $sql .= ' AND p.expiry_date <= ?';
            $values[] = $params['date_to'];
        }

        if (!empty($params['q'])) {
            $query = '%' . $params['q'] . '%';
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR s.name LIKE ? OR c.name LIKE ?)';
            array_push($values, $query, $query, $query, $query, $query);
        }

        $sql .= ' ORDER BY p.expiry_date ASC, p.name ASC LIMIT 400';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(static function ($row) {
            $qty = (int) ($row['stock_quantity'] ?? 0);
            $cost = (float) ($row['cost'] ?? 0);
            $price = (float) ($row['price'] ?? 0);
            $days = (int) ($row['days_until_expiry'] ?? 0);

            return [
                'id'               => 'p:' . $row['product_id'],
                'row_kind'         => 'product',
                'product_id'       => (int) $row['product_id'],
                'product_name'     => $row['product_name'],
                'sku'              => $row['sku'] ?? '',
                'barcode'          => $row['barcode'] ?? '',
                'category_name'    => $row['category_name'] ?? '',
                'store_id'         => (int) ($row['store_id'] ?? 0),
                'store_name'       => $row['store_name'] ?? '—',
                'expiry_date'      => $row['expiry_date'],
                'movement_date'    => $row['expiry_date'],
                'days_until_expiry'=> $days,
                'stock_quantity'   => $qty,
                'stock_out'        => $qty,
                'stock_out_value'  => round($qty * $cost, 4),
                'selling_value'    => round($qty * $price, 4),
                'purchase_price'   => $cost,
                'selling_price'    => $price,
                'status'           => $days < 0 ? 'expired' : ($days === 0 ? 'expires_today' : 'expiring'),
                'notes'            => $row['category_name'] ?? '—',
            ];
        }, $products);
    }

    private function requestParams()
    {
        $params = array_merge($_GET, $_POST);
        return array_map('trim', $params);
    }

    private function response($payload, $statusCode = 200)
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($payload);
        exit;
    }

    private function error($message, $statusCode = 500)
    {
        $this->response(['status' => 'error', 'message' => $message], $statusCode);
    }

    private function notFound()
    {
        $this->error('Resource not found', 404);
    }

    private function methodNotAllowed()
    {
        $this->error('Method not allowed', 405);
    }
}

<?php
/**
 * API rapports & analyses — ventes, succursales, caissiers, inventaire, clients.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class ReportsController
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest(string $method, array $path): void
    {
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin']);

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        $period = $_GET['period'] ?? 'month';
        if (!in_array($period, ['today', 'week', 'month', '90d'], true)) {
            $period = 'month';
        }

        try {
            echo json_encode([
                'status' => 'success',
                'data'   => $this->buildReport($period),
            ]);
        } catch (PDOException $e) {
            error_log('ReportsController: ' . $e->getMessage());
            http_response_code(500);
            $msg = 'Erreur base de données';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $msg .= ': ' . $e->getMessage();
            }
            echo json_encode(['status' => 'error', 'message' => $msg]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (isset($this->columnCache[$key])) {
            return $this->columnCache[$key];
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?
                 LIMIT 1'
            );
            $stmt->execute([$table, $column]);
            $this->columnCache[$key] = (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->columnCache[$key] = false;
        }
        return $this->columnCache[$key];
    }

    /** @return array{0: string, 1: string, 2: string} */
    private function periodBounds(string $period): array
    {
        $to = date('Y-m-d 23:59:59');
        switch ($period) {
            case 'today':
                $from = date('Y-m-d 00:00:00');
                $label = "Aujourd'hui";
                break;
            case 'week':
                $from = date('Y-m-d 00:00:00', strtotime('-6 days'));
                $label = '7 derniers jours';
                break;
            case '90d':
                $from = date('Y-m-d 00:00:00', strtotime('-89 days'));
                $label = '90 derniers jours';
                break;
            case 'month':
            default:
                $from = date('Y-m-d 00:00:00', strtotime('-29 days'));
                $label = '30 derniers jours';
                break;
        }
        return [$from, $to, $label];
    }

    private function salesBaseWhere(): string
    {
        $parts = ["s.status = 'completed'"];
        if ($this->hasColumn('sales', 'deleted_at')) {
            $parts[] = 's.deleted_at IS NULL';
        }
        return implode(' AND ', $parts);
    }

    /** @return array<string, mixed> */
    private function buildReport(string $period): array
    {
        [$from, $to, $periodLabel] = $this->periodBounds($period);
        [$saleScope, $saleParams] = StoreScope::sqlFilter($this->db, 'store_id', 's');
        $salesWhere = $this->salesBaseWhere();
        $dateFilter = ' AND s.created_at >= ? AND s.created_at <= ?';
        $baseParams = array_merge($saleParams, [$from, $to]);

        $baseSales = "FROM sales s WHERE {$salesWhere}{$saleScope}{$dateFilter}";

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(s.total), 0) AS revenue, COUNT(s.id) AS tx_count {$baseSales}");
        $stmt->execute($baseParams);
        $summaryRow = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0, 'tx_count' => 0];
        $totalRevenue = (float) $summaryRow['revenue'];
        $totalTx = (int) $summaryRow['tx_count'];
        $avgTicket = $totalTx > 0 ? round($totalRevenue / $totalTx, 2) : 0;

        $storeName = null;
        $storeId = StoreScope::activeStoreId();
        if ($storeId) {
            $st = $this->db->prepare('SELECT name FROM stores WHERE id = ? LIMIT 1');
            $st->execute([$storeId]);
            $storeName = $st->fetchColumn() ?: null;
        }

        return [
            'period'       => $period,
            'period_label' => $periodLabel,
            'from'         => $from,
            'to'           => $to,
            'store_name'   => $storeName,
            'is_global'    => StoreScope::isGlobalView(),
            'summary'      => [
                'revenue'    => $totalRevenue,
                'transactions' => $totalTx,
                'avg_ticket' => $avgTicket,
            ],
            'daily_sales'      => $this->dailySales($salesWhere, $saleScope, $from, $to, $saleParams),
            'branch_analytics' => $this->branchAnalytics($salesWhere, $from, $to),
            'cashier_performance' => $this->cashierPerformance($salesWhere, $saleScope, $from, $to, $saleParams),
            'inventory_analytics' => $this->inventoryAnalytics($from, $to, $salesWhere, $saleScope, $saleParams),
            'customer_analytics'  => $this->customerAnalytics($salesWhere, $saleScope, $from, $to, $saleParams),
        ];
    }

    /** @return array<string, mixed> */
    private function dailySales(string $salesWhere, string $saleScope, string $from, string $to, array $saleParams): array
    {
        $labels = [];
        $revenues = [];
        $counts = [];

        $start = new DateTime(substr($from, 0, 10));
        $end = new DateTime(substr($to, 0, 10));
        $interval = new DateInterval('P1D');
        $period = new DatePeriod($start, $interval, $end->modify('+1 day'));

        $sql = "SELECT COALESCE(SUM(s.total), 0) AS revenue, COUNT(s.id) AS cnt
                FROM sales s
                WHERE {$salesWhere}{$saleScope}
                AND DATE(s.created_at) = ?";
        $stmt = $this->db->prepare($sql);

        foreach ($period as $day) {
            $d = $day->format('Y-m-d');
            $labels[] = $day->format('d/m');
            $stmt->execute(array_merge($saleParams, [$d]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['revenue' => 0, 'cnt' => 0];
            $revenues[] = (float) $row['revenue'];
            $counts[] = (int) $row['cnt'];
        }

        $paymentLabels = [];
        $paymentAmounts = [];
        try {
            $paySql = "SELECT p.method, COALESCE(SUM(p.amount), 0) AS total
                       FROM payments p
                       INNER JOIN sales s ON p.sale_id = s.id
                       WHERE {$salesWhere}{$saleScope}
                       AND s.created_at >= ? AND s.created_at <= ?
                       GROUP BY p.method
                       ORDER BY total DESC";
            $payStmt = $this->db->prepare($paySql);
            $payStmt->execute(array_merge($saleParams, [$from, $to]));
            foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $p) {
                $paymentLabels[] = $p['method'];
                $paymentAmounts[] = (float) $p['total'];
            }
        } catch (PDOException $e) {
            error_log('Reports payment mix: ' . $e->getMessage());
        }

        return [
            'labels'   => $labels,
            'revenues' => $revenues,
            'counts'   => $counts,
            'payment_mix' => [
                'labels'  => $paymentLabels,
                'amounts' => $paymentAmounts,
            ],
        ];
    }

    /** @return array<string, mixed> */
    private function branchAnalytics(string $salesWhere, string $from, string $to): array
    {
        $accessible = StoreScope::accessibleStoreIds($this->db);
        if ($accessible === []) {
            return ['labels' => [], 'revenues' => [], 'transactions' => [], 'stores' => []];
        }

        $params = [$from, $to];
        $storeFilter = '';
        if ($accessible !== null) {
            $placeholders = implode(',', array_fill(0, count($accessible), '?'));
            $storeFilter = " AND st.id IN ({$placeholders})";
            $params = array_merge($params, $accessible);
        }

        $codeCol = $this->hasColumn('stores', 'code') ? 'st.code' : "'' AS code";
        $sql = "SELECT st.id, st.name, {$codeCol},
                       COALESCE(SUM(s.total), 0) AS revenue,
                       COUNT(s.id) AS tx_count
                FROM stores st
                LEFT JOIN sales s ON s.store_id = st.id
                    AND {$salesWhere}
                    AND s.created_at >= ? AND s.created_at <= ?
                WHERE 1=1{$storeFilter}";
        if ($this->hasColumn('stores', 'deleted_at')) {
            $sql .= ' AND st.deleted_at IS NULL';
        }
        if ($this->hasColumn('stores', 'is_active')) {
            $sql .= ' AND st.is_active = 1';
        }
        $sql .= $this->hasColumn('stores', 'code')
            ? ' GROUP BY st.id, st.name, st.code ORDER BY revenue DESC'
            : ' GROUP BY st.id, st.name ORDER BY revenue DESC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labels = [];
        $revenues = [];
        $transactions = [];
        $stores = [];

        foreach ($rows as $r) {
            $rev = (float) $r['revenue'];
            $tx = (int) $r['tx_count'];
            $labels[] = $r['name'];
            $revenues[] = $rev;
            $transactions[] = $tx;
            $stores[] = [
                'id'           => (int) $r['id'],
                'name'         => $r['name'],
                'code'         => $r['code'] ?? '',
                'revenue'      => $rev,
                'transactions' => $tx,
                'avg_ticket'   => $tx > 0 ? round($rev / $tx, 2) : 0,
            ];
        }

        return compact('labels', 'revenues', 'transactions', 'stores');
    }

    /** @return array<string, mixed> */
    private function cashierPerformance(string $salesWhere, string $saleScope, string $from, string $to, array $saleParams): array
    {
        $sql = "SELECT u.id, u.name,
                       COUNT(s.id) AS tx_count,
                       COALESCE(SUM(s.total), 0) AS revenue
                FROM sales s
                INNER JOIN users u ON s.user_id = u.id
                WHERE {$salesWhere}{$saleScope}
                AND s.created_at >= ? AND s.created_at <= ?";
        if ($this->hasColumn('users', 'deleted_at')) {
            $sql .= ' AND u.deleted_at IS NULL';
        }
        $sql .= ' GROUP BY u.id, u.name ORDER BY revenue DESC LIMIT 15';

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($saleParams, [$from, $to]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $labels = [];
        $revenues = [];
        $counts = [];
        $cashiers = [];

        foreach ($rows as $r) {
            $rev = (float) $r['revenue'];
            $tx = (int) $r['tx_count'];
            $labels[] = $r['name'];
            $revenues[] = $rev;
            $counts[] = $tx;
            $cashiers[] = [
                'id'           => (int) $r['id'],
                'name'         => $r['name'],
                'revenue'      => $rev,
                'transactions' => $tx,
                'avg_ticket'   => $tx > 0 ? round($rev / $tx, 2) : 0,
            ];
        }

        return compact('labels', 'revenues', 'counts', 'cashiers');
    }

    /** @return array<string, mixed> */
    private function inventoryAnalytics(string $from, string $to, string $salesWhere, string $saleScope, array $saleParams): array
    {
        [$pScope, $pParams] = StoreScope::sqlFilter($this->db, 'store_id', 'p');
        $productWhere = '1=1';
        if ($this->hasColumn('products', 'deleted_at')) {
            $productWhere = 'p.deleted_at IS NULL';
        }

        $totalProducts = 0;
        $outOfStock = 0;
        $lowStock = 0;
        $inventoryValue = 0.0;
        $totalUnits = 0;

        if ($this->hasColumn('products', 'stock_quantity')) {
            $stmt = $this->db->prepare("SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope}");
            $stmt->execute($pParams);
            $totalProducts = (int) $stmt->fetchColumn();

            if ($this->hasColumn('products', 'min_stock_level')) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(p.id) FROM products p WHERE {$productWhere}{$pScope} AND p.stock_quantity <= 0"
                );
                $stmt->execute($pParams);
                $outOfStock = (int) $stmt->fetchColumn();

                $stmt = $this->db->prepare(
                    "SELECT COUNT(p.id) FROM products p
                     WHERE {$productWhere}{$pScope}
                     AND p.stock_quantity > 0 AND p.stock_quantity <= p.min_stock_level"
                );
                $stmt->execute($pParams);
                $lowStock = (int) $stmt->fetchColumn();
            }

            $costCol = $this->hasColumn('products', 'cost') ? 'COALESCE(p.cost, 0)' : '0';
            $stmt = $this->db->prepare(
                "SELECT COALESCE(SUM(p.stock_quantity * {$costCol}), 0), COALESCE(SUM(p.stock_quantity), 0)
                 FROM products p WHERE {$productWhere}{$pScope}"
            );
            $stmt->execute($pParams);
            $invRow = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];
            $inventoryValue = (float) $invRow[0];
            $totalUnits = (int) $invRow[1];
        }

        $categoryLabels = [];
        $categoryValues = [];
        try {
            $catSql = "SELECT COALESCE(c.name, 'Sans catégorie') AS name,
                              COALESCE(SUM(p.stock_quantity * COALESCE(p.cost, p.price, 0)), 0) AS val
                       FROM products p
                       LEFT JOIN categories c ON p.category_id = c.id
                       WHERE {$productWhere}{$pScope}
                       GROUP BY COALESCE(c.name, 'Sans catégorie')
                       ORDER BY val DESC
                       LIMIT 8";
            $catStmt = $this->db->prepare($catSql);
            $catStmt->execute($pParams);
            foreach ($catStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $c) {
                $categoryLabels[] = $c['name'];
                $categoryValues[] = (float) $c['val'];
            }
        } catch (PDOException $e) {
            error_log('Reports inventory categories: ' . $e->getMessage());
        }

        $topMoving = [];
        try {
            $moveSql = "SELECT p.name, SUM(si.quantity) AS qty_sold, SUM(si.subtotal) AS revenue
                        FROM sale_items si
                        INNER JOIN sales s ON si.sale_id = s.id
                        INNER JOIN products p ON si.product_id = p.id
                        WHERE {$salesWhere}{$saleScope}
                        AND s.created_at >= ? AND s.created_at <= ?
                        GROUP BY p.id, p.name
                        ORDER BY qty_sold DESC
                        LIMIT 10";
            $moveStmt = $this->db->prepare($moveSql);
            $moveStmt->execute(array_merge($saleParams, [$from, $to]));
            $topMoving = $moveStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Reports top moving: ' . $e->getMessage());
        }

        $stockStatus = [
            'labels' => ['En stock', 'Stock bas', 'Rupture'],
            'counts' => [
                max(0, $totalProducts - $lowStock - $outOfStock),
                $lowStock,
                $outOfStock,
            ],
        ];

        return [
            'total_products'  => $totalProducts,
            'out_of_stock'    => $outOfStock,
            'low_stock'       => $lowStock,
            'inventory_value' => $inventoryValue,
            'total_units'     => $totalUnits,
            'category_chart'  => [
                'labels' => $categoryLabels ?: ['Aucune donnée'],
                'values' => $categoryValues ?: [0],
            ],
            'stock_status'    => $stockStatus,
            'top_moving'      => $topMoving,
        ];
    }

    /** @return array<string, mixed> */
    private function customerAnalytics(string $salesWhere, string $saleScope, string $from, string $to, array $saleParams): array
    {
        $custWhere = $this->hasColumn('customers', 'deleted_at')
            ? 'c.deleted_at IS NULL'
            : '1=1';

        $stmt = $this->db->prepare("SELECT COUNT(c.id) FROM customers c WHERE {$custWhere}");
        $stmt->execute();
        $totalCustomers = (int) $stmt->fetchColumn();

        $newCustomers = 0;
        if ($this->hasColumn('customers', 'created_at')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(c.id) FROM customers c
                 WHERE {$custWhere} AND c.created_at >= ? AND c.created_at <= ?"
            );
            $stmt->execute([$from, $to]);
            $newCustomers = (int) $stmt->fetchColumn();
        }

        $withPurchase = 0;
        $guestSales = 0;
        try {
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT s.customer_id) AS with_cust,
                        SUM(CASE WHEN s.customer_id IS NULL THEN 1 ELSE 0 END) AS guest_tx
                 FROM sales s
                 WHERE {$salesWhere}{$saleScope}
                 AND s.created_at >= ? AND s.created_at <= ?"
            );
            $stmt->execute(array_merge($saleParams, [$from, $to]));
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $withPurchase = (int) ($row['with_cust'] ?? 0);
            $guestSales = (int) ($row['guest_tx'] ?? 0);
        } catch (PDOException $e) {
            error_log('Reports customer counts: ' . $e->getMessage());
        }

        $topCustomers = [];
        try {
            $topSql = "SELECT c.id, c.name, c.phone,
                              COUNT(s.id) AS visits,
                              COALESCE(SUM(s.total), 0) AS spent
                       FROM sales s
                       INNER JOIN customers c ON s.customer_id = c.id
                       WHERE {$salesWhere}{$saleScope}
                       AND s.created_at >= ? AND s.created_at <= ?
                       GROUP BY c.id, c.name, c.phone
                       ORDER BY spent DESC
                       LIMIT 10";
            $topStmt = $this->db->prepare($topSql);
            $topStmt->execute(array_merge($saleParams, [$from, $to]));
            $topCustomers = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (PDOException $e) {
            error_log('Reports top customers: ' . $e->getMessage());
        }

        $identified = max(0, (int) $this->countIdentifiedSales($salesWhere, $saleScope, $from, $to, $saleParams));
        $guest = $guestSales;
        $loyaltyLabels = ['Clients identifiés', 'Ventes anonymes'];
        $loyaltyCounts = [$identified, $guest];

        $growthLabels = [];
        $growthCounts = [];
        if ($this->hasColumn('customers', 'created_at')) {
            $start = new DateTime(substr($from, 0, 10));
            $end = new DateTime(substr($to, 0, 10));
            $interval = new DateInterval('P1D');
            $period = new DatePeriod($start, $interval, $end->modify('+1 day'));
            $gStmt = $this->db->prepare(
                "SELECT COUNT(c.id) FROM customers c
                 WHERE {$custWhere} AND DATE(c.created_at) = ?"
            );
            foreach ($period as $day) {
                $growthLabels[] = $day->format('d/m');
                $gStmt->execute([$day->format('Y-m-d')]);
                $growthCounts[] = (int) $gStmt->fetchColumn();
            }
        }

        return [
            'total_customers'  => $totalCustomers,
            'new_customers'    => $newCustomers,
            'active_customers' => $withPurchase,
            'guest_transactions' => $guestSales,
            'loyalty_split'    => [
                'labels' => $loyaltyLabels,
                'counts' => $loyaltyCounts,
            ],
            'growth_chart'     => [
                'labels' => $growthLabels,
                'counts' => $growthCounts,
            ],
            'top_customers'    => $topCustomers,
        ];
    }

    private function countIdentifiedSales(string $salesWhere, string $saleScope, string $from, string $to, array $saleParams): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(s.id) FROM sales s
             WHERE {$salesWhere}{$saleScope}
             AND s.customer_id IS NOT NULL
             AND s.created_at >= ? AND s.created_at <= ?"
        );
        $stmt->execute(array_merge($saleParams, [$from, $to]));
        return (int) $stmt->fetchColumn();
    }
}

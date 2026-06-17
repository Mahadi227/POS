<?php
/**
 * API tableau de bord administrateur / manager.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/CustomerSchemaMigrator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/SaleFormatter.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class DashboardController
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        CustomerSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        if ($method === 'GET') {
            $this->getDashboardData();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
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

    /** @return array{0: string, 1: array<int, mixed>} */
    private function salesStoreScope(): array
    {
        return StoreScope::sqlFilter($this->db, 'store_id', 's');
    }

    private function salesBaseWhere(): string
    {
        $parts = ["s.status = 'completed'"];
        if ($this->hasColumn('sales', 'deleted_at')) {
            $parts[] = 's.deleted_at IS NULL';
        }
        return implode(' AND ', $parts);
    }

    private function trendPercent(float $today, float $yesterday): ?float
    {
        if ($yesterday <= 0) {
            return $today > 0 ? 100.0 : null;
        }
        return round((($today - $yesterday) / $yesterday) * 100, 1);
    }

    /** @return array{0: int, 1: int} */
    private function customerStats(string $today): array
    {
        $customerWhere = $this->hasColumn('customers', 'deleted_at')
            ? 'c.deleted_at IS NULL'
            : '1=1';

        // Preferred path: explicit customer store scope.
        if ($this->hasColumn('customers', 'store_id')) {
            [$cScope, $cParams] = StoreScope::sqlFilter($this->db, 'store_id', 'c');

            $stmt = $this->db->prepare("SELECT COUNT(c.id) FROM customers c WHERE {$customerWhere}{$cScope}");
            $stmt->execute($cParams);
            $activeCustomers = (int) $stmt->fetchColumn();

            $newCustomersToday = 0;
            if ($this->hasColumn('customers', 'created_at')) {
                $stmt = $this->db->prepare(
                    "SELECT COUNT(c.id) FROM customers c WHERE {$customerWhere}{$cScope} AND DATE(c.created_at) = ?"
                );
                $stmt->execute(array_merge($cParams, [$today]));
                $newCustomersToday = (int) $stmt->fetchColumn();
            }

            return [$activeCustomers, $newCustomersToday];
        }

        // Fallback path for legacy schema: derive customers from scoped sales.
        [$saleScope, $saleParams] = $this->salesStoreScope();
        $salesWhere = $this->salesBaseWhere();

        $stmt = $this->db->prepare(
            "SELECT COUNT(DISTINCT s.customer_id)
             FROM sales s
             WHERE {$salesWhere}{$saleScope} AND s.customer_id IS NOT NULL"
        );
        $stmt->execute($saleParams);
        $activeCustomers = (int) $stmt->fetchColumn();

        $newCustomersToday = 0;
        if ($this->hasColumn('customers', 'created_at')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(DISTINCT c.id)
                 FROM customers c
                 INNER JOIN sales s ON s.customer_id = c.id
                 WHERE {$customerWhere}
                   AND DATE(c.created_at) = ?
                   AND {$salesWhere}{$saleScope}"
            );
            $stmt->execute(array_merge([$today], $saleParams));
            $newCustomersToday = (int) $stmt->fetchColumn();
        }

        return [$activeCustomers, $newCustomersToday];
    }

    private function getDashboardData(): void
    {
        try {
            [$saleScope, $saleParams] = $this->salesStoreScope();
            $storeId = StoreScope::activeStoreId();
            $salesWhere = $this->salesBaseWhere();

            $today = date('Y-m-d');
            $yesterday = date('Y-m-d', strtotime('-1 day'));
            $startOfMonth = date('Y-m-01');

            $baseSales = "FROM sales s WHERE {$salesWhere}{$saleScope}";

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(s.total), 0) AS v {$baseSales} AND DATE(s.created_at) = ?");
            $stmt->execute(array_merge($saleParams, [$today]));
            $revenueToday = (float) $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(s.total), 0) AS v {$baseSales} AND DATE(s.created_at) = ?");
            $stmt->execute(array_merge($saleParams, [$yesterday]));
            $revenueYesterday = (float) $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COALESCE(SUM(s.total), 0) AS v {$baseSales} AND s.created_at >= ?");
            $stmt->execute(array_merge($saleParams, [$startOfMonth]));
            $revenueMonth = (float) $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COUNT(s.id) AS v {$baseSales} AND DATE(s.created_at) = ?");
            $stmt->execute(array_merge($saleParams, [$today]));
            $salesToday = (int) $stmt->fetchColumn();

            $stmt = $this->db->prepare("SELECT COUNT(s.id) AS v {$baseSales} AND DATE(s.created_at) = ?");
            $stmt->execute(array_merge($saleParams, [$yesterday]));
            $salesYesterday = (int) $stmt->fetchColumn();

            $lowStock = 0;
            if ($this->hasColumn('products', 'min_stock_level')) {
                $productWhere = 'stock_quantity <= min_stock_level';
                if ($this->hasColumn('products', 'deleted_at')) {
                    $productWhere .= ' AND deleted_at IS NULL';
                }
                [$pScope, $pParams] = StoreScope::sqlFilter($this->db, 'store_id');
                $stmt = $this->db->prepare("SELECT COUNT(id) FROM products WHERE {$productWhere}{$pScope}");
                $stmt->execute($pParams);
                $lowStock = (int) $stmt->fetchColumn();
            }

            [$activeCustomers, $newCustomersToday] = $this->customerStats($today);

            $txDeleted = $this->hasColumn('sales', 'deleted_at') ? ' AND s.deleted_at IS NULL' : '';
            $txSql = "SELECT s.id, s.receipt_no, s.total, s.created_at, s.status,
                             p.method AS payment_method, c.name AS customer_name
                      FROM sales s
                      LEFT JOIN customers c ON s.customer_id = c.id
                      LEFT JOIN payments p ON p.sale_id = s.id
                      WHERE 1=1{$txDeleted}{$saleScope}
                      ORDER BY s.created_at DESC
                      LIMIT 8";
            $txStmt = $this->db->prepare($txSql);
            $txStmt->execute($saleParams);
            $transactions = array_map(
                [SaleFormatter::class, 'formatListRow'],
                $txStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
            );

            $chartLabels = [];
            $chartRevenues = [];
            for ($i = 6; $i >= 0; $i--) {
                $monthStart = date('Y-m-01', strtotime("-$i months"));
                $monthEnd = date('Y-m-t 23:59:59', strtotime("-$i months"));
                $chartLabels[] = date('M Y', strtotime($monthStart));

                $stmt = $this->db->prepare(
                    "SELECT COALESCE(SUM(s.total), 0) {$baseSales} AND s.created_at >= ? AND s.created_at <= ?"
                );
                $stmt->execute(array_merge($saleParams, [$monthStart, $monthEnd]));
                $chartRevenues[] = (float) $stmt->fetchColumn();
            }

            $topProducts = [];
            try {
                $topSql = "SELECT p.name, SUM(si.quantity) AS total_sold, SUM(si.subtotal) AS total_revenue
                           FROM sale_items si
                           INNER JOIN sales s ON si.sale_id = s.id
                           INNER JOIN products p ON si.product_id = p.id
                           WHERE {$salesWhere} AND s.created_at >= ? {$saleScope}
                           GROUP BY p.id, p.name
                           ORDER BY total_sold DESC
                           LIMIT 5";
                $topStmt = $this->db->prepare($topSql);
                $topStmt->execute(array_merge([date('Y-m-d', strtotime('-30 days'))], $saleParams));
                $topProducts = $topStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            } catch (PDOException $e) {
                error_log('Dashboard top_products: ' . $e->getMessage());
            }

            $catLabels = ['Aucune donnée'];
            $catRevenues = [0];
            try {
                $catSql = "SELECT COALESCE(c.name, 'Sans catégorie') AS name, SUM(si.subtotal) AS category_revenue
                           FROM sale_items si
                           INNER JOIN sales s ON si.sale_id = s.id
                           INNER JOIN products p ON si.product_id = p.id
                           LEFT JOIN categories c ON p.category_id = c.id
                           WHERE {$salesWhere} AND s.created_at >= ? {$saleScope}
                           GROUP BY COALESCE(c.name, 'Sans catégorie')
                           ORDER BY category_revenue DESC
                           LIMIT 8";
                $catStmt = $this->db->prepare($catSql);
                $catStmt->execute(array_merge([$startOfMonth], $saleParams));
                $categorySales = $catStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

                if (!empty($categorySales)) {
                    $catLabels = [];
                    $catRevenues = [];
                    foreach ($categorySales as $cat) {
                        $catLabels[] = $cat['name'];
                        $catRevenues[] = (float) $cat['category_revenue'];
                    }
                }
            } catch (PDOException $e) {
                error_log('Dashboard category_chart: ' . $e->getMessage());
            }

            $storeName = null;
            if ($storeId) {
                $st = $this->db->prepare('SELECT name FROM stores WHERE id = ? LIMIT 1');
                $st->execute([$storeId]);
                $storeName = $st->fetchColumn() ?: null;
            }

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'user' => [
                        'name'  => $_SESSION['name'] ?? 'Admin',
                        'email' => $_SESSION['email'] ?? '',
                        'role'  => $_SESSION['role'] ?? '',
                    ],
                    'store_name'          => $storeName,
                    'revenue_today'       => $revenueToday,
                    'revenue_yesterday'   => $revenueYesterday,
                    'revenue_month'       => $revenueMonth,
                    'sales_today'         => $salesToday,
                    'sales_yesterday'     => $salesYesterday,
                    'low_stock_count'     => $lowStock,
                    'active_customers'    => $activeCustomers,
                    'new_customers_today' => $newCustomersToday,
                    'trends' => [
                        'revenue_pct' => $this->trendPercent($revenueToday, $revenueYesterday),
                        'sales_pct'   => $this->trendPercent((float) $salesToday, (float) $salesYesterday),
                    ],
                    'recent_transactions' => $transactions,
                    'chart' => [
                        'labels'   => $chartLabels,
                        'revenues' => $chartRevenues,
                    ],
                    'top_products' => $topProducts,
                    'category_chart' => [
                        'labels'   => $catLabels,
                        'revenues' => $catRevenues,
                    ],
                ],
            ]);
        } catch (PDOException $e) {
            error_log('DashboardController: ' . $e->getMessage());
            http_response_code(500);
            $message = 'Erreur base de données';
            if (defined('APP_DEBUG') && APP_DEBUG) {
                $message .= ': ' . $e->getMessage();
            }
            echo json_encode(['status' => 'error', 'message' => $message]);
        }
    }
}

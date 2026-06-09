<?php
// includes/Controllers/SalesController.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/SaleFormatter.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class SalesController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest($method, $path)
    {
        AuthMiddleware::apiProtect(['cashier', 'admin', 'manager', 'super_admin', 'staff']);

        $segment1 = $path[1] ?? null;
        $segment2 = $path[2] ?? null;

        if ($method === 'GET' && $segment1 === 'receipt' && $segment2) {
            $this->getSaleByReceipt($segment2);
            return;
        }

        if ($method === 'GET' && $segment1 === 'stats') {
            $this->getStats();
            return;
        }

        $id = isset($segment1) && is_numeric($segment1) ? (int) $segment1 : null;

        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getSaleDetails($id);
                } else {
                    $this->getSales();
                }
                break;
            case 'POST':
                if ($segment1 === null || $segment1 === '') {
                    $this->processCheckout();
                } else {
                    http_response_code(404);
                    echo json_encode(["status" => "error", "message" => "Endpoint not found"]);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["status" => "error", "message" => "Method not allowed"]);
        }
    }

    private function roleSlug(): string
    {
        return strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
    }

    /** Filtre SQL selon le rôle et la succursale active. */
    private function scopeFilter(): array
    {
        $role = $this->roleSlug();
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        if ($role === 'cashier') {
            $parts = ['s.user_id = ?'];
            $params = [$userId];
            [$storeSql, $storeParams] = StoreScope::sqlFilter($this->db, 'store_id', 's');
            return [' AND ' . implode(' AND ', $parts) . $storeSql, array_merge($params, $storeParams)];
        }

        return StoreScope::sqlFilter($this->db, 'store_id', 's');
    }

    private function canAccessSale(array $sale): bool
    {
        $role = $this->roleSlug();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = $_SESSION['store_id'] ?? null;

        if ($role === 'cashier') {
            if ((int) $sale['user_id'] !== $userId) {
                return false;
            }
            if ($storeId && (int) $sale['store_id'] !== (int) $storeId) {
                return false;
            }
        } elseif (in_array($role, ['admin', 'manager', 'staff'], true) && $storeId) {
            if ((int) $sale['store_id'] !== (int) $storeId) {
                return false;
            }
        }

        return true;
    }

    private function periodSql(string $period): string
    {
        switch ($period) {
            case 'today':
                return ' AND DATE(s.created_at) = CURDATE()';
            case 'week':
                return ' AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)';
            case 'month':
                return ' AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)';
            default:
                return '';
        }
    }

    private function getStats(): void
    {
        [$scopeSql, $scopeParams] = $this->scopeFilter();

        try {
            $base = "FROM sales s WHERE s.deleted_at IS NULL {$scopeSql}";

            $todayStmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue {$base} AND DATE(s.created_at) = CURDATE()"
            );
            $todayStmt->execute($scopeParams);
            $today = $todayStmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'revenue' => 0];

            $weekStmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue {$base}
                 AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
            );
            $weekStmt->execute($scopeParams);
            $week = $weekStmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'revenue' => 0];

            $monthStmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue {$base}
                 AND s.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)"
            );
            $monthStmt->execute($scopeParams);
            $month = $monthStmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'revenue' => 0];

            $payStmt = $this->db->prepare(
                "SELECT p.method, COUNT(*) AS cnt, COALESCE(SUM(s.total), 0) AS revenue
                 FROM sales s
                 LEFT JOIN payments p ON p.sale_id = s.id
                 WHERE s.deleted_at IS NULL AND DATE(s.created_at) = CURDATE() {$scopeSql}
                 GROUP BY p.method"
            );
            $payStmt->execute($scopeParams);
            $byPayment = $payStmt->fetchAll(PDO::FETCH_ASSOC);

            $todayCount = (int) $today['cnt'];
            $todayRevenue = (float) $today['revenue'];

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'today_count'    => $todayCount,
                    'today_revenue'  => $todayRevenue,
                    'today_avg'      => $todayCount > 0 ? round($todayRevenue / $todayCount, 2) : 0,
                    'week_count'     => (int) ($week['cnt'] ?? 0),
                    'week_revenue'   => (float) ($week['revenue'] ?? 0),
                    'month_count'    => (int) ($month['cnt'] ?? 0),
                    'month_revenue'  => (float) ($month['revenue'] ?? 0),
                    'payment_today'  => $byPayment,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur base de données']);
        }
    }

    private function getSales()
    {
        [$scopeSql, $scopeParams] = $this->scopeFilter();
        $period = $_GET['period'] ?? ($_GET['today'] === '1' ? 'today' : 'all');
        $payment = trim($_GET['payment'] ?? '');
        $startDate = trim($_GET['start_date'] ?? '');
        $endDate = trim($_GET['end_date'] ?? '');
        $limit = min(500, max(1, (int) ($_GET['limit'] ?? 200)));

        $sql = "SELECT s.*, u.name AS cashier_name, c.name AS customer_name, p.method AS payment_method
                FROM sales s
                LEFT JOIN users u ON s.user_id = u.id
                LEFT JOIN customers c ON s.customer_id = c.id
                LEFT JOIN payments p ON s.id = p.sale_id
                WHERE s.deleted_at IS NULL {$scopeSql}";

        $params = $scopeParams;

        $useCustomDates = false;
        if ($startDate !== '') {
            $startValid =
                DateTime::createFromFormat('Y-m-d', $startDate) &&
                DateTime::createFromFormat('Y-m-d', $startDate)->format('Y-m-d') === $startDate;
            if ($startValid) {
                $sql .= ' AND DATE(s.created_at) >= ?';
                $params[] = $startDate;
                $useCustomDates = true;
            }
        }

        if ($endDate !== '') {
            $endValid =
                DateTime::createFromFormat('Y-m-d', $endDate) &&
                DateTime::createFromFormat('Y-m-d', $endDate)->format('Y-m-d') === $endDate;
            if ($endValid) {
                $sql .= ' AND DATE(s.created_at) <= ?';
                $params[] = $endDate;
                $useCustomDates = true;
            }
        }

        if (! $useCustomDates) {
            $sql .= $this->periodSql($period);
        }

        if ($payment !== '') {
            $sql .= ' AND p.method = ?';
            $params[] = $payment;
        }

        $sql .= ' ORDER BY s.created_at DESC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $sales = array_map([SaleFormatter::class, 'formatListRow'], $stmt->fetchAll(PDO::FETCH_ASSOC));

        echo json_encode(["status" => "success", "data" => $sales]);
    }

    private function getSaleDetails($id)
    {
        $infoStmt = $this->db->prepare(
            "SELECT s.*, u.name AS cashier_name, c.name AS customer_name,
                    p.method AS payment_method, p.provider AS payment_provider,
                    p.transaction_ref AS payment_ref, st.name AS store_name
             FROM sales s
             LEFT JOIN users u ON s.user_id = u.id
             LEFT JOIN customers c ON s.customer_id = c.id
             LEFT JOIN payments p ON p.sale_id = s.id
             LEFT JOIN stores st ON s.store_id = st.id
             WHERE s.id = ? AND s.deleted_at IS NULL"
        );
        $infoStmt->execute([$id]);
        $saleInfo = $infoStmt->fetch(PDO::FETCH_ASSOC);

        if (!$saleInfo || !$this->canAccessSale($saleInfo)) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Sale not found"]);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT si.*, p.name AS product_name, p.sku
             FROM sale_items si
             JOIN products p ON si.product_id = p.id
             WHERE si.sale_id = ?"
        );
        $stmt->execute([$id]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            "status" => "success",
            "data"   => SaleFormatter::formatDetail($saleInfo, $items),
        ]);
    }

    private function getSaleByReceipt(string $receiptNo)
    {
        $receiptNo = trim($receiptNo);
        [$scopeSql, $scopeParams] = $this->scopeFilter();

        $stmt = $this->db->prepare(
            "SELECT s.*, u.name AS cashier_name
             FROM sales s
             LEFT JOIN users u ON s.user_id = u.id
             WHERE s.receipt_no = ? AND s.deleted_at IS NULL {$scopeSql}
             LIMIT 1"
        );
        $stmt->execute(array_merge([$receiptNo], $scopeParams));
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale) {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Ticket introuvable"]);
            return;
        }

        echo json_encode([
            "status" => "success",
            "data"   => SaleFormatter::formatListRow($sale),
        ]);
    }

    private function processCheckout()
    {
        $data = json_decode(file_get_contents("php://input"), true);

        if (empty($data['receipt_no']) || empty($data['items']) || !isset($data['total'])) {
            http_response_code(400);
            echo json_encode(["status" => "error", "message" => "Missing required fields"]);
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? ($data['user_id'] ?? 1));
        $storeId = (int) (StoreScope::activeStoreId() ?? ($data['store_id'] ?? 1));

        try {
            $this->db->beginTransaction();

            $stmt = $this->db->prepare(
                "INSERT INTO sales (receipt_no, store_id, user_id, customer_id, total, tax, discount, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'completed')"
            );
            $stmt->execute([
                $data['receipt_no'],
                $storeId,
                $userId,
                isset($data['customer_id']) && $data['customer_id'] !== '' ? $data['customer_id'] : null,
                $data['total'],
                $data['tax'] ?? 0,
                $data['discount'] ?? 0,
            ]);

            $saleId = $this->db->lastInsertId();

            $paymentStmt = $this->db->prepare(
                "INSERT INTO payments (sale_id, method, provider, transaction_ref, amount, status)
                 VALUES (?, ?, ?, ?, ?, 'success')"
            );
            $paymentStmt->execute([
                $saleId,
                $data['payment_method'] ?? 'cash',
                $data['payment_provider'] ?? null,
                $data['payment_ref'] ?? null,
                $data['total'],
            ]);

            $itemStmt = $this->db->prepare(
                "INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)"
            );
            $stockStmt = $this->db->prepare(
                "UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?"
            );
            $logStmt = $this->db->prepare(
                "INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason) VALUES (?, ?, ?, ?, 'sale')"
            );

            foreach ($data['items'] as $item) {
                $subtotal = $item['quantity'] * $item['unit_price'];

                $itemStmt->execute([
                    $saleId,
                    $item['product_id'],
                    $item['quantity'],
                    $item['unit_price'],
                    $subtotal,
                ]);

                $stockStmt->execute([$item['quantity'], $item['product_id']]);

                $logStmt->execute([
                    $storeId,
                    $item['product_id'],
                    $userId,
                    -1 * abs($item['quantity']),
                ]);
            }

            $this->db->commit();

            echo json_encode([
                "status"  => "success",
                "message" => "Sale successfully processed",
                "sale_id" => $saleId,
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            http_response_code(500);

            if ($e->getCode() == 23000) {
                echo json_encode([
                    "status"  => "error",
                    "message" => "A transaction with this receipt number already exists.",
                ]);
            } else {
                echo json_encode([
                    "status"  => "error",
                    "message" => "Database error: " . $e->getMessage(),
                ]);
            }
        }
    }
}

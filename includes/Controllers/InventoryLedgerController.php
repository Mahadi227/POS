<?php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Config/config.php';

class InventoryLedgerController
{
    private $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest($method, $path)
    {
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin', 'cashier', 'staff']);

        $resource = $path[1] ?? null;
        $id = isset($path[2]) ? (int) $path[2] : null;

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

    private function getLedgerEntries()
    {
        $params = $this->requestParams();
        $filters = [];
        $values = [];

        $sql = 'SELECT l.*, p.name AS product_name, p.sku, p.barcode, u.name AS user_name, s.name AS store_name, b.name AS branch_name, w.name AS warehouse_name
                FROM inventory_ledger l
                LEFT JOIN products p ON l.product_id = p.id
                LEFT JOIN users u ON l.user_id = u.id
                LEFT JOIN stores s ON l.store_id = s.id
                LEFT JOIN branches b ON l.branch_id = b.id
                LEFT JOIN warehouses w ON l.warehouse_id = w.id
                WHERE 1=1';

        if (!empty($params['product_id'])) {
            $sql .= ' AND l.product_id = ?';
            $values[] = (int) $params['product_id'];
        }

        if (!empty($params['store_id'])) {
            $sql .= ' AND l.store_id = ?';
            $values[] = (int) $params['store_id'];
        }

        if (!empty($params['type'])) {
            $params['movement_type'] = $params['type'];
        }
        if (!empty($params['movement_type'])) {
            $sql .= ' AND l.movement_type = ?';
            $values[] = $params['movement_type'];
        }

        if (!empty($params['q'])) {
            $query = '%' . $params['q'] . '%';
            $sql .= ' AND (
                p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ? OR u.name LIKE ? OR s.name LIKE ? OR l.notes LIKE ?
            )';
            array_push($values, $query, $query, $query, $query, $query, $query);
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

        $sql .= ' ORDER BY l.movement_date DESC LIMIT 250';

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $this->response(['status' => 'success', 'data' => $rows]);
        } catch (PDOException $e) {
            $this->error('Unable to load ledger entries');
        }
    }

    private function getMovements()
    {
        try {
            $stmt = $this->db->prepare('SELECT m.*, p.name AS product_name, s.name AS from_store, t.name AS to_store
                FROM stock_movements m
                LEFT JOIN products p ON m.product_id = p.id
                LEFT JOIN stores s ON m.from_store_id = s.id
                LEFT JOIN stores t ON m.to_store_id = t.id
                ORDER BY m.performed_at DESC LIMIT 250');
            $stmt->execute();
            $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            $this->error('Unable to load stock movements');
        }
    }

    private function getAnalytics()
    {
        try {
            $stmt = $this->db->prepare('SELECT movement_type, COUNT(*) AS count, SUM(stock_in) AS total_in, SUM(stock_out) AS total_out FROM inventory_ledger GROUP BY movement_type');
            $stmt->execute();
            $summary = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $this->db->prepare('SELECT p.name, SUM(l.stock_out) AS sold_units, SUM(l.stock_out_value) AS sold_value, SUM(l.estimated_profit) AS profit_value
                FROM inventory_ledger l
                LEFT JOIN products p ON l.product_id = p.id
                WHERE l.movement_type = ?
                GROUP BY p.id
                ORDER BY sold_units DESC
                LIMIT 10');
            $stmt->execute(['sale']);
            $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->response(['status' => 'success', 'data' => ['summary' => $summary, 'top_products' => $topProducts]]);
        } catch (PDOException $e) {
            $this->error('Unable to load analytics');
        }
    }

    private function getReports()
    {
        $this->response(['status' => 'success', 'data' => ['message' => 'Reports endpoint ready']]);
    }

    private function getAuditTrail()
    {
        try {
            $stmt = $this->db->prepare('SELECT a.*, u.name AS user_name FROM inventory_audit_trail a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.created_at DESC LIMIT 200');
            $stmt->execute();
            $this->response(['status' => 'success', 'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
        } catch (PDOException $e) {
            $this->error('Unable to load audit trail');
        }
    }

    private function createLedgerEntry()
    {
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
            $stmt = $this->db->prepare('INSERT INTO inventory_ledger (product_id, store_id, branch_id, warehouse_id, user_id, movement_type, reference_id, reference_type, opening_stock, stock_in, stock_out, current_stock, purchase_price, selling_price, opening_stock_value, stock_out_value, current_stock_value, estimated_profit, notes, movement_date)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');

            $stmt->execute([
                $data['product_id'],
                $data['store_id'] ?? null,
                $data['branch_id'] ?? null,
                $data['warehouse_id'] ?? null,
                $data['user_id'] ?? null,
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
            $this->error('Unable to create ledger entry');
        }
    }

    private function createMovement()
    {
        $this->response(['status' => 'success', 'message' => 'Stock movement endpoint ready']);
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

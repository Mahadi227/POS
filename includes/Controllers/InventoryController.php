<?php
// includes/Controllers/InventoryController.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Config/config.php';

class InventoryController {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
    }

    public function handleRequest($method, $path) {
        AuthMiddleware::apiProtect(['cashier', 'admin', 'manager', 'super_admin', 'staff']);

        $action = isset($path[1]) ? $path[1] : null;
        $id = isset($path[2]) ? (int)$path[2] : null;

        switch ($method) {
            case 'GET':
                if ($action === 'products') {
                    $this->getProducts();
                } else if ($action === 'categories') {
                    $this->getCategories();
                } else if ($action === 'stats') {
                    $this->getStats();
                } else if ($action === 'scan' && isset($path[2])) {
                    $this->scanBarcode($path[2]);
                } else {
                    $this->getProducts();
                }
                break;
            case 'POST':
                if ($action === 'products') {
                    $this->createProduct();
                } else if ($action === 'adjust') {
                    $this->adjustStock();
                } else if ($action === 'categories') {
                    $this->createCategory();
                }
                break;
            case 'PUT':
                if ($action === 'products' && $id) {
                    $this->updateProduct($id);
                }
                break;
            case 'DELETE':
                if ($action === 'products' && $id) {
                    $this->deleteProduct($id);
                }
                break;
            default:
                http_response_code(405);
                echo json_encode(["error" => "Method not allowed"]);
        }
    }

    private function storeFilterSql(): array
    {
        return StoreScope::sqlFilter($this->db, 'store_id', 'p');
    }

    private function scanBarcode($barcode) {
        [$storeSql, $storeParams] = $this->storeFilterSql();
        $stmt = $this->db->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name 
                                    FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id
                                    LEFT JOIN suppliers s ON p.supplier_id = s.id
                                    WHERE (p.barcode = ? OR p.sku = ?) AND p.deleted_at IS NULL {$storeSql}");
        $stmt->execute(array_merge([$barcode, $barcode], $storeParams));
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            echo json_encode(["status" => "success", "data" => $this->normalizeProductRow($product)]);
        } else {
            http_response_code(404);
            echo json_encode(["status" => "error", "message" => "Product not found"]);
        }
    }

    private function getStats() {
        [$storeSql, $storeParams] = $this->storeFilterSql();
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) AS total_products,
                    COALESCE(SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                    COALESCE(SUM(CASE WHEN p.stock_quantity > 0 AND p.stock_quantity <= COALESCE(p.min_stock_level, 5) THEN 1 ELSE 0 END), 0) AS low_stock,
                    COALESCE(SUM(p.price * p.stock_quantity), 0) AS inventory_value,
                    COALESCE(SUM(p.stock_quantity), 0) AS total_units
                FROM products p
                WHERE p.deleted_at IS NULL {$storeSql}
            ");
            $stmt->execute($storeParams);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

            $catStmt = $this->db->query("SELECT COUNT(*) FROM categories WHERE deleted_at IS NULL");
            $categoriesCount = (int) $catStmt->fetchColumn();

            echo json_encode([
                'status' => 'success',
                'data' => [
                    'total_products'   => (int) ($row['total_products'] ?? 0),
                    'out_of_stock'     => (int) ($row['out_of_stock'] ?? 0),
                    'low_stock'        => (int) ($row['low_stock'] ?? 0),
                    'inventory_value'  => (float) ($row['inventory_value'] ?? 0),
                    'total_units'      => (int) ($row['total_units'] ?? 0),
                    'categories_count' => $categoriesCount,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur base de données']);
        }
    }

    private function getProducts() {
        [$storeSql, $storeParams] = $this->storeFilterSql();
        $stmt = $this->db->prepare("SELECT p.*, c.name as category_name, s.name as supplier_name 
                                    FROM products p 
                                    LEFT JOIN categories c ON p.category_id = c.id
                                    LEFT JOIN suppliers s ON p.supplier_id = s.id
                                    WHERE p.deleted_at IS NULL {$storeSql}
                                    ORDER BY p.name ASC");
        $stmt->execute($storeParams);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $products = array_map([$this, 'normalizeProductRow'], $products);
        echo json_encode(["status" => "success", "data" => $products]);
    }

    /**
     * Convert stored image_url (relative) to a browser-ready absolute URL.
     */
    private function normalizeProductRow(array $product): array
    {
        if (!empty($product['image_url'])) {
            $product['image_url'] = $this->resolveImagePublicUrl($product['image_url']);
        }
        return $product;
    }

    private function resolveImagePublicUrl(?string $stored): ?string
    {
        if ($stored === null || trim($stored) === '') {
            return null;
        }
        if (preg_match('#^https?://#i', $stored) || strpos($stored, 'data:image') === 0) {
            return $stored;
        }

        $normalized = str_replace('\\', '/', $stored);
        $normalized = preg_replace('#^(\.\./)+#', '', $normalized);
        $normalized = ltrim($normalized, '/');
        if (strpos($normalized, 'public/') === 0) {
            $normalized = substr($normalized, 7);
        }

        $filename = basename($normalized);
        if ($filename === '' || $filename === '.' || $filename === '..') {
            return null;
        }

        $relativePath = 'uploads/products/' . $filename;
        $physical = __DIR__ . '/../../public/' . $relativePath;
        if (!is_file($physical)) {
            $legacy = __DIR__ . '/../../public/' . $normalized;
            if (is_file($legacy)) {
                $relativePath = $normalized;
            }
        }

        $base = $this->encodeAppBaseUrl();
        $segments = explode('/', 'public/' . $relativePath);
        $encoded = implode('/', array_map('rawurlencode', $segments));

        return $base . '/' . $encoded;
    }

  /** Encode path segments in APP_URL (handles spaces in folder names). */
    private function encodeAppBaseUrl(): string
    {
        $base = rtrim(APP_URL, '/');
        if (preg_match('#^(https?://[^/]+)(/.*)?$#i', $base, $m)) {
            $origin = $m[1];
            $path = $m[2] ?? '';
            if ($path !== '') {
                $segments = array_values(array_filter(explode('/', ltrim($path, '/')), 'strlen'));
                $path = '/' . implode('/', array_map('rawurlencode', $segments));
            }
            return $origin . $path;
        }
        return str_replace(' ', '%20', $base);
    }

    private function getCategories() {
        $stmt = $this->db->prepare("SELECT * FROM categories WHERE deleted_at IS NULL ORDER BY name ASC");
        $stmt->execute();
        echo json_encode(["status" => "success", "data" => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    private function createCategory() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(["error" => "Le nom de la catégorie est requis"]);
            return;
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
            $stmt->execute([
                $data['name'], 
                $data['description'] ?? null
            ]);
            
            $newId = $this->db->lastInsertId();
            echo json_encode(["status" => "success", "message" => "Category created", "id" => $newId, "name" => $data['name']]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error"]);
        }
    }

    private function createProduct() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        // Basic validation
        if(empty($data['name']) || empty($data['sku']) || !isset($data['price'])) {
            http_response_code(400);
            echo json_encode(["error" => "Missing required fields"]);
            return;
        }

        $imageUrl = null;
        if (!empty($data['image'])) {
            $imageUrl = $this->saveImage($data['image']);
        }

        try {
            $stmt = $this->db->prepare("INSERT INTO products (name, sku, barcode, category_id, supplier_id, price, cost, stock_quantity, min_stock_level, unit, expiry_date, store_id, image_url) 
                                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $data['name'], 
                $data['sku'], 
                !empty($data['barcode']) ? $data['barcode'] : null,
                $data['category_id'] ?? null,
                $data['supplier_id'] ?? null,
                $data['price'],
                $data['cost'] ?? 0,
                $data['stock_quantity'] ?? 0,
                $data['min_stock_level'] ?? 5,
                $data['unit'] ?? 'piece',
                $data['expiry_date'] ?? null,
                StoreScope::activeStoreId() ?? $data['store_id'] ?? 1,
                $imageUrl
            ]);

            $newId = $this->db->lastInsertId();
            
            // Log initial stock if > 0
            if (!empty($data['stock_quantity']) && $data['stock_quantity'] > 0) {
                $this->logInventory($newId, $data['stock_quantity'], 'restock', 1, StoreScope::activeStoreId() ?? $data['store_id'] ?? 1);
            }

            echo json_encode(["status" => "success", "message" => "Product created", "id" => $newId]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
    }

    private function updateProduct($id) {
        $data = json_decode(file_get_contents("php://input"), true);
        
        $imageUpdateSql = "";
        $params = [
            $data['name'], $data['sku'], !empty($data['barcode']) ? $data['barcode'] : null, $data['category_id'] ?? null, 
            $data['price'], $data['cost'], $data['min_stock_level'], 
            $data['unit'] ?? 'piece', !empty($data['expiry_date']) ? $data['expiry_date'] : null
        ];

        if (!empty($data['image'])) {
            $imageUrl = $this->saveImage($data['image']);
            if ($imageUrl) {
                $imageUpdateSql = ", image_url = ?";
                $params[] = $imageUrl;
            }
        } elseif (!empty($data['remove_image'])) {
            $imageUpdateSql = ", image_url = NULL";
        }
        $params[] = $id;

        try {
            $stmt = $this->db->prepare("UPDATE products SET 
                name = ?, sku = ?, barcode = ?, category_id = ?, price = ?, cost = ?, min_stock_level = ?, unit = ?, expiry_date = ?
                $imageUpdateSql
                WHERE id = ?");
            
            $stmt->execute($params);

            echo json_encode(["status" => "success", "message" => "Product updated"]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error"]);
        }
    }

    private function deleteProduct($id) {
        // Soft delete
        $stmt = $this->db->prepare("UPDATE products SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?");
        if ($stmt->execute([$id])) {
            echo json_encode(["status" => "success", "message" => "Product deleted"]);
        } else {
            http_response_code(500);
            echo json_encode(["error" => "Failed to delete product"]);
        }
    }

    private function adjustStock() {
        $data = json_decode(file_get_contents("php://input"), true);
        $productId = $data['product_id'];
        $changeAmount = (int)$data['change_amount']; // Can be negative or positive
        $reason = $data['reason'] ?? 'correction';
        $userId = $data['user_id'] ?? ($_SESSION['user_id'] ?? 1);
        $storeId = StoreScope::activeStoreId() ?? $data['store_id'] ?? ($_SESSION['store_id'] ?? 1);

        try {
            $this->db->beginTransaction();

            // Update product stock
            $stmt = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $stmt->execute([$changeAmount, $productId]);

            // Create inventory log
            $this->logInventory($productId, $changeAmount, $reason, $userId, $storeId);

            $this->db->commit();
            echo json_encode(["status" => "success", "message" => "Stock adjusted"]);
        } catch(PDOException $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(["error" => "Failed to adjust stock"]);
        }
    }

    private function logInventory($productId, $changeAmount, $reason, $userId, $storeId) {
        $stmt = $this->db->prepare("INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason) 
                                    VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$storeId, $productId, $userId, $changeAmount, $reason]);
    }

    private function saveImage($base64Image) {
        if (preg_match('/^data:image\/(\w+);base64,/', $base64Image, $type)) {
            $data = substr($base64Image, strpos($base64Image, ',') + 1);
            $type = strtolower($type[1]);
            
            if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png' ])) {
                return null;
            }

            $data = base64_decode($data);
            if ($data === false) {
                return null;
            }

            $uploadDir = __DIR__ . '/../../public/uploads/products/';
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            $filename = uniqid('prod_') . '.' . $type;
            file_put_contents($uploadDir . $filename, $data);
            return 'uploads/products/' . $filename;
        }
        return null;
    }
}

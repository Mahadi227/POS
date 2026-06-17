<?php
// includes/Controllers/InventoryController.php

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/CategorySchemaMigrator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/../Config/config.php';

class InventoryController {
    private $db;
    /** @var bool|null */
    private $categoriesHaveStoreId = null;

    public function __construct() {
        $this->db = Database::getInstance()->getConnection();
        CategorySchemaMigrator::ensure($this->db);
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
                } else if ($action === 'import') {
                    $this->importProducts();
                }
                break;
            case 'PUT':
                if ($action === 'products' && $id) {
                    $this->updateProduct($id);
                } else if ($action === 'categories' && $id) {
                    $this->updateCategory($id);
                }
                break;
            case 'DELETE':
                if ($action === 'products' && $id) {
                    $this->deleteProduct($id);
                } else if ($action === 'categories' && $id) {
                    $this->deleteCategory($id);
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

    private function categoryStoreFilterSql(string $alias = 'c'): array
    {
        if (!$this->categoriesHaveStoreColumn()) {
            return ['', []];
        }
        return StoreScope::sqlFilter($this->db, 'store_id', $alias);
    }

    private function categoriesHaveStoreColumn(): bool
    {
        if ($this->categoriesHaveStoreId !== null) {
            return $this->categoriesHaveStoreId;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute(['categories', 'store_id']);
            $this->categoriesHaveStoreId = (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->categoriesHaveStoreId = false;
        }
        return $this->categoriesHaveStoreId;
    }

    private function resolveProductStoreId(array $data = []): int
    {
        $active = StoreScope::activeStoreId();
        if ($active !== null && $active > 0) {
            return $active;
        }
        if (!empty($data['store_id'])) {
            return (int) $data['store_id'];
        }
        return StoreScope::resolveStoreId($this->db);
    }

    private function assertCategoryForStore(?int $categoryId, int $storeId): bool
    {
        if ($categoryId === null || $categoryId <= 0) {
            return true;
        }

        $category = $this->findCategory($categoryId);
        if (!$category) {
            http_response_code(400);
            echo json_encode([
                'status' => 'error',
                'error'   => 'Catégorie invalide pour cette succursale',
                'message' => 'Invalid category for this store',
            ]);
            return false;
        }

        if ($this->categoriesHaveStoreColumn()) {
            $catStore = (int) ($category['store_id'] ?? 0);
            if ($catStore > 0 && $catStore !== $storeId) {
                http_response_code(400);
                echo json_encode([
                    'status' => 'error',
                    'error'   => 'Cette catégorie appartient à une autre succursale',
                    'message' => 'Category belongs to another store',
                ]);
                return false;
            }
        }

        return true;
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

            [$catStoreSql, $catStoreParams] = $this->categoryStoreFilterSql();
            $catStmt = $this->db->prepare("SELECT COUNT(*) FROM categories c WHERE c.deleted_at IS NULL{$catStoreSql}");
            $catStmt->execute($catStoreParams);
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
        return resolve_product_image_url($stored);
    }

  /** Encode path segments in APP_URL (handles spaces in folder names). */
    private function encodeAppBaseUrl(): string
    {
        return str_replace(' ', '%20', request_app_base_url());
    }

    private function getCategories() {
        [$storeSql, $storeParams] = $this->storeFilterSql();
        [$catStoreSql, $catStoreParams] = $this->categoryStoreFilterSql();

        $storeSelect = $this->categoriesHaveStoreColumn()
            ? ', c.store_id, st.name AS store_name'
            : '';
        $storeJoin = $this->categoriesHaveStoreColumn()
            ? ' LEFT JOIN stores st ON st.id = c.store_id AND st.deleted_at IS NULL'
            : '';

        $productJoin = 'LEFT JOIN products p ON p.category_id = c.id AND p.deleted_at IS NULL';
        if ($storeSql !== '') {
            $productJoin .= $storeSql;
        }

        $whereExtra = $catStoreSql;
        $params = array_merge($storeParams, $catStoreParams);

        $stmt = $this->db->prepare(
            "SELECT c.id, c.name, c.description, c.parent_id{$storeSelect},
                    COUNT(p.id) AS product_count
             FROM categories c
             {$productJoin}
             {$storeJoin}
             WHERE c.deleted_at IS NULL{$whereExtra}
             GROUP BY c.id, c.name, c.description, c.parent_id" .
             ($this->categoriesHaveStoreColumn() ? ', c.store_id, st.name' : '') . "
             ORDER BY c.name ASC"
        );
        $stmt->execute($params);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['id'] = (int) $row['id'];
            $row['product_count'] = (int) ($row['product_count'] ?? 0);
            if ($this->categoriesHaveStoreColumn()) {
                $row['store_id'] = isset($row['store_id']) ? (int) $row['store_id'] : null;
            }
        }
        unset($row);

        echo json_encode(['status' => 'success', 'data' => $rows]);
    }

    private function updateCategory(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));

        if ($name === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Le nom de la catégorie est requis']);
            return;
        }

        $existing = $this->findCategory($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Catégorie introuvable']);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE categories SET name = ?, description = ? WHERE id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$name, $data['description'] ?? null, $id]);
            echo json_encode([
                'status'  => 'success',
                'message' => 'Category updated',
                'data'    => ['id' => $id, 'name' => $name],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'error' => 'Database error']);
        }
    }

    private function deleteCategory(int $id): void
    {
        $existing = $this->findCategory($id);
        if (!$existing) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Catégorie introuvable']);
            return;
        }

        [$storeSql, $storeParams] = $this->storeFilterSql();
        $countSql = "SELECT COUNT(*) FROM products p WHERE p.category_id = ? AND p.deleted_at IS NULL{$storeSql}";
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute(array_merge([$id], $storeParams));
        $productCount = (int) $countStmt->fetchColumn();

        if ($productCount > 0) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'error'   => 'Cette catégorie contient encore des produits',
                'message' => 'Cette catégorie contient encore des produits',
                'product_count' => $productCount,
            ]);
            return;
        }

        $stmt = $this->db->prepare('UPDATE categories SET deleted_at = CURRENT_TIMESTAMP WHERE id = ?');
        if ($stmt->execute([$id])) {
            echo json_encode(['status' => 'success', 'message' => 'Category deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'error' => 'Failed to delete category']);
        }
    }

    private function findCategory(int $id): ?array
    {
        [$catStoreSql, $catStoreParams] = $this->categoryStoreFilterSql();
        $stmt = $this->db->prepare(
            "SELECT c.* FROM categories c WHERE c.id = ? AND c.deleted_at IS NULL{$catStoreSql} LIMIT 1"
        );
        $stmt->execute(array_merge([$id], $catStoreParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function createCategory() {
        $data = json_decode(file_get_contents("php://input"), true);
        
        if (empty($data['name'])) {
            http_response_code(400);
            echo json_encode(["error" => "Le nom de la catégorie est requis"]);
            return;
        }

        $storeId = $this->resolveProductStoreId($data);
        if (!StoreScope::requireStoreAccess($this->db, $storeId)) {
            return;
        }

        try {
            if ($this->categoriesHaveStoreColumn()) {
                $stmt = $this->db->prepare("INSERT INTO categories (name, description, store_id) VALUES (?, ?, ?)");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null,
                    $storeId,
                ]);
            } else {
                $stmt = $this->db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
                $stmt->execute([
                    $data['name'],
                    $data['description'] ?? null,
                ]);
            }
            
            $newId = $this->db->lastInsertId();
            echo json_encode([
                "status" => "success",
                "message" => "Category created",
                "id" => (int) $newId,
                "name" => $data['name'],
                "store_id" => $storeId,
            ]);
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

        $storeId = $this->resolveProductStoreId($data);
        if (!StoreScope::requireStoreAccess($this->db, $storeId)) {
            return;
        }
        if (!$this->assertCategoryForStore(isset($data['category_id']) ? (int) $data['category_id'] : null, $storeId)) {
            return;
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
                $storeId,
                $imageUrl
            ]);

            $newId = $this->db->lastInsertId();
            
            // Log initial stock if > 0
            if (!empty($data['stock_quantity']) && $data['stock_quantity'] > 0) {
                $this->logInventory($newId, $data['stock_quantity'], 'restock', 1, $storeId);
            }

            echo json_encode(["status" => "success", "message" => "Product created", "id" => $newId]);
        } catch(PDOException $e) {
            http_response_code(500);
            echo json_encode(["error" => "Database error: " . $e->getMessage()]);
        }
    }

    private function updateProduct($id) {
        $data = json_decode(file_get_contents("php://input"), true);

        [$storeSql, $storeParams] = $this->storeFilterSql();
        $checkStmt = $this->db->prepare("SELECT p.store_id FROM products p WHERE p.id = ? AND p.deleted_at IS NULL{$storeSql} LIMIT 1");
        $checkStmt->execute(array_merge([$id], $storeParams));
        $productStoreId = (int) ($checkStmt->fetchColumn() ?: 0);
        if ($productStoreId <= 0) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'error' => 'Product not found']);
            return;
        }
        if (!$this->assertCategoryForStore(isset($data['category_id']) ? (int) $data['category_id'] : null, $productStoreId)) {
            return;
        }
        
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
        $productId = (int) ($data['product_id'] ?? 0);
        $changeAmount = (int) ($data['change_amount'] ?? 0);
        $reason = $data['reason'] ?? 'correction';
        $userId = (int) ($data['user_id'] ?? ($_SESSION['user_id'] ?? 1));
        $storeId = (int) (StoreScope::activeStoreId() ?? $data['store_id'] ?? ($_SESSION['store_id'] ?? 1));

        if ($productId <= 0 || $changeAmount === 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Invalid adjustment data']);
            return;
        }

        try {
            $this->db->beginTransaction();

            $prevStmt = $this->db->prepare('SELECT stock_quantity FROM products WHERE id = ? LIMIT 1');
            $prevStmt->execute([$productId]);
            $previousQty = (int) ($prevStmt->fetchColumn() ?: 0);

            $stmt = $this->db->prepare("UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?");
            $stmt->execute([$changeAmount, $productId]);

            $logId = $this->logInventory($productId, $changeAmount, $reason, $userId, $storeId);
            $ledgerId = $this->syncAdjustToLedger($productId, $changeAmount, $reason, $userId, $storeId, $logId);

            $this->db->commit();

            require_once __DIR__ . '/../Notifications/StockAlertNotifier.php';
            StockAlertNotifier::checkStoreProduct($this->db, $productId, $storeId, $previousQty);
            echo json_encode([
                'status'        => 'success',
                'message'       => 'Stock adjusted',
                'log_id'        => $logId,
                'ledger_id'     => $ledgerId,
                'product_id'    => $productId,
                'change_amount' => $changeAmount,
            ]);
        } catch (PDOException $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'error' => 'Failed to adjust stock']);
        }
    }

    private function logInventory($productId, $changeAmount, $reason, $userId, $storeId): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason)
             VALUES (?, ?, ?, ?, ?)"
        );
        $stmt->execute([$storeId, $productId, $userId, $changeAmount, $reason]);

        return (int) $this->db->lastInsertId();
    }

    private function syncAdjustToLedger(int $productId, int $changeAmount, string $reason, int $userId, int $storeId, int $logId): ?int
    {
        try {
            $this->db->query('SELECT 1 FROM inventory_ledger LIMIT 1');
        } catch (PDOException $e) {
            return null;
        }

        $pStmt = $this->db->prepare('SELECT stock_quantity, cost, price FROM products WHERE id = ? LIMIT 1');
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);
        if (!$product) {
            return null;
        }

        $currentStock = (int) ($product['stock_quantity'] ?? 0);
        $openingStock = max(0, $currentStock - $changeAmount);
        $stockIn = max(0, $changeAmount);
        $stockOut = max(0, -$changeAmount);
        $cost = (float) ($product['cost'] ?? 0);
        $price = (float) ($product['price'] ?? 0);
        $openingValue = $openingStock * $cost;
        $stockOutValue = $stockOut * $price;
        $currentValue = $currentStock * $cost;
        $estimatedProfit = $stockOut * ($price - $cost);
        $notes = sprintf('Stock adjusted via inventory (%s) — log #%d', $reason, $logId);

        $stmt = $this->db->prepare(
            'INSERT INTO inventory_ledger (
                product_id, store_id, user_id, movement_type, reference_id, reference_type,
                opening_stock, stock_in, stock_out, current_stock,
                purchase_price, selling_price, opening_stock_value, stock_out_value,
                current_stock_value, estimated_profit, notes, movement_date
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stmt->execute([
            $productId,
            $storeId,
            $userId,
            InventoryLedgerHelper::movementTypeFromReason($reason),
            (string) $logId,
            'inventory_log',
            $openingStock,
            $stockIn,
            $stockOut,
            $currentStock,
            $cost,
            $price,
            $openingValue,
            $stockOutValue,
            $currentValue,
            $estimatedProfit,
            $notes,
        ]);

        return (int) $this->db->lastInsertId();
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

    private function importProducts(): void
    {
        $role = StoreScope::roleSlug();
        if (!in_array($role, ['admin', 'manager', 'super_admin'], true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'error' => 'Import not allowed for this role']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $rows = $data['rows'] ?? [];
        $dryRun = !empty($data['dry_run']);
        $updateExisting = array_key_exists('update_existing', $data) ? (bool) $data['update_existing'] : true;
        $createCategories = array_key_exists('create_categories', $data) ? (bool) $data['create_categories'] : true;
        $storeId = $this->resolveProductStoreId($data);

        if (!StoreScope::requireStoreAccess($this->db, $storeId)) {
            return;
        }

        if (!is_array($rows) || $rows === []) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'No rows to import']);
            return;
        }

        if (count($rows) > 500) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'error' => 'Maximum 500 rows per import']);
            return;
        }

        $categoryMap = $this->buildCategoryNameMap($storeId);
        $userId = (int) ($_SESSION['user_id'] ?? 1);
        $results = [
            'created'  => 0,
            'updated'  => 0,
            'skipped'  => 0,
            'errors'   => [],
            'preview'  => [],
        ];

        $useTransaction = !$dryRun;
        if ($useTransaction) {
            $this->db->beginTransaction();
        }

        try {
            foreach ($rows as $index => $row) {
                $line = $index + 1;
                $parsed = $this->normalizeImportRow(is_array($row) ? $row : []);
                $validationError = $this->validateImportRow($parsed);
                if ($validationError !== null) {
                    $results['skipped']++;
                    $entry = ['line' => $line, 'sku' => $parsed['sku'] ?? '', 'name' => $parsed['name'] ?? '', 'status' => 'error', 'message' => $validationError];
                    $results['errors'][] = $entry;
                    $results['preview'][] = $entry;
                    continue;
                }

                $categoryResult = $this->resolveImportCategory(
                    $parsed['category'],
                    $storeId,
                    $categoryMap,
                    $createCategories,
                    $dryRun
                );
                if ($categoryResult['error'] !== null) {
                    $results['skipped']++;
                    $entry = ['line' => $line, 'sku' => $parsed['sku'], 'name' => $parsed['name'], 'status' => 'error', 'message' => $categoryResult['error']];
                    $results['errors'][] = $entry;
                    $results['preview'][] = $entry;
                    continue;
                }
                $categoryId = $categoryResult['id'];

                $existing = $this->findProductBySku($storeId, $parsed['sku']);
                if ($existing && !$updateExisting) {
                    $results['skipped']++;
                    $entry = ['line' => $line, 'sku' => $parsed['sku'], 'name' => $parsed['name'], 'status' => 'error', 'message' => 'SKU already exists'];
                    $results['errors'][] = $entry;
                    $results['preview'][] = $entry;
                    continue;
                }

                $action = $existing ? 'update' : 'create';
                if ($dryRun) {
                    if ($existing) {
                        $results['updated']++;
                    } else {
                        $results['created']++;
                    }
                    $results['preview'][] = [
                        'line'    => $line,
                        'sku'     => $parsed['sku'],
                        'name'    => $parsed['name'],
                        'status'  => 'ok',
                        'action'  => $action,
                        'message' => $action === 'update' ? 'Will update' : 'Will create',
                    ];
                    continue;
                }

                try {
                    if ($existing) {
                        $this->updateProductFromImport((int) $existing['id'], $parsed, $categoryId);
                        $results['updated']++;
                    } else {
                        $newId = $this->insertProductFromImport($parsed, $categoryId, $storeId, $userId);
                        if ($newId > 0 && $parsed['stock_quantity'] > 0) {
                            $this->logInventory($newId, $parsed['stock_quantity'], 'restock', $userId, $storeId);
                        }
                        $results['created']++;
                    }
                    $results['preview'][] = [
                        'line'    => $line,
                        'sku'     => $parsed['sku'],
                        'name'    => $parsed['name'],
                        'status'  => 'ok',
                        'action'  => $action,
                        'message' => $action === 'update' ? 'Updated' : 'Created',
                    ];
                } catch (PDOException $e) {
                    $results['skipped']++;
                    $msg = str_contains($e->getMessage(), 'Duplicate') ? 'Duplicate SKU or barcode' : 'Database error';
                    $entry = ['line' => $line, 'sku' => $parsed['sku'], 'name' => $parsed['name'], 'status' => 'error', 'message' => $msg];
                    $results['errors'][] = $entry;
                    $results['preview'][] = $entry;
                }
            }

            if ($useTransaction) {
                $this->db->commit();
            }

            echo json_encode([
                'status'  => 'success',
                'dry_run' => $dryRun,
                'data'    => $results,
            ]);
        } catch (Throwable $e) {
            if ($useTransaction && $this->db->inTransaction()) {
                $this->db->rollBack();
            }
            http_response_code(500);
            echo json_encode(['status' => 'error', 'error' => 'Import failed', 'message' => $e->getMessage()]);
        }
    }

    private function normalizeImportRow(array $row): array
    {
        $get = static function (array $r, array $keys, $default = '') {
            foreach ($keys as $key) {
                if (array_key_exists($key, $r) && $r[$key] !== '' && $r[$key] !== null) {
                    return is_string($r[$key]) ? trim($r[$key]) : $r[$key];
                }
            }
            return $default;
        };

        $unit = strtolower((string) $get($row, ['unit', 'unite', 'unité'], 'piece'));
        if (!in_array($unit, ['piece', 'kg', 'liter', 'box'], true)) {
            $unit = 'piece';
        }

        $expiry = $get($row, ['expiry', 'expiry_date', 'expiration', 'date_expiration'], '');
        if ($expiry !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            $ts = strtotime($expiry);
            $expiry = $ts ? date('Y-m-d', $ts) : '';
        }

        return [
            'name'           => (string) $get($row, ['name', 'nom', 'product_name', 'produit'], ''),
            'sku'            => (string) $get($row, ['sku', 'reference', 'ref', 'code'], ''),
            'barcode'        => (string) $get($row, ['barcode', 'code_barre', 'ean', 'upc'], ''),
            'category'       => (string) $get($row, ['category', 'categorie', 'catégorie', 'category_name'], ''),
            'price'          => $get($row, ['price', 'prix', 'sale_price', 'prix_vente'], null),
            'cost'           => $get($row, ['cost', 'cout', 'coût', 'cost_price', 'prix_achat'], 0),
            'stock_quantity' => $get($row, ['stock', 'stock_quantity', 'quantity', 'qty', 'quantite', 'quantité'], 0),
            'min_stock_level'=> $get($row, ['min_stock', 'min_stock_level', 'alerte', 'stock_min'], 5),
            'unit'           => $unit,
            'expiry_date'    => $expiry !== '' ? $expiry : null,
        ];
    }

    private function validateImportRow(array $row): ?string
    {
        if ($row['name'] === '') {
            return 'Product name is required';
        }
        if ($row['sku'] === '') {
            return 'SKU is required';
        }
        if ($row['price'] === null || $row['price'] === '' || !is_numeric($row['price']) || (float) $row['price'] < 0) {
            return 'Valid price is required';
        }
        if (!is_numeric($row['cost']) || (float) $row['cost'] < 0) {
            return 'Invalid cost';
        }
        if (!is_numeric($row['stock_quantity']) || (int) $row['stock_quantity'] < 0) {
            return 'Invalid stock quantity';
        }
        if (!is_numeric($row['min_stock_level']) || (int) $row['min_stock_level'] < 0) {
            return 'Invalid min stock';
        }
        return null;
    }

    /** @return array<string, int> */
    private function buildCategoryNameMap(int $storeId): array
    {
        [$catStoreSql, $catStoreParams] = $this->categoryStoreFilterSql();
        $stmt = $this->db->prepare(
            "SELECT c.id, c.name FROM categories c WHERE c.deleted_at IS NULL{$catStoreSql}"
        );
        $stmt->execute($catStoreParams);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $row) {
            $key = mb_strtolower(trim($row['name']));
            if ($key !== '') {
                $map[$key] = (int) $row['id'];
            }
        }
        return $map;
    }

    /**
     * @param array<string, int> $categoryMap
     * @return array{id: ?int, error: ?string}
     */
    private function resolveImportCategory(
        string $categoryName,
        int $storeId,
        array &$categoryMap,
        bool $createCategories,
        bool $dryRun
    ): array {
        $name = trim($categoryName);
        if ($name === '') {
            return ['id' => null, 'error' => null];
        }

        $key = mb_strtolower($name);
        if (isset($categoryMap[$key])) {
            return ['id' => $categoryMap[$key], 'error' => null];
        }

        if (!$createCategories) {
            return ['id' => null, 'error' => 'Category not found: ' . $name];
        }

        if ($dryRun) {
            return ['id' => null, 'error' => null];
        }

        if ($this->categoriesHaveStoreColumn()) {
            $stmt = $this->db->prepare('INSERT INTO categories (name, store_id) VALUES (?, ?)');
            $stmt->execute([$name, $storeId]);
        } else {
            $stmt = $this->db->prepare('INSERT INTO categories (name) VALUES (?)');
            $stmt->execute([$name]);
        }
        $newId = (int) $this->db->lastInsertId();
        $categoryMap[$key] = $newId;
        return ['id' => $newId, 'error' => null];
    }

    private function findProductBySku(int $storeId, string $sku): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, stock_quantity FROM products WHERE store_id = ? AND sku = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$storeId, $sku]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function insertProductFromImport(array $row, ?int $categoryId, int $storeId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO products (name, sku, barcode, category_id, price, cost, stock_quantity, min_stock_level, unit, expiry_date, store_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['name'],
            $row['sku'],
            $row['barcode'] !== '' ? $row['barcode'] : null,
            $categoryId,
            (float) $row['price'],
            (float) $row['cost'],
            (int) $row['stock_quantity'],
            (int) $row['min_stock_level'],
            $row['unit'],
            $row['expiry_date'],
            $storeId,
        ]);
        return (int) $this->db->lastInsertId();
    }

    private function updateProductFromImport(int $productId, array $row, ?int $categoryId): void
    {
        $stmt = $this->db->prepare(
            'UPDATE products SET
                name = ?, barcode = ?, category_id = ?, price = ?, cost = ?,
                stock_quantity = ?, min_stock_level = ?, unit = ?, expiry_date = ?
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $row['name'],
            $row['barcode'] !== '' ? $row['barcode'] : null,
            $categoryId,
            (float) $row['price'],
            (float) $row['cost'],
            (int) $row['stock_quantity'],
            (int) $row['min_stock_level'],
            $row['unit'],
            $row['expiry_date'],
            $productId,
        ]);
    }
}

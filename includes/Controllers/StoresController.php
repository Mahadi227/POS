<?php
/**
 * API succursales — CRUD, changement de contexte, transferts de stock.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/StoreSchemaMigrator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class StoresController
{
    private PDO $db;

    /** @var array<string, bool> */
    private array $columnCache = [];

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        StoreSchemaMigrator::ensure($this->db);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $key = "{$table}.{$column}";
        if (isset($this->columnCache[$key])) {
            return $this->columnCache[$key];
        }
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return $this->columnCache[$key] = (bool) $stmt->fetchColumn();
    }

    private function dbErrorMessage(PDOException $e): string
    {
        error_log('StoresController: ' . $e->getMessage());
        if (defined('APP_DEBUG') && APP_DEBUG) {
            return 'Erreur base de données: ' . $e->getMessage();
        }
        return 'Erreur base de données. Réessayez ou exécutez includes/Database/migrations/002_multi_store.sql';
    }

    public function handleRequest(string $method, array $path): void
    {
        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin', 'cashier']);

        $segment1 = $path[1] ?? null;
        if ($segment1 !== null && $segment1 !== '' && is_numeric($segment1)) {
            $id = (int) $segment1;
            $action = null;
        } else {
            $id = null;
            $action = ($segment1 !== null && $segment1 !== '') ? $segment1 : null;
        }

        if ($method === 'GET' && $action === null && $id === null) {
            $this->listStores();
            return;
        }

        if ($method === 'GET' && $action === 'context') {
            $this->getContext();
            return;
        }

        if ($method === 'GET' && $action === 'health') {
            $this->healthCheck();
            return;
        }

        if ($method === 'POST' && $action === 'switch') {
            $this->switchStore();
            return;
        }

        if ($method === 'POST' && count($path) === 1) {
            $this->createStore();
            return;
        }

        if ($action === 'transfers') {
            $this->handleTransfers($method, $path);
            return;
        }

        if ($method === 'GET' && $id > 0) {
            $this->getStore($id);
            return;
        }

        if ($method === 'PUT' && $id > 0) {
            $this->updateStore($id);
            return;
        }

        if ($method === 'DELETE' && $id > 0) {
            $this->deleteStore($id);
            return;
        }

        http_response_code(404);
        echo json_encode([
            'status'  => 'error',
            'message' => 'Endpoint not found',
            'debug'   => defined('APP_DEBUG') && APP_DEBUG
                ? ['method' => $method, 'path' => $path, 'action' => $action, 'id' => $id]
                : null,
        ]);
    }

    private function handleTransfers(string $method, array $path): void
    {
        $seg2 = $path[2] ?? null;

        if ($method === 'GET' && $seg2 === 'stats') {
            $this->transferStats();
            return;
        }

        if ($method === 'GET' && $seg2 === 'products') {
            $this->listTransferProducts();
            return;
        }

        $transferId = isset($seg2) && is_numeric($seg2) ? (int) $seg2 : null;

        if ($method === 'GET' && $transferId) {
            $this->getTransfer($transferId);
            return;
        }

        if ($method === 'GET') {
            $this->listTransfers();
            return;
        }

        if ($method === 'POST' && !$transferId) {
            $this->createTransfer();
            return;
        }

        if ($method === 'PUT' && $transferId) {
            $this->updateTransfer($transferId);
            return;
        }

        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function listStores(): void
    {
        $allowed = StoreScope::accessibleStoreIds($this->db);
        $sql = 'SELECT * FROM stores WHERE 1=1';
        if ($this->hasColumn('stores', 'deleted_at')) {
            $sql .= ' AND deleted_at IS NULL';
        }
        $params = [];

        if ($allowed !== null && $allowed !== []) {
            $placeholders = implode(',', array_fill(0, count($allowed), '?'));
            $sql .= " AND id IN ({$placeholders})";
            $params = $allowed;
        } elseif ($allowed === []) {
            echo json_encode(['status' => 'success', 'data' => []]);
            return;
        }

        $sql .= ' ORDER BY name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

        echo json_encode([
            'status' => 'success',
            'data'   => array_map([$this, 'formatStore'], $stores),
        ]);
    }

    private function getContext(): void
    {
        $activeId = StoreScope::activeStoreId();
        $store = null;

        if ($activeId) {
            $stmt = $this->db->prepare(
                'SELECT id, name, code, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $stmt->execute([$activeId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row) {
                $store = $this->formatStore($row);
            }
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'active_store_id'   => $activeId,
                'is_global_view'    => StoreScope::isGlobalView(),
                'can_manage_stores' => StoreScope::canManageStores(),
                'is_super_admin'    => StoreScope::isSuperAdmin(),
                'active_store'      => $store,
            ],
        ]);
    }

    private function switchStore(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $storeId = isset($data['store_id']) && $data['store_id'] !== '' && $data['store_id'] !== null
            ? (int) $data['store_id']
            : null;

        if ($storeId === null) {
            if (!StoreScope::isSuperAdmin()) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Vue globale réservée au super admin']);
                return;
            }
            StoreScope::setActiveStore(null);
            $_SESSION['active_store_id'] = null;
            echo json_encode([
                'status'  => 'success',
                'message' => 'Vue toutes succursales activée',
                'data'    => ['active_store_id' => null, 'is_global_view' => true],
            ]);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $storeId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Succursale non autorisée']);
            return;
        }

        StoreScope::setActiveStore($storeId);
        $stmt = $this->db->prepare(
            'SELECT id, name, code, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Succursale active mise à jour',
            'data'    => [
                'active_store_id' => $storeId,
                'is_global_view'  => false,
                'active_store'    => $row ? $this->formatStore($row) : null,
            ],
        ]);
    }

    private function getStore(int $id): void
    {
        if (!StoreScope::canAccessStore($this->db, $id)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
            return;
        }

        $stmt = $this->db->prepare(
            'SELECT s.*,
                (SELECT COUNT(*) FROM users u WHERE u.store_id = s.id AND u.deleted_at IS NULL) AS staff_count,
                (SELECT COUNT(*) FROM products p WHERE p.store_id = s.id AND p.deleted_at IS NULL) AS product_count,
                (SELECT COALESCE(SUM(total),0) FROM sales sa WHERE sa.store_id = s.id AND sa.deleted_at IS NULL AND DATE(sa.created_at)=CURDATE()) AS today_revenue
             FROM stores s WHERE s.id = ? AND s.deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Succursale introuvable']);
            return;
        }

        echo json_encode(['status' => 'success', 'data' => $this->formatStore($row, true)]);
    }

    private function healthCheck(): void
    {
        StoreSchemaMigrator::ensure($this->db);
        $cols = $this->db->query(
            "SELECT COLUMN_NAME FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'stores'"
        )->fetchAll(PDO::FETCH_COLUMN);

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'role'              => StoreScope::roleSlug(),
                'can_manage'        => StoreScope::canManageStores(),
                'stores_columns'    => $cols,
                'has_user_stores'   => StoreScope::tableExists($this->db, 'user_stores'),
            ],
        ]);
    }

    private function createStore(): void
    {
        if (!StoreScope::canManageStores()) {
            http_response_code(403);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Permission refusée. Connectez-vous en Admin, Manager ou Super Admin.',
            ]);
            return;
        }

        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données JSON invalides']);
            return;
        }

        if (empty(trim($data['name'] ?? ''))) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Le nom est requis']);
            return;
        }

        try {
            $fields = ['name' => trim($data['name'])];
            if ($this->hasColumn('stores', 'code')) {
                $fields['code'] = trim($data['code'] ?? '') ?: $this->generateStoreCode($data['name']);
            }
            if ($this->hasColumn('stores', 'location')) {
                $fields['location'] = $data['location'] ?? null;
            }
            if ($this->hasColumn('stores', 'phone')) {
                $fields['phone'] = $data['phone'] ?? null;
            }
            if ($this->hasColumn('stores', 'email')) {
                $fields['email'] = $data['email'] ?? null;
            }
            if ($this->hasColumn('stores', 'tax_rate')) {
                $fields['tax_rate'] = $data['tax_rate'] ?? 18.00;
            }
            if ($this->hasColumn('stores', 'currency')) {
                $fields['currency'] = $data['currency'] ?? 'FCFA';
            }
            if ($this->hasColumn('stores', 'is_active')) {
                $fields['is_active'] = isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1;
            }

            $cols = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $stmt = $this->db->prepare("INSERT INTO stores ({$cols}) VALUES ({$placeholders})");
            $stmt->execute(array_values($fields));
            $newId = (int) $this->db->lastInsertId();

            if (StoreScope::isSuperAdmin()) {
                $this->linkUserToStore((int) $_SESSION['user_id'], $newId);
            }

            echo json_encode([
                'status'  => 'success',
                'message' => 'Succursale créée',
                'id'      => $newId,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $this->dbErrorMessage($e)]);
        }
    }

    private function updateStore(int $id): void
    {
        if (!StoreScope::canManageStores()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission refusée']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $id)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        try {
            $sets = ['name = ?'];
            $params = [trim($data['name'] ?? '')];

            if ($this->hasColumn('stores', 'code')) {
                $sets[] = 'code = ?';
                $params[] = trim($data['code'] ?? '');
            }
            if ($this->hasColumn('stores', 'location')) {
                $sets[] = 'location = ?';
                $params[] = $data['location'] ?? null;
            }
            if ($this->hasColumn('stores', 'phone')) {
                $sets[] = 'phone = ?';
                $params[] = $data['phone'] ?? null;
            }
            if ($this->hasColumn('stores', 'email')) {
                $sets[] = 'email = ?';
                $params[] = $data['email'] ?? null;
            }
            if ($this->hasColumn('stores', 'tax_rate')) {
                $sets[] = 'tax_rate = ?';
                $params[] = $data['tax_rate'] ?? 18.00;
            }
            if ($this->hasColumn('stores', 'currency')) {
                $sets[] = 'currency = ?';
                $params[] = $data['currency'] ?? 'FCFA';
            }
            if ($this->hasColumn('stores', 'is_active')) {
                $sets[] = 'is_active = ?';
                $params[] = isset($data['is_active']) ? (int) (bool) $data['is_active'] : 1;
            }

            $params[] = $id;
            $where = $this->hasColumn('stores', 'deleted_at')
                ? 'WHERE id = ? AND deleted_at IS NULL'
                : 'WHERE id = ?';

            $stmt = $this->db->prepare('UPDATE stores SET ' . implode(', ', $sets) . ' ' . $where);
            $stmt->execute($params);
            echo json_encode(['status' => 'success', 'message' => 'Succursale mise à jour']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $this->dbErrorMessage($e)]);
        }
    }

    private function deleteStore(int $id): void
    {
        if (!StoreScope::canManageStores()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission refusée']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $id)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
            return;
        }

        $where = $this->hasColumn('stores', 'deleted_at')
            ? 'id = ? AND deleted_at IS NULL'
            : 'id = ?';
        $stmt = $this->db->prepare("SELECT id, name FROM stores WHERE {$where} LIMIT 1");
        $stmt->execute([$id]);
        $store = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$store) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Succursale introuvable']);
            return;
        }

        $activeCount = (int) $this->db->query(
            $this->hasColumn('stores', 'deleted_at')
                ? 'SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL'
                : 'SELECT COUNT(*) FROM stores'
        )->fetchColumn();

        if ($activeCount <= 1) {
            http_response_code(400);
            echo json_encode([
                'status'  => 'error',
                'message' => 'Impossible de supprimer la dernière succursale du réseau',
            ]);
            return;
        }

        $deps = $this->storeDependencyCounts($id);

        try {
            if ($this->hasColumn('stores', 'deleted_at')) {
                $sets = ['deleted_at = CURRENT_TIMESTAMP'];
                if ($this->hasColumn('stores', 'is_active')) {
                    $sets[] = 'is_active = 0';
                }
                $this->db->prepare('UPDATE stores SET ' . implode(', ', $sets) . " WHERE id = ?")->execute([$id]);
            } elseif ($this->hasColumn('stores', 'is_active')) {
                $this->db->prepare('UPDATE stores SET is_active = 0 WHERE id = ?')->execute([$id]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'status'  => 'error',
                    'message' => 'Suppression non supportée sur cette base. Exécutez la migration multi-store.',
                ]);
                return;
            }

            if (StoreScope::tableExists($this->db, 'user_stores')) {
                $this->db->prepare('DELETE FROM user_stores WHERE store_id = ?')->execute([$id]);
            }

            if ((int) StoreScope::activeStoreId() === $id) {
                StoreScope::setActiveStore(null);
                if (StoreScope::isSuperAdmin()) {
                    $_SESSION['active_store_id'] = null;
                }
            }

            $msg = sprintf(
                'Succursale « %s » supprimée',
                $store['name']
            );
            if ($deps['users'] + $deps['products'] + $deps['sales'] > 0) {
                $msg .= sprintf(
                    ' (données conservées : %d utilisateur(s), %d produit(s), %d vente(s))',
                    $deps['users'],
                    $deps['products'],
                    $deps['sales']
                );
            }

            echo json_encode([
                'status'  => 'success',
                'message' => $msg,
                'data'    => ['dependencies' => $deps],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $this->dbErrorMessage($e)]);
        }
    }

    /** @return array{users: int, products: int, sales: int} */
    private function storeDependencyCounts(int $storeId): array
    {
        $out = ['users' => 0, 'products' => 0, 'sales' => 0];

        try {
            $uWhere = $this->hasColumn('users', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM users WHERE store_id = ?{$uWhere}");
            $stmt->execute([$storeId]);
            $out['users'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // ignore
        }

        try {
            $pWhere = $this->hasColumn('products', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM products WHERE store_id = ?{$pWhere}");
            $stmt->execute([$storeId]);
            $out['products'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // ignore
        }

        try {
            $sWhere = $this->hasColumn('sales', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM sales WHERE store_id = ?{$sWhere}");
            $stmt->execute([$storeId]);
            $out['sales'] = (int) $stmt->fetchColumn();
        } catch (PDOException $e) {
            // ignore
        }

        return $out;
    }

    private function transferStats(): void
    {
        [$filterSql, $params] = StoreScope::sqlFilter($this->db, 'from_store_id', 'sm');
        $base = "FROM stock_movements sm WHERE 1=1 {$filterSql}";

        $stats = ['pending' => 0, 'accepted' => 0, 'rejected' => 0, 'pending_units' => 0];

        foreach (['pending', 'accepted', 'rejected'] as $st) {
            $stmt = $this->db->prepare("SELECT COUNT(*) {$base} AND sm.status = ?");
            $stmt->execute(array_merge($params, [$st]));
            $stats[$st] = (int) $stmt->fetchColumn();
        }

        $stmt = $this->db->prepare("SELECT COALESCE(SUM(sm.quantity), 0) {$base} AND sm.status = 'pending'");
        $stmt->execute($params);
        $stats['pending_units'] = (int) $stmt->fetchColumn();

        echo json_encode(['status' => 'success', 'data' => $stats]);
    }

    private function listTransferProducts(): void
    {
        $storeId = (int) ($_GET['store_id'] ?? 0);
        $q = trim($_GET['q'] ?? '');

        if ($storeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'store_id requis']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $storeId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Succursale non autorisée']);
            return;
        }

        $deleted = $this->hasColumn('products', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';
        $sql = "SELECT p.id, p.name, p.sku, p.barcode, p.stock_quantity, p.price, p.unit
                FROM products p
                WHERE p.store_id = ?{$deleted}";
        $params = [$storeId];

        if ($q !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $like = '%' . $q . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' ORDER BY p.name ASC LIMIT 80';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'status' => 'success',
            'data'   => array_map(static function ($r) {
                return [
                    'id'             => (int) $r['id'],
                    'name'           => $r['name'],
                    'sku'            => $r['sku'] ?? '',
                    'barcode'        => $r['barcode'] ?? '',
                    'stock_quantity' => (int) ($r['stock_quantity'] ?? 0),
                    'price'          => (float) ($r['price'] ?? 0),
                    'unit'           => $r['unit'] ?? 'piece',
                ];
            }, $rows),
        ]);
    }

    private function listTransfers(): void
    {
        [$filterSql, $params] = StoreScope::sqlFilter($this->db, 'from_store_id', 'sm');
        $status = $_GET['status'] ?? '';
        $fromStore = isset($_GET['from_store']) && $_GET['from_store'] !== '' ? (int) $_GET['from_store'] : null;
        $toStore = isset($_GET['to_store']) && $_GET['to_store'] !== '' ? (int) $_GET['to_store'] : null;
        $search = trim($_GET['q'] ?? '');

        $sql = "SELECT sm.*, p.name AS product_name, p.sku, p.stock_quantity AS source_stock,
                       fs.name AS from_store_name, ts.name AS to_store_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                JOIN stores fs ON fs.id = sm.from_store_id
                JOIN stores ts ON ts.id = sm.to_store_id
                WHERE 1=1 {$filterSql}";
        $bind = $params;

        if (in_array($status, ['pending', 'accepted', 'rejected'], true)) {
            $sql .= ' AND sm.status = ?';
            $bind[] = $status;
        }
        if ($fromStore) {
            $sql .= ' AND sm.from_store_id = ?';
            $bind[] = $fromStore;
        }
        if ($toStore) {
            $sql .= ' AND sm.to_store_id = ?';
            $bind[] = $toStore;
        }
        if ($search !== '') {
            $sql .= ' AND (p.name LIKE ? OR p.sku LIKE ? OR fs.name LIKE ? OR ts.name LIKE ?)';
            $like = '%' . $search . '%';
            $bind = array_merge($bind, [$like, $like, $like, $like]);
        }

        $sql .= ' ORDER BY sm.created_at DESC LIMIT 200';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($bind);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        echo json_encode([
            'status' => 'success',
            'data'   => array_map([$this, 'formatTransfer'], $rows),
        ]);
    }

    private function getTransfer(int $id): void
    {
        [$filterSql, $params] = StoreScope::sqlFilter($this->db, 'from_store_id', 'sm');
        $sql = "SELECT sm.*, p.name AS product_name, p.sku, p.barcode, p.stock_quantity AS source_stock,
                       fs.name AS from_store_name, ts.name AS to_store_name
                FROM stock_movements sm
                JOIN products p ON p.id = sm.product_id
                JOIN stores fs ON fs.id = sm.from_store_id
                JOIN stores ts ON ts.id = sm.to_store_id
                WHERE sm.id = ? {$filterSql} LIMIT 1";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$id], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Transfert introuvable']);
            return;
        }

        echo json_encode(['status' => 'success', 'data' => $this->formatTransfer($row)]);
    }

    private function formatTransfer(array $row): array
    {
        $labels = [
            'pending'  => 'En attente',
            'accepted' => 'Accepté',
            'rejected' => 'Refusé',
        ];
        $status = $row['status'] ?? 'pending';

        return [
            'id'               => (int) $row['id'],
            'from_store_id'    => (int) $row['from_store_id'],
            'to_store_id'      => (int) $row['to_store_id'],
            'from_store_name'  => $row['from_store_name'] ?? '',
            'to_store_name'    => $row['to_store_name'] ?? '',
            'product_id'       => (int) $row['product_id'],
            'product_name'     => $row['product_name'] ?? '',
            'sku'              => $row['sku'] ?? '',
            'quantity'         => (int) ($row['quantity'] ?? 0),
            'source_stock'     => isset($row['source_stock']) ? (int) $row['source_stock'] : null,
            'status'           => $status,
            'status_label'     => $labels[$status] ?? $status,
            'can_accept'       => $status === 'pending' && StoreScope::canAccessStore($this->db, (int) $row['to_store_id']),
            'created_at'       => $row['created_at'] ?? null,
        ];
    }

    private function createTransfer(): void
    {
        if (!in_array(StoreScope::roleSlug(), ['super_admin', 'admin', 'manager'], true)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Permission refusée']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $fromId = (int) ($data['from_store_id'] ?? 0);
        $toId = (int) ($data['to_store_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        $qty = (int) ($data['quantity'] ?? 0);

        if ($fromId <= 0 || $toId <= 0 || $productId <= 0 || $qty <= 0 || $fromId === $toId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données de transfert invalides']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $fromId) || !StoreScope::canAccessStore($this->db, $toId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Succursale non autorisée']);
            return;
        }

        $pDeleted = $this->hasColumn('products', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
        $pStmt = $this->db->prepare(
            "SELECT id, store_id, stock_quantity, sku, name FROM products WHERE id = ?{$pDeleted}"
        );
        $pStmt->execute([$productId]);
        $product = $pStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || (int) $product['store_id'] !== $fromId) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Produit introuvable dans la succursale source']);
            return;
        }

        if ((int) $product['stock_quantity'] < $qty) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Stock insuffisant']);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO stock_movements (from_store_id, to_store_id, product_id, quantity, status)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$fromId, $toId, $productId, $qty, 'pending']);

        echo json_encode([
            'status'  => 'success',
            'message' => sprintf(
                'Transfert de %d × %s vers %s en attente',
                $qty,
                $product['name'],
                $this->storeName($toId)
            ),
            'id'      => (int) $this->db->lastInsertId(),
        ]);
    }

    private function storeName(int $storeId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM stores WHERE id = ? LIMIT 1');
        $stmt->execute([$storeId]);
        return (string) ($stmt->fetchColumn() ?: 'Succursale');
    }

    private function updateTransfer(int $transferId): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $action = $data['action'] ?? '';

        $stmt = $this->db->prepare(
            'SELECT sm.*, p.sku, p.name, p.barcode, p.price, p.cost, p.category_id
             FROM stock_movements sm
             JOIN products p ON p.id = sm.product_id
             WHERE sm.id = ? LIMIT 1'
        );
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$transfer || $transfer['status'] !== 'pending') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Transfert introuvable ou déjà traité']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, (int) $transfer['to_store_id'])) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Accès refusé']);
            return;
        }

        if ($action === 'reject') {
            if (!in_array(StoreScope::roleSlug(), ['super_admin', 'admin', 'manager'], true)) {
                http_response_code(403);
                echo json_encode(['status' => 'error', 'message' => 'Permission refusée']);
                return;
            }
            $this->db->prepare('UPDATE stock_movements SET status = ? WHERE id = ?')->execute(['rejected', $transferId]);
            echo json_encode(['status' => 'success', 'message' => 'Transfert refusé']);
            return;
        }

        if ($action !== 'accept') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Action invalide']);
            return;
        }

        try {
            $this->db->beginTransaction();
            $qty = (int) $transfer['quantity'];
            $fromId = (int) $transfer['from_store_id'];
            $toId = (int) $transfer['to_store_id'];
            $productId = (int) $transfer['product_id'];
            $userId = (int) ($_SESSION['user_id'] ?? 1);

            $stockStmt = $this->db->prepare(
                'SELECT stock_quantity FROM products WHERE id = ? AND store_id = ? FOR UPDATE'
            );
            $stockStmt->execute([$productId, $fromId]);
            $stock = (int) $stockStmt->fetchColumn();

            if ($stock < $qty) {
                throw new RuntimeException('Stock insuffisant');
            }

            $this->db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?')
                ->execute([$qty, $productId]);

            $destStmt = $this->db->prepare(
                'SELECT id, stock_quantity FROM products WHERE store_id = ? AND sku = ? AND deleted_at IS NULL LIMIT 1'
            );
            $destStmt->execute([$toId, $transfer['sku']]);
            $dest = $destStmt->fetch(PDO::FETCH_ASSOC);

            if ($dest) {
                $this->db->prepare('UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?')
                    ->execute([$qty, $dest['id']]);
                $destProductId = (int) $dest['id'];
            } else {
                $srcStmt = $this->db->prepare('SELECT * FROM products WHERE id = ?');
                $srcStmt->execute([$productId]);
                $src = $srcStmt->fetch(PDO::FETCH_ASSOC);
                $ins = $this->db->prepare(
                    'INSERT INTO products (category_id, supplier_id, store_id, sku, barcode, name, price, cost,
                     stock_quantity, min_stock_level, unit, image_url)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
                );
                $ins->execute([
                    $src['category_id'],
                    $src['supplier_id'],
                    $toId,
                    $src['sku'],
                    $src['barcode'],
                    $src['name'],
                    $src['price'],
                    $src['cost'],
                    $qty,
                    $src['min_stock_level'] ?? 5,
                    $src['unit'] ?? 'piece',
                    $src['image_url'],
                ]);
                $destProductId = (int) $this->db->lastInsertId();
            }

            $log = $this->db->prepare(
                'INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason) VALUES (?, ?, ?, ?, ?)'
            );
            $log->execute([$fromId, $productId, $userId, -$qty, 'transfer']);
            $log->execute([$toId, $destProductId, $userId, $qty, 'transfer']);

            $this->db->prepare('UPDATE stock_movements SET status = ? WHERE id = ?')->execute(['accepted', $transferId]);

            $this->db->commit();
            echo json_encode(['status' => 'success', 'message' => 'Transfert accepté']);
        } catch (Throwable $e) {
            $this->db->rollBack();
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Échec du transfert: ' . $e->getMessage()]);
        }
    }

    private function formatStore(array $row, bool $extended = false): array
    {
        $out = [
            'id'        => (int) $row['id'],
            'name'      => $row['name'],
            'code'      => $row['code'] ?? null,
            'location'  => $row['location'] ?? null,
            'phone'     => $row['phone'] ?? null,
            'email'     => $row['email'] ?? null,
            'tax_rate'  => isset($row['tax_rate']) ? (float) $row['tax_rate'] : 18.0,
            'currency'  => $row['currency'] ?? 'FCFA',
            'is_active' => isset($row['is_active']) ? (bool) $row['is_active'] : true,
        ];

        if ($extended) {
            $out['staff_count'] = (int) ($row['staff_count'] ?? 0);
            $out['product_count'] = (int) ($row['product_count'] ?? 0);
            $out['today_revenue'] = (float) ($row['today_revenue'] ?? 0);
            $out['dependencies'] = $this->storeDependencyCounts((int) $row['id']);
        }

        return $out;
    }

    private function generateStoreCode(string $name): string
    {
        $base = strtoupper(preg_replace('/[^A-Z0-9]/', '', substr($name, 0, 6)));
        return $base !== '' ? $base . '-' . random_int(10, 99) : 'STR-' . random_int(100, 999);
    }

    private function linkUserToStore(int $userId, int $storeId): void
    {
        if (!StoreScope::tableExists($this->db, 'user_stores')) {
            return;
        }
        try {
            $stmt = $this->db->prepare('INSERT IGNORE INTO user_stores (user_id, store_id) VALUES (?, ?)');
            $stmt->execute([$userId, $storeId]);
        } catch (PDOException $e) {
            // ignore
        }
    }
}

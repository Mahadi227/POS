<?php
/**
 * API dédiée au module caissier — statistiques et contexte.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/CustomerSchemaMigrator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/SaleFormatter.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/../Manager/Services/ReturnApprovalService.php';
require_once __DIR__ . '/../Manager/Services/CashierShiftService.php';
require_once __DIR__ . '/../CashRegister/CashRegisterSchema.php';

class CashierController
{
    private PDO $db;
    /** @var bool|null */
    private $customersHaveStoreId = null;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        CustomerSchemaMigrator::ensure($this->db);
    }

    private function customersHaveStoreColumn(): bool
    {
        if ($this->customersHaveStoreId !== null) {
            return $this->customersHaveStoreId;
        }
        try {
            $stmt = $this->db->prepare(
                'SELECT 1 FROM information_schema.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
            );
            $stmt->execute(['customers', 'store_id']);
            $this->customersHaveStoreId = (bool) $stmt->fetchColumn();
        } catch (PDOException $e) {
            $this->customersHaveStoreId = false;
        }
        return $this->customersHaveStoreId;
    }

  /** @return array{0: string, 1: array<int, mixed>} */
    private function customerStoreFilterSql(string $alias = 'c'): array
    {
        if (!$this->customersHaveStoreColumn()) {
            return ['', []];
        }
        return StoreScope::sqlFilter($this->db, 'store_id', $alias);
    }

    private function findCustomerForStore(int $id): ?array
    {
        [$storeSql, $storeParams] = $this->customerStoreFilterSql('c');
        $stmt = $this->db->prepare(
            "SELECT c.id, c.name, c.phone, c.email, c.loyalty_points
             FROM customers c
             WHERE c.id = ? AND c.deleted_at IS NULL{$storeSql}
             LIMIT 1"
        );
        $stmt->execute(array_merge([$id], $storeParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function handleRequest(string $method, array $path): void
    {
        AuthMiddleware::apiProtect(['cashier', 'admin', 'manager', 'super_admin']);

        $action = $path[1] ?? 'dashboard';

        if ($action === 'return' && $method === 'POST') {
            $this->processReturn();
            return;
        }

        if ($action === 'shift') {
            $this->handleShift($method, $path[2] ?? null);
            return;
        }

        if ($action === 'profile') {
            if ($method === 'GET') {
                $this->getProfile();
            } elseif ($method === 'POST') {
                $this->updateProfile();
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            return;
        }

        if ($action === 'customers') {
            $customerId = isset($path[2]) && is_numeric($path[2]) ? (int) $path[2] : null;
            if ($method === 'GET' && !$customerId) {
                $this->listCustomers();
            } elseif ($method === 'POST' && !$customerId) {
                $this->createCustomer();
            } elseif ($method === 'POST' && $customerId) {
                $this->updateCustomer($customerId);
            } elseif ($method === 'DELETE' && $customerId) {
                $this->deleteCustomer($customerId);
            } else {
                http_response_code(405);
                echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            }
            return;
        }

        if ($method !== 'GET') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        switch ($action) {
            case 'dashboard':
                $this->getDashboardStats();
                break;
            case 'context':
                $this->getContext();
                break;
            case 'pos':
            case 'pos-bootstrap':
                $this->getPosBootstrap();
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Cashier endpoint not found']);
        }
    }

    private function canAccessSale(array $sale): bool
    {
        $roleSlug = $this->roleSlug();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = $_SESSION['store_id'] ?? null;

        if ($roleSlug === 'cashier') {
            if ((int) $sale['user_id'] !== $userId) {
                return false;
            }
            if ($storeId && (int) $sale['store_id'] !== (int) $storeId) {
                return false;
            }
        } elseif (in_array($roleSlug, ['admin', 'manager', 'super_admin', 'staff'], true) && $storeId) {
            if ((int) $sale['store_id'] !== (int) $storeId) {
                return false;
            }
        }

        return true;
    }

    private function processReturn(): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = StoreScope::resolveStoreId($this->db);

        $service = new ReturnApprovalService();
        $result = $service->queueReturnRequest(
            $data,
            $userId,
            $storeId,
            fn (array $sale) => $this->canAccessSale($sale)
        );

        if (($result['status'] ?? '') !== 'success') {
            $msg = (string) ($result['message'] ?? '');
            $code = 400;
            if ($msg === 'Vente introuvable') {
                $code = 404;
            } elseif (str_contains($msg, 'Erreur') || str_contains($msg, 'non disponible')) {
                $code = 500;
            }
            http_response_code($code);
        }

        echo json_encode($result);
    }

    private function handleShift(string $method, ?string $subAction): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = StoreScope::resolveStoreId($this->db);
        $service = new CashierShiftService();

        if ($storeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid store context']);
            return;
        }

        if ($method === 'GET' && !$subAction) {
            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'shift'                => $service->currentShift($userId, $storeId),
                    'shift_module_ready'   => $service->tableReady(),
                    'available_registers'  => $service->availableRegisters($userId, $storeId),
                    'register_module_ready'=> CashRegisterSchema::ready(),
                ],
            ]);
            return;
        }

        if ($method !== 'POST') {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];

        if ($subAction === 'open') {
            $result = $service->openShift(
                $userId,
                $storeId,
                (float) ($data['opening_float'] ?? 0),
                isset($data['notes']) ? (string) $data['notes'] : null,
                !empty($data['register_id']) ? (int) $data['register_id'] : null
            );
            if (($result['status'] ?? '') !== 'success') {
                http_response_code(400);
            }
            echo json_encode($result);
            return;
        }

        if ($subAction === 'close') {
            $result = $service->closeShift(
                $userId,
                $storeId,
                (float) ($data['counted_cash'] ?? 0),
                isset($data['notes']) ? (string) $data['notes'] : null
            );
            if (($result['status'] ?? '') !== 'success') {
                http_response_code(400);
            }
            echo json_encode($result);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Shift endpoint not found']);
    }

    /** Filtre ventes du jour selon rôle (caissier / magasin). */
    private function todaySalesScope(string $alias = 's'): array
    {
        $roleSlug = $this->roleSlug();
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = $_SESSION['store_id'] ?? null;

        $sql = '';
        $params = [];

        if ($roleSlug === 'cashier') {
            $sql .= " AND {$alias}.user_id = ?";
            $params[] = $userId;
            if ($storeId) {
                $sql .= " AND {$alias}.store_id = ?";
                $params[] = (int) $storeId;
            }
        } elseif (in_array($roleSlug, ['admin', 'manager', 'super_admin'], true) && $storeId) {
            $sql .= " AND {$alias}.store_id = ?";
            $params[] = (int) $storeId;
        }

        return [$sql, $params];
    }

    private function getDashboardStats(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $storeId = isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null;
        $today = date('Y-m-d');

        [$scopeSql, $scopeParams] = $this->todaySalesScope('s');

        $sql = "SELECT COUNT(s.id) AS sales_count, COALESCE(SUM(s.total), 0) AS revenue
                FROM sales s
                WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.deleted_at IS NULL {$scopeSql}";
        $params = array_merge([$today], $scopeParams);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'revenue' => 0];

        $salesCount = (int) $row['sales_count'];
        $revenue = (float) $row['revenue'];
        $avgTicket = $salesCount > 0 ? round($revenue / $salesCount, 2) : 0.0;

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $yesterdayParams = array_merge([$yesterday], $scopeParams);
        $yesterdayStmt = $this->db->prepare($sql);
        $yesterdayStmt->execute($yesterdayParams);
        $yesterdayRow = $yesterdayStmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'revenue' => 0];
        $yesterdaySales = (int) $yesterdayRow['sales_count'];
        $yesterdayRevenue = (float) $yesterdayRow['revenue'];

        $storeName = 'RetailPOS';
        if ($storeId) {
            $storeStmt = $this->db->prepare(
                'SELECT name, location, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
            );
            $storeStmt->execute([$storeId]);
            if ($storeRow = $storeStmt->fetch(PDO::FETCH_ASSOC)) {
                $storeName = $storeRow['name'];
            }
        }

        $recentSql = "SELECT s.*, p.method AS payment_method
                      FROM sales s
                      LEFT JOIN payments p ON p.sale_id = s.id
                      WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.deleted_at IS NULL {$scopeSql}
                      ORDER BY s.created_at DESC
                      LIMIT 6";
        $recentStmt = $this->db->prepare($recentSql);
        $recentStmt->execute($params);
        $recentSales = array_map(
            [SaleFormatter::class, 'formatListRow'],
            $recentStmt->fetchAll(PDO::FETCH_ASSOC) ?: []
        );

        $paySql = "SELECT p.method, COUNT(*) AS cnt, COALESCE(SUM(p.amount), 0) AS amount
                   FROM payments p
                   INNER JOIN sales s ON s.id = p.sale_id
                   WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.deleted_at IS NULL {$scopeSql}
                   GROUP BY p.method";
        $payStmt = $this->db->prepare($paySql);
        $payStmt->execute($params);
        $paymentSummary = [];
        foreach ($payStmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $payRow) {
            $paymentSummary[] = [
                'method' => $payRow['method'],
                'count'  => (int) $payRow['cnt'],
                'amount' => (float) $payRow['amount'],
            ];
        }

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'sales_count'          => $salesCount,
                'revenue'              => $revenue,
                'avg_ticket'           => $avgTicket,
                'sales_count_yesterday'=> $yesterdaySales,
                'revenue_yesterday'    => $yesterdayRevenue,
                'date'                 => $today,
                'user_id'              => $userId,
                'store_id'             => $storeId,
                'store_name'           => $storeName,
                'cashier_name'         => $_SESSION['name'] ?? null,
                'role'                 => $_SESSION['role'] ?? null,
                'recent_sales'         => $recentSales,
                'payment_summary'      => $paymentSummary,
                'shift'                => (new CashierShiftService())->currentShift(
                    $userId,
                    (int) ($storeId ?? 0)
                ),
                'shift_module_ready'   => (new CashierShiftService())->tableReady(),
            ],
        ]);
    }

    private function getContext(): void
    {
        echo json_encode([
            'status' => 'success',
            'data'   => [
                'user_id'   => (int) ($_SESSION['user_id'] ?? 0),
                'name'      => $_SESSION['name'] ?? '',
                'email'     => $_SESSION['email'] ?? '',
                'role'      => $_SESSION['role'] ?? '',
                'store_id'  => isset($_SESSION['store_id']) ? (int) $_SESSION['store_id'] : null,
            ],
        ]);
    }

    private function getPosBootstrap(): void
    {
        $storeId = StoreScope::resolveStoreId($this->db);
        $store = ['id' => $storeId, 'name' => 'RetailPOS', 'tax_rate' => 18.0, 'currency' => 'FCFA'];
        $customers = [];

        $stmt = $this->db->prepare(
            'SELECT id, name, location, tax_rate, currency FROM stores WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$storeId]);
        if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $store = $row;
            $store['id'] = (int) $store['id'];
            $store['tax_rate'] = (float) $store['tax_rate'];
        }

        if ($this->customersHaveStoreColumn()) {
            [$storeSql, $storeParams] = $this->customerStoreFilterSql('customers');
            $cust = $this->db->prepare(
                "SELECT id, name, phone, store_id FROM customers
                 WHERE deleted_at IS NULL{$storeSql}
                 ORDER BY name ASC LIMIT 100"
            );
            $cust->execute($storeParams);
        } else {
            $cust = $this->db->prepare(
                'SELECT id, name, phone FROM customers WHERE deleted_at IS NULL ORDER BY name ASC LIMIT 100'
            );
            $cust->execute();
        }
        $customers = $cust->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $taxPercent = ((float) ($store['tax_rate'] ?? 0)) > 0 ? (float) $store['tax_rate'] : 18.0;
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        $shiftService = new CashierShiftService();

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'user' => [
                    'id'   => $userId,
                    'name' => $_SESSION['name'] ?? 'Caissier',
                    'role' => $_SESSION['role'] ?? '',
                ],
                'store' => $store,
                'customers' => $customers,
                'settings' => [
                    'tax_percent' => $taxPercent,
                    'tax_rate'    => $taxPercent / 100,
                    'currency'    => $store['currency'] ?? 'FCFA',
                ],
                'shift' => $shiftService->currentShift($userId, $storeId),
                'shift_module_ready' => $shiftService->tableReady(),
            ],
        ]);
    }

    private function getProfile(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
            return;
        }

        $stmt = $this->db->prepare(
            "SELECT u.id, u.name, u.email, u.is_active, u.store_id, u.last_login, u.created_at,
                    r.name AS role_name, st.name AS store_name, st.location AS store_location
             FROM users u
             JOIN roles r ON u.role_id = r.id
             LEFT JOIN stores st ON u.store_id = st.id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        [$scopeSql, $scopeParams] = $this->todaySalesScope('s');
        $today = date('Y-m-d');
        $statsStmt = $this->db->prepare(
            "SELECT COUNT(s.id) AS sales_count, COALESCE(SUM(s.total), 0) AS revenue
             FROM sales s
             WHERE DATE(s.created_at) = ? AND s.status = 'completed' AND s.deleted_at IS NULL {$scopeSql}"
        );
        $statsStmt->execute(array_merge([$today], $scopeParams));
        $stats = $statsStmt->fetch(PDO::FETCH_ASSOC) ?: ['sales_count' => 0, 'revenue' => 0];

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'id'             => (int) $user['id'],
                'name'           => $user['name'],
                'email'          => $user['email'],
                'role'           => $user['role_name'],
                'is_active'      => (bool) $user['is_active'],
                'store_id'       => $user['store_id'] ? (int) $user['store_id'] : null,
                'store_name'     => $user['store_name'] ?? null,
                'store_location' => $user['store_location'] ?? null,
                'last_login'     => $user['last_login'],
                'member_since'   => $user['created_at'],
                'today_sales'    => (int) $stats['sales_count'],
                'today_revenue'  => (float) $stats['revenue'],
            ],
        ]);
    }

    private function updateProfile(): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($userId <= 0) {
            http_response_code(401);
            echo json_encode(['status' => 'error', 'message' => 'Non authentifié']);
            return;
        }

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        $currentPassword = (string) ($data['current_password'] ?? '');
        $newPassword = (string) ($data['new_password'] ?? '');
        $confirmPassword = (string) ($data['confirm_password'] ?? '');

        if ($name === '' || strlen($name) < 2) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Le nom doit contenir au moins 2 caractères']);
            return;
        }

        $wantsPasswordChange = $newPassword !== '' || $confirmPassword !== '' || $currentPassword !== '';

        if ($wantsPasswordChange) {
            if (strlen($newPassword) < 6) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Le nouveau mot de passe doit faire au moins 6 caractères']);
                return;
            }
            if ($newPassword !== $confirmPassword) {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Les mots de passe ne correspondent pas']);
                return;
            }
            if ($currentPassword === '') {
                http_response_code(400);
                echo json_encode(['status' => 'error', 'message' => 'Saisissez votre mot de passe actuel']);
                return;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT id, name, password_hash FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $stmt->execute([$userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Utilisateur introuvable']);
            return;
        }

        if ($wantsPasswordChange && !password_verify($currentPassword, $user['password_hash'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Mot de passe actuel incorrect']);
            return;
        }

        try {
            if ($wantsPasswordChange) {
                $hash = password_hash($newPassword, PASSWORD_DEFAULT);
                $upd = $this->db->prepare(
                    'UPDATE users SET name = ?, password_hash = ? WHERE id = ?'
                );
                $upd->execute([$name, $hash, $userId]);
            } else {
                $upd = $this->db->prepare('UPDATE users SET name = ? WHERE id = ?');
                $upd->execute([$name, $userId]);
            }

            $_SESSION['name'] = $name;

            echo json_encode([
                'status'  => 'success',
                'message' => $wantsPasswordChange
                    ? 'Profil et mot de passe mis à jour'
                    : 'Profil mis à jour',
                'data'    => ['name' => $name],
            ]);
        } catch (Throwable $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour']);
        }
    }

    private function listCustomers(): void
    {
        $search = trim((string) ($_GET['q'] ?? ''));
        $limit = min(500, max(1, (int) ($_GET['limit'] ?? 200)));
        [$storeSql, $storeParams] = $this->customerStoreFilterSql('c');

        $sql = "SELECT c.id, c.name, c.phone, c.email, c.loyalty_points,
                       COUNT(s.id) AS sales_count
                FROM customers c
                LEFT JOIN sales s ON s.customer_id = c.id AND s.deleted_at IS NULL
                WHERE c.deleted_at IS NULL{$storeSql}";
        $params = $storeParams;

        if ($search !== '') {
            $sql .= ' AND (c.name LIKE ? OR c.phone LIKE ? OR c.email LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, [$like, $like, $like]);
        }

        $sql .= ' GROUP BY c.id ORDER BY c.name ASC LIMIT ' . $limit;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $customers = array_map(static function (array $row) {
            return [
                'id'             => (int) $row['id'],
                'name'           => $row['name'],
                'phone'          => $row['phone'] ?? '',
                'email'          => $row['email'] ?? '',
                'loyalty_points' => (int) ($row['loyalty_points'] ?? 0),
                'sales_count'    => (int) ($row['sales_count'] ?? 0),
            ];
        }, $rows);

        echo json_encode([
            'status' => 'success',
            'data'   => $customers,
            'total'  => count($customers),
        ]);
    }

    private function parseCustomerPayload(): ?array
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $name = trim((string) ($data['name'] ?? ''));
        $phone = trim((string) ($data['phone'] ?? ''));
        $email = trim((string) ($data['email'] ?? ''));

        if ($name === '' || strlen($name) < 2) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Le nom doit contenir au moins 2 caractères']);
            return null;
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Adresse e-mail invalide']);
            return null;
        }

        return [
            'name'  => $name,
            'phone' => $phone !== '' ? $phone : null,
            'email' => $email !== '' ? $email : null,
        ];
    }

    private function createCustomer(): void
    {
        $payload = $this->parseCustomerPayload();
        if ($payload === null) {
            return;
        }

        try {
            $storeId = StoreScope::resolveStoreId($this->db);
            if ($this->customersHaveStoreColumn()) {
                $stmt = $this->db->prepare(
                    'INSERT INTO customers (name, phone, email, store_id) VALUES (?, ?, ?, ?)'
                );
                $stmt->execute([$payload['name'], $payload['phone'], $payload['email'], $storeId]);
            } else {
                $stmt = $this->db->prepare(
                    'INSERT INTO customers (name, phone, email) VALUES (?, ?, ?)'
                );
                $stmt->execute([$payload['name'], $payload['phone'], $payload['email']]);
            }
            $id = (int) $this->db->lastInsertId();

            echo json_encode([
                'status'  => 'success',
                'message' => 'Client créé avec succès',
                'data'    => array_merge(['id' => $id, 'sales_count' => 0, 'loyalty_points' => 0], $payload),
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la création du client']);
        }
    }

    private function updateCustomer(int $id): void
    {
        $payload = $this->parseCustomerPayload();
        if ($payload === null) {
            return;
        }

        $check = $this->findCustomerForStore($id);
        if (!$check) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Client introuvable']);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?'
            );
            $stmt->execute([$payload['name'], $payload['phone'], $payload['email'], $id]);

            echo json_encode([
                'status'  => 'success',
                'message' => 'Client mis à jour',
                'data'    => array_merge(['id' => $id], $payload),
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Erreur lors de la mise à jour']);
        }
    }

    private function deleteCustomer(int $id): void
    {
        if (!$this->findCustomerForStore($id)) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Client introuvable']);
            return;
        }

        $stmt = $this->db->prepare(
            'UPDATE customers SET deleted_at = NOW() WHERE id = ?'
        );
        $stmt->execute([$id]);

        echo json_encode([
            'status'  => 'success',
            'message' => 'Client supprimé',
        ]);
    }

    private function roleSlug(): string
    {
        return strtolower(str_replace(' ', '_', trim($_SESSION['role'] ?? '')));
    }
}

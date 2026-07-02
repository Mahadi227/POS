<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Platform/SaaSPhase7Migrator.php';
require_once __DIR__ . '/../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/../Platform/Services/WebhookDispatcherService.php';
require_once __DIR__ . '/../Platform/UsageMeteringHook.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';
require_once __DIR__ . '/../Api/ApiProblem.php';

final class ApiV2SalesController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
        SaaSPhase7Migrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $id = isset($path[1]) && ctype_digit((string) $path[1]) ? (int) $path[1] : 0;

        if ($method === 'GET') {
            ApiV2Auth::requireScope($this->db, 'sales:read');
            if ($id > 0) {
                $this->getSale($id);
                return;
            }
            $this->listSales();
            return;
        }

        if ($method === 'POST' && $id <= 0) {
            ApiV2Auth::requireScope($this->db, 'sales:write');
            $this->createSale();
            return;
        }

        ApiProblem::send(405, 'Method Not Allowed', 'GET or POST supported');
    }

    private function createSale(): void
    {
        $ctx = ApiV2Auth::authenticate($this->db);
        $tenantId = TenantScope::id();
        $idempotencyKey = trim($_SERVER['HTTP_IDEMPOTENCY_KEY'] ?? '');

        if ($idempotencyKey !== '') {
            $cached = $this->getIdempotentResponse($tenantId, $idempotencyKey);
            if ($cached !== null) {
                echo $cached;
                return;
            }
        }

        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        if (empty($data['receipt_no']) || empty($data['items']) || !isset($data['total'])) {
            ApiProblem::send(422, 'Validation Error', 'receipt_no, items and total are required');
            return;
        }

        $userId = (int) ($ctx['user_id'] ?? 0);
        if ($userId <= 0) {
            $userId = $this->resolveApiUserId($tenantId, (int) ($data['user_id'] ?? 0));
        }
        if ($userId <= 0) {
            ApiProblem::send(422, 'Validation Error', 'user_id required for API key auth');
            return;
        }

        $storeId = (int) ($data['store_id'] ?? $ctx['store_id'] ?? 0);
        if ($storeId <= 0) {
            ApiProblem::send(422, 'Validation Error', 'store_id is required');
            return;
        }

        try {
            TenantScope::assertResource($this->db, 'stores', $storeId);
        } catch (RuntimeException) {
            ApiProblem::forbidden('Store not accessible');
            return;
        }

        try {
            $this->db->beginTransaction();

            $tenantCol = $this->hasColumn('sales', 'tenant_id') ? ', tenant_id' : '';
            $tenantVal = $this->hasColumn('sales', 'tenant_id') ? ', ?' : '';
            $params = [
                $data['receipt_no'],
                $storeId,
                $userId,
                isset($data['customer_id']) && $data['customer_id'] !== '' ? $data['customer_id'] : null,
                $data['total'],
                $data['tax'] ?? 0,
                $data['discount'] ?? 0,
            ];
            if ($tenantCol) {
                $params[] = $tenantId;
            }

            $stmt = $this->db->prepare(
                "INSERT INTO sales (receipt_no, store_id, user_id, customer_id, total, tax, discount, status{$tenantCol})
                 VALUES (?, ?, ?, ?, ?, ?, ?, 'completed'{$tenantVal})"
            );
            $stmt->execute($params);
            $saleId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                "INSERT INTO payments (sale_id, method, provider, transaction_ref, amount, status)
                 VALUES (?, ?, ?, ?, ?, 'success')"
            )->execute([
                $saleId,
                $data['payment_method'] ?? 'cash',
                $data['payment_provider'] ?? null,
                $data['payment_ref'] ?? null,
                $data['total'],
            ]);

            $itemStmt = $this->db->prepare(
                'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)'
            );
            $stockStmt = $this->db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');
            $logStmt = $this->db->prepare(
                "INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason) VALUES (?, ?, ?, ?, 'sale')"
            );

            foreach ($data['items'] as $item) {
                $qty = (int) ($item['quantity'] ?? 0);
                $unitPrice = (float) ($item['unit_price'] ?? 0);
                $productId = (int) ($item['product_id'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new InvalidArgumentException('Invalid sale item');
                }
                $subtotal = $qty * $unitPrice;
                $itemStmt->execute([$saleId, $productId, $qty, $unitPrice, $subtotal]);
                $stockStmt->execute([$qty, $productId]);
                $logStmt->execute([$storeId, $productId, $userId, -1 * abs($qty)]);
                $logId = (int) $this->db->lastInsertId();
                InventoryLedgerHelper::syncLogToLedger(
                    $this->db,
                    $logId,
                    $productId,
                    -1 * abs($qty),
                    'sale',
                    $userId,
                    $storeId,
                    'sale',
                    sprintf('Sale #%s — receipt %s', $saleId, $data['receipt_no'])
                );
            }

            $this->db->commit();

            WebhookDispatcherService::dispatch($this->db, $tenantId, 'sale.completed', [
                'sale_id' => $saleId,
                'receipt_no' => (string) $data['receipt_no'],
                'total' => (float) $data['total'],
                'store_id' => $storeId,
            ]);
            UsageMeteringHook::trackSale($tenantId);

            $response = json_encode([
                'data' => [
                    'sale_id' => $saleId,
                    'receipt_no' => $data['receipt_no'],
                    'total' => (float) $data['total'],
                    'store_id' => $storeId,
                ],
            ], JSON_UNESCAPED_UNICODE);

            if ($idempotencyKey !== '') {
                $this->storeIdempotentResponse($tenantId, $idempotencyKey, $response);
            }

            echo $response;
        } catch (InvalidArgumentException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            ApiProblem::send(422, 'Validation Error', $e->getMessage());
        } catch (PDOException $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            if ((int) $e->getCode() === 23000) {
                ApiProblem::send(409, 'Conflict', 'Receipt number already exists');
                return;
            }
            ApiProblem::send(500, 'Server Error', 'Unable to create sale');
        }
    }

    private function resolveApiUserId(int $tenantId, int $requested): int
    {
        if ($requested > 0) {
            $stmt = $this->db->prepare('SELECT id FROM users WHERE id = ? AND tenant_id = ? AND deleted_at IS NULL LIMIT 1');
            $stmt->execute([$requested, $tenantId]);
            $id = $stmt->fetchColumn();
            return $id ? (int) $id : 0;
        }
        if (!$this->hasColumn('users', 'tenant_id')) {
            return 1;
        }
        $stmt = $this->db->prepare(
            "SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.tenant_id = ? AND u.deleted_at IS NULL
               AND r.name IN ('super_admin','Admin','admin')
             ORDER BY u.id ASC LIMIT 1"
        );
        $stmt->execute([$tenantId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function getIdempotentResponse(int $tenantId, string $key): ?string
    {
        if (!$this->tableExists('api_idempotency_keys')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT response_json FROM api_idempotency_keys
             WHERE tenant_id = ? AND idempotency_key = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) LIMIT 1'
        );
        $stmt->execute([$tenantId, $key]);
        $raw = $stmt->fetchColumn();
        return $raw ? (string) $raw : null;
    }

    private function storeIdempotentResponse(int $tenantId, string $key, string $json): void
    {
        if (!$this->tableExists('api_idempotency_keys')) {
            return;
        }
        $this->db->prepare(
            'INSERT INTO api_idempotency_keys (tenant_id, idempotency_key, response_json) VALUES (?, ?, ?)'
        )->execute([$tenantId, $key, $json]);
    }

    private function listSales(): void
    {
        $pg = ApiV2Auth::pagination();
        $tenantId = TenantScope::id();
        $hasTenantCol = $this->hasColumn('sales', 'tenant_id');

        $hasDeleted = $this->hasColumn('sales', 'deleted_at');
        $deletedSql = $hasDeleted ? ' AND deleted_at IS NULL' : '';
        $deletedSqlS = $hasDeleted ? ' AND s.deleted_at IS NULL' : '';

        if ($hasTenantCol) {
            $countStmt = $this->db->prepare('SELECT COUNT(*) FROM sales WHERE tenant_id = ?' . $deletedSql);
            $countStmt->execute([$tenantId]);
        } else {
            $countStmt = $this->db->prepare(
                'SELECT COUNT(*) FROM sales s INNER JOIN stores st ON st.id = s.store_id
                 WHERE st.tenant_id = ?' . $deletedSqlS
            );
            $countStmt->execute([$tenantId]);
        }
        $total = (int) $countStmt->fetchColumn();

        if ($hasTenantCol) {
            $sql = 'SELECT id, receipt_no, store_id, user_id, customer_id, total, tax, discount, status, created_at
                    FROM sales WHERE tenant_id = ?' . $deletedSql . '
                    ORDER BY id DESC LIMIT ? OFFSET ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tenantId, $pg['per_page'], $pg['offset']]);
        } else {
            $sql = 'SELECT s.id, s.receipt_no, s.store_id, s.user_id, s.customer_id, s.total, s.tax, s.discount, s.status, s.created_at
                    FROM sales s INNER JOIN stores st ON st.id = s.store_id
                    WHERE st.tenant_id = ?' . $deletedSqlS . '
                    ORDER BY s.id DESC LIMIT ? OFFSET ?';
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$tenantId, $pg['per_page'], $pg['offset']]);
        }

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        header('X-Total-Count: ' . $total);
        ApiV2Auth::jsonSuccess($rows, [
            'page' => $pg['page'],
            'per_page' => $pg['per_page'],
            'total' => $total,
        ]);
    }

    private function getSale(int $id): void
    {
        $tenantId = TenantScope::id();
        $hasDeleted = $this->hasColumn('sales', 'deleted_at');
        $deletedSql = $hasDeleted ? ' AND deleted_at IS NULL' : '';
        $deletedSqlS = $hasDeleted ? ' AND s.deleted_at IS NULL' : '';

        if ($this->hasColumn('sales', 'tenant_id')) {
            $stmt = $this->db->prepare(
                'SELECT id, receipt_no, store_id, user_id, customer_id, total, tax, discount, status, created_at
                 FROM sales WHERE id = ? AND tenant_id = ?' . $deletedSql . ' LIMIT 1'
            );
            $stmt->execute([$id, $tenantId]);
        } else {
            $stmt = $this->db->prepare(
                'SELECT s.id, s.receipt_no, s.store_id, s.user_id, s.customer_id, s.total, s.tax, s.discount, s.status, s.created_at
                 FROM sales s INNER JOIN stores st ON st.id = s.store_id
                 WHERE s.id = ? AND st.tenant_id = ?' . $deletedSqlS . ' LIMIT 1'
            );
            $stmt->execute([$id, $tenantId]);
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ApiProblem::notFound('Sale not found');
            return;
        }
        ApiV2Auth::jsonSuccess($row);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

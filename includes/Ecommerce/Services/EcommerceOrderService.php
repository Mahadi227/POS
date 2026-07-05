<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/EcommercePaystackService.php';

/**
 * Creates POS sales from web checkout (channel=web).
 */
final class EcommerceOrderService
{
    public const PROVIDER_COD = 'cod';
    public const PROVIDER_ECOMMERCE = 'ecommerce';
    public const PROVIDER_PAYSTACK = 'paystack';

    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<int, array{product_id:int, quantity:int, unit_price:float}> $items
     * @return array{sale_id:int, receipt_no:string, status:string, payment_method:string}
     */
    public function placeOrder(
        int $tenantId,
        int $storeId,
        array $items,
        float $total,
        float $tax = 0.0,
        ?int $customerId = null,
        string $paymentMethod = 'card'
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('Cart is empty');
        }

        $userId = $this->resolveWebUserId($tenantId);
        if ($userId <= 0) {
            throw new RuntimeException('No system user for web orders');
        }

        $checkoutMethod = $this->normalizeCheckoutMethod($paymentMethod);
        $statuses = $this->resolveInitialStatuses($checkoutMethod);
        $dbMethod = $this->mapPaymentMethodForDb($checkoutMethod);
        $provider = $checkoutMethod === 'cash_on_delivery' ? self::PROVIDER_COD : self::PROVIDER_ECOMMERCE;

        $receiptNo = 'WEB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $this->db->beginTransaction();
        try {
            $cols = ['receipt_no', 'store_id', 'user_id', 'customer_id', 'total', 'tax', 'discount', 'status'];
            $vals = ['?', '?', '?', '?', '?', '?', '?', '?'];
            $params = [$receiptNo, $storeId, $userId, $customerId, $total, $tax, 0, $statuses['sale_status']];

            if ($this->hasColumn('sales', 'tenant_id')) {
                $cols[] = 'tenant_id';
                $vals[] = '?';
                $params[] = $tenantId;
            }
            if ($this->hasColumn('sales', 'channel')) {
                $cols[] = 'channel';
                $vals[] = "'web'";
            }

            $sql = 'INSERT INTO sales (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $this->db->prepare($sql)->execute($params);
            $saleId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                "INSERT INTO payments (sale_id, method, provider, transaction_ref, amount, status)
                 VALUES (?, ?, ?, ?, ?, ?)"
            )->execute([
                $saleId,
                $dbMethod,
                $provider,
                'WEB-' . $saleId,
                $total,
                $statuses['payment_status'],
            ]);

            $this->insertSaleItems($saleId, $items);

            if ($statuses['fulfill_stock']) {
                $this->deductStockForSale($saleId, $storeId, $userId);
            }

            $this->db->commit();

            return [
                'sale_id' => $saleId,
                'receipt_no' => $receiptNo,
                'status' => $statuses['sale_status'],
                'payment_method' => $checkoutMethod,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Creates a pending web order before Paystack checkout (no stock deduction).
     *
     * @param array<int, array{product_id:int, quantity:int, unit_price:float}> $items
     * @return array{sale_id:int, receipt_no:string, status:string, payment_method:string, reference:string}
     */
    public function createPaystackPendingOrder(
        int $tenantId,
        int $storeId,
        array $items,
        float $total,
        float $tax,
        ?int $customerId,
        string $paymentMethod,
        string $reference
    ): array {
        if ($items === []) {
            throw new InvalidArgumentException('Cart is empty');
        }

        $reference = trim($reference);
        if ($reference === '') {
            throw new InvalidArgumentException('Payment reference is required');
        }

        $userId = $this->resolveWebUserId($tenantId);
        if ($userId <= 0) {
            throw new RuntimeException('No system user for web orders');
        }

        $checkoutMethod = $this->normalizeCheckoutMethod($paymentMethod);
        if ($checkoutMethod === 'cash_on_delivery') {
            throw new InvalidArgumentException('Paystack is not used for cash on delivery');
        }

        $dbMethod = $this->mapPaymentMethodForDb($checkoutMethod);
        $receiptNo = 'WEB-' . date('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));

        $this->db->beginTransaction();
        try {
            $cols = ['receipt_no', 'store_id', 'user_id', 'customer_id', 'total', 'tax', 'discount', 'status'];
            $vals = ['?', '?', '?', '?', '?', '?', '?', '?'];
            $params = [$receiptNo, $storeId, $userId, $customerId, $total, $tax, 0, 'pending'];

            if ($this->hasColumn('sales', 'tenant_id')) {
                $cols[] = 'tenant_id';
                $vals[] = '?';
                $params[] = $tenantId;
            }
            if ($this->hasColumn('sales', 'channel')) {
                $cols[] = 'channel';
                $vals[] = "'web'";
            }

            $sql = 'INSERT INTO sales (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $vals) . ')';
            $this->db->prepare($sql)->execute($params);
            $saleId = (int) $this->db->lastInsertId();

            $this->db->prepare(
                "INSERT INTO payments (sale_id, method, provider, transaction_ref, amount, status)
                 VALUES (?, ?, ?, ?, ?, 'pending')"
            )->execute([
                $saleId,
                $dbMethod,
                self::PROVIDER_PAYSTACK,
                $reference,
                $total,
            ]);

            $this->insertSaleItems($saleId, $items);
            $this->db->commit();

            return [
                'sale_id' => $saleId,
                'receipt_no' => $receiptNo,
                'status' => 'pending',
                'payment_method' => $checkoutMethod,
                'reference' => $reference,
            ];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /**
     * Verifies Paystack payment and completes the order (deducts stock).
     *
     * @return array{sale_id:int, status:string, already_completed?:bool}
     */
    public function completePaystackPayment(string $reference, int $tenantId, int $storeId, EcommercePaystackService $paystack): array
    {
        $reference = trim($reference);
        if ($reference === '') {
            throw new InvalidArgumentException('Payment reference is required');
        }

        $order = $this->findByPaystackReference($reference);
        if (!$order) {
            throw new RuntimeException('Order not found');
        }

        $saleId = (int) ($order['id'] ?? 0);
        if ($saleId <= 0) {
            throw new RuntimeException('Order not found');
        }

        if ((int) ($order['store_id'] ?? 0) !== $storeId) {
            throw new RuntimeException('Order not found');
        }
        if ($this->hasColumn('sales', 'tenant_id') && (int) ($order['tenant_id'] ?? 0) !== $tenantId) {
            throw new RuntimeException('Order not found');
        }

        if (($order['status'] ?? '') === 'completed') {
            return ['sale_id' => $saleId, 'status' => 'completed', 'already_completed' => true];
        }

        if (($order['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Order cannot be completed');
        }

        $verify = $paystack->verify($tenantId, $reference);
        if (($verify['status'] ?? '') !== 'success') {
            throw new RuntimeException('Payment was not successful');
        }

        $metaSaleId = (int) ($verify['metadata']['sale_id'] ?? 0);
        if ($metaSaleId > 0 && $metaSaleId !== $saleId) {
            throw new RuntimeException('Payment reference mismatch');
        }

        $userId = $this->resolveWebUserId($tenantId);

        $this->db->beginTransaction();
        try {
            $this->deductStockForSale($saleId, $storeId, $userId);

            $stmt = $this->db->prepare(
                "UPDATE sales SET status = 'completed' WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$saleId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Order could not be completed');
            }

            $this->db->prepare(
                "UPDATE payments SET status = 'success' WHERE sale_id = ? AND provider = ? AND transaction_ref = ?"
            )->execute([$saleId, self::PROVIDER_PAYSTACK, $reference]);

            $this->db->commit();

            return ['sale_id' => $saleId, 'status' => 'completed'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<string, mixed>|null */
    public function findByPaystackReference(string $reference): ?array
    {
        $reference = trim($reference);
        if ($reference === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT s.*, p.method AS payment_method, p.status AS payment_status, p.provider AS payment_provider,
                    p.transaction_ref AS payment_reference
             FROM payments p
             INNER JOIN sales s ON s.id = p.sale_id
             WHERE p.transaction_ref = ? AND p.provider = ?
             LIMIT 1"
        );
        $stmt->execute([$reference, self::PROVIDER_PAYSTACK]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    public function isPaystackOrder(array $order): bool
    {
        return (string) ($order['payment_provider'] ?? $order['provider'] ?? '') === self::PROVIDER_PAYSTACK;
    }

    /**
     * Admin/manager confirms a cash-on-delivery web order (deducts stock).
     *
     * @return array{ok:bool, status:string}
     */
    public function acceptOrder(int $saleId, int $tenantId, int $storeId, int $acceptedByUserId): array
    {
        $order = $this->getWebOrderForAdmin($saleId, $tenantId, $storeId);
        if (!$order) {
            throw new RuntimeException('Order not found');
        }
        if (($order['status'] ?? '') !== 'pending') {
            throw new RuntimeException('Order is not awaiting approval');
        }
        if (!$this->isCodOrder($order)) {
            throw new RuntimeException('Only cash-on-delivery orders require manual approval');
        }

        $items = $this->loadSaleItems($saleId);
        if ($items === []) {
            throw new RuntimeException('Order has no items');
        }

        $this->db->beginTransaction();
        try {
            $this->deductStockForSale($saleId, $storeId, $acceptedByUserId);

            $stmt = $this->db->prepare(
                "UPDATE sales SET status = 'completed' WHERE id = ? AND status = 'pending'"
            );
            $stmt->execute([$saleId]);
            if ($stmt->rowCount() === 0) {
                throw new RuntimeException('Order could not be confirmed');
            }

            $this->db->commit();

            return ['ok' => true, 'status' => 'completed'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listOrdersForCustomer(int $customerId, int $limit = 20): array
    {
        $stmt = $this->db->prepare(
            "SELECT s.* FROM sales s WHERE s.customer_id = ?"
            . ($this->hasColumn('sales', 'channel') ? " AND s.channel = 'web'" : '')
            . ' ORDER BY s.id DESC LIMIT ?'
        );
        $stmt->bindValue(1, $customerId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(fn (array $row) => $this->attachPayment($row), $rows);
    }

    /** @return array<string, mixed>|null */
    public function getOrder(int $saleId, ?int $customerId = null): ?array
    {
        $sql = 'SELECT * FROM sales WHERE id = ?';
        $params = [$saleId];
        if ($customerId !== null) {
            $sql .= ' AND customer_id = ?';
            $params[] = $customerId;
        }
        $stmt = $this->db->prepare($sql . ' LIMIT 1');
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }

        $sale['items'] = $this->loadSaleItemsWithNames($saleId);
        return $this->attachPayment($sale);
    }

    public function isCodOrder(array $order): bool
    {
        $provider = (string) ($order['payment_provider'] ?? $order['provider'] ?? '');
        if ($provider === self::PROVIDER_COD) {
            return true;
        }

        return ($order['payment_method'] ?? '') === 'cash'
            && $provider === self::PROVIDER_COD;
    }

    public static function normalizeCheckoutMethod(string $method): string
    {
        $method = strtolower(trim($method));

        return match ($method) {
            'cash_on_delivery', 'cod', 'cash' => 'cash_on_delivery',
            'mobile_money', 'mobile', 'momo' => 'mobile_money',
            default => 'card',
        };
    }

    /** @return array{sale_status:string, payment_status:string, fulfill_stock:bool} */
    private function resolveInitialStatuses(string $checkoutMethod): array
    {
        if ($checkoutMethod === 'cash_on_delivery') {
            return [
                'sale_status' => 'pending',
                'payment_status' => 'pending',
                'fulfill_stock' => false,
            ];
        }

        return [
            'sale_status' => 'completed',
            'payment_status' => 'success',
            'fulfill_stock' => true,
        ];
    }

    private function mapPaymentMethodForDb(string $checkoutMethod): string
    {
        return match ($checkoutMethod) {
            'mobile_money' => 'mobile_money',
            'cash_on_delivery' => 'cash',
            default => 'card',
        };
    }

    /**
     * @param array<int, array{product_id:int, quantity:int, unit_price?:float}> $items
     */
    private function insertSaleItems(int $saleId, array $items): void
    {
        $itemStmt = $this->db->prepare(
            'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $unitPrice = (float) ($item['unit_price'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                throw new InvalidArgumentException('Invalid item');
            }
            $subtotal = round($qty * $unitPrice, 2);
            $itemStmt->execute([$saleId, $productId, $qty, $unitPrice, $subtotal]);
        }
    }

    private function deductStockForSale(int $saleId, int $storeId, int $userId): void
    {
        $lines = $this->loadSaleItems($saleId);
        $stockStmt = $this->db->prepare(
            'UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ? AND store_id = ? AND stock_quantity >= ?'
        );
        $logStmt = $this->db->prepare(
            "INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason) VALUES (?, ?, ?, ?, 'sale')"
        );

        foreach ($lines as $item) {
            $qty = (int) ($item['quantity'] ?? 0);
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            $stockStmt->execute([$qty, $productId, $storeId, $qty]);
            if ($stockStmt->rowCount() === 0) {
                throw new RuntimeException('Insufficient stock for product #' . $productId);
            }
            $logStmt->execute([$storeId, $productId, $userId, -1 * abs($qty)]);
            $logId = (int) $this->db->lastInsertId();
            InventoryLedgerHelper::syncLogToLedger(
                $this->db,
                $logId,
                $productId,
                -1 * abs($qty),
                'sale',
                $userId,
                $storeId
            );
        }
    }

    /** @return array<int, array{product_id:int, quantity:int, unit_price:float}> */
    private function loadSaleItems(int $saleId): array
    {
        $stmt = $this->db->prepare(
            'SELECT product_id, quantity, unit_price FROM sale_items WHERE sale_id = ?'
        );
        $stmt->execute([$saleId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(static fn (array $row): array => [
            'product_id' => (int) $row['product_id'],
            'quantity' => (int) $row['quantity'],
            'unit_price' => (float) $row['unit_price'],
        ], $rows);
    }

    /** @return array<int, array<string, mixed>> */
    private function loadSaleItemsWithNames(int $saleId): array
    {
        $items = $this->db->prepare(
            'SELECT si.*, p.name AS product_name FROM sale_items si LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?'
        );
        $items->execute([$saleId]);
        return $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    private function getWebOrderForAdmin(int $saleId, int $tenantId, int $storeId): ?array
    {
        $where = ['s.id = ?', 's.store_id = ?'];
        $params = [$saleId, $storeId];
        if ($this->hasColumn('sales', 'tenant_id')) {
            $where = ['s.id = ?', 's.tenant_id = ?', 's.store_id = ?'];
            $params = [$saleId, $tenantId, $storeId];
        }
        if ($this->hasColumn('sales', 'channel')) {
            $where[] = "s.channel = 'web'";
        }

        $stmt = $this->db->prepare('SELECT s.* FROM sales s WHERE ' . implode(' AND ', $where) . ' LIMIT 1');
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }

        return $this->attachPayment($sale);
    }

    /** @param array<string, mixed> $sale */
    private function attachPayment(array $sale): array
    {
        $payment = $this->getPaymentForSale((int) $sale['id']);
        if ($payment) {
            $sale['payment'] = $payment;
            $sale['payment_method'] = (string) ($payment['method'] ?? '');
            $sale['payment_status'] = (string) ($payment['status'] ?? '');
            $sale['payment_provider'] = (string) ($payment['provider'] ?? '');
            $sale['checkout_method'] = $this->resolveCheckoutMethodLabel($payment);
        }

        return $sale;
    }

    /** @return array<string, mixed>|null */
    private function getPaymentForSale(int $saleId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM payments WHERE sale_id = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$saleId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $payment */
    private function resolveCheckoutMethodLabel(array $payment): string
    {
        if (($payment['provider'] ?? '') === self::PROVIDER_COD) {
            return 'cash_on_delivery';
        }

        if (($payment['provider'] ?? '') === self::PROVIDER_PAYSTACK) {
            return match ((string) ($payment['method'] ?? '')) {
                'mobile_money' => 'mobile_money',
                default => 'card',
            };
        }

        return match ((string) ($payment['method'] ?? '')) {
            'mobile_money' => 'mobile_money',
            'cash' => 'cash_on_delivery',
            default => 'card',
        };
    }

    private function resolveWebUserId(int $tenantId): int
    {
        $roleOrder = "FIELD(LOWER(REPLACE(r.name, ' ', '_')), 'super_admin', 'admin', 'manager', 'cashier')";
        $activeClause = $this->hasColumn('users', 'status')
            ? " AND u.status = 'active'"
            : ($this->hasColumn('users', 'is_active')
                ? ' AND u.is_active = 1'
                : '');

        $baseSql = "SELECT u.id FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             WHERE u.deleted_at IS NULL{$activeClause}
             AND LOWER(REPLACE(r.name, ' ', '_')) IN ('super_admin', 'admin', 'manager', 'cashier')";

        if ($this->hasColumn('users', 'tenant_id') && $tenantId > 0) {
            $stmt = $this->db->prepare(
                $baseSql . ' AND u.tenant_id = ? ORDER BY ' . $roleOrder . ', u.id ASC LIMIT 1'
            );
            $stmt->execute([$tenantId]);
            $id = (int) ($stmt->fetchColumn() ?: 0);
            if ($id > 0) {
                return $id;
            }
        }

        $stmt = $this->db->query($baseSql . ' ORDER BY ' . $roleOrder . ', u.id ASC LIMIT 1');
        $id = (int) ($stmt->fetchColumn() ?: 0);
        if ($id > 0) {
            return $id;
        }

        $stmt = $this->db->query('SELECT id FROM users WHERE deleted_at IS NULL ORDER BY id ASC LIMIT 1');

        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/EcommerceCatalogRepository.php';

/**
 * Admin CRUD and reporting for tenant e-commerce.
 */
final class EcommerceAdminRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function resolveStoreId(int $tenantId): int
    {
        $settings = $this->getSettings($tenantId);
        $storeId = (int) ($settings['default_store_id'] ?? 0);
        if ($storeId <= 0) {
            $storeId = StoreScope::resolveStoreId($this->db);
        }
        if ($storeId <= 0) {
            $storeId = (new EcommerceCatalogRepository($this->db))->defaultStoreId($tenantId);
        }

        return max(0, $storeId);
    }

    /** @return array<string, mixed> */
    public function dashboardStats(int $tenantId, int $storeId): array
    {
        $onlineProducts = 0;
        $totalProducts = 0;
        if ($this->hasColumn('products', 'is_online')) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM products WHERE store_id = ? AND deleted_at IS NULL AND is_online = 1'
            );
            $stmt->execute([$storeId]);
            $onlineProducts = (int) $stmt->fetchColumn();
        } else {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM products WHERE store_id = ? AND deleted_at IS NULL'
            );
            $stmt->execute([$storeId]);
            $onlineProducts = (int) $stmt->fetchColumn();
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM products WHERE store_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$storeId]);
        $totalProducts = (int) $stmt->fetchColumn();

        $webOrdersToday = 0;
        $webRevenueToday = 0.0;
        $webOrdersTotal = 0;
        $webRevenueTotal = 0.0;
        $webFilter = $this->webOrderSqlCondition();
        if ($webFilter !== '') {
            $today = date('Y-m-d');
            $deleted = $this->hasColumn('sales', 'deleted_at') ? ' AND deleted_at IS NULL' : '';
            $scope = 'store_id = ?';
            $params = [$storeId];
            if ($this->hasColumn('sales', 'tenant_id')) {
                $scope = 'tenant_id = ? AND store_id = ?';
                $params = [$tenantId, $storeId];
            }

            $sql = "SELECT COUNT(*), COALESCE(SUM(total), 0) FROM sales WHERE {$scope} AND {$webFilter} AND DATE(created_at) = ?{$deleted}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(array_merge($params, [$today]));
            [$webOrdersToday, $webRevenueToday] = array_map(
                static fn($v, $i) => $i === 0 ? (int) $v : (float) $v,
                $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0],
                [0, 1]
            );

            $sqlTotal = "SELECT COUNT(*), COALESCE(SUM(total), 0) FROM sales WHERE {$scope} AND {$webFilter}{$deleted}";
            $stmt = $this->db->prepare($sqlTotal);
            $stmt->execute($params);
            [$webOrdersTotal, $webRevenueTotal] = array_map(
                static fn($v, $i) => $i === 0 ? (int) $v : (float) $v,
                $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0],
                [0, 1]
            );
        }

        $accounts = 0;
        if ($this->tableExists('ecommerce_storefront_accounts')) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ecommerce_storefront_accounts WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $accounts = (int) $stmt->fetchColumn();
        }

        $brands = 0;
        if ($this->tableExists('brands')) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM brands WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $brands = (int) $stmt->fetchColumn();
        }

        $blogPosts = 0;
        if ($this->tableExists('ecommerce_blog_posts')) {
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM ecommerce_blog_posts WHERE tenant_id = ?');
            $stmt->execute([$tenantId]);
            $blogPosts = (int) $stmt->fetchColumn();
        }

        return [
            'online_products' => $onlineProducts,
            'total_products' => $totalProducts,
            'web_orders_today' => $webOrdersToday,
            'web_revenue_today' => $webRevenueToday,
            'web_orders_total' => $webOrdersTotal,
            'web_revenue_total' => $webRevenueTotal,
            'storefront_accounts' => $accounts,
            'brands' => $brands,
            'blog_posts' => $blogPosts,
        ];
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listProducts(int $storeId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $where = ['p.store_id = ?', 'p.deleted_at IS NULL'];
        $params = [$storeId];

        if (isset($filters['online']) && $filters['online'] !== '') {
            if ($this->hasColumn('products', 'is_online')) {
                $where[] = 'p.is_online = ?';
                $params[] = (int) $filters['online'];
            }
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $countStmt = $this->db->prepare(
            'SELECT COUNT(*) FROM products p WHERE ' . implode(' AND ', $where)
        );
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $sql = 'SELECT p.id, p.name, p.sku, p.price, p.stock_quantity, p.category_id, p.image_url'
            . ($this->hasColumn('products', 'is_online') ? ', p.is_online' : ', 1 AS is_online')
            . ($this->hasColumn('products', 'slug') ? ', p.slug' : ', NULL AS slug')
            . ($this->hasColumn('products', 'brand_id') ? ', p.brand_id' : ', NULL AS brand_id')
            . ', c.name AS category_name FROM products p'
            . ' LEFT JOIN categories c ON c.id = p.category_id'
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.name ASC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    public function setProductOnline(int $productId, int $storeId, bool $online): bool
    {
        if (!$this->hasColumn('products', 'is_online')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE products SET is_online = ?, updated_at = NOW() WHERE id = ? AND store_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$online ? 1 : 0, $productId, $storeId]);
        return $stmt->rowCount() > 0;
    }

    public function updateProductSlug(int $productId, int $storeId, string $slug): bool
    {
        if (!$this->hasColumn('products', 'slug')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'UPDATE products SET slug = ?, updated_at = NOW() WHERE id = ? AND store_id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([$slug, $productId, $storeId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array{items: array<int, array<string, mixed>>, total: int} */
    public function listWebOrders(int $tenantId, int $storeId, int $limit = 30, int $offset = 0): array
    {
        $where = ['s.store_id = ?'];
        $params = [$storeId];
        if ($this->hasColumn('sales', 'tenant_id')) {
            $where = ['s.tenant_id = ?', 's.store_id = ?'];
            $params = [$tenantId, $storeId];
        }
        $webFilter = $this->webOrderSqlCondition('s');
        if ($webFilter !== '') {
            $where[] = $webFilter;
        }
        if ($this->hasColumn('sales', 'deleted_at')) {
            $where[] = 's.deleted_at IS NULL';
        }

        $whereSql = implode(' AND ', $where);
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM sales s WHERE $whereSql");
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        $stmt = $this->db->prepare(
            "SELECT s.id, s.receipt_no, s.total, s.tax, s.status, s.created_at, s.customer_id,
                    c.name AS customer_name, c.email AS customer_email,
                    pay.method AS payment_method, pay.status AS payment_status, pay.provider AS payment_provider
             FROM sales s
             LEFT JOIN customers c ON c.id = s.customer_id
             LEFT JOIN payments pay ON pay.id = (
                 SELECT p2.id FROM payments p2 WHERE p2.sale_id = s.id ORDER BY p2.id DESC LIMIT 1
             )
             WHERE $whereSql
             ORDER BY s.id DESC
             LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute($params);

        return ['items' => $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'total' => $total];
    }

    /** @return array<string, mixed>|null */
    public function getWebOrder(int $saleId, int $tenantId, int $storeId): ?array
    {
        $where = ['s.id = ?', 's.store_id = ?'];
        $params = [$saleId, $storeId];
        if ($this->hasColumn('sales', 'tenant_id')) {
            $where = ['s.id = ?', 's.tenant_id = ?', 's.store_id = ?'];
            $params = [$saleId, $tenantId, $storeId];
        }
        $webFilter = $this->webOrderSqlCondition('s');
        if ($webFilter !== '') {
            $where[] = $webFilter;
        }

        $stmt = $this->db->prepare(
            'SELECT s.* FROM sales s WHERE ' . implode(' AND ', $where) . ' LIMIT 1'
        );
        $stmt->execute($params);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$sale) {
            return null;
        }

        $pay = $this->db->prepare(
            'SELECT method, status, provider, amount FROM payments WHERE sale_id = ? ORDER BY id DESC LIMIT 1'
        );
        $pay->execute([$saleId]);
        if ($payment = $pay->fetch(PDO::FETCH_ASSOC)) {
            $sale['payment'] = $payment;
            $sale['payment_method'] = $payment['method'];
            $sale['payment_status'] = $payment['status'];
            $sale['payment_provider'] = $payment['provider'];
            $sale['checkout_method'] = ($payment['provider'] ?? '') === 'cod'
                ? 'cash_on_delivery'
                : (string) ($payment['method'] ?? 'card');
        }

        $items = $this->db->prepare(
            'SELECT si.*, p.name AS product_name FROM sale_items si
             LEFT JOIN products p ON p.id = si.product_id WHERE si.sale_id = ?'
        );
        $items->execute([$saleId]);
        $sale['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $sale;
    }

    /** @return array<int, array<string, mixed>> */
    public function listBrands(int $tenantId): array
    {
        if (!$this->tableExists('brands')) {
            return [];
        }
        $stmt = $this->db->prepare('SELECT * FROM brands WHERE tenant_id = ? ORDER BY name ASC');
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveBrand(int $tenantId, array $data, ?int $id = null): int
    {
        if (!$this->tableExists('brands')) {
            throw new RuntimeException('Brands table missing');
        }
        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new InvalidArgumentException('Brand name required');
        }
        $slug = trim((string) ($data['slug'] ?? ''));
        if ($slug === '') {
            $slug = self::slugify($name);
        }
        $logoUrl = trim((string) ($data['logo_url'] ?? '')) ?: null;
        $storeId = isset($data['store_id']) ? (int) $data['store_id'] : null;

        if ($id) {
            $stmt = $this->db->prepare(
                'UPDATE brands SET name = ?, slug = ?, logo_url = ?, store_id = ? WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([$name, $slug, $logoUrl, $storeId, $id, $tenantId]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO brands (tenant_id, store_id, name, slug, logo_url) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $storeId, $name, $slug, $logoUrl]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteBrand(int $tenantId, int $id): bool
    {
        if (!$this->tableExists('brands')) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM brands WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function listBlogPosts(int $tenantId, bool $publishedOnly = false): array
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return [];
        }
        $sql = 'SELECT * FROM ecommerce_blog_posts WHERE tenant_id = ?';
        if ($publishedOnly) {
            $sql .= ' AND is_published = 1';
        }
        $sql .= ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @param array<string, mixed> $data */
    public function saveBlogPost(int $tenantId, array $data, ?int $id = null): int
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            throw new RuntimeException('Blog table missing');
        }
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '') {
            throw new InvalidArgumentException('Title required');
        }
        $slug = trim((string) ($data['slug'] ?? '')) ?: self::slugify($title);
        $excerpt = trim((string) ($data['excerpt'] ?? ''));
        $body = trim((string) ($data['body'] ?? ''));
        $published = !empty($data['is_published']) ? 1 : 0;
        $publishedAt = $published ? ($data['published_at'] ?? date('Y-m-d H:i:s')) : null;

        if ($id) {
            $stmt = $this->db->prepare(
                'UPDATE ecommerce_blog_posts SET slug = ?, title = ?, excerpt = ?, body = ?, is_published = ?, published_at = ? WHERE id = ? AND tenant_id = ?'
            );
            $stmt->execute([$slug, $title, $excerpt, $body, $published, $publishedAt, $id, $tenantId]);
            return $id;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO ecommerce_blog_posts (tenant_id, slug, title, excerpt, body, is_published, published_at) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $slug, $title, $excerpt, $body, $published, $publishedAt]);
        return (int) $this->db->lastInsertId();
    }

    public function deleteBlogPost(int $tenantId, int $id): bool
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return false;
        }
        $stmt = $this->db->prepare('DELETE FROM ecommerce_blog_posts WHERE id = ? AND tenant_id = ?');
        $stmt->execute([$id, $tenantId]);
        return $stmt->rowCount() > 0;
    }

    /** @return array<int, array<string, mixed>> */
    public function listStorefrontAccounts(int $tenantId, int $limit = 50, int $offset = 0): array
    {
        if (!$this->tableExists('ecommerce_storefront_accounts')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, customer_id, email, name, phone, created_at, last_login
             FROM ecommerce_storefront_accounts
             WHERE tenant_id = ? ORDER BY id DESC LIMIT ? OFFSET ?'
        );
        $stmt->bindValue(1, $tenantId, PDO::PARAM_INT);
        $stmt->bindValue(2, $limit, PDO::PARAM_INT);
        $stmt->bindValue(3, $offset, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array<string, mixed>|null */
    public function getStorefrontAccount(int $tenantId, int $id): ?array
    {
        if (!$this->tableExists('ecommerce_storefront_accounts')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, customer_id, email, name, phone, created_at, last_login
             FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND id = ? LIMIT 1'
        );
        $stmt->execute([$tenantId, $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    /** @param array<string, mixed> $data */
    public function saveStorefrontAccount(int $tenantId, array $data, ?int $id = null): int
    {
        require_once __DIR__ . '/../Services/EcommerceAccountService.php';

        if (!$this->tableExists('ecommerce_storefront_accounts')) {
            throw new RuntimeException('Accounts table missing');
        }

        $name = trim((string) ($data['name'] ?? ''));
        $phone = EcommerceAccountService::normalizePhone((string) ($data['phone'] ?? ''));
        $emailInput = trim((string) ($data['email'] ?? ''));
        $password = (string) ($data['password'] ?? '');

        if ($name === '') {
            throw new InvalidArgumentException('Name required');
        }
        if ($phone === '') {
            throw new InvalidArgumentException('Phone required');
        }
        if (strlen(preg_replace('/\D+/', '', $phone)) < 8) {
            throw new InvalidArgumentException('Invalid phone');
        }

        $email = null;
        if ($emailInput !== '') {
            $email = EcommerceAccountService::normalizeOptionalEmail($emailInput);
        }

        $excludeId = $id ?? 0;
        $phoneStmt = $this->db->prepare(
            'SELECT id FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND phone = ? AND id != ? LIMIT 1'
        );
        $phoneStmt->execute([$tenantId, $phone, $excludeId]);
        if ($phoneStmt->fetchColumn()) {
            throw new InvalidArgumentException('Phone already registered');
        }

        if ($email !== null) {
            $emailStmt = $this->db->prepare(
                'SELECT id FROM ecommerce_storefront_accounts WHERE tenant_id = ? AND email = ? AND id != ? LIMIT 1'
            );
            $emailStmt->execute([$tenantId, $email, $excludeId]);
            if ($emailStmt->fetchColumn()) {
                throw new InvalidArgumentException('Email already registered');
            }
        }

        if ($id) {
            $existing = $this->getStorefrontAccount($tenantId, $id);
            if (!$existing) {
                throw new RuntimeException('Account not found');
            }

            $storefrontEmail = $email ?? (string) ($existing['email'] ?? '');
            if ($storefrontEmail === '') {
                $storefrontEmail = EcommerceAccountService::placeholderEmailForPhone($tenantId, $phone);
            }

            $params = [$name, $phone, $storefrontEmail, $id, $tenantId];
            $sql = 'UPDATE ecommerce_storefront_accounts SET name = ?, phone = ?, email = ?';
            if ($password !== '') {
                $sql .= ', password_hash = ?';
                $params = [$name, $phone, $storefrontEmail, password_hash($password, PASSWORD_DEFAULT), $id, $tenantId];
            }
            $sql .= ' WHERE id = ? AND tenant_id = ?';
            $this->db->prepare($sql)->execute($params);

            $customerId = (int) ($existing['customer_id'] ?? 0);
            if ($customerId > 0) {
                if ($email !== null) {
                    $this->db->prepare('UPDATE customers SET name = ?, phone = ?, email = ? WHERE id = ?')
                        ->execute([$name, $phone, $email, $customerId]);
                } else {
                    $this->db->prepare('UPDATE customers SET name = ?, phone = ? WHERE id = ?')
                        ->execute([$name, $phone, $customerId]);
                }
            }

            return $id;
        }

        $storefrontEmail = $email ?? EcommerceAccountService::placeholderEmailForPhone($tenantId, $phone);
        $passwordHash = $password !== ''
            ? password_hash($password, PASSWORD_DEFAULT)
            : password_hash(bin2hex(random_bytes(16)), PASSWORD_DEFAULT);

        $customerId = (new EcommerceAccountService($this->db))->ensurePosCustomerRecord($name, $email, $phone);

        $stmt = $this->db->prepare(
            'INSERT INTO ecommerce_storefront_accounts (tenant_id, customer_id, email, password_hash, name, phone)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $customerId, $storefrontEmail, $passwordHash, $name, $phone]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteStorefrontAccount(int $tenantId, int $id): bool
    {
        if (!$this->tableExists('ecommerce_storefront_accounts')) {
            return false;
        }
        $stmt = $this->db->prepare(
            'DELETE FROM ecommerce_storefront_accounts WHERE id = ? AND tenant_id = ?'
        );
        $stmt->execute([$id, $tenantId]);

        return $stmt->rowCount() > 0;
    }

    /** @return array<string, mixed> */
    public function getSettings(int $tenantId): array
    {
        $defaults = [
            'default_store_id' => null,
            'currency' => 'EUR',
            'tax_rate' => 0.0,
            'paystack_public_key' => '',
            'paystack_secret_key' => '',
            'paystack_enabled' => 0,
            'paystack_currency' => '',
        ];
        if (!$this->tableExists('ecommerce_settings')) {
            return $defaults;
        }
        $stmt = $this->db->prepare('SELECT * FROM ecommerce_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return $defaults;
        }

        return array_merge($defaults, $row);
    }

    /** @return array<string, mixed> */
    public function getSettingsForApi(int $tenantId): array
    {
        $settings = $this->getSettings($tenantId);
        $settings['paystack_secret_key_set'] = trim((string) ($settings['paystack_secret_key'] ?? '')) !== '';
        unset($settings['paystack_secret_key']);

        return $settings;
    }

    /** @param array<string, mixed> $data */
    public function saveSettings(int $tenantId, array $data): void
    {
        if (!$this->tableExists('ecommerce_settings')) {
            throw new RuntimeException('Settings table missing');
        }
        $existing = $this->getSettings($tenantId);
        $storeId = isset($data['default_store_id']) ? (int) $data['default_store_id'] : null;
        $currency = strtoupper(trim((string) ($data['currency'] ?? 'EUR')));
        $taxRate = round((float) ($data['tax_rate'] ?? 0), 2);
        $paystackPublic = trim((string) ($data['paystack_public_key'] ?? $existing['paystack_public_key'] ?? ''));
        $paystackSecret = trim((string) ($data['paystack_secret_key'] ?? ''));
        if ($paystackSecret === '') {
            $paystackSecret = trim((string) ($existing['paystack_secret_key'] ?? ''));
        }
        $paystackEnabled = !empty($data['paystack_enabled']) ? 1 : 0;
        $paystackCurrency = strtoupper(trim((string) ($data['paystack_currency'] ?? $existing['paystack_currency'] ?? '')));

        $hasPaystackCols = $this->hasColumn('ecommerce_settings', 'paystack_enabled');

        $exists = $this->db->prepare('SELECT 1 FROM ecommerce_settings WHERE tenant_id = ? LIMIT 1');
        $exists->execute([$tenantId]);
        if ($exists->fetchColumn()) {
            if ($hasPaystackCols) {
                $stmt = $this->db->prepare(
                    'UPDATE ecommerce_settings SET default_store_id = ?, currency = ?, tax_rate = ?,
                     paystack_public_key = ?, paystack_secret_key = ?, paystack_enabled = ?, paystack_currency = ?
                     WHERE tenant_id = ?'
                );
                $stmt->execute([
                    $storeId ?: null,
                    $currency,
                    $taxRate,
                    $paystackPublic ?: null,
                    $paystackSecret ?: null,
                    $paystackEnabled,
                    $paystackCurrency ?: null,
                    $tenantId,
                ]);
            } else {
                $stmt = $this->db->prepare(
                    'UPDATE ecommerce_settings SET default_store_id = ?, currency = ?, tax_rate = ? WHERE tenant_id = ?'
                );
                $stmt->execute([$storeId ?: null, $currency, $taxRate, $tenantId]);
            }
            return;
        }

        if ($hasPaystackCols) {
            $stmt = $this->db->prepare(
                'INSERT INTO ecommerce_settings
                 (tenant_id, default_store_id, currency, tax_rate, paystack_public_key, paystack_secret_key, paystack_enabled, paystack_currency)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $stmt->execute([
                $tenantId,
                $storeId ?: null,
                $currency,
                $taxRate,
                $paystackPublic ?: null,
                $paystackSecret ?: null,
                $paystackEnabled,
                $paystackCurrency ?: null,
            ]);
            return;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO ecommerce_settings (tenant_id, default_store_id, currency, tax_rate) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$tenantId, $storeId ?: null, $currency, $taxRate]);
    }

    /** @return array<int, array<string, mixed>> */
    public function listStores(int $tenantId): array
    {
        if (!$this->hasColumn('stores', 'tenant_id')) {
            $stmt = $this->db->query('SELECT id, name, currency FROM stores WHERE deleted_at IS NULL ORDER BY name ASC');
            return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        $stmt = $this->db->prepare(
            'SELECT id, name, currency FROM stores WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY name ASC'
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public static function slugify(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
        return trim($text, '-') ?: 'item';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function webOrderSqlCondition(string $alias = ''): string
    {
        $prefix = $alias !== '' ? $alias . '.' : '';
        if ($this->hasColumn('sales', 'channel')) {
            return "({$prefix}channel = 'web' OR {$prefix}receipt_no LIKE 'WEB-%')";
        }

        return "{$prefix}receipt_no LIKE 'WEB-%'";
    }
}

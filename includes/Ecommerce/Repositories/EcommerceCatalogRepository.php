<?php
declare(strict_types=1);

/**
 * Read-only catalog queries for tenant storefront.
 */
final class EcommerceCatalogRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function defaultStoreId(int $tenantId): int
    {
        $stmt = $this->db->prepare(
            'SELECT id FROM stores WHERE tenant_id = ? AND deleted_at IS NULL ORDER BY id ASC LIMIT 1'
        );
        $stmt->execute([$tenantId]);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    /** @return array<int, array<string, mixed>> */
    public function listStores(int $tenantId): array
    {
        $where = ['deleted_at IS NULL'];
        $params = [];

        if ($this->hasColumn('stores', 'tenant_id')) {
            $where[] = 'tenant_id = ?';
            $params[] = $tenantId;
        }
        if ($this->hasColumn('stores', 'is_active')) {
            $where[] = 'is_active = 1';
        }

        $sql = 'SELECT id, name, location, currency, code FROM stores WHERE '
            . implode(' AND ', $where)
            . ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    public function isStoreForTenant(int $tenantId, int $storeId): bool
    {
        if ($storeId <= 0) {
            return false;
        }

        foreach ($this->listStores($tenantId) as $store) {
            if ((int) ($store['id'] ?? 0) === $storeId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Pick the active storefront branch from request, session, or defaults.
     *
     * @param array<int, array<string, mixed>> $stores
     */
    public function pickStoreId(int $tenantId, int $defaultStoreId, array $stores, ?int $requestedStoreId, ?int $sessionStoreId): int
    {
        $allowed = array_values(array_filter(array_map(
            static fn(array $store): int => (int) ($store['id'] ?? 0),
            $stores
        )));

        if ($allowed === []) {
            return max(0, $defaultStoreId);
        }

        if ($requestedStoreId !== null && $requestedStoreId > 0 && in_array($requestedStoreId, $allowed, true)) {
            return $requestedStoreId;
        }

        if ($sessionStoreId !== null && $sessionStoreId > 0 && in_array($sessionStoreId, $allowed, true)) {
            return $sessionStoreId;
        }

        if ($defaultStoreId > 0 && in_array($defaultStoreId, $allowed, true)) {
            return $defaultStoreId;
        }

        return $allowed[0];
    }

    /** @return array<string, mixed>|null */
    public function getSettings(int $tenantId): ?array
    {
        if (!$this->tableExists('ecommerce_settings')) {
            return null;
        }
        $stmt = $this->db->prepare('SELECT * FROM ecommerce_settings WHERE tenant_id = ? LIMIT 1');
        $stmt->execute([$tenantId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listProducts(int $tenantId, int $storeId, array $filters = [], int $limit = 24, int $offset = 0): array
    {
        $where = ['p.deleted_at IS NULL', 'p.store_id = ?'];
        $params = [$storeId];

        if ($this->hasColumn('products', 'is_online')) {
            $where[] = 'p.is_online = 1';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'p.category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['brand_id'])) {
            $where[] = 'p.brand_id = ?';
            $params[] = (int) $filters['brand_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }

        $sql = 'SELECT p.*, c.name AS category_name'
            . ($this->tableExists('brands') ? ', b.name AS brand_name, b.slug AS brand_slug' : '')
            . ' FROM products p'
            . ' LEFT JOIN categories c ON c.id = p.category_id'
            . ($this->tableExists('brands') ? ' LEFT JOIN brands b ON b.id = p.brand_id' : '')
            . ' WHERE ' . implode(' AND ', $where)
            . ' ORDER BY p.updated_at DESC LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset;

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countProducts(int $tenantId, int $storeId, array $filters = []): int
    {
        $where = ['deleted_at IS NULL', 'store_id = ?'];
        $params = [$storeId];
        if ($this->hasColumn('products', 'is_online')) {
            $where[] = 'is_online = 1';
        }
        if (!empty($filters['category_id'])) {
            $where[] = 'category_id = ?';
            $params[] = (int) $filters['category_id'];
        }
        if (!empty($filters['brand_id'])) {
            $where[] = 'brand_id = ?';
            $params[] = (int) $filters['brand_id'];
        }
        if (!empty($filters['q'])) {
            $where[] = '(name LIKE ? OR sku LIKE ? OR barcode LIKE ?)';
            $q = '%' . $filters['q'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM products WHERE ' . implode(' AND ', $where));
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function getProduct(int $storeId, int $productId): ?array
    {
        $sql = 'SELECT p.*, c.name AS category_name'
            . ($this->tableExists('brands') ? ', b.name AS brand_name, b.slug AS brand_slug' : '')
            . ' FROM products p'
            . ' LEFT JOIN categories c ON c.id = p.category_id'
            . ($this->tableExists('brands') ? ' LEFT JOIN brands b ON b.id = p.brand_id' : '')
            . ' WHERE p.id = ? AND p.store_id = ? AND p.deleted_at IS NULL';
        if ($this->hasColumn('products', 'is_online')) {
            $sql .= ' AND p.is_online = 1';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$productId, $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function getProductBySlug(int $storeId, string $slug): ?array
    {
        if (preg_match('/^p-(\d+)$/', $slug, $m)) {
            return $this->getProduct($storeId, (int) $m[1]);
        }

        if (!$this->hasColumn('products', 'slug')) {
            return null;
        }

        $sql = 'SELECT p.*, c.name AS category_name'
            . ($this->tableExists('brands') ? ', b.name AS brand_name, b.slug AS brand_slug' : '')
            . ' FROM products p'
            . ' LEFT JOIN categories c ON c.id = p.category_id'
            . ($this->tableExists('brands') ? ' LEFT JOIN brands b ON b.id = p.brand_id' : '')
            . ' WHERE p.slug = ? AND p.store_id = ? AND p.deleted_at IS NULL';
        if ($this->hasColumn('products', 'is_online')) {
            $sql .= ' AND p.is_online = 1';
        }
        $sql .= ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$slug, $storeId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /**
     * Resolve a storefront product from ?slug= or ?id= query params.
     *
     * @return array<string, mixed>|null
     */
    public function resolveProduct(int $storeId, string $slug, int $productId): ?array
    {
        $slug = trim($slug);
        if ($slug !== '') {
            $bySlug = $this->getProductBySlug($storeId, $slug);
            if ($bySlug) {
                return $bySlug;
            }
        }
        if ($productId > 0) {
            return $this->getProduct($storeId, $productId);
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listRelatedProducts(int $tenantId, int $storeId, int $productId, ?int $categoryId, int $limit = 4): array
    {
        if ($storeId <= 0 || $productId <= 0) {
            return [];
        }

        $filters = [];
        if ($categoryId !== null && $categoryId > 0) {
            $filters['category_id'] = $categoryId;
        }

        $items = $this->listProducts($tenantId, $storeId, $filters, $limit + 1, 0);
        $related = [];
        foreach ($items as $item) {
            if ((int) ($item['id'] ?? 0) === $productId) {
                continue;
            }
            $related[] = $item;
            if (count($related) >= $limit) {
                break;
            }
        }

        return $related;
    }

    /** @return array<int, array<string, mixed>> */
    public function listCategories(int $storeId): array
    {
        if ($storeId <= 0) {
            return [];
        }

        $productJoin = [
            'p.category_id = c.id',
            'p.store_id = ?',
            'p.deleted_at IS NULL',
        ];
        $params = [$storeId];

        if ($this->hasColumn('products', 'is_online')) {
            $productJoin[] = 'p.is_online = 1';
        }

        $categoryStoreSql = '';
        if ($this->hasColumn('categories', 'store_id')) {
            $categoryStoreSql = ' AND c.store_id = ?';
            $params[] = $storeId;
        }

        $sql = 'SELECT c.*, COUNT(DISTINCT p.id) AS product_count'
            . ' FROM categories c'
            . ' INNER JOIN products p ON ' . implode(' AND ', $productJoin)
            . ' WHERE c.deleted_at IS NULL' . $categoryStoreSql
            . ' GROUP BY c.id'
            . ' HAVING product_count > 0'
            . ' ORDER BY c.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['product_count'] = (int) ($row['product_count'] ?? 0);
            if (isset($row['store_id'])) {
                $row['store_id'] = (int) $row['store_id'];
            }
        }
        unset($row);

        return $rows;
    }

    /** @return array<int, array<string, mixed>> */
    public function listBrands(int $tenantId, int $storeId): array
    {
        if ($storeId <= 0 || !$this->tableExists('brands') || !$this->hasColumn('products', 'brand_id')) {
            return [];
        }

        $productJoin = [
            'p.brand_id = b.id',
            'p.store_id = ?',
            'p.deleted_at IS NULL',
        ];
        $params = [$storeId];

        if ($this->hasColumn('products', 'is_online')) {
            $productJoin[] = 'p.is_online = 1';
        }

        $brandStoreSql = '';
        if ($this->hasColumn('brands', 'store_id')) {
            $brandStoreSql = ' AND (b.store_id IS NULL OR b.store_id = ?)';
        }

        $params[] = $tenantId;
        if ($brandStoreSql !== '') {
            $params[] = $storeId;
        }

        $sql = 'SELECT b.*, COUNT(DISTINCT p.id) AS product_count'
            . ' FROM brands b'
            . ' INNER JOIN products p ON ' . implode(' AND ', $productJoin)
            . ' WHERE b.tenant_id = ?' . $brandStoreSql
            . ' GROUP BY b.id'
            . ' HAVING product_count > 0'
            . ' ORDER BY b.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($rows as &$row) {
            $row['id'] = (int) ($row['id'] ?? 0);
            $row['product_count'] = (int) ($row['product_count'] ?? 0);
            if (isset($row['store_id'])) {
                $row['store_id'] = $row['store_id'] !== null ? (int) $row['store_id'] : null;
            }
        }
        unset($row);

        return $rows;
    }

    /** @return array<string, mixed>|null */
    public function getBrand(int $tenantId, int $storeId, int $brandId): ?array
    {
        if ($brandId <= 0) {
            return null;
        }

        foreach ($this->listBrands($tenantId, $storeId) as $brand) {
            if ((int) ($brand['id'] ?? 0) === $brandId) {
                return $brand;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function getBrandBySlug(int $tenantId, int $storeId, string $slug): ?array
    {
        $slug = trim($slug);
        if ($slug === '') {
            return null;
        }

        foreach ($this->listBrands($tenantId, $storeId) as $brand) {
            if ((string) ($brand['slug'] ?? '') === $slug) {
                return $brand;
            }
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function resolveBrand(int $tenantId, int $storeId, string $slug, int $brandId): ?array
    {
        $slug = trim($slug);
        if ($slug !== '') {
            $bySlug = $this->getBrandBySlug($tenantId, $storeId, $slug);
            if ($bySlug) {
                return $bySlug;
            }
        }
        if ($brandId > 0) {
            return $this->getBrand($tenantId, $storeId, $brandId);
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function getCategory(int $storeId, int $categoryId): ?array
    {
        if ($categoryId <= 0) {
            return null;
        }

        foreach ($this->listCategories($storeId) as $category) {
            if ((int) ($category['id'] ?? 0) === $categoryId) {
                return $category;
            }
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listBlogPosts(int $tenantId, int $limit = 12, int $offset = 0): array
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_blog_posts WHERE tenant_id = ? AND is_published = 1'
            . ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC'
            . ' LIMIT ' . (int) $limit . ' OFFSET ' . (int) $offset
        );
        $stmt->execute([$tenantId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countBlogPosts(int $tenantId): int
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return 0;
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ecommerce_blog_posts WHERE tenant_id = ? AND is_published = 1'
        );
        $stmt->execute([$tenantId]);
        return (int) $stmt->fetchColumn();
    }

    /** @return array<string, mixed>|null */
    public function getBlogPost(int $tenantId, string $slug): ?array
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_blog_posts WHERE tenant_id = ? AND slug = ? AND is_published = 1 LIMIT 1'
        );
        $stmt->execute([$tenantId, $slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function getBlogPostById(int $tenantId, int $postId): ?array
    {
        if ($postId <= 0 || !$this->tableExists('ecommerce_blog_posts')) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_blog_posts WHERE tenant_id = ? AND id = ? AND is_published = 1 LIMIT 1'
        );
        $stmt->execute([$tenantId, $postId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    /** @return array<string, mixed>|null */
    public function resolveBlogPost(int $tenantId, string $slug, int $postId): ?array
    {
        $slug = trim($slug);
        if ($slug !== '') {
            $bySlug = $this->getBlogPost($tenantId, $slug);
            if ($bySlug) {
                return $bySlug;
            }
        }
        if ($postId > 0) {
            return $this->getBlogPostById($tenantId, $postId);
        }

        return null;
    }

    /** @return array<int, array<string, mixed>> */
    public function listRelatedBlogPosts(int $tenantId, string $excludeSlug, int $limit = 3): array
    {
        if (!$this->tableExists('ecommerce_blog_posts')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM ecommerce_blog_posts WHERE tenant_id = ? AND is_published = 1 AND slug != ?'
            . ' ORDER BY COALESCE(published_at, created_at) DESC, id DESC LIMIT ' . (int) $limit
        );
        $stmt->execute([$tenantId, $excludeSlug]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
}

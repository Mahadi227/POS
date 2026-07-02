<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';
require_once __DIR__ . '/../Api/ApiProblem.php';

final class ApiV2InventoryController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        if ($method !== 'GET' || ($path[1] ?? '') !== 'levels') {
            ApiProblem::notFound();
            return;
        }

        ApiV2Auth::requireScope($this->db, 'inventory:read');
        $this->stockLevels();
    }

    private function stockLevels(): void
    {
        $pg = ApiV2Auth::pagination(100, 200);
        [$tenantSql, $tenantParams] = TenantScope::sqlFilter($this->db, 'tenant_id', 'p');

        $storeId = isset($_GET['store_id']) ? (int) $_GET['store_id'] : 0;
        $storeSql = '';
        $storeParams = [];
        if ($storeId > 0) {
            $storeSql = ' AND p.store_id = ?';
            $storeParams[] = $storeId;
        }

        $countSql = 'SELECT COUNT(*) FROM products p WHERE 1=1';
        if ($this->hasColumn('products', 'deleted_at')) {
            $countSql .= ' AND p.deleted_at IS NULL';
        }
        $countSql .= $tenantSql . $storeSql;
        $stmt = $this->db->prepare($countSql);
        $stmt->execute(array_merge($tenantParams, $storeParams));
        $total = (int) $stmt->fetchColumn();

        $sql = 'SELECT p.id, p.name, p.sku, p.barcode, p.stock_quantity, p.store_id, p.price
                FROM products p WHERE 1=1';
        if ($this->hasColumn('products', 'deleted_at')) {
            $sql .= ' AND p.deleted_at IS NULL';
        }
        $sql .= $tenantSql . $storeSql . ' ORDER BY p.id DESC LIMIT ? OFFSET ?';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($tenantParams, $storeParams, [$pg['per_page'], $pg['offset']]));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('X-Total-Count: ' . $total);
        ApiV2Auth::jsonSuccess($rows, [
            'page' => $pg['page'],
            'per_page' => $pg['per_page'],
            'total' => $total,
        ]);
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
}

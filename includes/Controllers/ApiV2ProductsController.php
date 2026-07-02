<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';

final class ApiV2ProductsController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        if ($method !== 'GET') {
            ApiProblem::send(405, 'Method Not Allowed', 'GET only');
            return;
        }

        ApiV2Auth::requireScope($this->db, 'products:read');
        $barcode = $path[2] ?? null;
        if (($path[1] ?? '') === 'barcode' && $barcode) {
            $this->byBarcode((string) $barcode);
            return;
        }

        $this->listProducts();
    }

    private function listProducts(): void
    {
        $pg = ApiV2Auth::pagination();
        [$tenantSql, $tenantParams] = TenantScope::sqlFilter($this->db, 'tenant_id', 'p');

        $countSql = 'SELECT COUNT(*) FROM products p WHERE 1=1';
        if ($this->hasColumn('products', 'deleted_at')) {
            $countSql .= ' AND p.deleted_at IS NULL';
        }
        $countSql .= $tenantSql;
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($tenantParams);
        $total = (int) $stmt->fetchColumn();

        $sql = 'SELECT p.id, p.name, p.sku, p.barcode, p.price, p.stock_quantity, p.store_id
                FROM products p WHERE 1=1';
        if ($this->hasColumn('products', 'deleted_at')) {
            $sql .= ' AND p.deleted_at IS NULL';
        }
        $sql .= $tenantSql . ' ORDER BY p.id DESC LIMIT ? OFFSET ?';
        $stmt = $this->db->prepare($sql);
        $params = array_merge($tenantParams, [$pg['per_page'], $pg['offset']]);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        header('X-Total-Count: ' . $total);
        ApiV2Auth::jsonSuccess($rows, [
            'page' => $pg['page'],
            'per_page' => $pg['per_page'],
            'total' => $total,
        ]);
    }

    private function byBarcode(string $code): void
    {
        [$tenantSql, $tenantParams] = TenantScope::sqlFilter($this->db, 'tenant_id', 'p');
        $sql = 'SELECT p.id, p.name, p.sku, p.barcode, p.price, p.stock_quantity, p.store_id
                FROM products p WHERE p.barcode = ?';
        if ($this->hasColumn('products', 'deleted_at')) {
            $sql .= ' AND p.deleted_at IS NULL';
        }
        $sql .= $tenantSql . ' LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$code], $tenantParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            ApiProblem::notFound('Product not found');
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
}

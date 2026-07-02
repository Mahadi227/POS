<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Platform/TenantSchemaMigrator.php';
require_once __DIR__ . '/../Platform/TenantScope.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Api/ApiV2Auth.php';

final class ApiV2StoresController
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        TenantSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? '';

        if ($method === 'GET' && $action === '') {
            ApiV2Auth::requireScope($this->db, 'stores:read');
            $this->listStores();
            return;
        }
        if ($method === 'POST' && $action === 'switch') {
            ApiV2Auth::requireScope($this->db, 'stores:read');
            $this->switchStore();
            return;
        }

        ApiProblem::notFound();
    }

    private function listStores(): void
    {
        [$tenantSql, $tenantParams] = TenantScope::sqlFilter($this->db, 'tenant_id', 's');
        $sql = 'SELECT s.id, s.name, s.code, s.location, s.currency, s.is_active
                FROM stores s WHERE 1=1';
        if ($this->hasColumn('stores', 'deleted_at')) {
            $sql .= ' AND s.deleted_at IS NULL';
        }
        $sql .= $tenantSql . ' ORDER BY s.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($tenantParams);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        ApiV2Auth::jsonSuccess($rows, ['total' => count($rows)]);
    }

    private function switchStore(): void
    {
        $ctx = ApiV2Auth::authenticate($this->db);
        if ($ctx['auth_type'] === 'api_key') {
            ApiProblem::forbidden('Store switch requires JWT user authentication');
            return;
        }

        $data = json_decode(file_get_contents('php://input') ?: '', true) ?: [];
        $storeId = (int) ($data['store_id'] ?? 0);
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

        $_SESSION['active_store_id'] = $storeId;
        ApiV2Auth::jsonSuccess(['store_id' => $storeId, 'message' => 'Active store updated']);
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

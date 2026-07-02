<?php
declare(strict_types=1);

require_once __DIR__ . '/../TenantSchemaMigrator.php';

final class PlatformDashboardService
{
    private PDO $db;
    private TenantRepository $tenants;

    public function __construct(PDO $db, TenantRepository $tenants)
    {
        $this->db = $db;
        $this->tenants = $tenants;
    }

    public function summary(): array
    {
        $byStatus = $this->tenants->countByStatus();

        $storeCount = 0;
        $userCount = 0;
        if ($this->columnExists('stores', 'tenant_id')) {
            $storeCount = (int) $this->db->query(
                'SELECT COUNT(*) FROM stores WHERE deleted_at IS NULL'
            )->fetchColumn();
        }
        if ($this->columnExists('users', 'tenant_id')) {
            $userCount = (int) $this->db->query(
                'SELECT COUNT(*) FROM users WHERE deleted_at IS NULL'
            )->fetchColumn();
        }

        return [
            'tenants' => $byStatus,
            'stores_total' => $storeCount,
            'users_total' => $userCount,
            'schema_version' => TenantSchemaMigrator::VERSION,
        ];
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }
}

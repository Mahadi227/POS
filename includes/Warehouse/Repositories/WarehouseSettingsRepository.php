<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WarehouseSettingsSchema.php';

class WarehouseSettingsRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        WarehouseSettingsSchema::ensure($this->db);
    }

    public function getSettingsJson(int $warehouseId): ?array
    {
        $stmt = $this->db->prepare('SELECT settings FROM warehouse_settings WHERE warehouse_id = ? LIMIT 1');
        $stmt->execute([$warehouseId]);
        $raw = $stmt->fetchColumn();
        if ($raw === false) {
            return null;
        }
        $decoded = json_decode((string) $raw, true);
        return is_array($decoded) ? $decoded : null;
    }

    public function saveSettingsJson(int $warehouseId, array $settings, int $userId): void
    {
        $json = json_encode($settings, JSON_UNESCAPED_UNICODE);
        $stmt = $this->db->prepare(
            'INSERT INTO warehouse_settings (warehouse_id, settings, updated_by)
             VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE settings = VALUES(settings), updated_by = VALUES(updated_by), updated_at = NOW()'
        );
        $stmt->execute([$warehouseId, $json, $userId]);
    }

    public function deleteSettings(int $warehouseId): void
    {
        $stmt = $this->db->prepare('DELETE FROM warehouse_settings WHERE warehouse_id = ?');
        $stmt->execute([$warehouseId]);
    }

    public function logChange(
        int $warehouseId,
        ?int $userId,
        ?string $userName,
        string $key,
        ?string $oldValue,
        ?string $newValue
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO warehouse_settings_audit
                (warehouse_id, user_id, user_name, setting_key, old_value, new_value, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $warehouseId,
            $userId,
            $userName,
            $key,
            $oldValue,
            $newValue,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function listAudit(
        int $warehouseId,
        ?string $search,
        int $limit,
        int $offset
    ): array {
        $where = 'warehouse_id = ?';
        $params = [$warehouseId];
        if ($search) {
            $where .= ' AND (setting_key LIKE ? OR user_name LIKE ? OR old_value LIKE ? OR new_value LIKE ? OR ip_address LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 5, $like));
        }
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT a.*, u.name AS actor_name
                FROM warehouse_settings_audit a
                LEFT JOIN users u ON u.id = a.user_id
                WHERE {$where}
                ORDER BY a.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countAudit(int $warehouseId, ?string $search): int
    {
        $where = 'warehouse_id = ?';
        $params = [$warehouseId];
        if ($search) {
            $where .= ' AND (setting_key LIKE ? OR user_name LIKE ? OR old_value LIKE ? OR new_value LIKE ? OR ip_address LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 5, $like));
        }
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM warehouse_settings_audit WHERE {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function listManagers(?int $warehouseId = null): array
    {
        $sql = "SELECT u.id, u.name, u.email, u.warehouse_id
                FROM users u
                INNER JOIN roles r ON r.id = u.role_id
                WHERE u.deleted_at IS NULL AND u.status = 'active'
                  AND (LOWER(REPLACE(r.name, ' ', '_')) IN ('warehouse_manager', 'admin', 'super_admin', 'manager')
                       OR u.id IN (SELECT manager_id FROM warehouses WHERE manager_id IS NOT NULL AND deleted_at IS NULL))";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND (u.warehouse_id = ? OR u.warehouse_id IS NULL)';
            $params[] = $warehouseId;
        }
        $sql .= ' ORDER BY u.name ASC LIMIT 100';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

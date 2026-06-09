<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../ManagerAuth.php';

class AuditService
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        try {
            $db = Database::getInstance()->getConnection();
            $db->query('SELECT 1 FROM manager_audit_log LIMIT 1');
        } catch (Throwable $e) {
            return;
        }

        $stmt = Database::getInstance()->getConnection()->prepare(
            'INSERT INTO manager_audit_log (store_id, user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            StoreScope::activeStoreId(),
            ManagerAuth::currentUserId(),
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function recent(?int $storeId, int $limit = 100): array
    {
        try {
            $db = Database::getInstance()->getConnection();
            $db->query('SELECT 1 FROM manager_audit_log LIMIT 1');
        } catch (Throwable $e) {
            return [];
        }

        $sql = 'SELECT l.*, u.name AS user_name FROM manager_audit_log l JOIN users u ON u.id = l.user_id WHERE 1=1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . (int) $limit;
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

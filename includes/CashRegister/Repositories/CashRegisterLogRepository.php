<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashRegisterLogRepository
{
    public static function log(
        string $action,
        ?int $storeId = null,
        ?int $registerId = null,
        ?int $userId = null,
        ?string $entityType = null,
        ?int $entityId = null,
        ?array $details = null
    ): void {
        if (!CashRegisterSchema::ready()) {
            return;
        }
        $stmt = Database::getInstance()->getConnection()->prepare(
            "INSERT INTO cash_register_logs
                (store_id, register_id, user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $storeId,
            $registerId,
            $userId,
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public function list(?int $storeId, array $filters = []): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $limit = min(500, max(1, (int) ($filters['limit'] ?? 200)));
        $sql = "SELECT l.*, u.name AS user_name, r.name AS register_name, r.register_code,
                       s.name AS store_name
                FROM cash_register_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN cash_registers r ON r.id = l.register_id
                LEFT JOIN stores s ON s.id = l.store_id
                WHERE 1=1";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND DATE(l.created_at) >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND DATE(l.created_at) <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['action']) && $filters['action'] !== 'all') {
            $sql .= ' AND l.action = ?';
            $params[] = $filters['action'];
        }
        if (!empty($filters['q'])) {
            $like = '%' . $filters['q'] . '%';
            $sql .= ' AND (l.action LIKE ? OR u.name LIKE ? OR r.name LIKE ? OR r.register_code LIKE ? OR l.ip_address LIKE ? OR l.entity_type LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }
        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . $limit;
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * @return array<int, string>
     */
    public function distinctActions(?int $storeId): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $sql = 'SELECT DISTINCT action FROM cash_register_logs WHERE 1=1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY action ASC';
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return array_column($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [], 'action');
    }

    private const NOTIFY_ACTIONS = [
        'register_opened',
        'register_closed',
        'cash_difference',
        'large_refund',
        'large_withdrawal',
        'register_inactive',
        'transfer_requested',
        'session_opened',
        'session_closed',
    ];

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotifications(?int $storeId, ?string $since = null, int $limit = 40): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count(self::NOTIFY_ACTIONS), '?'));
        $sql = "SELECT l.*, u.name AS user_name, r.name AS register_name
                FROM cash_register_logs l
                LEFT JOIN users u ON u.id = l.user_id
                LEFT JOIN cash_registers r ON r.id = l.register_id
                WHERE l.action IN ({$placeholders})";
        $params = self::NOTIFY_ACTIONS;

        if ($storeId !== null) {
            $sql .= ' AND l.store_id = ?';
            $params[] = $storeId;
        }
        if ($since !== null && $since !== '') {
            $sql .= ' AND l.created_at > ?';
            $params[] = $since;
        }

        $sql .= ' ORDER BY l.created_at DESC LIMIT ' . (int) $limit;
        $stmt = Database::getInstance()->getConnection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

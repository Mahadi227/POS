<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashRegisterRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableExists(): bool
    {
        return CashRegisterSchema::ready();
    }

    public function list(?int $storeId, ?string $status = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $sql = "SELECT r.*, s.name AS store_name, u.name AS assigned_cashier,
                       (SELECT COUNT(*) FROM cash_register_sessions crs
                        WHERE crs.register_id = r.id AND crs.status = 'open') AS is_session_open,
                       (SELECT crs.id FROM cash_register_sessions crs
                        WHERE crs.register_id = r.id AND crs.status = 'open'
                        ORDER BY crs.opened_at DESC LIMIT 1) AS open_session_id
                FROM cash_registers r
                INNER JOIN stores s ON s.id = r.store_id
                LEFT JOIN users u ON u.id = r.assigned_user_id
                WHERE r.deleted_at IS NULL";
        $params = [];

        if ($storeId !== null) {
            $sql .= ' AND r.store_id = ?';
            $params[] = $storeId;
        }
        if ($status !== null && $status !== 'all') {
            $sql .= ' AND r.status = ?';
            $params[] = $status;
        }

        $sql .= ' ORDER BY r.store_id ASC, r.name ASC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT r.*, s.name AS store_name, u.name AS assigned_cashier,
                    (SELECT COUNT(*) FROM cash_register_sessions crs
                     WHERE crs.register_id = r.id AND crs.status = 'open') AS is_session_open,
                    (SELECT crs.id FROM cash_register_sessions crs
                     WHERE crs.register_id = r.id AND crs.status = 'open'
                     ORDER BY crs.opened_at DESC LIMIT 1) AS open_session_id
             FROM cash_registers r
             INNER JOIN stores s ON s.id = r.store_id
             LEFT JOIN users u ON u.id = r.assigned_user_id
             WHERE r.id = ? AND r.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cash_registers
                (store_id, register_code, name, assigned_user_id, status, opening_balance, current_balance, config)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['store_id'],
            (string) $data['register_code'],
            (string) $data['name'],
            !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            (string) ($data['status'] ?? 'active'),
            round((float) ($data['opening_balance'] ?? 0), 2),
            round((float) ($data['current_balance'] ?? $data['opening_balance'] ?? 0), 2),
            !empty($data['config']) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cash_registers
             SET name = ?, assigned_user_id = ?, status = ?, opening_balance = ?, config = ?, updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL"
        );
        return $stmt->execute([
            (string) $data['name'],
            !empty($data['assigned_user_id']) ? (int) $data['assigned_user_id'] : null,
            (string) ($data['status'] ?? 'active'),
            round((float) ($data['opening_balance'] ?? 0), 2),
            !empty($data['config']) ? json_encode($data['config'], JSON_UNESCAPED_UNICODE) : null,
            $id,
        ]);
    }

    public function softDelete(int $id): bool
    {
        $stmt = $this->db->prepare('UPDATE cash_registers SET deleted_at = NOW(), status = \'inactive\' WHERE id = ?');
        return $stmt->execute([$id]);
    }

    public function updateBalance(int $id, float $balance): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE cash_registers SET current_balance = ?, last_activity_at = NOW() WHERE id = ?'
        );
        return $stmt->execute([round($balance, 2), $id]);
    }

    public function countSummary(?int $storeId): array
    {
        if (!$this->tableExists()) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'open_sessions' => 0];
        }

        $sql = "SELECT
                    COUNT(*) AS total,
                    COALESCE(SUM(CASE WHEN r.status = 'active' THEN 1 ELSE 0 END), 0) AS active,
                    COALESCE(SUM(CASE WHEN r.status != 'active' THEN 1 ELSE 0 END), 0) AS inactive,
                    COALESCE(SUM(r.current_balance), 0) AS total_balance
                FROM cash_registers r
                WHERE r.deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND r.store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $openSql = 'SELECT COUNT(*) FROM cash_register_sessions WHERE status = \'open\'';
        $openParams = [];
        if ($storeId !== null) {
            $openSql .= ' AND store_id = ?';
            $openParams[] = $storeId;
        }
        $openStmt = $this->db->prepare($openSql);
        $openStmt->execute($openParams);

        return [
            'total'         => (int) ($row['total'] ?? 0),
            'active'        => (int) ($row['active'] ?? 0),
            'inactive'      => (int) ($row['inactive'] ?? 0),
            'total_balance' => round((float) ($row['total_balance'] ?? 0), 2),
            'open_sessions' => (int) $openStmt->fetchColumn(),
        ];
    }
}

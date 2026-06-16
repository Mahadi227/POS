<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashMovementRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cash_movements
                (store_id, register_id, session_id, movement_type, amount, balance_after,
                 reference_type, reference_id, payment_method, reason, created_by, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['store_id'],
            !empty($data['register_id']) ? (int) $data['register_id'] : null,
            !empty($data['session_id']) ? (int) $data['session_id'] : null,
            (string) $data['movement_type'],
            round((float) ($data['amount'] ?? 0), 2),
            isset($data['balance_after']) ? round((float) $data['balance_after'], 2) : null,
            $data['reference_type'] ?? null,
            !empty($data['reference_id']) ? (int) $data['reference_id'] : null,
            $data['payment_method'] ?? null,
            $data['reason'] ?? null,
            !empty($data['created_by']) ? (int) $data['created_by'] : null,
            (string) ($data['sync_status'] ?? 'synced'),
            $data['local_uuid'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function list(?int $storeId, array $filters = [], int $limit = 200): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }

        $sql = "SELECT m.*, r.name AS register_name, u.name AS created_by_name
                FROM cash_movements m
                LEFT JOIN cash_registers r ON r.id = m.register_id
                LEFT JOIN users u ON u.id = m.created_by
                WHERE 1=1";
        $params = [];

        if ($storeId !== null) {
            $sql .= ' AND m.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['register_id'])) {
            $sql .= ' AND m.register_id = ?';
            $params[] = (int) $filters['register_id'];
        }
        if (!empty($filters['movement_type']) && $filters['movement_type'] !== 'all') {
            $sql .= ' AND m.movement_type = ?';
            $params[] = (string) $filters['movement_type'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND m.created_at >= ?';
            $params[] = $filters['from'] . ' 00:00:00';
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND m.created_at <= ?';
            $params[] = $filters['to'] . ' 23:59:59';
        }

        $sql .= ' ORDER BY m.created_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function listPendingSync(int $limit = 100): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT * FROM cash_movements WHERE sync_status = 'pending' ORDER BY created_at ASC LIMIT ?"
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

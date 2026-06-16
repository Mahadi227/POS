<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';

class CashRegisterSessionRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function findOpenByRegister(int $registerId): ?array
    {
        if (!CashRegisterSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT crs.*, r.name AS register_name, u.name AS cashier_name
             FROM cash_register_sessions crs
             INNER JOIN cash_registers r ON r.id = crs.register_id
             INNER JOIN users u ON u.id = crs.user_id
             WHERE crs.register_id = ? AND crs.status = 'open'
             ORDER BY crs.opened_at DESC LIMIT 1"
        );
        $stmt->execute([$registerId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO cash_register_sessions
                (register_id, store_id, user_id, shift_type, status, opening_balance, opening_notes, opened_by, cashier_shift_id)
             VALUES (?, ?, ?, ?, 'open', ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['register_id'],
            (int) $data['store_id'],
            (int) $data['user_id'],
            (string) ($data['shift_type'] ?? 'morning'),
            round((float) ($data['opening_balance'] ?? 0), 2),
            $data['opening_notes'] ?? null,
            (int) ($data['opened_by'] ?? $data['user_id']),
            !empty($data['cashier_shift_id']) ? (int) $data['cashier_shift_id'] : null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function close(int $id, array $data): bool
    {
        $stmt = $this->db->prepare(
            "UPDATE cash_register_sessions
             SET status = 'closed', closed_at = NOW(), closing_balance = ?, expected_cash = ?,
                 counted_cash = ?, variance = ?, cash_sales = ?, card_sales = ?, mobile_sales = ?,
                 refunds = ?, expenses = ?, total_sales = ?, transaction_count = ?,
                 closing_notes = ?, closed_by = ?
             WHERE id = ? AND status = 'open'"
        );
        return $stmt->execute([
            round((float) ($data['closing_balance'] ?? 0), 2),
            round((float) ($data['expected_cash'] ?? 0), 2),
            round((float) ($data['counted_cash'] ?? 0), 2),
            round((float) ($data['variance'] ?? 0), 2),
            round((float) ($data['cash_sales'] ?? 0), 2),
            round((float) ($data['card_sales'] ?? 0), 2),
            round((float) ($data['mobile_sales'] ?? 0), 2),
            round((float) ($data['refunds'] ?? 0), 2),
            round((float) ($data['expenses'] ?? 0), 2),
            round((float) ($data['total_sales'] ?? 0), 2),
            (int) ($data['transaction_count'] ?? 0),
            $data['closing_notes'] ?? null,
            (int) ($data['closed_by'] ?? 0),
            $id,
        ]);
    }

    public function list(?int $storeId, ?string $status = null, int $limit = 100): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $sql = "SELECT crs.*, r.name AS register_name, r.register_code, s.name AS store_name,
                       u.name AS cashier_name, ou.name AS opened_by_name, cu.name AS closed_by_name
                FROM cash_register_sessions crs
                INNER JOIN cash_registers r ON r.id = crs.register_id
                INNER JOIN stores s ON s.id = crs.store_id
                INNER JOIN users u ON u.id = crs.user_id
                LEFT JOIN users ou ON ou.id = crs.opened_by
                LEFT JOIN users cu ON cu.id = crs.closed_by
                WHERE 1=1";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND crs.store_id = ?';
            $params[] = $storeId;
        }
        if ($status !== null && $status !== 'all') {
            $sql .= ' AND crs.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY crs.opened_at DESC LIMIT ' . (int) $limit;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        if (!CashRegisterSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT crs.*, r.name AS register_name, r.register_code, s.name AS store_name, u.name AS cashier_name
             FROM cash_register_sessions crs
             INNER JOIN cash_registers r ON r.id = crs.register_id
             INNER JOIN stores s ON s.id = crs.store_id
             INNER JOIN users u ON u.id = crs.user_id
             WHERE crs.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findOpenByShift(int $shiftId): ?array
    {
        if (!CashRegisterSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT crs.*, r.name AS register_name
             FROM cash_register_sessions crs
             INNER JOIN cash_registers r ON r.id = crs.register_id
             WHERE crs.cashier_shift_id = ? AND crs.status = 'open'
             ORDER BY crs.opened_at DESC LIMIT 1"
        );
        $stmt->execute([$shiftId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function listByRegister(int $registerId, int $limit = 20): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT crs.*, u.name AS cashier_name, ou.name AS opened_by_name, cu.name AS closed_by_name
             FROM cash_register_sessions crs
             INNER JOIN users u ON u.id = crs.user_id
             LEFT JOIN users ou ON ou.id = crs.opened_by
             LEFT JOIN users cu ON cu.id = crs.closed_by
             WHERE crs.register_id = ?
             ORDER BY crs.opened_at DESC
             LIMIT ?"
        );
        $stmt->execute([$registerId, $limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function incrementSale(int $id, float $amount, string $method): bool
    {
        $col = match ($method) {
            'card' => 'card_sales',
            'mobile_money' => 'mobile_sales',
            default => 'cash_sales',
        };
        $stmt = $this->db->prepare(
            "UPDATE cash_register_sessions
             SET total_sales = COALESCE(total_sales, 0) + ?,
                 {$col} = COALESCE({$col}, 0) + ?,
                 transaction_count = COALESCE(transaction_count, 0) + 1
             WHERE id = ? AND status = 'open'"
        );
        return $stmt->execute([$amount, $amount, $id]);
    }
}

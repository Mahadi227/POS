<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class BatchTrackingRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $warehouseId = null, ?string $status = null, ?string $search = null, int $days = 30): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $days = max(1, min(365, $days));
        $expiryScope = in_array($status, ['expiring_soon', 'at_risk', 'expired'], true);
        $sql = "SELECT b.*, p.name AS product_name, p.sku, w.name AS warehouse_name,
                       (b.quantity * b.unit_cost) AS stock_value,
                       DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
                FROM batch_tracking b
                INNER JOIN products p ON p.id = b.product_id
                INNER JOIN warehouses w ON w.id = b.warehouse_id
                WHERE 1=1";
        $params = [];
        if ($expiryScope) {
            $sql .= ' AND b.expiry_date IS NOT NULL';
        }
        if ($warehouseId) {
            $sql .= ' AND b.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status === 'expiring_soon') {
            $sql .= " AND b.status = 'active'
                      AND b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
                      AND b.expiry_date >= CURDATE()";
            $params[] = $days;
        } elseif ($status === 'at_risk') {
            $sql .= " AND b.status IN ('active', 'expired')
                      AND (b.status = 'expired' OR b.expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY))";
            $params[] = $days;
        } elseif ($status === 'expired') {
            $sql .= " AND (b.status = 'expired' OR (b.status = 'active' AND b.expiry_date < CURDATE()))";
        } elseif ($status && $status !== 'all') {
            $sql .= ' AND b.status = ?';
            $params[] = $status;
        }
        if ($search) {
            $sql .= ' AND (b.batch_number LIKE ? OR b.barcode LIKE ? OR b.serial_number LIKE ?
                      OR p.name LIKE ? OR p.sku LIKE ? OR w.name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 6, $like));
        }
        $sql .= ' ORDER BY b.expiry_date ASC, b.batch_number ASC LIMIT 200';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT b.*, p.name AS product_name, p.sku, p.barcode AS product_barcode,
                    w.name AS warehouse_name,
                    (b.quantity * b.unit_cost) AS stock_value,
                    DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
             FROM batch_tracking b
             INNER JOIN products p ON p.id = b.product_id
             INNER JOIN warehouses w ON w.id = b.warehouse_id
             WHERE b.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'active' => 0, 'expiring_soon' => 0, 'expired' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                       SUM(CASE WHEN status = 'active' AND expiry_date IS NOT NULL
                            AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS expiring_soon,
                       SUM(CASE WHEN status = 'expired' OR (expiry_date IS NOT NULL AND expiry_date < CURDATE()) THEN 1 ELSE 0 END) AS expired
                FROM batch_tracking WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'expiring_soon' => (int) ($row['expiring_soon'] ?? 0),
            'expired' => (int) ($row['expired'] ?? 0),
        ];
    }

    public function expirySummary(?int $warehouseId = null, int $days = 30): array
    {
        if (!WmsSchema::ready()) {
            return [
                'expiring_soon' => 0, 'past_expiry' => 0, 'units_at_risk' => 0, 'value_at_risk' => 0,
            ];
        }
        $days = max(1, min(365, $days));
        $sql = "SELECT
                    SUM(CASE WHEN status = 'active'
                        AND expiry_date >= CURDATE()
                        AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY) THEN 1 ELSE 0 END) AS expiring_soon,
                    SUM(CASE WHEN status = 'expired'
                        OR (status = 'active' AND expiry_date < CURDATE()) THEN 1 ELSE 0 END) AS past_expiry,
                    SUM(CASE WHEN status IN ('active', 'expired')
                        AND (status = 'expired' OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                        THEN quantity ELSE 0 END) AS units_at_risk,
                    SUM(CASE WHEN status IN ('active', 'expired')
                        AND (status = 'expired' OR expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY))
                        THEN (quantity * unit_cost) ELSE 0 END) AS value_at_risk
                FROM batch_tracking
                WHERE expiry_date IS NOT NULL";
        $params = [$days, $days, $days];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'expiring_soon' => (int) ($row['expiring_soon'] ?? 0),
            'past_expiry' => (int) ($row['past_expiry'] ?? 0),
            'units_at_risk' => (int) ($row['units_at_risk'] ?? 0),
            'value_at_risk' => round((float) ($row['value_at_risk'] ?? 0), 2),
        ];
    }

    public function updateStatus(int $id, string $status): bool
    {
        $allowed = ['active', 'expired', 'recalled', 'depleted'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $stmt = $this->db->prepare('UPDATE batch_tracking SET status = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            "INSERT INTO batch_tracking
                (warehouse_id, product_id, batch_number, barcode, serial_number, manufacturing_date, expiry_date, quantity, unit_cost, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['warehouse_id'],
            (int) $data['product_id'],
            (string) $data['batch_number'],
            $data['barcode'] ?? null,
            $data['serial_number'] ?? null,
            $data['manufacturing_date'] ?? null,
            $data['expiry_date'] ?? null,
            (int) ($data['quantity'] ?? 0),
            round((float) ($data['unit_cost'] ?? 0), 4),
            (string) ($data['status'] ?? 'active'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function countExpiringSoon(int $days = 30): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM batch_tracking WHERE status = 'active' AND expiry_date IS NOT NULL
             AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)"
        );
        $stmt->execute([$days]);
        return (int) $stmt->fetchColumn();
    }
}

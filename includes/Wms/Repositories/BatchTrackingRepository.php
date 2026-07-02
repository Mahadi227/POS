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

    public function list(
        ?int $warehouseId = null,
        ?string $status = null,
        ?string $search = null,
        int $days = 30,
        int $limit = 50,
        int $offset = 0,
        ?string $scope = null,
        ?string $strategy = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, $status, $search, $days, $scope);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $order = match ($scope) {
            'serial' => 'b.serial_number ASC, b.batch_number ASC',
            'expiry' => 'b.expiry_date ASC, b.batch_number ASC',
            'fifo' => $strategy === 'fifo'
                ? 'COALESCE(b.manufacturing_date, DATE(b.created_at)) ASC, b.created_at ASC, b.id ASC'
                : 'b.expiry_date IS NULL, b.expiry_date ASC, COALESCE(b.manufacturing_date, DATE(b.created_at)) ASC, b.id ASC',
            default => 'b.expiry_date ASC, b.batch_number ASC',
        };
        $sql = "SELECT b.*, p.name AS product_name, p.sku, w.name AS warehouse_name,
                       (b.quantity * b.unit_cost) AS stock_value,
                       DATEDIFF(b.expiry_date, CURDATE()) AS days_to_expiry
                FROM batch_tracking b
                {$joins}
                WHERE 1=1 {$where}
                ORDER BY {$order}
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(
        ?int $warehouseId = null,
        ?string $status = null,
        ?string $search = null,
        int $days = 30,
        ?string $scope = null
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, $status, $search, $days, $scope);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM batch_tracking b {$joins} WHERE 1=1 {$where}"
        );
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function statusBreakdown(
        ?int $warehouseId = null,
        ?string $search = null,
        int $days = 30,
        ?string $scope = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, null, $search, $days, $scope);
        $stmt = $this->db->prepare(
            "SELECT b.status, COUNT(*) AS cnt
             FROM batch_tracking b {$joins}
             WHERE 1=1 {$where}
             GROUP BY b.status
             ORDER BY cnt DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = array_map(static fn (array $r) => [
            'status' => (string) ($r['status'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $rows);
        if ($warehouseId || $search) {
            return $out;
        }
        $expiring = $scope === 'serial'
            ? ($this->serialSummary($warehouseId)['expiring_soon'] ?? 0)
            : ($this->summary(null)['expiring_soon'] ?? 0);
        if ($expiring > 0) {
            $out[] = ['status' => 'expiring_soon', 'count' => $expiring];
        }
        return $out;
    }

    public function serialSummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'active' => 0, 'inactive' => 0, 'products' => 0, 'expiring_soon' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                       SUM(CASE WHEN status IN ('recalled', 'depleted', 'expired') THEN 1 ELSE 0 END) AS inactive,
                       COUNT(DISTINCT product_id) AS products,
                       SUM(CASE WHEN status = 'active' AND expiry_date IS NOT NULL
                            AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                            AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS expiring_soon
                FROM batch_tracking
                WHERE serial_number IS NOT NULL AND TRIM(serial_number) <> ''";
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
            'inactive' => (int) ($row['inactive'] ?? 0),
            'products' => (int) ($row['products'] ?? 0),
            'expiring_soon' => (int) ($row['expiring_soon'] ?? 0),
        ];
    }

    public function expiryBreakdown(?int $warehouseId = null, int $days = 30, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $out = [];
        foreach (['expiring_soon', 'expired', 'at_risk'] as $filter) {
            $cnt = $this->count($warehouseId, $filter, $search, $days, 'expiry');
            if ($cnt > 0) {
                $out[] = ['status' => $filter, 'count' => $cnt];
            }
        }
        return $out;
    }

    public function fifoSummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['batches' => 0, 'units' => 0, 'with_expiry' => 0, 'expiring_7d' => 0];
        }
        $sql = "SELECT COUNT(*) AS batches,
                       COALESCE(SUM(quantity), 0) AS units,
                       SUM(CASE WHEN expiry_date IS NOT NULL THEN 1 ELSE 0 END) AS with_expiry,
                       SUM(CASE WHEN expiry_date IS NOT NULL
                            AND expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
                            AND expiry_date >= CURDATE() THEN 1 ELSE 0 END) AS expiring_7d
                FROM batch_tracking
                WHERE status = 'active' AND quantity > 0";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'batches' => (int) ($row['batches'] ?? 0),
            'units' => (int) ($row['units'] ?? 0),
            'with_expiry' => (int) ($row['with_expiry'] ?? 0),
            'expiring_7d' => (int) ($row['expiring_7d'] ?? 0),
        ];
    }

    public function fifoStrategyBreakdown(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $cnt = $this->count($warehouseId, 'active', $search, 30, 'fifo');
        if ($cnt <= 0) {
            return [];
        }
        return [
            ['status' => 'fefo', 'count' => $cnt],
            ['status' => 'fifo', 'count' => $cnt],
        ];
    }

    /** @return array{0: string, 1: array<int, mixed>, 2: string} */
    private function filterClause(
        ?int $warehouseId,
        ?string $status,
        ?string $search,
        int $days,
        ?string $scope = null
    ): array {
        $days = max(1, min(365, $days));
        $joins = 'INNER JOIN products p ON p.id = b.product_id
                  INNER JOIN warehouses w ON w.id = b.warehouse_id';
        $sql = '';
        $params = [];
        $expiryScope = in_array($status, ['expiring_soon', 'at_risk', 'expired'], true);
        if ($expiryScope) {
            $sql .= ' AND b.expiry_date IS NOT NULL';
        }
        if ($scope === 'serial') {
            $sql .= " AND b.serial_number IS NOT NULL AND TRIM(b.serial_number) <> ''";
        }
        if ($scope === 'expiry') {
            $sql .= ' AND b.expiry_date IS NOT NULL';
        }
        if ($scope === 'fifo') {
            $sql .= " AND b.status = 'active' AND b.quantity > 0";
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
        return [$sql, $params, $joins];
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

    public function expiryWarehouseBreakdown(
        ?int $warehouseId = null,
        int $days = 30,
        ?string $search = null,
        ?string $status = 'at_risk'
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, $status, $search, $days, 'expiry');
        $sql = "SELECT w.id AS warehouse_id, w.name AS label,
                       COUNT(*) AS count,
                       COALESCE(SUM(b.quantity * b.unit_cost), 0) AS value_at_risk
                FROM batch_tracking b
                {$joins}
                WHERE 1=1 {$where}
                GROUP BY w.id, w.name
                ORDER BY value_at_risk DESC
                LIMIT 12";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static function (array $row) {
            $row['value_at_risk'] = round((float) ($row['value_at_risk'] ?? 0), 2);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function expiryTrendChart(
        ?int $warehouseId = null,
        int $days = 30,
        ?string $search = null,
        ?string $status = 'at_risk'
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, $status, $search, $days, 'expiry');
        $sql = "SELECT DATE(b.expiry_date) AS d,
                       COUNT(*) AS batch_count,
                       COALESCE(SUM(b.quantity), 0) AS units,
                       COALESCE(SUM(b.quantity * b.unit_cost), 0) AS value_at_risk
                FROM batch_tracking b
                {$joins}
                WHERE 1=1 {$where}
                GROUP BY DATE(b.expiry_date)
                ORDER BY d ASC
                LIMIT 90";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static function (array $row) {
            $row['value_at_risk'] = round((float) ($row['value_at_risk'] ?? 0), 2);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function expiryUrgencyBreakdown(
        ?int $warehouseId = null,
        int $days = 30,
        ?string $search = null,
        ?string $status = 'at_risk'
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params, $joins] = $this->filterClause($warehouseId, $status, $search, $days, 'expiry');
        $sql = "SELECT
                    SUM(CASE WHEN b.status = 'expired' OR b.expiry_date < CURDATE() THEN 1 ELSE 0 END) AS expired,
                    SUM(CASE WHEN b.expiry_date >= CURDATE() AND DATEDIFF(b.expiry_date, CURDATE()) <= 7 THEN 1 ELSE 0 END) AS critical,
                    SUM(CASE WHEN DATEDIFF(b.expiry_date, CURDATE()) BETWEEN 8 AND 30 THEN 1 ELSE 0 END) AS warning,
                    SUM(CASE WHEN DATEDIFF(b.expiry_date, CURDATE()) > 30 THEN 1 ELSE 0 END) AS upcoming
                FROM batch_tracking b
                {$joins}
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            ['urgency' => 'expired', 'count' => (int) ($row['expired'] ?? 0)],
            ['urgency' => 'critical', 'count' => (int) ($row['critical'] ?? 0)],
            ['urgency' => 'warning', 'count' => (int) ($row['warning'] ?? 0)],
            ['urgency' => 'upcoming', 'count' => (int) ($row['upcoming'] ?? 0)],
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

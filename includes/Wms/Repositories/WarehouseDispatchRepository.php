<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseDispatchRepository
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
        int $limit = 150,
        int $offset = 0,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $dateFrom, $dateTo);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $order = $status === 'history' ? 'COALESCE(d.received_at, d.created_at) DESC' : 'd.created_at DESC';
        $sql = "SELECT d.*, w.name AS from_warehouse_name, s.name AS to_store_name, tw.name AS to_warehouse_name,
                       u.name AS created_by_name, ru.name AS received_by_name,
                       (SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                        FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id) AS total_value
                FROM warehouse_dispatches d
                INNER JOIN warehouses w ON w.id = d.from_warehouse_id
                LEFT JOIN stores s ON s.id = d.to_store_id
                LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
                LEFT JOIN users u ON u.id = d.created_by
                LEFT JOIN users ru ON ru.id = d.received_by
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
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $dateFrom, $dateTo);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM warehouse_dispatches d
             INNER JOIN warehouses w ON w.id = d.from_warehouse_id
             LEFT JOIN stores s ON s.id = d.to_store_id
             LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
             WHERE 1=1 {$where}"
        );
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function statusBreakdown(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $statusScope = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $statusScope, $search, $dateFrom, $dateTo);
        $stmt = $this->db->prepare(
            "SELECT d.status, COUNT(*) AS cnt
             FROM warehouse_dispatches d
             INNER JOIN warehouses w ON w.id = d.from_warehouse_id
             LEFT JOIN stores s ON s.id = d.to_store_id
             LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
             WHERE 1=1 {$where}
             GROUP BY d.status
             ORDER BY cnt DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn (array $r) => [
            'status' => (string) ($r['status'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $rows);
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT d.*, w.name AS from_warehouse_name, s.name AS to_store_name, tw.name AS to_warehouse_name,
                    u.name AS created_by_name, ru.name AS received_by_name,
                    (SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                     FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id) AS total_value
             FROM warehouse_dispatches d
             INNER JOIN warehouses w ON w.id = d.from_warehouse_id
             LEFT JOIN stores s ON s.id = d.to_store_id
             LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
             LEFT JOIN users u ON u.id = d.created_by
             LEFT JOIN users ru ON ru.id = d.received_by
             WHERE d.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            "SELECT di.*, p.name AS product_name, p.sku, bt.batch_number
             FROM warehouse_dispatch_items di
             INNER JOIN products p ON p.id = di.product_id
             LEFT JOIN batch_tracking bt ON bt.id = di.batch_id
             WHERE di.dispatch_id = ?
             ORDER BY p.name ASC"
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'outgoing' => 0, 'delivered' => 0, 'draft' => 0, 'total_value' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status IN ('picking','packed','dispatched','in_transit') THEN 1 ELSE 0 END) AS outgoing,
                       SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                       SUM(CASE WHEN status IN ('draft','picking','packed') THEN 1 ELSE 0 END) AS draft
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $valueSql = 'SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0) FROM warehouse_dispatch_items di
                     INNER JOIN warehouse_dispatches d ON d.id = di.dispatch_id WHERE 1=1';
        $valueParams = [];
        if ($warehouseId) {
            $valueSql .= ' AND d.from_warehouse_id = ?';
            $valueParams[] = $warehouseId;
        }
        $vStmt = $this->db->prepare($valueSql);
        $vStmt->execute($valueParams);

        return [
            'total' => (int) ($row['total'] ?? 0),
            'outgoing' => (int) ($row['outgoing'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
            'total_value' => round((float) ($vStmt->fetchColumn() ?: 0), 4),
        ];
    }

    public function create(array $data, array $items): int
    {
        $num = (string) ($data['dispatch_number'] ?? ('DSP-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_dispatches
                (dispatch_number, from_warehouse_id, to_store_id, to_warehouse_id, status, driver_name, vehicle_number,
                 delivery_date, total_items, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $num,
            (int) $data['from_warehouse_id'],
            $data['to_store_id'] ?? null,
            $data['to_warehouse_id'] ?? null,
            (string) ($data['status'] ?? 'draft'),
            $data['driver_name'] ?? null,
            $data['vehicle_number'] ?? null,
            $data['delivery_date'] ?? null,
            count($items),
            $data['notes'] ?? null,
            (int) $data['created_by'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            'INSERT INTO warehouse_dispatch_items (dispatch_id, product_id, batch_id, quantity, unit_cost) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                $id,
                (int) $item['product_id'],
                $item['batch_id'] ?? null,
                (int) ($item['quantity'] ?? 0),
                round((float) ($item['unit_cost'] ?? 0), 4),
            ]);
        }
        return $id;
    }

    public function updateStatus(int $id, string $status, ?int $receivedBy = null): bool
    {
        $extra = $status === 'delivered' ? ', received_at = NOW(), received_by = ?' : '';
        if ($extra) {
            $stmt = $this->db->prepare("UPDATE warehouse_dispatches SET status = ? {$extra} WHERE id = ?");
            return $stmt->execute([$status, $receivedBy, $id]);
        }
        $stmt = $this->db->prepare('UPDATE warehouse_dispatches SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function countOutgoing(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM warehouse_dispatches WHERE status IN ('dispatched','in_transit','picking','packed')");
        return (int) $stmt->fetchColumn();
    }

    public function packingSummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['queue' => 0, 'packed' => 0, 'total' => 0];
        }
        $sql = "SELECT
                    SUM(CASE WHEN status = 'picking' THEN 1 ELSE 0 END) AS queue,
                    SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) AS packed,
                    SUM(CASE WHEN status IN ('picking','packed') THEN 1 ELSE 0 END) AS total
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'queue' => (int) ($row['queue'] ?? 0),
            'packed' => (int) ($row['packed'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function pickingSummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['queue' => 0, 'progress' => 0, 'total' => 0];
        }
        $sql = "SELECT
                    SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS queue,
                    SUM(CASE WHEN status = 'picking' THEN 1 ELSE 0 END) AS progress,
                    SUM(CASE WHEN status IN ('draft','picking') THEN 1 ELSE 0 END) AS total
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'queue' => (int) ($row['queue'] ?? 0),
            'progress' => (int) ($row['progress'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function shippingSummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['ready' => 0, 'in_transit' => 0, 'total' => 0];
        }
        $sql = "SELECT
                    SUM(CASE WHEN status = 'packed' THEN 1 ELSE 0 END) AS ready,
                    SUM(CASE WHEN status IN ('dispatched','in_transit') THEN 1 ELSE 0 END) AS in_transit,
                    SUM(CASE WHEN status IN ('packed','dispatched','in_transit') THEN 1 ELSE 0 END) AS total
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'ready' => (int) ($row['ready'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function deliverySummary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['pending' => 0, 'delivered' => 0, 'delivered_today' => 0, 'total' => 0];
        }
        $sql = "SELECT
                    SUM(CASE WHEN status IN ('dispatched','in_transit') THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                    SUM(CASE WHEN status = 'delivered' AND DATE(received_at) = CURDATE() THEN 1 ELSE 0 END) AS delivered_today,
                    SUM(CASE WHEN status IN ('dispatched','in_transit','delivered') THEN 1 ELSE 0 END) AS total
                FROM warehouse_dispatches WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'delivered_today' => (int) ($row['delivered_today'] ?? 0),
            'total' => (int) ($row['total'] ?? 0),
        ];
    }

    public function historySummary(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'delivered' => 0, 'cancelled' => 0, 'in_transit' => 0, 'total_value' => 0, 'total_items' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'history', $search, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                       SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                       SUM(CASE WHEN d.status IN ('dispatched','in_transit') THEN 1 ELSE 0 END) AS in_transit,
                       COALESCE(SUM(CASE WHEN d.status = 'delivered' THEN d.total_items ELSE 0 END), 0) AS total_items,
                       COALESCE(SUM(CASE WHEN d.status = 'delivered' THEN (
                           SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                           FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id
                       ) ELSE 0 END), 0) AS total_value
                FROM warehouse_dispatches d
                INNER JOIN warehouses w ON w.id = d.from_warehouse_id
                LEFT JOIN stores s ON s.id = d.to_store_id
                LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_value' => round((float) ($row['total_value'] ?? 0), 4),
        ];
    }

    public function reportSummary(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (!WmsSchema::ready()) {
            return [
                'total' => 0, 'draft' => 0, 'in_transit' => 0, 'delivered' => 0,
                'cancelled' => 0, 'total_items' => 0, 'total_value' => 0,
            ];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN d.status IN ('draft','picking','packed') THEN 1 ELSE 0 END) AS draft,
                       SUM(CASE WHEN d.status IN ('dispatched','in_transit') THEN 1 ELSE 0 END) AS in_transit,
                       SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered,
                       SUM(CASE WHEN d.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                       COALESCE(SUM(d.total_items), 0) AS total_items,
                       COALESCE(SUM((
                           SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                           FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id
                       )), 0) AS total_value
                FROM warehouse_dispatches d
                INNER JOIN warehouses w ON w.id = d.from_warehouse_id
                LEFT JOIN stores s ON s.id = d.to_store_id
                LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'draft' => (int) ($row['draft'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'delivered' => (int) ($row['delivered'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_value' => round((float) ($row['total_value'] ?? 0), 4),
        ];
    }

    public function dispatchTrend(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $days = 30
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        $days = max(7, min(90, $days));
        [$where, $params] = $this->filterClause($warehouseId, null, $search, $dateFrom, $dateTo);
        $where .= " AND COALESCE(d.received_at, d.delivery_date, d.created_at) >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        $sql = "SELECT DATE(COALESCE(d.received_at, d.delivery_date, d.created_at)) AS d,
                       COUNT(*) AS dispatch_count,
                       COALESCE(SUM(d.total_items), 0) AS total_items,
                       COALESCE(SUM((
                           SELECT COALESCE(SUM(di.quantity * di.unit_cost), 0)
                           FROM warehouse_dispatch_items di WHERE di.dispatch_id = d.id
                       )), 0) AS total_value,
                       SUM(CASE WHEN d.status = 'delivered' THEN 1 ELSE 0 END) AS delivered
                FROM warehouse_dispatches d
                INNER JOIN warehouses w ON w.id = d.from_warehouse_id
                LEFT JOIN stores s ON s.id = d.to_store_id
                LEFT JOIN warehouses tw ON tw.id = d.to_warehouse_id
                WHERE 1=1 {$where}
                GROUP BY DATE(COALESCE(d.received_at, d.delivery_date, d.created_at))
                ORDER BY d ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function filterClause(
        ?int $warehouseId,
        ?string $status,
        ?string $search,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $sql = '';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND d.from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            if ($status === 'open') {
                $sql .= " AND d.status IN ('draft','picking','packed')";
            } elseif ($status === 'in_flight') {
                $sql .= " AND d.status IN ('dispatched','in_transit')";
            } elseif ($status === 'packing_active') {
                $sql .= " AND d.status IN ('picking','packed')";
            } elseif ($status === 'packing_queue') {
                $sql .= " AND d.status = 'picking'";
            } elseif ($status === 'packing_ready') {
                $sql .= " AND d.status = 'packed'";
            } elseif ($status === 'picking_active') {
                $sql .= " AND d.status IN ('draft','picking')";
            } elseif ($status === 'picking_queue') {
                $sql .= " AND d.status = 'draft'";
            } elseif ($status === 'picking_progress') {
                $sql .= " AND d.status = 'picking'";
            } elseif ($status === 'shipping_active') {
                $sql .= " AND d.status IN ('packed','dispatched','in_transit')";
            } elseif ($status === 'shipping_ready') {
                $sql .= " AND d.status = 'packed'";
            } elseif ($status === 'delivery_pending') {
                $sql .= " AND d.status IN ('dispatched','in_transit')";
            } elseif ($status === 'delivery_active') {
                $sql .= " AND d.status IN ('dispatched','in_transit','delivered')";
            } elseif ($status === 'delivery_done') {
                $sql .= " AND d.status = 'delivered'";
            } elseif ($status === 'history') {
                $sql .= " AND d.status IN ('delivered','cancelled','dispatched','in_transit')";
            } else {
                $sql .= ' AND d.status = ?';
                $params[] = $status;
            }
        }
        if ($search) {
            $like = '%' . $search . '%';
            $sql .= ' AND (d.dispatch_number LIKE ? OR d.driver_name LIKE ? OR d.vehicle_number LIKE ?
                      OR w.name LIKE ? OR s.name LIKE ? OR tw.name LIKE ?';
            $params = array_merge($params, array_fill(0, 6, $like));
            if (preg_match('/^DSP-(\d+)$/i', trim($search), $m)) {
                $sql .= ' OR d.id = ?';
                $params[] = (int) $m[1];
            } elseif (ctype_digit(trim($search))) {
                $sql .= ' OR d.id = ?';
                $params[] = (int) trim($search);
            }
            $sql .= ')';
        }
        if ($dateFrom) {
            $sql .= ' AND DATE(COALESCE(d.received_at, d.delivery_date, d.created_at)) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND DATE(COALESCE(d.received_at, d.delivery_date, d.created_at)) <= ?';
            $params[] = $dateTo;
        }
        return [$sql, $params];
    }
}

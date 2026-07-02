<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseTransferRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $direction = null,
        int $limit = 50,
        int $offset = 0,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $direction, $transferType, $storeId, $dateFrom, $dateTo);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $order = ($status === 'history' || in_array($status, ['completed', 'rejected', 'cancelled'], true))
            ? 'COALESCE(t.completed_at, t.approved_at, t.created_at) DESC'
            : 't.created_at DESC';
        $sql = "SELECT t.*, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name,
                       fs.name AS from_store_name, ts.name AS to_store_name,
                       ru.name AS requested_by_name, au.name AS approved_by_name,
                       (SELECT COUNT(*) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_items,
                       (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0)
                        FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                LEFT JOIN users au ON au.id = t.approved_by
                WHERE 1=1 {$where}
                ORDER BY {$order}
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(
        ?string $status = null,
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $direction = null,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $direction, $transferType, $storeId, $dateFrom, $dateTo);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN stores fs ON fs.id = t.from_store_id
             LEFT JOIN stores ts ON ts.id = t.to_store_id
             LEFT JOIN users ru ON ru.id = t.requested_by
             WHERE 1=1 {$where}"
        );
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    public function statusBreakdown(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $direction = null,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $direction, $transferType, $storeId, $dateFrom, $dateTo);
        $stmt = $this->db->prepare(
            "SELECT t.status, COUNT(*) AS cnt
             FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN stores fs ON fs.id = t.from_store_id
             LEFT JOIN stores ts ON ts.id = t.to_store_id
             LEFT JOIN users ru ON ru.id = t.requested_by
             WHERE 1=1 {$where}
             GROUP BY t.status
             ORDER BY cnt DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn (array $r) => [
            'status' => (string) ($r['status'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $rows);
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function filterClause(
        ?int $warehouseId,
        ?string $status,
        ?string $search,
        ?string $direction,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        $sql = '';
        $params = [];
        if ($transferType) {
            $sql .= ' AND t.transfer_type = ?';
            $params[] = $transferType;
        }
        if ($storeId) {
            $sql .= ' AND (t.from_store_id = ? OR t.to_store_id = ?)';
            $params[] = $storeId;
            $params[] = $storeId;
        }
        if ($direction === 'incoming') {
            $sql .= ' AND t.to_warehouse_id IS NOT NULL';
            if ($warehouseId) {
                $sql .= ' AND t.to_warehouse_id = ?';
                $params[] = $warehouseId;
            }
        } elseif ($direction === 'outgoing') {
            $sql .= ' AND t.from_warehouse_id IS NOT NULL';
            if ($warehouseId) {
                $sql .= ' AND t.from_warehouse_id = ?';
                $params[] = $warehouseId;
            }
        } elseif ($warehouseId) {
            $sql .= ' AND (t.from_warehouse_id = ? OR t.to_warehouse_id = ?)';
            $params[] = $warehouseId;
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            if ($status === 'incoming_active') {
                $sql .= " AND t.status IN ('approved','picking','in_transit','received')";
            } elseif ($status === 'incoming_pending') {
                $sql .= " AND t.status = 'approved'";
            } elseif ($status === 'outgoing_active') {
                $sql .= " AND t.status IN ('requested','approved','picking','in_transit')";
            } elseif ($status === 'outgoing_pending') {
                $sql .= " AND t.status = 'requested'";
            } elseif ($status === 'trf_active') {
                $sql .= " AND t.status IN ('requested','approved','picking','in_transit','received')";
            } elseif ($status === 'trf_pending') {
                $sql .= " AND t.status = 'requested'";
            } elseif ($status === 'btr_active') {
                $sql .= " AND t.status IN ('requested','approved','picking','in_transit','received')";
            } elseif ($status === 'btr_pending') {
                $sql .= " AND t.status = 'requested'";
            } elseif ($status === 'history') {
                $sql .= " AND t.status IN ('completed','rejected','cancelled')";
            } else {
                $sql .= ' AND t.status = ?';
                $params[] = $status;
            }
        }
        if ($search) {
            $like = '%' . $search . '%';
            $sql .= ' AND (t.transfer_number LIKE ? OR t.reason LIKE ? OR fw.name LIKE ? OR tw.name LIKE ?
                      OR fs.name LIKE ? OR ts.name LIKE ? OR ru.name LIKE ?';
            $params = array_merge($params, array_fill(0, 7, $like));
            if (preg_match('/^WT-(\d+)$/i', trim($search), $m)) {
                $sql .= ' OR t.id = ?';
                $params[] = (int) $m[1];
            } elseif (ctype_digit(trim($search))) {
                $sql .= ' OR t.id = ?';
                $params[] = (int) trim($search);
            }
            $sql .= ')';
        }
        if ($dateFrom) {
            $sql .= ' AND DATE(COALESCE(t.completed_at, t.approved_at, t.created_at)) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $sql .= ' AND DATE(COALESCE(t.completed_at, t.approved_at, t.created_at)) <= ?';
            $params[] = $dateTo;
        }
        return [$sql, $params];
    }

    public function create(array $data, array $items): int
    {
        $num = (string) ($data['transfer_number'] ?? ('WT-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_transfers
                (transfer_number, transfer_type, from_warehouse_id, to_warehouse_id, from_store_id, to_store_id,
                 status, reason, requested_by, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $num,
            (string) $data['transfer_type'],
            $data['from_warehouse_id'] ?? null,
            $data['to_warehouse_id'] ?? null,
            $data['from_store_id'] ?? null,
            $data['to_store_id'] ?? null,
            (string) ($data['status'] ?? 'requested'),
            $data['reason'] ?? null,
            (int) $data['requested_by'],
            (string) ($data['sync_status'] ?? 'synced'),
            $data['local_uuid'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            'INSERT INTO warehouse_transfer_items (transfer_id, product_id, batch_id, quantity_requested, unit_cost) VALUES (?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $itemStmt->execute([
                $id,
                (int) $item['product_id'],
                $item['batch_id'] ?? null,
                (int) ($item['quantity'] ?? $item['quantity_requested'] ?? 0),
                round((float) ($item['unit_cost'] ?? 0), 4),
            ]);
        }
        return $id;
    }

    public function findById(int $id): ?array
    {
        if (!WmsSchema::ready()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT t.*, fw.name AS from_warehouse_name, tw.name AS to_warehouse_name,
                    fs.name AS from_store_name, ts.name AS to_store_name,
                    ru.name AS requested_by_name, au.name AS approved_by_name, rcv.name AS received_by_name,
                    (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0)
                     FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id) AS total_value
             FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN stores fs ON fs.id = t.from_store_id
             LEFT JOIN stores ts ON ts.id = t.to_store_id
             LEFT JOIN users ru ON ru.id = t.requested_by
             LEFT JOIN users au ON au.id = t.approved_by
             LEFT JOIN users rcv ON rcv.id = t.received_by
             WHERE t.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            'SELECT ti.*, p.name AS product_name, p.sku, bt.batch_number
             FROM warehouse_transfer_items ti
             INNER JOIN products p ON p.id = ti.product_id
             LEFT JOIN batch_tracking bt ON bt.id = ti.batch_id
             WHERE ti.transfer_id = ?
             ORDER BY p.name ASC'
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'requested' => 0, 'in_progress' => 0, 'completed' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status = 'requested' THEN 1 ELSE 0 END) AS requested,
                       SUM(CASE WHEN status IN ('approved','picking','in_transit','received') THEN 1 ELSE 0 END) AS in_progress,
                       SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM warehouse_transfers WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
            $params[] = $warehouseId;
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'requested' => (int) ($row['requested'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }

    public function branchSummary(?int $storeId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'requested' => 0, 'in_progress' => 0, 'completed' => 0];
        }
        [$where, $params] = $this->filterClause(null, null, $search, null, 'branch_to_branch', $storeId);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN t.status = 'requested' THEN 1 ELSE 0 END) AS requested,
                       SUM(CASE WHEN t.status IN ('approved','picking','in_transit','received') THEN 1 ELSE 0 END) AS in_progress,
                       SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'requested' => (int) ($row['requested'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }

    public function approvalSummary(?int $warehouseId = null, ?string $search = null, ?string $transferType = null): array
    {
        if (!WmsSchema::ready()) {
            return ['pending' => 0, 'warehouse' => 0, 'branch' => 0, 'total_value' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'requested', $search, null, $transferType, null);
        $sql = "SELECT COUNT(*) AS pending,
                       SUM(CASE WHEN t.transfer_type IN ('warehouse_to_warehouse','warehouse_to_store','store_to_warehouse') THEN 1 ELSE 0 END) AS warehouse,
                       SUM(CASE WHEN t.transfer_type = 'branch_to_branch' THEN 1 ELSE 0 END) AS branch,
                       COALESCE(SUM(
                           (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0)
                            FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                       ), 0) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'warehouse' => (int) ($row['warehouse'] ?? 0),
            'branch' => (int) ($row['branch'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    public function approvalTypeBreakdown(?int $warehouseId = null, ?string $search = null, ?string $transferType = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'requested', $search, null, $transferType, null);
        $stmt = $this->db->prepare(
            "SELECT t.transfer_type, COUNT(*) AS cnt
             FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             LEFT JOIN stores fs ON fs.id = t.from_store_id
             LEFT JOIN stores ts ON ts.id = t.to_store_id
             LEFT JOIN users ru ON ru.id = t.requested_by
             WHERE 1=1 {$where}
             GROUP BY t.transfer_type
             ORDER BY cnt DESC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn (array $r) => [
            'type' => (string) ($r['transfer_type'] ?? ''),
            'count' => (int) ($r['cnt'] ?? 0),
        ], $rows);
    }

    public function historySummary(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null
    ): array {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'completed' => 0, 'rejected' => 0, 'cancelled' => 0, 'total_items' => 0, 'total_value' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'history', $search, null, $transferType, null, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN t.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                       SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                       SUM(CASE WHEN t.status = 'completed' THEN
                           (SELECT COALESCE(SUM(ti.quantity_requested), 0) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                           ELSE 0 END) AS total_items,
                       SUM(CASE WHEN t.status = 'completed' THEN
                           (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                           ELSE 0 END) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    public function reportSummary(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null,
        ?string $direction = null
    ): array {
        if (!WmsSchema::ready()) {
            return [
                'total' => 0, 'requested' => 0, 'in_progress' => 0, 'completed' => 0,
                'rejected' => 0, 'cancelled' => 0, 'total_items' => 0, 'total_value' => 0,
            ];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search, $direction, $transferType, null, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN t.status = 'requested' THEN 1 ELSE 0 END) AS requested,
                       SUM(CASE WHEN t.status IN ('approved','picking','in_transit','received') THEN 1 ELSE 0 END) AS in_progress,
                       SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN t.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                       SUM(CASE WHEN t.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelled,
                       COALESCE(SUM(
                           (SELECT COALESCE(SUM(ti.quantity_requested), 0) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                       ), 0) AS total_items,
                       COALESCE(SUM(
                           (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                       ), 0) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'requested' => (int) ($row['requested'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'cancelled' => (int) ($row['cancelled'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
            'total_value' => round((float) ($row['total_value'] ?? 0), 4),
        ];
    }

    public function transferTrend(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null,
        ?string $direction = null,
        int $days = 30
    ): array {
        if (!WmsSchema::ready()) {
            return [];
        }
        $days = max(7, min(90, $days));
        [$where, $params] = $this->filterClause($warehouseId, null, $search, $direction, $transferType, null, $dateFrom, $dateTo);
        $where .= " AND COALESCE(t.completed_at, t.approved_at, t.created_at) >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        $sql = "SELECT DATE(COALESCE(t.completed_at, t.approved_at, t.created_at)) AS d,
                       COUNT(*) AS transfer_count,
                       SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       COALESCE(SUM(
                           (SELECT COALESCE(SUM(ti.quantity_requested * ti.unit_cost), 0) FROM warehouse_transfer_items ti WHERE ti.transfer_id = t.id)
                       ), 0) AS total_value
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}
                GROUP BY DATE(COALESCE(t.completed_at, t.approved_at, t.created_at))
                ORDER BY d ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function incomingSummary(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['pending' => 0, 'in_transit' => 0, 'completed' => 0, 'active' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search, 'incoming');
        $sql = "SELECT
                    SUM(CASE WHEN t.status = 'approved' THEN 1 ELSE 0 END) AS pending,
                    SUM(CASE WHEN t.status IN ('in_transit','picking','received') THEN 1 ELSE 0 END) AS in_transit,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN t.status IN ('approved','in_transit','picking','received') THEN 1 ELSE 0 END) AS active
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'in_transit' => (int) ($row['in_transit'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    public function outgoingSummary(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['requested' => 0, 'in_progress' => 0, 'completed' => 0, 'active' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search, 'outgoing');
        $sql = "SELECT
                    SUM(CASE WHEN t.status = 'requested' THEN 1 ELSE 0 END) AS requested,
                    SUM(CASE WHEN t.status IN ('approved','picking','in_transit') THEN 1 ELSE 0 END) AS in_progress,
                    SUM(CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN t.status IN ('requested','approved','picking','in_transit') THEN 1 ELSE 0 END) AS active
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                LEFT JOIN users ru ON ru.id = t.requested_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'requested' => (int) ($row['requested'] ?? 0),
            'in_progress' => (int) ($row['in_progress'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
        ];
    }

    public function updateStatus(int $id, string $status, ?int $userId = null, string $field = 'approved_by'): bool
    {
        $allowed = ['approved_by', 'received_by'];
        if (!in_array($field, $allowed, true)) {
            $field = 'approved_by';
        }
        $extra = $status === 'approved' ? ', approved_at = NOW()' : ($status === 'completed' ? ', completed_at = NOW()' : '');
        $stmt = $this->db->prepare("UPDATE warehouse_transfers SET status = ?, {$field} = ? {$extra} WHERE id = ?");
        return $stmt->execute([$status, $userId, $id]);
    }

    public function countPending(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM warehouse_transfers WHERE status IN ('requested','approved','in_transit')");
        return (int) $stmt->fetchColumn();
    }
}

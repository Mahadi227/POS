<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WmsSyncMonitorService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function ready(): bool
    {
        return WmsSchema::ready() && $this->hasSyncColumn('goods_receipts');
    }

    /** @return array{stats: array<string, int>, chart: array<string, mixed>} */
    public function dashboard(?int $storeId): array
    {
        if (!$this->ready()) {
            return [
                'stats' => $this->emptyStats(),
                'chart' => ['labels' => [], 'synced' => [], 'conflicts' => []],
            ];
        }

        return [
            'stats' => $this->computeStats($storeId),
            'chart' => $this->activityChart($storeId, 7),
        ];
    }

    public function listWarehouses(?int $storeId): array
    {
        if (!$this->ready()) {
            return [];
        }

        $sql = "SELECT w.id, w.warehouse_code, w.name, w.status, s.name AS store_name
                FROM warehouses w
                LEFT JOIN stores s ON s.id = w.store_id
                WHERE w.deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND (w.store_id = ? OR w.store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY w.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function (array $row) {
            $id = (int) $row['id'];
            return [
                'id' => $id,
                'code' => (string) ($row['warehouse_code'] ?? ''),
                'name' => (string) ($row['name'] ?? ''),
                'status' => (string) ($row['status'] ?? ''),
                'store_name' => $row['store_name'] ?? null,
                'pending' => $this->countForWarehouse($id, 'pending'),
                'conflicts' => $this->countForWarehouse($id, 'conflict'),
                'synced_offline' => $this->countSyncedOfflineForWarehouse($id),
            ];
        }, $rows);
    }

    public function listItems(?int $storeId, string $status, ?int $warehouseId = null): array
    {
        if (!$this->ready()) {
            return [];
        }

        $status = in_array($status, ['pending', 'conflict'], true) ? $status : 'pending';
        $items = array_merge(
            $this->fetchReceipts($storeId, $status, $warehouseId),
            $this->fetchTransfers($storeId, $status, $warehouseId),
            $this->fetchMovements($storeId, $status, $warehouseId)
        );

        usort($items, static fn (array $a, array $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($items, 0, 200);
    }

    public function resolveItem(int $id, string $entity, string $action): array
    {
        if (!$this->ready()) {
            return ['status' => 'error', 'message' => 'WMS module not ready'];
        }

        $map = [
            'receipt' => 'goods_receipts',
            'transfer' => 'warehouse_transfers',
            'movement' => 'warehouse_stock_movements',
        ];
        if (!isset($map[$entity])) {
            return ['status' => 'error', 'message' => 'Invalid entity'];
        }

        $newStatus = $action === 'dismiss' ? 'synced' : 'pending';
        $table = $map[$entity];
        $stmt = $this->db->prepare("UPDATE {$table} SET sync_status = ? WHERE id = ? LIMIT 1");
        $stmt->execute([$newStatus, $id]);

        if ($stmt->rowCount() === 0) {
            return ['status' => 'error', 'message' => 'Item not found'];
        }

        return ['status' => 'success', 'message' => $newStatus === 'pending' ? 'Queued for retry' : 'Marked as resolved'];
    }

    private function emptyStats(): array
    {
        return [
            'pending' => 0,
            'conflicts' => 0,
            'synced_today' => 0,
            'warehouses_with_issues' => 0,
            'total_warehouses' => 0,
        ];
    }

    private function computeStats(?int $storeId): array
    {
        $pending = $this->countAll($storeId, 'pending');
        $conflicts = $this->countAll($storeId, 'conflict');
        $warehouses = $this->listWarehouses($storeId);

        return [
            'pending' => $pending,
            'conflicts' => $conflicts,
            'synced_today' => $this->countSyncedToday($storeId),
            'warehouses_with_issues' => count(array_filter($warehouses, static fn (array $w) => ($w['pending'] ?? 0) > 0 || ($w['conflicts'] ?? 0) > 0)),
            'total_warehouses' => count($warehouses),
        ];
    }

    private function countAll(?int $storeId, string $status): int
    {
        return $this->countReceipts($storeId, $status)
            + $this->countTransfers($storeId, $status)
            + $this->countMovements($storeId, $status);
    }

    private function countForWarehouse(int $warehouseId, string $status): int
    {
        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts WHERE warehouse_id = ? AND sync_status = ?",
            [$warehouseId, $status]
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers
             WHERE sync_status = ? AND (from_warehouse_id = ? OR to_warehouse_id = ?)",
            [$status, $warehouseId, $warehouseId]
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements WHERE warehouse_id = ? AND sync_status = ?",
            [$warehouseId, $status]
        );
    }

    private function countSyncedOfflineForWarehouse(int $warehouseId): int
    {
        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts WHERE warehouse_id = ? AND sync_status = 'synced' AND local_uuid IS NOT NULL",
            [$warehouseId]
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers
             WHERE sync_status = 'synced' AND local_uuid IS NOT NULL
               AND (from_warehouse_id = ? OR to_warehouse_id = ?)",
            [$warehouseId, $warehouseId]
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements WHERE warehouse_id = ? AND sync_status = 'synced' AND local_uuid IS NOT NULL",
            [$warehouseId]
        );
    }

    private function countReceipts(?int $storeId, string $status): int
    {
        [$scope, $params] = $this->receiptStoreScope($storeId);
        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             WHERE g.sync_status = ?{$scope}",
            array_merge([$status], $params)
        );
    }

    private function countTransfers(?int $storeId, string $status): int
    {
        [$scope, $params] = $this->transferStoreScope($storeId);
        return $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             WHERE t.sync_status = ?{$scope}",
            array_merge([$status], $params)
        );
    }

    private function countMovements(?int $storeId, string $status): int
    {
        [$scope, $params] = $this->movementStoreScope($storeId);
        return $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements m
             INNER JOIN warehouses w ON w.id = m.warehouse_id
             WHERE m.sync_status = ?{$scope}",
            array_merge([$status], $params)
        );
    }

    private function countSyncedToday(?int $storeId): int
    {
        [$rScope, $rParams] = $this->receiptStoreScope($storeId, 'w');
        [$tScope, $tParams] = $this->transferStoreScope($storeId);
        [$mScope, $mParams] = $this->movementStoreScope($storeId, 'w');

        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             WHERE g.sync_status = 'synced' AND g.local_uuid IS NOT NULL AND DATE(g.received_at) = CURDATE(){$rScope}",
            $rParams
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             WHERE t.sync_status = 'synced' AND t.local_uuid IS NOT NULL AND DATE(t.created_at) = CURDATE(){$tScope}",
            $tParams
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements m
             INNER JOIN warehouses w ON w.id = m.warehouse_id
             WHERE m.sync_status = 'synced' AND m.local_uuid IS NOT NULL AND DATE(m.created_at) = CURDATE(){$mScope}",
            $mParams
        );
    }

    /** @return array{labels: string[], synced: int[], conflicts: int[]} */
    private function activityChart(?int $storeId, int $days): array
    {
        $labels = [];
        $synced = [];
        $conflicts = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', strtotime("-{$i} days"));
            $labels[] = date('M j', strtotime($date));
            $synced[] = $this->countSyncedOnDate($storeId, $date);
            $conflicts[] = $this->countConflictsOnDate($storeId, $date);
        }

        return compact('labels', 'synced', 'conflicts');
    }

    private function countSyncedOnDate(?int $storeId, string $date): int
    {
        [$rScope, $rParams] = $this->receiptStoreScope($storeId, 'w');
        [$tScope, $tParams] = $this->transferStoreScope($storeId);
        [$mScope, $mParams] = $this->movementStoreScope($storeId, 'w');

        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             WHERE g.sync_status = 'synced' AND g.local_uuid IS NOT NULL AND DATE(g.received_at) = ?{$rScope}",
            array_merge([$date], $rParams)
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             WHERE t.sync_status = 'synced' AND t.local_uuid IS NOT NULL AND DATE(t.created_at) = ?{$tScope}",
            array_merge([$date], $tParams)
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements m
             INNER JOIN warehouses w ON w.id = m.warehouse_id
             WHERE m.sync_status = 'synced' AND m.local_uuid IS NOT NULL AND DATE(m.created_at) = ?{$mScope}",
            array_merge([$date], $mParams)
        );
    }

    private function countConflictsOnDate(?int $storeId, string $date): int
    {
        [$rScope, $rParams] = $this->receiptStoreScope($storeId, 'w');
        [$tScope, $tParams] = $this->transferStoreScope($storeId);
        [$mScope, $mParams] = $this->movementStoreScope($storeId, 'w');

        return $this->scalar(
            "SELECT COUNT(*) FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             WHERE g.sync_status = 'conflict' AND DATE(g.received_at) = ?{$rScope}",
            array_merge([$date], $rParams)
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_transfers t
             LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
             LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
             WHERE t.sync_status = 'conflict' AND DATE(t.created_at) = ?{$tScope}",
            array_merge([$date], $tParams)
        ) + $this->scalar(
            "SELECT COUNT(*) FROM warehouse_stock_movements m
             INNER JOIN warehouses w ON w.id = m.warehouse_id
             WHERE m.sync_status = 'conflict' AND DATE(m.created_at) = ?{$mScope}",
            array_merge([$date], $mParams)
        );
    }

    private function fetchReceipts(?int $storeId, string $status, ?int $warehouseId): array
    {
        [$scope, $params] = $this->receiptStoreScope($storeId);
        $sql = "SELECT g.id, g.grn_number AS reference, g.sync_status, g.local_uuid, g.received_at AS created_at,
                       w.name AS warehouse_name, 'receipt' AS entity
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                WHERE g.sync_status = ?{$scope}";
        $bind = array_merge([$status], $params);
        if ($warehouseId) {
            $sql .= ' AND g.warehouse_id = ?';
            $bind[] = $warehouseId;
        }
        $sql .= ' ORDER BY g.received_at DESC LIMIT 80';
        return $this->mapItems($this->query($sql, $bind));
    }

    private function fetchTransfers(?int $storeId, string $status, ?int $warehouseId): array
    {
        [$scope, $params] = $this->transferStoreScope($storeId);
        $sql = "SELECT t.id, t.transfer_number AS reference, t.sync_status, t.local_uuid, t.created_at,
                       COALESCE(fw.name, tw.name, fs.name, ts.name) AS warehouse_name, 'transfer' AS entity
                FROM warehouse_transfers t
                LEFT JOIN warehouses fw ON fw.id = t.from_warehouse_id
                LEFT JOIN warehouses tw ON tw.id = t.to_warehouse_id
                LEFT JOIN stores fs ON fs.id = t.from_store_id
                LEFT JOIN stores ts ON ts.id = t.to_store_id
                WHERE t.sync_status = ?{$scope}";
        $bind = array_merge([$status], $params);
        if ($warehouseId) {
            $sql .= ' AND (t.from_warehouse_id = ? OR t.to_warehouse_id = ?)';
            $bind[] = $warehouseId;
            $bind[] = $warehouseId;
        }
        $sql .= ' ORDER BY t.created_at DESC LIMIT 80';
        return $this->mapItems($this->query($sql, $bind));
    }

    private function fetchMovements(?int $storeId, string $status, ?int $warehouseId): array
    {
        [$scope, $params] = $this->movementStoreScope($storeId);
        $sql = "SELECT m.id, CONCAT(m.movement_type, ' #', m.id) AS reference, m.sync_status, m.local_uuid, m.created_at,
                       w.name AS warehouse_name, 'movement' AS entity, m.notes
                FROM warehouse_stock_movements m
                INNER JOIN warehouses w ON w.id = m.warehouse_id
                WHERE m.sync_status = ?{$scope}";
        $bind = array_merge([$status], $params);
        if ($warehouseId) {
            $sql .= ' AND m.warehouse_id = ?';
            $bind[] = $warehouseId;
        }
        $sql .= ' ORDER BY m.created_at DESC LIMIT 80';
        return $this->mapItems($this->query($sql, $bind));
    }

    private function mapItems(array $rows): array
    {
        return array_map(static function (array $row) {
            return [
                'id' => (int) $row['id'],
                'entity' => (string) ($row['entity'] ?? ''),
                'reference' => (string) ($row['reference'] ?? ''),
                'status' => (string) ($row['sync_status'] ?? ''),
                'local_uuid' => $row['local_uuid'] ?? null,
                'warehouse_name' => $row['warehouse_name'] ?? null,
                'created_at' => $row['created_at'] ?? null,
                'notes' => $row['notes'] ?? null,
            ];
        }, $rows);
    }

    private function receiptStoreScope(?int $storeId, string $alias = 'w'): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        return [" AND ({$alias}.store_id = ? OR {$alias}.store_id IS NULL)", [$storeId]];
    }

    private function transferStoreScope(?int $storeId): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        return [
            ' AND (fw.store_id = ? OR tw.store_id = ? OR fw.store_id IS NULL OR tw.store_id IS NULL)',
            [$storeId, $storeId],
        ];
    }

    private function movementStoreScope(?int $storeId, string $alias = 'w'): array
    {
        return $this->receiptStoreScope($storeId, $alias);
    }

    private function query(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function scalar(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function hasSyncColumn(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, 'sync_status']);
        return (bool) $stmt->fetchColumn();
    }
}

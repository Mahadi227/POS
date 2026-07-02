<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../WmsSchema.php';

class GoodsReceiptRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $warehouseId = null, ?string $status = null, ?string $search = null, int $limit = 150, int $offset = 0, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $dateFrom, $dateTo);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT g.*, w.name AS warehouse_name, s.name AS supplier_name, u.name AS received_by_name,
                       po.po_number
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                LEFT JOIN purchase_orders po ON po.id = g.purchase_order_id
                WHERE 1=1 {$where}
                ORDER BY g.received_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(?int $warehouseId = null, ?string $status = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        if (!WmsSchema::ready()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*)
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array{status: string, count: int}> */
    public function statusBreakdown(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search);
        $sql = "SELECT g.status, COUNT(*) AS count
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}
                GROUP BY g.status
                ORDER BY count DESC, g.status ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $row): array => [
            'status' => (string) ($row['status'] ?? ''),
            'count' => (int) ($row['count'] ?? 0),
        ], $rows);
    }

    public function summary(?int $warehouseId = null, ?string $status = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'pending' => 0, 'completed' => 0, 'total_value' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN g.status IN ('pending','inspecting','accepted') THEN 1 ELSE 0 END) AS pending,
                       SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN g.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                       SUM(CASE WHEN g.status = 'inspecting' THEN 1 ELSE 0 END) AS inspecting,
                       COALESCE(SUM(g.total_value), 0) AS total_value,
                       COALESCE(SUM(g.total_items), 0) AS total_items
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'inspecting' => (int) ($row['inspecting'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
        ];
    }

    public function receiptTrend(?int $warehouseId = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, int $days = 30): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $days = max(7, min(90, $days));
        [$where, $params] = $this->filterClause($warehouseId, null, $search, $dateFrom, $dateTo);
        $where .= " AND g.received_at >= DATE_SUB(CURDATE(), INTERVAL {$days} DAY)";
        $sql = "SELECT DATE(g.received_at) AS d,
                       COUNT(*) AS receipt_count,
                       COALESCE(SUM(g.total_items), 0) AS total_items,
                       COALESCE(SUM(g.total_value), 0) AS total_value,
                       SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) AS completed
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}
                GROUP BY DATE(g.received_at)
                ORDER BY d ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function incomingSummary(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'pending' => 0, 'inspecting' => 0, 'accepted' => 0, 'total_value' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'incoming', $search);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN g.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                       SUM(CASE WHEN g.status = 'inspecting' THEN 1 ELSE 0 END) AS inspecting,
                       SUM(CASE WHEN g.status = 'accepted' THEN 1 ELSE 0 END) AS accepted,
                       COALESCE(SUM(g.total_value), 0) AS total_value
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'pending' => (int) ($row['pending'] ?? 0),
            'inspecting' => (int) ($row['inspecting'] ?? 0),
            'accepted' => (int) ($row['accepted'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
        ];
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterClause(?int $warehouseId, ?string $status, ?string $search, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $where = '';
        $params = [];
        if ($warehouseId) {
            $where .= ' AND g.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status === 'incoming') {
            $where .= " AND g.status IN ('pending','inspecting','accepted')";
        } elseif ($status === 'inspection') {
            $where .= " AND g.status IN ('pending','inspecting')";
        } elseif ($status === 'history') {
            $where .= " AND g.status IN ('completed','rejected')";
        } elseif ($status && $status !== 'all') {
            $where .= ' AND g.status = ?';
            $params[] = $status;
        }
        if ($dateFrom) {
            $where .= ' AND DATE(g.received_at) >= ?';
            $params[] = $dateFrom;
        }
        if ($dateTo) {
            $where .= ' AND DATE(g.received_at) <= ?';
            $params[] = $dateTo;
        }
        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $where .= ' AND (g.grn_number LIKE ? OR w.name LIKE ? OR s.name LIKE ? OR u.name LIKE ? OR g.notes LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like);
        }
        if (!$warehouseId) {
            [$storeScope, $storeParams] = $this->storeScopeSql('w');
            $where .= $storeScope;
            $params = array_merge($params, $storeParams);
        }
        return [$where, $params];
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function storeScopeSql(string $alias = 'w'): array
    {
        if (StoreScope::isGlobalView()) {
            return ['', []];
        }
        $col = "{$alias}.store_id";
        $active = StoreScope::activeStoreId();
        if ($active !== null) {
            return [" AND ({$col} IS NULL OR {$col} = ?)", [$active]];
        }
        $allowed = StoreScope::accessibleStoreIds($this->db);
        if ($allowed === null || $allowed === []) {
            return ['', []];
        }
        if (count($allowed) === 1) {
            return [" AND ({$col} IS NULL OR {$col} = ?)", [$allowed[0]]];
        }
        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        return [" AND ({$col} IS NULL OR {$col} IN ({$placeholders}))", $allowed];
    }

    public function historySummary(?int $warehouseId = null, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'completed' => 0, 'rejected' => 0, 'total_value' => 0, 'total_items' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, 'history', $search, $dateFrom, $dateTo);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN g.status = 'completed' THEN 1 ELSE 0 END) AS completed,
                       SUM(CASE WHEN g.status = 'rejected' THEN 1 ELSE 0 END) AS rejected,
                       COALESCE(SUM(CASE WHEN g.status = 'completed' THEN g.total_value ELSE 0 END), 0) AS total_value,
                       COALESCE(SUM(CASE WHEN g.status = 'completed' THEN g.total_items ELSE 0 END), 0) AS total_items
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'rejected' => (int) ($row['rejected'] ?? 0),
            'total_value' => (float) ($row['total_value'] ?? 0),
            'total_items' => (int) ($row['total_items'] ?? 0),
        ];
    }

    public function create(array $data, array $items): int
    {
        $grn = (string) ($data['grn_number'] ?? ('GRN-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)))));
        $stmt = $this->db->prepare(
            "INSERT INTO goods_receipts
                (grn_number, warehouse_id, supplier_id, purchase_order_id, status, total_items, total_value,
                 received_by, notes, sync_status, local_uuid)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $grn,
            (int) $data['warehouse_id'],
            $data['supplier_id'] ?? null,
            $data['purchase_order_id'] ?? null,
            (string) ($data['status'] ?? 'pending'),
            count($items),
            round((float) ($data['total_value'] ?? 0), 2),
            (int) ($data['received_by'] ?? 0),
            $data['notes'] ?? null,
            (string) ($data['sync_status'] ?? 'synced'),
            $data['local_uuid'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            "INSERT INTO goods_receipt_items
                (goods_receipt_id, product_id, quantity_expected, quantity_received, unit_cost, batch_number, expiry_date, barcode, location_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        foreach ($items as $item) {
            $qty = (int) ($item['quantity_received'] ?? $item['quantity'] ?? 0);
            $itemStmt->execute([
                $id,
                (int) $item['product_id'],
                (int) ($item['quantity_expected'] ?? $qty),
                $qty,
                round((float) ($item['unit_cost'] ?? 0), 4),
                $item['batch_number'] ?? null,
                $item['expiry_date'] ?? null,
                $item['barcode'] ?? null,
                $item['location_id'] ?? null,
            ]);
        }
        return $id;
    }

    public function inspectionSummary(?int $warehouseId = null, ?string $search = null): array
    {
        if (!WmsSchema::ready()) {
            return ['pending' => 0, 'inspecting' => 0, 'passed_today' => 0, 'rejected_today' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search);
        $sql = "SELECT SUM(CASE WHEN g.status = 'pending' THEN 1 ELSE 0 END) AS pending,
                       SUM(CASE WHEN g.status = 'inspecting' THEN 1 ELSE 0 END) AS inspecting,
                       SUM(CASE WHEN g.inspection_status IN ('passed','partial') AND DATE(g.inspected_at) = CURDATE() THEN 1 ELSE 0 END) AS passed_today,
                       SUM(CASE WHEN g.inspection_status = 'failed' AND DATE(g.inspected_at) = CURDATE() THEN 1 ELSE 0 END) AS rejected_today
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'pending' => (int) ($row['pending'] ?? 0),
            'inspecting' => (int) ($row['inspecting'] ?? 0),
            'passed_today' => (int) ($row['passed_today'] ?? 0),
            'rejected_today' => (int) ($row['rejected_today'] ?? 0),
            'total' => (int) ($row['pending'] ?? 0) + (int) ($row['inspecting'] ?? 0),
        ];
    }

    public function saveInspectionItems(int $receiptId, array $items): bool
    {
        $stmt = $this->db->prepare(
            'UPDATE goods_receipt_items SET quantity_received = ?, quantity_damaged = ? WHERE id = ? AND goods_receipt_id = ?'
        );
        foreach ($items as $item) {
            $itemId = (int) ($item['id'] ?? 0);
            if ($itemId <= 0) {
                continue;
            }
            $received = max(0, (int) ($item['quantity_received'] ?? 0));
            $damaged = max(0, min($received, (int) ($item['quantity_damaged'] ?? 0)));
            $stmt->execute([$received, $damaged, $itemId, $receiptId]);
        }
        return true;
    }

    private function resolveInspectionStatus(int $receiptId): string
    {
        $stmt = $this->db->prepare('SELECT COALESCE(SUM(quantity_damaged), 0) FROM goods_receipt_items WHERE goods_receipt_id = ?');
        $stmt->execute([$receiptId]);
        return ((int) $stmt->fetchColumn()) > 0 ? 'partial' : 'passed';
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT g.*, w.name AS warehouse_name, s.name AS supplier_name, u.name AS received_by_name,
                    po.po_number
             FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             LEFT JOIN suppliers s ON s.id = g.supplier_id
             LEFT JOIN users u ON u.id = g.received_by
             LEFT JOIN purchase_orders po ON po.id = g.purchase_order_id
             WHERE g.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            'SELECT gi.*, p.name AS product_name, p.sku FROM goods_receipt_items gi
             INNER JOIN products p ON p.id = gi.product_id WHERE gi.goods_receipt_id = ?'
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): bool
    {
        if ($status === 'rejected') {
            $stmt = $this->db->prepare(
                "UPDATE goods_receipts SET status = ?, inspection_status = 'failed', inspected_at = NOW(), inspected_by = ? WHERE id = ?"
            );
            return $stmt->execute([$status, $userId, $id]);
        }
        if ($status === 'accepted' || $status === 'completed') {
            $inspection = $this->resolveInspectionStatus($id);
            $stmt = $this->db->prepare(
                "UPDATE goods_receipts SET status = ?, inspection_status = ?, inspected_at = NOW(), inspected_by = ? WHERE id = ?"
            );
            return $stmt->execute([$status, $inspection, $userId, $id]);
        }
        if ($status === 'inspecting') {
            $stmt = $this->db->prepare("UPDATE goods_receipts SET status = ?, inspection_status = 'pending' WHERE id = ?");
            return $stmt->execute([$status, $id]);
        }
        $stmt = $this->db->prepare('UPDATE goods_receipts SET status = ? WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    public function countIncoming(): int
    {
        $stmt = $this->db->query("SELECT COUNT(*) FROM goods_receipts WHERE status IN ('pending','inspecting')");
        return (int) $stmt->fetchColumn();
    }
}

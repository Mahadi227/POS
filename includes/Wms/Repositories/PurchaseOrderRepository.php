<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class PurchaseOrderRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function tableReady(): bool
    {
        if (!WmsSchema::ready()) {
            return false;
        }
        try {
            $this->db->query('SELECT 1 FROM purchase_orders LIMIT 1');
            return true;
        } catch (PDOException $e) {
            return false;
        }
    }

    public function list(?int $warehouseId, ?string $status, ?string $search, int $limit = 50, int $offset = 0): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT po.*, w.name AS warehouse_name, s.name AS supplier_name,
                       u.name AS created_by_name, ua.name AS approved_by_name,
                       (SELECT COUNT(*) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS line_count,
                       (SELECT COALESCE(SUM(poi.quantity_ordered), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_qty_ordered,
                       (SELECT COALESCE(SUM(poi.quantity_received), 0) FROM purchase_order_items poi WHERE poi.purchase_order_id = po.id) AS total_qty_received
                FROM purchase_orders po
                INNER JOIN warehouses w ON w.id = po.warehouse_id
                INNER JOIN suppliers s ON s.id = po.supplier_id
                INNER JOIN users u ON u.id = po.created_by
                LEFT JOIN users ua ON ua.id = po.approved_by
                WHERE 1=1 {$where}
                ORDER BY po.created_at DESC
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(?int $warehouseId, ?string $status, ?string $search): int
    {
        if (!$this->tableReady()) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search);
        $sql = "SELECT COUNT(*)
                FROM purchase_orders po
                INNER JOIN warehouses w ON w.id = po.warehouse_id
                INNER JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return list<array{status: string, count: int}> */
    public function statusBreakdown(?int $warehouseId, ?string $search): array
    {
        if (!$this->tableReady()) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, null, $search);
        $sql = "SELECT po.status, COUNT(*) AS count
                FROM purchase_orders po
                INNER JOIN warehouses w ON w.id = po.warehouse_id
                INNER JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1 {$where}
                GROUP BY po.status
                ORDER BY count DESC, po.status ASC";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn(array $row): array => [
            'status' => (string) ($row['status'] ?? ''),
            'count' => (int) ($row['count'] ?? 0),
        ], $rows);
    }

    public function summary(?int $warehouseId, ?string $status, ?string $search): array
    {
        if (!$this->tableReady()) {
            return ['total' => 0, 'open' => 0, 'received' => 0, 'total_amount' => 0];
        }
        [$where, $params] = $this->filterClause($warehouseId, $status, $search);
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN po.status IN ('draft','pending','approved','partial') THEN 1 ELSE 0 END) AS open_count,
                       SUM(CASE WHEN po.status = 'received' THEN 1 ELSE 0 END) AS received,
                       COALESCE(SUM(po.total_amount), 0) AS total_amount
                FROM purchase_orders po
                INNER JOIN warehouses w ON w.id = po.warehouse_id
                INNER JOIN suppliers s ON s.id = po.supplier_id
                WHERE 1=1 {$where}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'open' => (int) ($row['open_count'] ?? 0),
            'received' => (int) ($row['received'] ?? 0),
            'total_amount' => round((float) ($row['total_amount'] ?? 0), 2),
        ];
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableReady()) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT po.*, w.name AS warehouse_name, s.name AS supplier_name,
                    u.name AS created_by_name, ua.name AS approved_by_name
             FROM purchase_orders po
             INNER JOIN warehouses w ON w.id = po.warehouse_id
             INNER JOIN suppliers s ON s.id = po.supplier_id
             INNER JOIN users u ON u.id = po.created_by
             LEFT JOIN users ua ON ua.id = po.approved_by
             WHERE po.id = ? LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['items'] = $this->itemsForPo($id);
        return $row;
    }

    /** @return list<array<string, mixed>> */
    public function itemsForPo(int $poId): array
    {
        $stmt = $this->db->prepare(
            "SELECT poi.*, p.name AS product_name, p.sku, p.barcode
             FROM purchase_order_items poi
             INNER JOIN products p ON p.id = poi.product_id
             WHERE poi.purchase_order_id = ?
             ORDER BY poi.id ASC"
        );
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function create(array $data, array $items): int
    {
        $poNumber = (string) ($data['po_number'] ?? $this->nextPoNumber());
        $total = 0.0;
        foreach ($items as $item) {
            $qty = (int) ($item['quantity_ordered'] ?? $item['quantity'] ?? 0);
            $cost = (float) ($item['unit_cost'] ?? 0);
            $total += $qty * $cost;
        }
        $stmt = $this->db->prepare(
            "INSERT INTO purchase_orders
                (po_number, supplier_id, warehouse_id, store_id, status, total_amount,
                 expected_date, notes, created_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            $poNumber,
            (int) $data['supplier_id'],
            (int) $data['warehouse_id'],
            $data['store_id'] ?? null,
            (string) ($data['status'] ?? 'draft'),
            round($total, 2),
            $data['expected_date'] ?? null,
            $data['notes'] ?? null,
            (int) $data['created_by'],
        ]);
        $id = (int) $this->db->lastInsertId();
        $itemStmt = $this->db->prepare(
            "INSERT INTO purchase_order_items
                (purchase_order_id, product_id, quantity_ordered, quantity_received, unit_cost, line_total)
             VALUES (?, ?, ?, 0, ?, ?)"
        );
        foreach ($items as $item) {
            $qty = (int) ($item['quantity_ordered'] ?? $item['quantity'] ?? 0);
            $cost = round((float) ($item['unit_cost'] ?? 0), 4);
            $lineTotal = round($qty * $cost, 2);
            $itemStmt->execute([$id, (int) $item['product_id'], $qty, $cost, $lineTotal]);
        }
        return $id;
    }

    public function updateStatus(int $id, string $status, ?int $userId = null): bool
    {
        if ($status === 'approved' && $userId) {
            $stmt = $this->db->prepare(
                'UPDATE purchase_orders SET status = ?, approved_by = ?, approved_at = NOW(), updated_at = NOW() WHERE id = ?'
            );
            return $stmt->execute([$status, $userId, $id]);
        }
        $stmt = $this->db->prepare('UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ?');
        return $stmt->execute([$status, $id]);
    }

    /** @param list<array<string, mixed>> $grnItems */
    public function syncFromGrn(int $poId, array $grnItems): void
    {
        if (!$this->tableReady() || $poId <= 0 || !$grnItems) {
            return;
        }
        $upd = $this->db->prepare(
            'UPDATE purchase_order_items
             SET quantity_received = LEAST(quantity_ordered, quantity_received + ?)
             WHERE purchase_order_id = ? AND product_id = ?'
        );
        foreach ($grnItems as $item) {
            $qty = (int) ($item['quantity_received'] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $upd->execute([$qty, $poId, (int) $item['product_id']]);
        }
        $this->recalculateStatus($poId);
    }

    public function recalculateStatus(int $poId): void
    {
        $stmt = $this->db->prepare(
            'SELECT COALESCE(SUM(quantity_ordered), 0) AS ordered,
                    COALESCE(SUM(quantity_received), 0) AS received
             FROM purchase_order_items WHERE purchase_order_id = ?'
        );
        $stmt->execute([$poId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $ordered = (int) ($row['ordered'] ?? 0);
        $received = (int) ($row['received'] ?? 0);
        if ($ordered <= 0) {
            return;
        }
        $status = $received >= $ordered ? 'received' : ($received > 0 ? 'partial' : null);
        if ($status) {
            $this->db->prepare('UPDATE purchase_orders SET status = ?, updated_at = NOW() WHERE id = ? AND status NOT IN (\'cancelled\', \'draft\')')
                ->execute([$status, $poId]);
        }
    }

    public function resolveSupplierId(?int $id, ?string $name): int
    {
        if ($id && $id > 0) {
            return $id;
        }
        $name = trim((string) $name);
        if ($name === '') {
            throw new InvalidArgumentException('Supplier required');
        }
        $stmt = $this->db->prepare('SELECT id FROM suppliers WHERE name = ? AND (deleted_at IS NULL OR deleted_at = \'0000-00-00 00:00:00\') LIMIT 1');
        $stmt->execute([$name]);
        $existing = $stmt->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }
        $ins = $this->db->prepare('INSERT INTO suppliers (name) VALUES (?)');
        $ins->execute([$name]);
        return (int) $this->db->lastInsertId();
    }

    public function nextPoNumber(): string
    {
        return 'PO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(3)));
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function filterClause(?int $warehouseId, ?string $status, ?string $search): array
    {
        $sql = '';
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND po.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            if ($status === 'open') {
                $sql .= " AND po.status IN ('draft','pending','approved','partial')";
            } else {
                $sql .= ' AND po.status = ?';
                $params[] = $status;
            }
        }
        if ($search) {
            $like = '%' . $search . '%';
            $sql .= ' AND (po.po_number LIKE ? OR s.name LIKE ? OR w.name LIKE ? OR po.notes LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }
        return [$sql, $params];
    }
}

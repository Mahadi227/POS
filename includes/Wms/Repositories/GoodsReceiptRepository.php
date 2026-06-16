<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class GoodsReceiptRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $warehouseId = null, ?string $status = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT g.*, w.name AS warehouse_name, s.name AS supplier_name, u.name AS received_by_name
                FROM goods_receipts g
                INNER JOIN warehouses w ON w.id = g.warehouse_id
                LEFT JOIN suppliers s ON s.id = g.supplier_id
                LEFT JOIN users u ON u.id = g.received_by
                WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND g.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            $sql .= ' AND g.status = ?';
            $params[] = $status;
        }
        $sql .= ' ORDER BY g.received_at DESC LIMIT 150';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT g.*, w.name AS warehouse_name, s.name AS supplier_name, u.name AS received_by_name
             FROM goods_receipts g
             INNER JOIN warehouses w ON w.id = g.warehouse_id
             LEFT JOIN suppliers s ON s.id = g.supplier_id
             LEFT JOIN users u ON u.id = g.received_by
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
        $extra = $status === 'accepted' || $status === 'completed' ? ', inspected_at = NOW(), inspected_by = ?' : '';
        if ($extra) {
            $stmt = $this->db->prepare("UPDATE goods_receipts SET status = ?, inspection_status = 'passed' {$extra} WHERE id = ?");
            return $stmt->execute([$status, $userId, $id]);
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

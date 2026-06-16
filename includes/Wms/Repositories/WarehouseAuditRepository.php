<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseAuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $warehouseId = null, ?string $status = null, ?string $search = null, ?string $auditType = null): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $sql = "SELECT a.*, w.name AS warehouse_name,
                       cu.name AS conducted_by_name, au.name AS approved_by_name,
                       (SELECT COUNT(*) FROM warehouse_audit_items ai WHERE ai.audit_id = a.id) AS total_items,
                       (SELECT COALESCE(SUM(ABS(ai.variance_qty)), 0)
                        FROM warehouse_audit_items ai WHERE ai.audit_id = a.id) AS total_variance_qty
                FROM warehouse_audits a
                INNER JOIN warehouses w ON w.id = a.warehouse_id
                LEFT JOIN users cu ON cu.id = a.conducted_by
                LEFT JOIN users au ON au.id = a.approved_by
                WHERE 1=1";
        $params = [];
        if ($warehouseId) {
            $sql .= ' AND a.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        if ($status && $status !== 'all') {
            $sql .= ' AND a.status = ?';
            $params[] = $status;
        }
        if ($auditType && $auditType !== 'all') {
            $sql .= ' AND a.audit_type = ?';
            $params[] = $auditType;
        }
        if ($search) {
            $sql .= ' AND (w.name LIKE ? OR a.notes LIKE ? OR cu.name LIKE ? OR au.name LIKE ?)';
            $like = '%' . $search . '%';
            $params = array_merge($params, array_fill(0, 4, $like));
        }
        $sql .= ' ORDER BY a.created_at DESC LIMIT 150';
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
            "SELECT a.*, w.name AS warehouse_name,
                    cu.name AS conducted_by_name, au.name AS approved_by_name
             FROM warehouse_audits a
             INNER JOIN warehouses w ON w.id = a.warehouse_id
             LEFT JOIN users cu ON cu.id = a.conducted_by
             LEFT JOIN users au ON au.id = a.approved_by
             WHERE a.id = ?
             LIMIT 1"
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $items = $this->db->prepare(
            'SELECT ai.*, p.name AS product_name, p.sku
             FROM warehouse_audit_items ai
             INNER JOIN products p ON p.id = ai.product_id
             WHERE ai.audit_id = ?
             ORDER BY p.name ASC'
        );
        $items->execute([$id]);
        $row['items'] = $items->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return $row;
    }

    public function summary(?int $warehouseId = null): array
    {
        if (!WmsSchema::ready()) {
            return ['total' => 0, 'open' => 0, 'with_variance' => 0, 'completed' => 0];
        }
        $sql = "SELECT COUNT(*) AS total,
                       SUM(CASE WHEN status IN ('draft','in_progress','pending_approval') THEN 1 ELSE 0 END) AS open,
                       SUM(CASE WHEN ABS(variance_value) > 0.0001 THEN 1 ELSE 0 END) AS with_variance,
                       SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) AS completed
                FROM warehouse_audits WHERE 1=1";
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
            'open' => (int) ($row['open'] ?? 0),
            'with_variance' => (int) ($row['with_variance'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
        ];
    }

    public function create(array $data, array $items): int
    {
        $totals = $this->computeTotals($items);
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_audits
                (warehouse_id, audit_type, status, expected_value, counted_value, variance_value, notes, conducted_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['warehouse_id'],
            (string) ($data['audit_type'] ?? 'cycle_count'),
            (string) ($data['status'] ?? 'draft'),
            $totals['expected_value'],
            $totals['counted_value'],
            $totals['variance_value'],
            $data['notes'] ?? null,
            $data['conducted_by'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $this->insertItems($id, $items);
        return $id;
    }

    public function updateStatus(int $id, string $status, ?int $userId = null, ?string $userField = null): bool
    {
        $allowed = ['draft', 'in_progress', 'pending_approval', 'approved', 'rejected'];
        if (!in_array($status, $allowed, true)) {
            return false;
        }
        $extra = '';
        $params = [$status];
        if ($status === 'in_progress') {
            $extra = ', started_at = COALESCE(started_at, NOW())';
        } elseif ($status === 'pending_approval') {
            $extra = ', completed_at = NOW()';
            if ($userId) {
                $extra .= ', conducted_by = ?';
                $params[] = $userId;
            }
        } elseif ($status === 'approved') {
            $extra = ', approved_by = ?, completed_at = COALESCE(completed_at, NOW())';
            $params[] = $userId;
        }
        $params[] = $id;
        $stmt = $this->db->prepare("UPDATE warehouse_audits SET status = ? {$extra} WHERE id = ?");
        return $stmt->execute($params);
    }

    private function computeTotals(array $items): array
    {
        $expected = 0.0;
        $counted = 0.0;
        foreach ($items as &$item) {
            $sys = (int) ($item['system_qty'] ?? 0);
            $cnt = (int) ($item['counted_qty'] ?? 0);
            $cost = round((float) ($item['unit_cost'] ?? 0), 4);
            $item['variance_qty'] = $cnt - $sys;
            $expected += $sys * $cost;
            $counted += $cnt * $cost;
        }
        unset($item);
        return [
            'expected_value' => round($expected, 4),
            'counted_value' => round($counted, 4),
            'variance_value' => round($counted - $expected, 4),
        ];
    }

    private function insertItems(int $auditId, array $items): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO warehouse_audit_items (audit_id, product_id, system_qty, counted_qty, variance_qty, unit_cost)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        foreach ($items as $item) {
            $sys = (int) ($item['system_qty'] ?? 0);
            $cnt = (int) ($item['counted_qty'] ?? 0);
            $stmt->execute([
                $auditId,
                (int) $item['product_id'],
                $sys,
                $cnt,
                $cnt - $sys,
                round((float) ($item['unit_cost'] ?? 0), 4),
            ]);
        }
    }
}

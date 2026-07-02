<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseCalendarService
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /** @return array{module_ready:bool,from:string,to:string,summary:array,events:list<array>,by_day:array<string,list<array>>} */
    public function month(?int $warehouseId, ?int $storeId, int $year, int $month, array $types = []): array
    {
        if (!WmsSchema::ready()) {
            return ['module_ready' => false, 'from' => '', 'to' => '', 'summary' => [], 'events' => [], 'by_day' => []];
        }

        $month = max(1, min(12, $month));
        $from = sprintf('%04d-%02d-01', $year, $month);
        $to = date('Y-m-t', strtotime($from));

        $allowed = ['task', 'receiving', 'dispatch', 'transfer', 'expiry', 'count'];
        $types = $types ? array_values(array_intersect($types, $allowed)) : $allowed;

        $events = [];
        foreach ($types as $type) {
            try {
                $chunk = match ($type) {
                    'task' => $this->taskEvents($warehouseId, $from, $to),
                    'receiving' => $this->receivingEvents($warehouseId, $from, $to),
                    'dispatch' => $this->dispatchEvents($warehouseId, $from, $to),
                    'transfer' => $this->transferEvents($warehouseId, $from, $to),
                    'expiry' => $this->expiryEvents($warehouseId, $storeId, $from, $to),
                    'count' => $this->countEvents($warehouseId, $from, $to),
                    default => [],
                };
                $events = array_merge($events, $chunk);
            } catch (Throwable $e) {
                error_log('WarehouseCalendarService::' . $type . ' — ' . $e->getMessage());
            }
        }

        usort($events, static fn ($a, $b) => [$a['date'], $a['sort']] <=> [$b['date'], $b['sort']]);

        $byDay = [];
        foreach ($events as $ev) {
            $byDay[$ev['date']][] = $ev;
        }

        return [
            'module_ready' => true,
            'from' => $from,
            'to' => $to,
            'year' => $year,
            'month' => $month,
            'summary' => $this->summarize($events),
            'events' => $events,
            'by_day' => $byDay,
        ];
    }

    private function summarize(array $events): array
    {
        $counts = ['task' => 0, 'receiving' => 0, 'dispatch' => 0, 'transfer' => 0, 'expiry' => 0, 'count' => 0, 'total' => count($events)];
        foreach ($events as $ev) {
            $t = $ev['type'] ?? 'task';
            if (isset($counts[$t])) {
                $counts[$t]++;
            }
        }
        return $counts;
    }

    private function taskEvents(?int $warehouseId, string $from, string $to): array
    {
        if (!$this->tableExists('warehouse_tasks')) {
            return [];
        }
        $sql = "SELECT t.id, t.task_type, t.title, t.status, t.priority, t.due_date, u.name AS assigned_name
                FROM warehouse_tasks t
                LEFT JOIN users u ON u.id = t.assigned_to
                WHERE t.due_date BETWEEN ? AND ? AND t.status NOT IN ('cancelled')";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND t.warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'task-' . $r['id'],
            'type' => 'task',
            'date' => $this->normalizeDate($r['due_date']),
            'title' => $r['title'],
            'subtitle' => $r['assigned_name'] ?? '',
            'status' => $r['status'],
            'priority' => $r['priority'],
            'task_type' => $r['task_type'],
            'href' => $this->taskHref($r['task_type']),
            'sort' => 1,
        ], $rows);
    }

    private function receivingEvents(?int $warehouseId, string $from, string $to): array
    {
        if (!$this->tableExists('goods_receipts')) {
            return [];
        }
        $sql = "SELECT id, grn_number, status, DATE(received_at) AS ev_date
                FROM goods_receipts
                WHERE DATE(received_at) BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'grn-' . $r['id'],
            'type' => 'receiving',
            'date' => $this->normalizeDate($r['ev_date']),
            'title' => $r['grn_number'],
            'subtitle' => $r['status'],
            'status' => $r['status'],
            'priority' => 'normal',
            'href' => 'receiving/goods_receipts.php',
            'sort' => 2,
        ], $rows);
    }

    private function dispatchEvents(?int $warehouseId, string $from, string $to): array
    {
        if (!$this->tableExists('warehouse_dispatches')) {
            return [];
        }
        $sql = "SELECT id, dispatch_number, status, DATE(created_at) AS ev_date
                FROM warehouse_dispatches
                WHERE DATE(created_at) BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND from_warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'dispatch-' . $r['id'],
            'type' => 'dispatch',
            'date' => $this->normalizeDate($r['ev_date']),
            'title' => $r['dispatch_number'],
            'subtitle' => $r['status'],
            'status' => $r['status'],
            'priority' => 'normal',
            'href' => 'dispatch/dispatch_orders.php',
            'sort' => 3,
        ], $rows);
    }

    private function transferEvents(?int $warehouseId, string $from, string $to): array
    {
        if (!$this->tableExists('warehouse_transfers')) {
            return [];
        }
        $sql = "SELECT id, transfer_number, status,
                       DATE(COALESCE(completed_at, approved_at, created_at)) AS ev_date
                FROM warehouse_transfers
                WHERE DATE(COALESCE(completed_at, approved_at, created_at)) BETWEEN ? AND ?
                AND status IN ('requested','approved','picking','in_transit','received','completed')";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND (from_warehouse_id = ? OR to_warehouse_id = ?)';
            $params[] = $warehouseId;
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'transfer-' . $r['id'],
            'type' => 'transfer',
            'date' => $this->normalizeDate($r['ev_date']),
            'title' => $r['transfer_number'],
            'subtitle' => $r['status'],
            'status' => $r['status'],
            'priority' => 'high',
            'href' => 'transfers/transfer_requests.php',
            'sort' => 4,
        ], $rows);
    }

    private function expiryEvents(?int $warehouseId, ?int $storeId, string $from, string $to): array
    {
        if (!$this->tableExists('batch_tracking')) {
            return [];
        }
        $sql = "SELECT b.id, b.batch_number, b.expiry_date, p.name AS product_name
                FROM batch_tracking b
                INNER JOIN products p ON p.id = b.product_id
                WHERE b.expiry_date BETWEEN ? AND ? AND b.quantity > 0";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND b.warehouse_id = ?';
            $params[] = $warehouseId;
        } elseif ($storeId) {
            $sql .= ' AND b.warehouse_id IN (SELECT id FROM warehouses WHERE store_id = ? OR store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' LIMIT 120';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'expiry-' . $r['id'],
            'type' => 'expiry',
            'date' => $this->normalizeDate($r['expiry_date']),
            'title' => $r['product_name'],
            'subtitle' => $r['batch_number'],
            'status' => 'expiring',
            'priority' => 'high',
            'href' => 'batch/expiry_management.php',
            'sort' => 5,
        ], $rows);
    }

    private function countEvents(?int $warehouseId, string $from, string $to): array
    {
        if (!$this->tableExists('inventory_counts')) {
            return [];
        }
        $sql = "SELECT id, count_number, count_type, status, scheduled_date
                FROM inventory_counts
                WHERE scheduled_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($warehouseId) {
            $sql .= ' AND warehouse_id = ?';
            $params[] = $warehouseId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(fn (array $r) => [
            'id' => 'count-' . $r['id'],
            'type' => 'count',
            'date' => $this->normalizeDate($r['scheduled_date']),
            'title' => $r['count_number'],
            'subtitle' => $r['count_type'] . ' · ' . $r['status'],
            'status' => $r['status'],
            'priority' => 'normal',
            'href' => 'inventory/stock_count.php',
            'sort' => 6,
        ], $rows);
    }

    private function taskHref(string $type): string
    {
        return match ($type) {
            'receiving' => 'receiving/goods_receipts.php',
            'dispatch', 'picking', 'packing', 'shipping' => 'dispatch/dispatch_orders.php',
            'transfer' => 'transfers/transfer_requests.php',
            'inventory_count' => 'inventory/stock_count.php',
            default => 'tasks/tasks.php',
        };
    }

    private function normalizeDate(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }
        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $m)) {
            return $m[1];
        }
        $ts = strtotime($value);
        return $ts ? date('Y-m-d', $ts) : '';
    }

    private function tableExists(string $table): bool
    {
        try {
            $this->db->query("SELECT 1 FROM {$table} LIMIT 1");
            return true;
        } catch (Throwable) {
            return false;
        }
    }
}

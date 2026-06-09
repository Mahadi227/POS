<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../Repositories/ApprovalRepository.php';
require_once __DIR__ . '/ShiftService.php';

class SupervisionService
{
    private PDO $db;
    private ApprovalRepository $approvals;
    private ShiftService $shifts;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->approvals = new ApprovalRepository($this->db);
        $this->shifts = new ShiftService();
    }

    public function dashboard(?int $storeId): array
    {
        [$salesSql, $salesParams] = $this->salesScope($storeId);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS revenue
             FROM sales s
             WHERE DATE(s.created_at) = CURDATE()
               AND s.status = 'completed'
               AND s.deleted_at IS NULL {$salesSql}"
        );
        $stmt->execute($salesParams);
        $sales = $stmt->fetch(PDO::FETCH_ASSOC) ?: ['cnt' => 0, 'revenue' => 0];

        [$invSql, $invParams] = $this->productScope($storeId);
        $invStmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN p.stock_quantity <= 0 THEN 1 ELSE 0 END), 0) AS out_of_stock,
                COALESCE(SUM(CASE WHEN p.stock_quantity > 0 AND p.stock_quantity <= COALESCE(p.min_stock_level, 5) THEN 1 ELSE 0 END), 0) AS low_stock
             FROM products p
             WHERE p.deleted_at IS NULL {$invSql}"
        );
        $invStmt->execute($invParams);
        $inv = $invStmt->fetch(PDO::FETCH_ASSOC) ?: ['out_of_stock' => 0, 'low_stock' => 0];

        $live = $this->liveRegisters($storeId);

        return [
            'sales_today' => [
                'count' => (int) $sales['cnt'],
                'revenue' => (float) $sales['revenue'],
            ],
            'pending_approvals' => $this->approvals->countPending($storeId),
            'live_registers' => count(array_filter($live, fn ($r) => !empty($r['online']))),
            'inventory_alerts' => (int) $inv['out_of_stock'] + (int) $inv['low_stock'],
            'approvals_preview' => array_slice($this->approvals->listPending($storeId), 0, 5),
            'registers_preview' => array_slice($live, 0, 5),
        ];
    }

    public function liveRegisters(?int $storeId): array
    {
        $registers = [];
        $openShifts = $this->shifts->listOpen($storeId);

        foreach ($openShifts as $shift) {
            $registers[] = [
                'cashier_id' => (int) $shift['user_id'],
                'cashier_name' => $shift['cashier_name'],
                'shift_id' => (int) $shift['id'],
                'opened_at' => $shift['opened_at'],
                'online' => true,
                'source' => 'shift',
            ];
        }

        if ($this->syncTableExists()) {
            [$syncSql, $syncParams] = $this->syncScope($storeId);
            $stmt = $this->db->prepare(
                "SELECT user_id, device_id, last_seen, status
                 FROM sync_devices
                 WHERE last_seen >= DATE_SUB(NOW(), INTERVAL 3 MINUTE) {$syncSql}
                 ORDER BY last_seen DESC"
            );
            $stmt->execute($syncParams);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $uid = (int) $row['user_id'];
                if (array_filter($registers, fn ($r) => $r['cashier_id'] === $uid)) {
                    continue;
                }
                $name = $this->userName($uid);
                $registers[] = [
                    'cashier_id' => $uid,
                    'cashier_name' => $name,
                    'device_id' => $row['device_id'],
                    'last_seen' => $row['last_seen'],
                    'online' => ($row['status'] ?? '') === 'online',
                    'source' => 'heartbeat',
                ];
            }
        }

        return $registers;
    }

    private function syncTableExists(): bool
    {
        try {
            $this->db->query('SELECT 1 FROM sync_devices LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    private function userName(int $userId): string
    {
        $stmt = $this->db->prepare('SELECT name FROM users WHERE id = ? LIMIT 1');
        $stmt->execute([$userId]);
        return (string) ($stmt->fetchColumn() ?: 'Caissier');
    }

    private function salesScope(?int $storeId): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        return ['AND s.store_id = ?', [$storeId]];
    }

    private function productScope(?int $storeId): array
    {
        return StoreScope::sqlFilter($this->db, 'store_id', 'p');
    }

    private function syncScope(?int $storeId): array
    {
        if ($storeId === null) {
            return ['', []];
        }
        return ['AND store_id = ?', [$storeId]];
    }
}

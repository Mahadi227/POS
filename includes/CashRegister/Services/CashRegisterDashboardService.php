<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../CashRegisterSchema.php';
require_once __DIR__ . '/../Repositories/CashRegisterRepository.php';
require_once __DIR__ . '/../Repositories/CashReconciliationRepository.php';
require_once __DIR__ . '/../Repositories/CashRegisterLogRepository.php';
require_once __DIR__ . '/../Repositories/CashRegisterSessionRepository.php';

class CashRegisterDashboardService
{
    private PDO $db;
    private CashRegisterRepository $registers;
    private CashReconciliationRepository $reconciliations;
    private CashRegisterLogRepository $logs;
    private CashRegisterSessionRepository $sessions;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->registers = new CashRegisterRepository($this->db);
        $this->reconciliations = new CashReconciliationRepository($this->db);
        $this->logs = new CashRegisterLogRepository();
        $this->sessions = new CashRegisterSessionRepository($this->db);
    }

    public function dashboard(?int $storeId): array
    {
        if (!CashRegisterSchema::ready()) {
            return [
                'module_ready' => false,
                'summary' => [],
                'payments_today' => [],
                'collection_chart' => [],
                'register_status' => [],
                'recent_activities' => [],
            ];
        }

        $regSummary = $this->registers->countSummary($storeId);
        $salesToday = $this->salesToday($storeId);
        $payments = $this->paymentsToday($storeId);
        $pendingRecon = $this->reconciliations->countPending($storeId);
        $openSessions = $this->sessions->list($storeId, 'open', 50);
        $closedToday = $this->countClosedToday($storeId);

        $expectedCash = 0.0;
        $actualCash = 0.0;
        foreach ($openSessions as $s) {
            $expectedCash += (float) ($s['opening_balance'] ?? 0) + (float) ($s['cash_sales'] ?? 0);
            $actualCash += (float) ($s['opening_balance'] ?? 0) + (float) ($s['cash_sales'] ?? 0);
        }

        return [
            'module_ready' => true,
            'summary' => [
                'total_registers' => $regSummary['total'],
                'open_registers' => $regSummary['open_sessions'],
                'closed_registers' => max(0, $regSummary['active'] - $regSummary['open_sessions']),
                'current_cash_balance' => $regSummary['total_balance'],
                'expected_cash' => round($expectedCash, 2),
                'cash_difference' => round($actualCash - $expectedCash, 2),
                'sales_today' => $salesToday['revenue'],
                'transactions_today' => $salesToday['count'],
                'cash_collected' => $payments['cash'] ?? 0,
                'mobile_collected' => $payments['mobile_money'] ?? 0,
                'card_collected' => $payments['card'] ?? 0,
                'pending_reconciliation' => $pendingRecon,
                'active_cashiers' => count(array_unique(array_column($openSessions, 'user_id'))),
                'sessions_closed_today' => $closedToday,
            ],
            'payments_today' => $payments,
            'collection_chart' => $this->hourlyCollection($storeId),
            'register_status' => $this->registerStatusList($storeId),
            'recent_activities' => $this->logs->list($storeId, 12),
            'performance_chart' => $this->registerPerformance($storeId),
        ];
    }

    public function analytics(?int $storeId, string $period = 'month'): array
    {
        if (!CashRegisterSchema::ready()) {
            return ['module_ready' => false, 'charts' => []];
        }

        $days = $period === 'week' ? 7 : ($period === 'year' ? 365 : 30);
        $from = date('Y-m-d 00:00:00', strtotime('-' . ($days - 1) . ' days'));

        return [
            'module_ready' => true,
            'daily_collection' => $this->dailyCollection($storeId, $from),
            'branch_comparison' => $this->branchComparison(),
            'cashier_performance' => $this->cashierPerformance($storeId, $from),
            'refund_trends' => $this->refundTrends($storeId, $from),
        ];
    }

    public function history(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        $sessions = $this->sessions->list($storeId, 'all', 300);
        if ($from) {
            $sessions = array_values(array_filter($sessions, fn ($s) => substr((string) $s['opened_at'], 0, 10) >= $from));
        }
        if ($to) {
            $sessions = array_values(array_filter($sessions, fn ($s) => substr((string) $s['opened_at'], 0, 10) <= $to));
        }
        return $sessions;
    }

    private function salesToday(?int $storeId): array
    {
        $sql = "SELECT COUNT(*) AS cnt, COALESCE(SUM(total), 0) AS revenue
                FROM sales WHERE status = 'completed' AND deleted_at IS NULL AND DATE(created_at) = CURDATE()";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['count' => (int) ($row['cnt'] ?? 0), 'revenue' => round((float) ($row['revenue'] ?? 0), 2)];
    }

    private function paymentsToday(?int $storeId): array
    {
        $sql = "SELECT p.method, COALESCE(SUM(p.amount), 0) AS amount
                FROM payments p
                INNER JOIN sales s ON s.id = p.sale_id
                WHERE s.status = 'completed' AND s.deleted_at IS NULL AND DATE(s.created_at) = CURDATE()";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY p.method';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $out = ['cash' => 0.0, 'card' => 0.0, 'mobile_money' => 0.0, 'split' => 0.0];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $out[$row['method'] ?? 'cash'] = round((float) $row['amount'], 2);
        }
        return $out;
    }

    private function hourlyCollection(?int $storeId): array
    {
        $sql = "SELECT HOUR(s.created_at) AS hr, COALESCE(SUM(p.amount), 0) AS amount
                FROM sales s
                INNER JOIN payments p ON p.sale_id = s.id
                WHERE s.status = 'completed' AND s.deleted_at IS NULL AND DATE(s.created_at) = CURDATE()";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY HOUR(s.created_at) ORDER BY hr';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['hr']] = round((float) $row['amount'], 2);
        }
        $chart = [];
        for ($h = 0; $h < 24; $h++) {
            $chart[] = ['hour' => $h, 'amount' => $map[$h] ?? 0.0];
        }
        return $chart;
    }

    private function registerStatusList(?int $storeId): array
    {
        $registers = $this->registers->list($storeId);
        return array_map(static function (array $r): array {
            return [
                'id' => (int) $r['id'],
                'name' => $r['name'],
                'code' => $r['register_code'],
                'store_name' => $r['store_name'],
                'status' => $r['status'],
                'session_status' => ((int) ($r['is_session_open'] ?? 0) > 0) ? 'open' : 'closed',
                'cashier' => $r['assigned_cashier'] ?? '—',
                'balance' => round((float) ($r['current_balance'] ?? 0), 2),
                'last_activity' => $r['last_activity_at'],
            ];
        }, $registers);
    }

    private function registerPerformance(?int $storeId): array
    {
        $sql = "SELECT r.name, COALESCE(SUM(crs.total_sales), 0) AS revenue
                FROM cash_registers r
                LEFT JOIN cash_register_sessions crs ON crs.register_id = r.id
                    AND crs.opened_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                WHERE r.deleted_at IS NULL";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND r.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY r.id, r.name ORDER BY revenue DESC LIMIT 8';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($row) => [
            'label' => $row['name'],
            'value' => round((float) $row['revenue'], 2),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function countClosedToday(?int $storeId): int
    {
        $sql = "SELECT COUNT(*) FROM cash_register_sessions WHERE status = 'closed' AND DATE(closed_at) = CURDATE()";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    private function dailyCollection(?int $storeId, string $from): array
    {
        $sql = "SELECT DATE(s.created_at) AS day, COALESCE(SUM(p.amount), 0) AS amount
                FROM sales s INNER JOIN payments p ON p.sale_id = s.id
                WHERE s.status = 'completed' AND s.deleted_at IS NULL AND s.created_at >= ?";
        $params = [$from];
        if ($storeId !== null) {
            $sql .= ' AND s.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY DATE(s.created_at) ORDER BY day';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return array_map(static fn ($r) => [
            'day' => $r['day'],
            'amount' => round((float) $r['amount'], 2),
        ], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    private function branchComparison(): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        $stmt = $this->db->query(
            "SELECT s.name, COALESCE(SUM(r.current_balance), 0) AS balance, COUNT(r.id) AS registers
             FROM stores s
             LEFT JOIN cash_registers r ON r.store_id = s.id AND r.deleted_at IS NULL
             WHERE s.deleted_at IS NULL
             GROUP BY s.id, s.name
             ORDER BY balance DESC
             LIMIT 10"
        );
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function cashierPerformance(?int $storeId, string $from): array
    {
        $sql = "SELECT u.name, COALESCE(SUM(crs.total_sales), 0) AS revenue, COUNT(crs.id) AS sessions
                FROM cash_register_sessions crs
                INNER JOIN users u ON u.id = crs.user_id
                WHERE crs.opened_at >= ?";
        $params = [$from];
        if ($storeId !== null) {
            $sql .= ' AND crs.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY u.id, u.name ORDER BY revenue DESC LIMIT 10';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    private function refundTrends(?int $storeId, string $from): array
    {
        $sql = "SELECT DATE(created_at) AS day, COALESCE(SUM(refunds), 0) AS amount
                FROM cash_register_sessions WHERE opened_at >= ?";
        $params = [$from];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY DATE(created_at) ORDER BY day';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

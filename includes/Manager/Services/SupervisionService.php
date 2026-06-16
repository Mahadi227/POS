<?php

declare(strict_types=1);



require_once __DIR__ . '/../../Database/Database.php';

require_once __DIR__ . '/../../Database/SyncSchemaMigrator.php';

require_once __DIR__ . '/../../Helpers/StoreScope.php';

require_once __DIR__ . '/../Repositories/ApprovalRepository.php';

require_once __DIR__ . '/ShiftService.php';



class SupervisionService

{

    private const ONLINE_MINUTES = 5;

    private const RECENT_SALE_MINUTES = 10;



    private PDO $db;

    private ApprovalRepository $approvals;

    private ShiftService $shifts;



    public function __construct(?PDO $db = null)

    {

        $this->db = $db ?? Database::getInstance()->getConnection();

        SyncSchemaMigrator::ensure($this->db);

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

        $onlineCount = count(array_filter($live, fn ($r) => ($r['status'] ?? '') === 'online'));

        $idleCount = count(array_filter($live, fn ($r) => ($r['status'] ?? '') === 'idle'));

        $activeCount = $onlineCount + $idleCount;

        $onShift = count(array_filter($live, fn ($r) => !empty($r['shift_open'])));

        $preview = array_values(array_filter(

            $live,

            fn ($r) => in_array($r['status'] ?? '', ['online', 'idle'], true)

        ));



        return [

            'sales_today' => [

                'count' => (int) $sales['cnt'],

                'revenue' => (float) $sales['revenue'],

            ],

            'pending_approvals' => $this->approvals->countPending($storeId),

            'live_registers' => $onlineCount,

            'live_registers_idle' => $idleCount,

            'live_registers_active' => $activeCount,

            'live_registers_tracked' => count($live),

            'registers_on_shift' => $onShift,

            'inventory_alerts' => (int) $inv['out_of_stock'] + (int) $inv['low_stock'],

            'approvals_preview' => array_slice($this->approvals->listPending($storeId), 0, 5),

            'registers_preview' => array_slice($preview, 0, 6),

        ];

    }



    public function liveRegisters(?int $storeId): array

    {

        $openShifts = $this->shifts->listOpen($storeId);

        $presenceMap = $this->presenceMap($storeId);

        $salesMap = $this->recentSalesMap($storeId);



        $registers = [];

        $seenUsers = [];



        foreach ($openShifts as $shift) {

            $uid = (int) $shift['user_id'];

            $seenUsers[$uid] = true;

            $registers[] = $this->buildRegisterRow(

                $uid,

                (string) $shift['cashier_name'],

                $presenceMap[$uid] ?? null,

                $salesMap[$uid] ?? null,

                $shift

            );

        }



        foreach ($presenceMap as $uid => $presence) {

            if (!empty($seenUsers[$uid])) {

                continue;

            }

            if (!$this->isRecentTimestamp($presence['last_seen_at'] ?? null, self::ONLINE_MINUTES * 3)) {

                continue;

            }

            $registers[] = $this->buildRegisterRow(

                $uid,

                $this->userName($uid),

                $presence,

                $salesMap[$uid] ?? null,

                null

            );

        }



        foreach ($salesMap as $uid => $sales) {

            if (!empty($seenUsers[$uid])) {

                continue;

            }

            if ((int) ($sales['sales_count'] ?? 0) <= 0) {

                continue;

            }

            $seenUsers[$uid] = true;

            $registers[] = $this->buildRegisterRow(

                (int) $uid,

                $this->userName((int) $uid),

                $presenceMap[$uid] ?? null,

                $sales,

                null

            );

        }



        usort($registers, function (array $a, array $b): int {

            $order = ['online' => 0, 'idle' => 1, 'offline' => 2];

            $cmp = ($order[$a['status']] ?? 9) <=> ($order[$b['status']] ?? 9);

            if ($cmp !== 0) {

                return $cmp;

            }

            return strcmp((string) ($b['last_activity_at'] ?? ''), (string) ($a['last_activity_at'] ?? ''));

        });



        return $registers;

    }



    private function buildRegisterRow(

        int $userId,

        string $cashierName,

        ?array $presence,

        ?array $sales,

        ?array $shift

    ): array {

        $lastSeen = $presence['last_seen_at'] ?? null;

        $lastSaleAt = $sales['last_sale_at'] ?? null;

        $lastActivity = $this->maxTimestamp($lastSeen, $lastSaleAt);

        $hasOpenShift = $shift !== null;

        $status = $this->resolveStatus($lastSeen, $lastSaleAt, $hasOpenShift);



        return [

            'cashier_id' => $userId,

            'cashier_name' => $cashierName,

            'shift_id' => $hasOpenShift ? (int) $shift['id'] : null,

            'shift_open' => $hasOpenShift,

            'opened_at' => $hasOpenShift ? ($shift['opened_at'] ?? null) : null,

            'last_seen' => $lastSeen,

            'last_sale_at' => $lastSaleAt,

            'last_activity_at' => $lastActivity,

            'sales_today' => (int) ($sales['sales_count'] ?? 0),

            'sales_today_amount' => (float) ($sales['sales_total'] ?? 0),

            'current_page' => $presence['last_page'] ?? null,

            'status' => $status,

            'online' => $status === 'online',

            'source' => $hasOpenShift ? 'shift' : 'presence',

        ];

    }



    private function resolveStatus(?string $lastSeen, ?string $lastSaleAt, bool $hasOpenShift): string

    {

        if ($this->isRecentTimestamp($lastSeen, self::ONLINE_MINUTES)) {

            return 'online';

        }

        if ($this->isRecentTimestamp($lastSaleAt, self::RECENT_SALE_MINUTES)) {

            return 'online';

        }

        if ($hasOpenShift) {

            return 'idle';

        }

        if ($this->isRecentTimestamp($lastSeen, self::ONLINE_MINUTES * 3)) {

            return 'idle';

        }

        return 'offline';

    }



    private function presenceMap(?int $storeId): array

    {

        if (!$this->presenceTableExists()) {

            return [];

        }



        [$sql, $params] = $this->presenceScope($storeId);

        $stmt = $this->db->prepare(

            "SELECT user_id, store_id, is_online, last_seen_at, last_page

             FROM cashier_presence

             WHERE 1=1 {$sql}

             ORDER BY last_seen_at DESC"

        );

        $stmt->execute($params);



        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $map[(int) $row['user_id']] = $row;

        }

        return $map;

    }



    private function recentSalesMap(?int $storeId): array

    {

        [$sql, $params] = $this->salesScope($storeId);

        $stmt = $this->db->prepare(

            "SELECT

                s.user_id,

                MAX(s.created_at) AS last_sale_at,

                COUNT(*) AS sales_count,

                COALESCE(SUM(s.total), 0) AS sales_total

             FROM sales s

             WHERE DATE(s.created_at) = CURDATE()

               AND s.status = 'completed'

               AND s.deleted_at IS NULL {$sql}

             GROUP BY s.user_id"

        );

        $stmt->execute($params);



        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $map[(int) $row['user_id']] = $row;

        }

        return $map;

    }



    private function presenceTableExists(): bool

    {

        try {

            $this->db->query('SELECT 1 FROM cashier_presence LIMIT 1');

            return true;

        } catch (Throwable $e) {

            return false;

        }

    }



    private function isRecentTimestamp(?string $timestamp, int $minutes): bool

    {

        if (!$timestamp) {

            return false;

        }

        $ts = strtotime($timestamp);

        if ($ts === false) {

            return false;

        }

        return $ts >= (time() - ($minutes * 60));

    }



    private function maxTimestamp(?string $a, ?string $b): ?string

    {

        if (!$a) {

            return $b;

        }

        if (!$b) {

            return $a;

        }

        return strtotime($a) >= strtotime($b) ? $a : $b;

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



    private function presenceScope(?int $storeId): array

    {

        if ($storeId === null) {

            return ['', []];

        }

        return ['AND store_id = ?', [$storeId]];

    }



    public function teamPerformance(?int $storeId, string $period = 'today', ?string $dateFrom = null, ?string $dateTo = null): array

    {

        [$period, $from, $to, $useDateFilter] = $this->resolveTeamPeriod($period, $dateFrom, $dateTo);

        [$salesSql, $salesParams] = $this->salesScope($storeId);

        $dateSql = $useDateFilter ? 'AND s.created_at >= ? AND s.created_at <= ?' : '';



        $stmt = $this->db->prepare(

            "SELECT

                u.id AS user_id,

                u.name AS cashier_name,

                COUNT(s.id) AS transactions,

                COALESCE(SUM(s.total), 0) AS revenue,

                MAX(s.created_at) AS last_sale_at

             FROM sales s

             INNER JOIN users u ON u.id = s.user_id

             INNER JOIN roles r ON r.id = u.role_id

             WHERE s.status = 'completed'

               AND s.deleted_at IS NULL

               AND u.deleted_at IS NULL

               AND LOWER(REPLACE(r.name, ' ', '_')) = 'cashier'

               {$dateSql}

               {$salesSql}

             GROUP BY u.id, u.name

             ORDER BY revenue DESC"

        );

        $stmt->execute($useDateFilter ? array_merge([$from, $to], $salesParams) : $salesParams);

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];



        $returnsMap = $this->returnsMap($storeId, $from, $to, $useDateFilter);



        $cashiers = [];

        $totalRevenue = 0.0;

        $totalTx = 0;

        $totalReturns = 0;



        foreach ($rows as $row) {

            $uid = (int) $row['user_id'];

            $revenue = (float) $row['revenue'];

            $tx = (int) $row['transactions'];

            $ret = $returnsMap[$uid] ?? ['count' => 0, 'amount' => 0.0];



            $totalRevenue += $revenue;

            $totalTx += $tx;

            $totalReturns += (int) $ret['count'];



            $cashiers[] = [

                'user_id' => $uid,

                'cashier_name' => (string) $row['cashier_name'],

                'revenue' => $revenue,

                'transactions' => $tx,

                'avg_ticket' => $tx > 0 ? round($revenue / $tx, 2) : 0.0,

                'returns_count' => (int) $ret['count'],

                'returns_amount' => (float) $ret['amount'],

                'last_sale_at' => $row['last_sale_at'] ?? null,

            ];

        }



        return [

            'period' => $period,

            'from' => $useDateFilter && $from ? substr($from, 0, 10) : null,

            'to' => $useDateFilter && $to ? substr($to, 0, 10) : null,

            'summary' => [

                'active_cashiers' => count($cashiers),

                'total_revenue' => $totalRevenue,

                'total_transactions' => $totalTx,

                'avg_ticket' => $totalTx > 0 ? round($totalRevenue / $totalTx, 2) : 0.0,

                'total_returns' => $totalReturns,

            ],

            'cashiers' => $cashiers,

        ];

    }



    /** @return array{0: string, 1: ?string, 2: ?string, 3: bool} */

    private function resolveTeamPeriod(string $period, ?string $dateFrom, ?string $dateTo): array

    {

        if ($period === 'all') {

            return ['all', null, null, false];

        }



        if ($period === 'custom') {

            $fromDay = $this->parseDate($dateFrom);

            $toDay = $this->parseDate($dateTo);

            if ($fromDay === null || $toDay === null) {

                return $this->resolveTeamPeriod('today', null, null);

            }

            if ($fromDay > $toDay) {

                [$fromDay, $toDay] = [$toDay, $fromDay];

            }

            return ['custom', $fromDay . ' 00:00:00', $toDay . ' 23:59:59', true];

        }



        if (!in_array($period, ['today', 'week', 'month'], true)) {

            $period = 'today';

        }



        [$period, $from, $to] = $this->periodBounds($period);

        return [$period, $from, $to, true];

    }



    private function parseDate(?string $value): ?string

    {

        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {

            return null;

        }

        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d', $ts);

    }



    /** @return array{0: string, 1: string, 2: string} */

    private function periodBounds(string $period): array

    {

        $end = date('Y-m-d 23:59:59');

        switch ($period) {

            case 'week':

                $start = date('Y-m-d 00:00:00', strtotime('-6 days'));

                break;

            case 'month':

                $start = date('Y-m-d 00:00:00', strtotime('-29 days'));

                break;

            case 'today':

            default:

                $period = 'today';

                $start = date('Y-m-d 00:00:00');

                break;

        }



        return [$period, $start, $end];

    }



    /** @return array<int, array{count: int, amount: float}> */

    private function returnsMap(?int $storeId, ?string $from, ?string $to, bool $useDateFilter): array

    {

        if (!$this->approvals->tableExists()) {

            return [];

        }



        [$sql, $params] = $this->approvalsScope($storeId);

        $dateSql = $useDateFilter ? 'AND created_at >= ? AND created_at <= ?' : '';

        $stmt = $this->db->prepare(

            "SELECT requested_by AS user_id,

                    COUNT(*) AS returns_count,

                    COALESCE(SUM(amount), 0) AS returns_amount

             FROM manager_approvals

             WHERE type = 'return'

               AND status = 'approved'

               {$dateSql}

               {$sql}

             GROUP BY requested_by"

        );

        $stmt->execute($useDateFilter ? array_merge([$from, $to], $params) : $params);



        $map = [];

        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {

            $map[(int) $row['user_id']] = [

                'count' => (int) $row['returns_count'],

                'amount' => (float) $row['returns_amount'],

            ];

        }



        return $map;

    }



    private function approvalsScope(?int $storeId): array

    {

        if ($storeId === null) {

            return ['', []];

        }

        return ['AND store_id = ?', [$storeId]];

    }



    /**
     * Stock alerts for manager operations — low, out, expiry.
     *
     * @return array{summary: array<string, int>, items: array<int, array<string, mixed>>, filter: string}
     */
    public function inventoryAlerts(?int $storeId, string $filter = 'all'): array
    {
        $allowed = ['all', 'out', 'low', 'expiring', 'expired'];
        if (!in_array($filter, $allowed, true)) {
            $filter = 'all';
        }

        [$invSql, $invParams] = $this->productScope($storeId);

        $alertCase = "CASE
            WHEN p.stock_quantity <= 0 THEN 'out_of_stock'
            WHEN p.expiry_date IS NOT NULL AND p.expiry_date <> '' AND p.expiry_date > '1000-01-01'
                 AND p.stock_quantity > 0 AND p.expiry_date < CURDATE() THEN 'expired'
            WHEN p.expiry_date IS NOT NULL AND p.expiry_date <> '' AND p.expiry_date > '1000-01-01'
                 AND p.stock_quantity > 0 AND p.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 'expiring'
            WHEN p.stock_quantity > 0 AND p.stock_quantity <= COALESCE(p.min_stock_level, 5) THEN 'low_stock'
            ELSE NULL
        END";

        $summaryStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN alert_type = 'out_of_stock' THEN 1 ELSE 0 END), 0) AS out_of_stock,
                COALESCE(SUM(CASE WHEN alert_type = 'low_stock' THEN 1 ELSE 0 END), 0) AS low_stock,
                COALESCE(SUM(CASE WHEN alert_type = 'expired' THEN 1 ELSE 0 END), 0) AS expired,
                COALESCE(SUM(CASE WHEN alert_type = 'expiring' THEN 1 ELSE 0 END), 0) AS expiring_soon
             FROM (
                SELECT {$alertCase} AS alert_type
                FROM products p
                WHERE p.deleted_at IS NULL {$invSql}
             ) alerts
             WHERE alert_type IS NOT NULL"
        );
        $summaryStmt->execute($invParams);
        $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $filterSql = '';
        if ($filter === 'out') {
            $filterSql = " AND ({$alertCase}) = 'out_of_stock'";
        } elseif ($filter === 'low') {
            $filterSql = " AND ({$alertCase}) = 'low_stock'";
        } elseif ($filter === 'expired') {
            $filterSql = " AND ({$alertCase}) = 'expired'";
        } elseif ($filter === 'expiring') {
            $filterSql = " AND ({$alertCase}) = 'expiring'";
        } else {
            $filterSql = " AND ({$alertCase}) IS NOT NULL";
        }

        $listStmt = $this->db->prepare(
            "SELECT p.id, p.name, p.sku, p.stock_quantity,
                    COALESCE(p.min_stock_level, 5) AS min_stock_level,
                    p.expiry_date,
                    COALESCE(c.name, '') AS category_name,
                    ({$alertCase}) AS alert_type,
                    DATEDIFF(p.expiry_date, CURDATE()) AS days_until_expiry
             FROM products p
             LEFT JOIN categories c ON c.id = p.category_id
             WHERE p.deleted_at IS NULL {$invSql}{$filterSql}
             ORDER BY FIELD(({$alertCase}), 'out_of_stock', 'expired', 'expiring', 'low_stock'),
                      p.stock_quantity ASC, p.name ASC
             LIMIT 200"
        );
        $listStmt->execute($invParams);
        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'summary' => [
                'total'          => (int) ($summaryRow['total'] ?? 0),
                'out_of_stock'   => (int) ($summaryRow['out_of_stock'] ?? 0),
                'low_stock'      => (int) ($summaryRow['low_stock'] ?? 0),
                'expired'        => (int) ($summaryRow['expired'] ?? 0),
                'expiring_soon'  => (int) ($summaryRow['expiring_soon'] ?? 0),
            ],
            'items'  => $items,
            'filter' => $filter,
        ];
    }



    /**
     * Flagged / unusual sales for manager review.
     *
     * @return array<string, mixed>
     */
    public function salesReview(
        ?int $storeId,
        string $period = 'today',
        ?string $dateFrom = null,
        ?string $dateTo = null,
        string $filter = 'all'
    ): array {
        $allowedFilters = ['all', 'cancelled', 'discount', 'high', 'pending'];
        if (!in_array($filter, $allowedFilters, true)) {
            $filter = 'all';
        }

        [$periodKey, $from, $to, $useDateFilter] = $this->resolveTeamPeriod($period, $dateFrom, $dateTo);
        [$salesSql, $salesParams] = $this->salesScope($storeId);
        $dateSql = $useDateFilter ? 'AND s.created_at >= ? AND s.created_at <= ?' : '';
        $baseParams = $useDateFilter ? array_merge($salesParams, [$from, $to]) : $salesParams;

        $avgStmt = $this->db->prepare(
            "SELECT COALESCE(AVG(s.total), 0)
             FROM sales s
             WHERE s.deleted_at IS NULL AND s.status = 'completed' {$dateSql} {$salesSql}"
        );
        $avgStmt->execute($baseParams);
        $avgTicket = (float) $avgStmt->fetchColumn();
        $highThreshold = max(round($avgTicket * 3, 2), 50000.0);

        $flagCase = "CASE
            WHEN s.status = 'cancelled' THEN 'cancelled'
            WHEN s.status = 'pending' THEN 'pending'
            WHEN s.discount > 0 AND (s.discount >= 5000 OR s.discount >= (s.total + s.discount) * 0.15) THEN 'high_discount'
            WHEN s.status = 'completed' AND s.total >= {$highThreshold} THEN 'high_amount'
            ELSE NULL
        END";

        $flaggedWhere = "(
            s.status IN ('cancelled', 'pending')
            OR (s.discount > 0 AND (s.discount >= 5000 OR s.discount >= (s.total + s.discount) * 0.15))
            OR (s.status = 'completed' AND s.total >= {$highThreshold})
        )";

        $summaryStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN flag_type = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled,
                COALESCE(SUM(CASE WHEN flag_type = 'high_discount' THEN 1 ELSE 0 END), 0) AS high_discount,
                COALESCE(SUM(CASE WHEN flag_type = 'high_amount' THEN 1 ELSE 0 END), 0) AS high_amount,
                COALESCE(SUM(CASE WHEN flag_type = 'pending' THEN 1 ELSE 0 END), 0) AS pending
             FROM (
                SELECT {$flagCase} AS flag_type
                FROM sales s
                WHERE s.deleted_at IS NULL {$dateSql} {$salesSql} AND {$flaggedWhere}
             ) flagged
             WHERE flag_type IS NOT NULL"
        );
        $summaryStmt->execute($baseParams);
        $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $filterSql = '';
        if ($filter === 'cancelled') {
            $filterSql = " AND ({$flagCase}) = 'cancelled'";
        } elseif ($filter === 'discount') {
            $filterSql = " AND ({$flagCase}) = 'high_discount'";
        } elseif ($filter === 'high') {
            $filterSql = " AND ({$flagCase}) = 'high_amount'";
        } elseif ($filter === 'pending') {
            $filterSql = " AND ({$flagCase}) = 'pending'";
        } else {
            $filterSql = " AND ({$flagCase}) IS NOT NULL";
        }

        $listStmt = $this->db->prepare(
            "SELECT s.id, s.receipt_no, s.total, s.discount, s.status, s.created_at,
                    u.name AS cashier_name,
                    ({$flagCase}) AS flag_type,
                    COALESCE(pm.payment_methods, '') AS payment_methods
             FROM sales s
             INNER JOIN users u ON u.id = s.user_id
             LEFT JOIN (
                SELECT sale_id, GROUP_CONCAT(DISTINCT method ORDER BY method SEPARATOR ', ') AS payment_methods
                FROM payments
                GROUP BY sale_id
             ) pm ON pm.sale_id = s.id
             WHERE s.deleted_at IS NULL {$dateSql} {$salesSql}
               AND {$flaggedWhere}{$filterSql}
             ORDER BY s.created_at DESC
             LIMIT 200"
        );
        $listStmt->execute($baseParams);
        $items = $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return [
            'period'         => $periodKey,
            'avg_ticket'     => round($avgTicket, 2),
            'high_threshold' => $highThreshold,
            'summary'        => [
                'total'         => (int) ($summaryRow['total'] ?? 0),
                'cancelled'     => (int) ($summaryRow['cancelled'] ?? 0),
                'high_discount' => (int) ($summaryRow['high_discount'] ?? 0),
                'high_amount'   => (int) ($summaryRow['high_amount'] ?? 0),
                'pending'       => (int) ($summaryRow['pending'] ?? 0),
            ],
            'items'  => $items,
            'filter' => $filter,
        ];
    }

    /**
     * End-of-day store rollup for manager reports.
     *
     * @return array<string, mixed>
     */
    public function dailySummary(?int $storeId, ?string $date = null): array
    {
        $day = $this->parseDate($date) ?? date('Y-m-d');
        $from = $day . ' 00:00:00';
        $to = $day . ' 23:59:59';

        $prevDay = date('Y-m-d', strtotime($day . ' -1 day'));
        $prevFrom = $prevDay . ' 00:00:00';
        $prevTo = $prevDay . ' 23:59:59';

        [$salesSql, $salesParams] = $this->salesScope($storeId);

        $salesStmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS tx_count,
                COALESCE(SUM(s.total), 0) AS revenue,
                COALESCE(SUM(s.discount), 0) AS discount_total,
                COALESCE(SUM(CASE WHEN s.status = 'cancelled' THEN 1 ELSE 0 END), 0) AS cancelled_count
             FROM sales s
             WHERE s.deleted_at IS NULL
               AND s.status = 'completed'
               AND s.created_at >= ? AND s.created_at <= ?
               {$salesSql}"
        );
        $salesStmt->execute(array_merge([$from, $to], $salesParams));
        $salesRow = $salesStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $prevStmt = $this->db->prepare(
            "SELECT COUNT(*) AS tx_count, COALESCE(SUM(s.total), 0) AS revenue
             FROM sales s
             WHERE s.deleted_at IS NULL
               AND s.status = 'completed'
               AND s.created_at >= ? AND s.created_at <= ?
               {$salesSql}"
        );
        $prevStmt->execute(array_merge([$prevFrom, $prevTo], $salesParams));
        $prevRow = $prevStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $txCount = (int) ($salesRow['tx_count'] ?? 0);
        $revenue = (float) ($salesRow['revenue'] ?? 0);
        $avgTicket = $txCount > 0 ? round($revenue / $txCount, 2) : 0.0;

        $payments = $this->dailyPaymentMix($storeId, $from, $to);
        $returns = $this->dailyReturns($storeId, $from, $to);
        $approvals = $this->dailyApprovals($storeId, $from, $to);
        $shifts = $this->dailyShifts($storeId, $day);
        $hourly = $this->dailyHourlySales($storeId, $from, $to);
        $team = $this->teamPerformance($storeId, 'custom', $day, $day);

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

        return [
            'date'            => $day,
            'label'           => $this->formatDayLabel($day),
            'sales'           => [
                'count'           => $txCount,
                'revenue'         => round($revenue, 2),
                'avg_ticket'      => $avgTicket,
                'discount_total'  => round((float) ($salesRow['discount_total'] ?? 0), 2),
                'cancelled_count' => (int) ($salesRow['cancelled_count'] ?? 0),
                'vs_previous'     => [
                    'count'    => (int) ($prevRow['tx_count'] ?? 0),
                    'revenue'  => round((float) ($prevRow['revenue'] ?? 0), 2),
                    'count_pct' => $this->percentChange($txCount, (int) ($prevRow['tx_count'] ?? 0)),
                    'revenue_pct' => $this->percentChange($revenue, (float) ($prevRow['revenue'] ?? 0)),
                ],
            ],
            'payments'        => $payments,
            'returns'         => $returns,
            'approvals'       => $approvals,
            'shifts'          => $shifts,
            'hourly'          => $hourly,
            'top_cashiers'    => array_slice($team['cashiers'] ?? [], 0, 5),
            'inventory'       => [
                'out_of_stock' => (int) ($inv['out_of_stock'] ?? 0),
                'low_stock'    => (int) ($inv['low_stock'] ?? 0),
                'total_alerts' => (int) ($inv['out_of_stock'] ?? 0) + (int) ($inv['low_stock'] ?? 0),
            ],
        ];
    }

    /** @return list<array{method: string, count: int, amount: float}> */
    private function dailyPaymentMix(?int $storeId, string $from, string $to): array
    {
        [$salesSql, $salesParams] = $this->salesScope($storeId);
        $stmt = $this->db->prepare(
            "SELECT p.method,
                    COUNT(DISTINCT p.sale_id) AS cnt,
                    COALESCE(SUM(p.amount), 0) AS amount
             FROM payments p
             INNER JOIN sales s ON s.id = p.sale_id
             WHERE s.deleted_at IS NULL
               AND s.status = 'completed'
               AND s.created_at >= ? AND s.created_at <= ?
               {$salesSql}
             GROUP BY p.method
             ORDER BY amount DESC"
        );
        $stmt->execute(array_merge([$from, $to], $salesParams));

        return array_map(static function (array $row): array {
            return [
                'method' => (string) ($row['method'] ?? 'other'),
                'count'  => (int) ($row['cnt'] ?? 0),
                'amount' => round((float) ($row['amount'] ?? 0), 2),
            ];
        }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    /** @return array{count: int, amount: float} */
    private function dailyReturns(?int $storeId, string $from, string $to): array
    {
        if (!$this->approvals->tableExists()) {
            return ['count' => 0, 'amount' => 0.0];
        }

        [$sql, $params] = $this->approvalsScope($storeId);
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS cnt, COALESCE(SUM(amount), 0) AS amount
             FROM manager_approvals
             WHERE type = 'return'
               AND status = 'approved'
               AND reviewed_at >= ? AND reviewed_at <= ?
               {$sql}"
        );
        $stmt->execute(array_merge([$from, $to], $params));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'count'  => (int) ($row['cnt'] ?? 0),
            'amount' => round((float) ($row['amount'] ?? 0), 2),
        ];
    }

    /** @return array<string, mixed> */
    private function dailyApprovals(?int $storeId, string $from, string $to): array
    {
        if (!$this->approvals->tableExists()) {
            return [
                'pending_now'     => 0,
                'approved_day'    => 0,
                'rejected_day'    => 0,
                'processed_day'   => 0,
            ];
        }

        [$sql, $params] = $this->approvalsScope($storeId);

        $pendingStmt = $this->db->prepare(
            "SELECT COUNT(*) FROM manager_approvals WHERE status = 'pending' {$sql}"
        );
        $pendingStmt->execute($params);
        $pendingNow = (int) $pendingStmt->fetchColumn();

        $dayStmt = $this->db->prepare(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END), 0) AS approved,
                COALESCE(SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END), 0) AS rejected
             FROM manager_approvals
             WHERE reviewed_at >= ? AND reviewed_at <= ?
               {$sql}"
        );
        $dayStmt->execute(array_merge([$from, $to], $params));
        $dayRow = $dayStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $approved = (int) ($dayRow['approved'] ?? 0);
        $rejected = (int) ($dayRow['rejected'] ?? 0);

        return [
            'pending_now'   => $pendingNow,
            'approved_day'  => $approved,
            'rejected_day'  => $rejected,
            'processed_day' => $approved + $rejected,
        ];
    }

    /** @return array<string, mixed> */
    private function dailyShifts(?int $storeId, string $day): array
    {
        if (!$this->shifts->tableExists()) {
            return [
                'opened'         => 0,
                'closed'         => 0,
                'open_now'       => 0,
                'total_variance' => 0.0,
                'items'          => [],
            ];
        }

        $params = [$day, $day];
        $storeSql = '';
        if ($storeId !== null) {
            $storeSql = 'AND cs.store_id = ?';
            $params[] = $storeId;
        }

        $stmt = $this->db->prepare(
            "SELECT cs.*, u.name AS cashier_name
             FROM cashier_shifts cs
             INNER JOIN users u ON u.id = cs.user_id
             WHERE (DATE(cs.opened_at) = ? OR DATE(cs.closed_at) = ?)
               {$storeSql}
             ORDER BY cs.opened_at ASC"
        );
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $opened = 0;
        $closed = 0;
        $openNow = 0;
        $totalVariance = 0.0;
        $items = [];

        foreach ($rows as $row) {
            if (substr((string) ($row['opened_at'] ?? ''), 0, 10) === $day) {
                $opened++;
            }
            if (($row['status'] ?? '') === 'closed' && substr((string) ($row['closed_at'] ?? ''), 0, 10) === $day) {
                $closed++;
                $totalVariance += (float) ($row['variance'] ?? 0);
            }
            if (($row['status'] ?? '') === 'open') {
                $openNow++;
            }

            $variance = ($row['variance'] !== null && $row['variance'] !== '') ? (float) $row['variance'] : null;
            $recon = 'open';
            if (($row['status'] ?? '') === 'closed' && $variance !== null) {
                $recon = abs($variance) < 500 ? 'balanced' : ($variance < 0 ? 'short' : 'over');
            }

            $items[] = [
                'id'                    => (int) ($row['id'] ?? 0),
                'cashier_name'          => (string) ($row['cashier_name'] ?? ''),
                'status'                => (string) ($row['status'] ?? ''),
                'opening_float'         => round((float) ($row['opening_float'] ?? 0), 2),
                'total_sales'           => round((float) ($row['total_sales'] ?? 0), 2),
                'transaction_count'     => (int) ($row['transaction_count'] ?? 0),
                'expected_cash'         => $row['expected_cash'] !== null ? round((float) $row['expected_cash'], 2) : null,
                'counted_cash'          => $row['counted_cash'] !== null ? round((float) $row['counted_cash'], 2) : null,
                'variance'              => $variance !== null ? round($variance, 2) : null,
                'reconciliation_status' => $recon,
                'opened_at'             => $row['opened_at'] ?? null,
                'closed_at'             => $row['closed_at'] ?? null,
            ];
        }

        return [
            'opened'         => $opened,
            'closed'         => $closed,
            'open_now'       => $openNow,
            'total_variance' => round($totalVariance, 2),
            'items'          => $items,
        ];
    }

    /** @return list<array{hour: int, count: int, revenue: float}> */
    private function dailyHourlySales(?int $storeId, string $from, string $to): array
    {
        [$salesSql, $salesParams] = $this->salesScope($storeId);
        $stmt = $this->db->prepare(
            "SELECT HOUR(s.created_at) AS hr,
                    COUNT(*) AS cnt,
                    COALESCE(SUM(s.total), 0) AS revenue
             FROM sales s
             WHERE s.deleted_at IS NULL
               AND s.status = 'completed'
               AND s.created_at >= ? AND s.created_at <= ?
               {$salesSql}
             GROUP BY HOUR(s.created_at)
             ORDER BY hr ASC"
        );
        $stmt->execute(array_merge([$from, $to], $salesParams));

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $map[(int) $row['hr']] = [
                'hour'    => (int) $row['hr'],
                'count'   => (int) ($row['cnt'] ?? 0),
                'revenue' => round((float) ($row['revenue'] ?? 0), 2),
            ];
        }

        $hourly = [];
        for ($h = 0; $h < 24; $h++) {
            $hourly[] = $map[$h] ?? ['hour' => $h, 'count' => 0, 'revenue' => 0.0];
        }

        return $hourly;
    }

    private function formatDayLabel(string $day): string
    {
        $ts = strtotime($day);
        if ($ts === false) {
            return $day;
        }

        return date('Y-m-d', $ts);
    }

    private function percentChange(float|int $current, float|int $previous): ?int
    {
        if ($previous == 0 && $current == 0) {
            return null;
        }
        if ($previous == 0) {
            return 100;
        }

        return (int) round((($current - $previous) / $previous) * 100);
    }

}


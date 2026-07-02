<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../Repositories/AccountRepository.php';
require_once __DIR__ . '/../Repositories/JournalRepository.php';
require_once __DIR__ . '/../Repositories/TreasuryRepository.php';
require_once __DIR__ . '/../Repositories/ExpenseRepository.php';
require_once __DIR__ . '/../AccountingSchema.php';

class AccountingDashboardService
{
    private PDO $db;
    private JournalRepository $journal;
    private TreasuryRepository $treasury;
    private ExpenseRepository $expenses;
    private AccountRepository $accounts;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->journal = new JournalRepository($this->db);
        $this->treasury = new TreasuryRepository($this->db);
        $this->expenses = new ExpenseRepository($this->db);
        $this->accounts = new AccountRepository($this->db);
    }

    public function dashboard(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $from = $from ?? date('Y-m-01');
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $today = date('Y-m-d');
        $revenuePeriod = $this->journal->revenueTotal($storeId, $from, $to);
        $expensePeriod = $this->journal->expenseTotal($storeId, $from, $to);
        $revenueToday = $this->journal->revenueTotal($storeId, $today, $today);
        $grossProfit = $revenuePeriod - $this->cogsTotal($storeId, $from, $to);
        $netProfit = $revenuePeriod - $expensePeriod;

        $cashBalance = $this->treasury->cashBalance($storeId);
        $bankBalance = $this->treasury->bankBalance($storeId);
        $mobileBalance = $this->treasury->mobileBalance($storeId);
        $inventoryValue = $this->inventoryValue($storeId);
        $pendingExpenses = $this->expenses->pendingTotal($storeId);

        return [
            'module_ready' => true,
            'period' => ['from' => $from, 'to' => $to],
            'hero' => [
                'total_revenue' => $revenuePeriod,
                'total_expenses' => $expensePeriod,
                'net_profit' => round($netProfit, 2),
                'treasury_total' => round($cashBalance + $bankBalance + $mobileBalance, 2),
            ],
            'summary' => [
                'total_revenue' => $revenuePeriod,
                'total_expenses' => $expensePeriod,
                'gross_profit' => round($grossProfit, 2),
                'net_profit' => round($netProfit, 2),
                'cash_balance' => $cashBalance,
                'bank_balance' => $bankBalance,
                'mobile_money_balance' => $mobileBalance,
                'accounts_receivable' => $this->treasury->receivableBalance($storeId),
                'accounts_payable' => $this->treasury->payableBalance($storeId),
                'inventory_value' => $inventoryValue,
                'warehouse_stock_value' => $this->warehouseValue($storeId),
                'daily_sales' => $revenueToday,
                'monthly_sales' => $this->journal->revenueTotal($storeId, date('Y-m-01'), $today),
                'outstanding_debts' => $this->treasury->receivableBalance($storeId),
                'pending_expenses' => $pendingExpenses,
            ],
            'charts' => [
                'revenue_trend' => $this->journal->dailyTrend($storeId, 'revenue', 30, $from, $to),
                'expense_trend' => $this->journal->dailyTrend($storeId, 'expense', 30, $from, $to),
                'expense_by_category' => $this->expenseByCategory($storeId, $from, $to),
            ],
            'treasury_mix' => [
                ['key' => 'cash', 'amount' => $cashBalance],
                ['key' => 'bank', 'amount' => $bankBalance],
                ['key' => 'mobile', 'amount' => $mobileBalance],
            ],
            'branch_comparison' => $this->branchComparison($from, $to),
        ];
    }

    public function analytics(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        $data = $this->dashboard($storeId, $from, $to);
        if (!($data['module_ready'] ?? false)) {
            return ['module_ready' => false];
        }

        $from = $data['period']['from'] ?? date('Y-m-01');
        $to = $data['period']['to'] ?? date('Y-m-d');
        $revenue = (float) ($data['summary']['total_revenue'] ?? 0);
        $expenses = (float) ($data['summary']['total_expenses'] ?? 0);
        $net = (float) ($data['summary']['net_profit'] ?? 0);
        $gross = (float) ($data['summary']['gross_profit'] ?? 0);
        $days = max(1, (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1);

        $data['insights'] = [
            'profit_margin' => $revenue > 0 ? round(($net / $revenue) * 100, 1) : 0,
            'gross_margin' => $revenue > 0 ? round(($gross / $revenue) * 100, 1) : 0,
            'expense_ratio' => $revenue > 0 ? round(($expenses / $revenue) * 100, 1) : 0,
            'avg_daily_revenue' => round($revenue / $days, 2),
            'avg_daily_expense' => round($expenses / $days, 2),
        ];

        return $data;
    }

    private function cogsTotal(?int $storeId, string $from, string $to): float
    {
        $balances = $this->accounts->balances($storeId, $from, $to);
        $acct = $this->accounts->findByCode('5050', $storeId);
        if (!$acct) {
            return 0.0;
        }
        return (float) ($balances[(int) $acct['id']] ?? 0);
    }

    private function inventoryValue(?int $storeId): float
    {
        try {
            $sql = 'SELECT COALESCE(SUM(stock_quantity * cost), 0) FROM products WHERE deleted_at IS NULL';
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return round((float) $stmt->fetchColumn(), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function warehouseValue(?int $storeId): float
    {
        try {
            $this->db->query('SELECT 1 FROM warehouse_inventory LIMIT 1');
        } catch (Throwable) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(wi.quantity * p.cost), 0)
                FROM warehouse_inventory wi
                INNER JOIN products p ON p.id = wi.product_id
                WHERE p.deleted_at IS NULL';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND wi.store_id = ?';
            $params[] = $storeId;
        }
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            return round((float) $stmt->fetchColumn(), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function expenseByCategory(?int $storeId, string $from, string $to): array
    {
        $rows = $this->expenses->list($storeId, ['from' => $from, 'to' => $to, 'status' => 'approved'], 500);
        $map = [];
        foreach ($rows as $r) {
            $cat = $r['category'] ?? 'misc';
            $map[$cat] = ($map[$cat] ?? 0) + (float) $r['amount'];
        }
        arsort($map);
        $out = [];
        foreach ($map as $k => $v) {
            $out[] = ['category' => $k, 'amount' => round($v, 2)];
        }
        return $out;
    }

    private function branchComparison(string $from, string $to): array
    {
        try {
            $stmt = $this->db->query('SELECT id, name FROM stores WHERE deleted_at IS NULL ORDER BY name');
            $stores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        } catch (Throwable) {
            return [];
        }
        $out = [];
        foreach ($stores as $s) {
            $sid = (int) $s['id'];
            $out[] = [
                'store_id' => $sid,
                'name' => $s['name'],
                'revenue' => $this->journal->revenueTotal($sid, $from, $to),
                'expenses' => $this->journal->expenseTotal($sid, $from, $to),
            ];
        }
        return $out;
    }
}

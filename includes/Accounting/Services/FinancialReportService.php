<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../Repositories/AccountRepository.php';
require_once __DIR__ . '/../Repositories/JournalRepository.php';
require_once __DIR__ . '/../Repositories/TreasuryRepository.php';
require_once __DIR__ . '/../Repositories/ExpenseRepository.php';
require_once __DIR__ . '/../AccountingSchema.php';

class FinancialReportService
{
    private AccountRepository $accounts;
    private JournalRepository $journal;
    private TreasuryRepository $treasury;
    private ExpenseRepository $expenses;

    public function __construct()
    {
        $this->accounts = new AccountRepository();
        $this->journal = new JournalRepository();
        $this->treasury = new TreasuryRepository();
        $this->expenses = new ExpenseRepository();
    }

    public function hubSummary(?int $storeId, string $from, string $to): array
    {
        $pl = $this->profitAndLoss($storeId, $from, $to);
        $bs = $this->balanceSheet($storeId, $to);
        $cf = $this->cashFlow($storeId, $from, $to);
        $treasury = ($cf['balances']['cash'] ?? 0) + ($cf['balances']['bank'] ?? 0) + ($cf['balances']['mobile_money'] ?? 0);
        return [
            'period' => ['from' => $from, 'to' => $to],
            'as_of' => $to,
            'net_profit' => $pl['net_profit'],
            'revenue' => $pl['revenue'],
            'total_assets' => $bs['totals']['asset'] ?? 0,
            'total_liabilities' => $bs['totals']['liability'] ?? 0,
            'total_equity' => $bs['totals']['equity'] ?? 0,
            'net_cash_flow' => $cf['net_cash_flow'],
            'treasury_total' => round($treasury, 2),
        ];
    }

    public function profitAndLoss(?int $storeId, string $from, string $to): array
    {
        $revenue = $this->journal->revenueTotal($storeId, $from, $to);
        $expenses = $this->journal->expenseTotal($storeId, $from, $to);
        $cogsAcct = $this->accounts->findByCode('5050', $storeId);
        $cogs = 0.0;
        if ($cogsAcct) {
            $bal = $this->accounts->balances($storeId, $from, $to);
            $cogs = (float) ($bal[(int) $cogsAcct['id']] ?? 0);
        }
        $gross = $revenue - $cogs;
        return [
            'period' => ['from' => $from, 'to' => $to],
            'revenue' => $revenue,
            'cogs' => round($cogs, 2),
            'gross_profit' => round($gross, 2),
            'expenses' => $expenses,
            'net_profit' => round($gross - ($expenses - $cogs), 2),
        ];
    }

    public function profitAndLossPage(?int $storeId, string $from, string $to): array
    {
        if (!AccountingSchema::ready()) {
            return ['module_ready' => false];
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = $this->profitAndLoss($storeId, $from, $to);
        $revenue = (float) ($base['revenue'] ?? 0);
        $expenses = (float) ($base['expenses'] ?? 0);
        $cogs = (float) ($base['cogs'] ?? 0);
        $gross = (float) ($base['gross_profit'] ?? 0);
        $net = (float) ($base['net_profit'] ?? 0);
        $opex = max(0, $expenses - $cogs);
        $days = max(1, (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1);

        return array_merge($base, [
            'module_ready' => true,
            'operating_expenses' => round($opex, 2),
            'charts' => [
                'revenue_trend' => $this->journal->dailyTrend($storeId, 'revenue', 30, $from, $to),
                'expense_trend' => $this->journal->dailyTrend($storeId, 'expense', 30, $from, $to),
                'expense_by_category' => $this->expenseByCategory($storeId, $from, $to),
            ],
            'insights' => [
                'gross_margin' => $revenue > 0 ? round($gross / $revenue * 100, 1) : 0,
                'net_margin' => $revenue > 0 ? round($net / $revenue * 100, 1) : 0,
                'expense_ratio' => $revenue > 0 ? round($expenses / $revenue * 100, 1) : 0,
                'avg_daily_revenue' => round($revenue / $days, 2),
                'avg_daily_profit' => round($net / $days, 2),
            ],
        ]);
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

    public function balanceSheet(?int $storeId, string $asOf): array
    {
        $balances = $this->accounts->balances($storeId, null, $asOf);
        $accounts = $this->accounts->list($storeId);
        $sections = ['asset' => [], 'liability' => [], 'equity' => []];
        $totals = ['asset' => 0.0, 'liability' => 0.0, 'equity' => 0.0];
        foreach ($accounts as $a) {
            if (($a['account_subtype'] ?? '') === 'header') {
                continue;
            }
            $type = $a['account_type'];
            if (!isset($sections[$type])) {
                continue;
            }
            $bal = (float) ($balances[(int) $a['id']] ?? 0);
            if (abs($bal) < 0.001) {
                continue;
            }
            $sections[$type][] = ['code' => $a['code'], 'name' => $a['name'], 'balance' => $bal];
            $totals[$type] += $bal;
        }
        return [
            'as_of' => $asOf,
            'assets' => $sections['asset'],
            'liabilities' => $sections['liability'],
            'equity' => $sections['equity'],
            'totals' => array_map(static fn ($v) => round($v, 2), $totals),
        ];
    }

    public function balanceSheetPage(?int $storeId, string $asOf): array
    {
        if (!AccountingSchema::ready()) {
            return ['module_ready' => false];
        }

        $base = $this->balanceSheet($storeId, $asOf);
        $totals = $base['totals'] ?? [];
        $assets = (float) ($totals['asset'] ?? 0);
        $liabilities = (float) ($totals['liability'] ?? 0);
        $equity = (float) ($totals['equity'] ?? 0);
        $liabEquity = $liabilities + $equity;

        $allAccounts = [];
        foreach (['asset' => $base['assets'], 'liability' => $base['liabilities'], 'equity' => $base['equity']] as $type => $rows) {
            foreach ($rows as $row) {
                $allAccounts[] = array_merge($row, ['type' => $type]);
            }
        }
        usort($allAccounts, static fn ($a, $b) => abs((float) $b['balance']) <=> abs((float) $a['balance']));
        $topAccounts = array_slice($allAccounts, 0, 8);

        return array_merge($base, [
            'module_ready' => true,
            'net_worth' => round($assets - $liabilities, 2),
            'composition' => [
                ['key' => 'asset', 'amount' => $assets],
                ['key' => 'liability', 'amount' => $liabilities],
                ['key' => 'equity', 'amount' => $equity],
            ],
            'charts' => [
                'top_accounts' => array_map(static fn ($r) => [
                    'label' => trim(($r['code'] ?? '') . ' ' . ($r['name'] ?? '')),
                    'amount' => round(abs((float) $r['balance']), 2),
                    'type' => $r['type'],
                ], $topAccounts),
            ],
            'insights' => [
                'debt_to_equity' => $equity > 0 ? round($liabilities / $equity, 2) : null,
                'equity_ratio' => $assets > 0 ? round($equity / $assets * 100, 1) : 0,
                'liability_ratio' => $assets > 0 ? round($liabilities / $assets * 100, 1) : 0,
                'is_balanced' => abs($assets - $liabEquity) < 0.02,
                'balance_gap' => round($assets - $liabEquity, 2),
                'account_count' => count($base['assets']) + count($base['liabilities']) + count($base['equity']),
            ],
        ]);
    }

    public function cashFlow(?int $storeId, string $from, string $to): array
    {
        $cashIn = $this->journal->revenueTotal($storeId, $from, $to);
        $cashOut = $this->journal->expenseTotal($storeId, $from, $to);
        $arCollected = 0.0;
        $apPaid = 0.0;
        return [
            'period' => ['from' => $from, 'to' => $to],
            'cash_in' => [
                'sales' => $cashIn,
                'receivables_collected' => $arCollected,
                'total' => round($cashIn + $arCollected, 2),
            ],
            'cash_out' => [
                'expenses' => $cashOut,
                'payables_paid' => $apPaid,
                'total' => round($cashOut + $apPaid, 2),
            ],
            'net_cash_flow' => round($cashIn + $arCollected - $cashOut - $apPaid, 2),
            'balances' => [
                'cash' => $this->treasury->cashBalance($storeId),
                'bank' => $this->treasury->bankBalance($storeId),
                'mobile_money' => $this->treasury->mobileBalance($storeId),
            ],
        ];
    }

    public function cashFlowPage(?int $storeId, string $from, string $to): array
    {
        if (!AccountingSchema::ready()) {
            return ['module_ready' => false];
        }

        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = $this->cashFlow($storeId, $from, $to);
        $revenueTrend = $this->journal->dailyTrend($storeId, 'revenue', 30, $from, $to);
        $expenseTrend = $this->journal->dailyTrend($storeId, 'expense', 30, $from, $to);
        $days = max(1, (int) floor((strtotime($to) - strtotime($from)) / 86400) + 1);
        $cashIn = (float) ($base['cash_in']['total'] ?? 0);
        $cashOut = (float) ($base['cash_out']['total'] ?? 0);
        $treasuryTotal = ($base['balances']['cash'] ?? 0) + ($base['balances']['bank'] ?? 0) + ($base['balances']['mobile_money'] ?? 0);
        $dailyBurn = max(0.01, ($cashOut - $cashIn) / $days);

        $revMap = [];
        foreach ($revenueTrend as $row) {
            $revMap[$row['day']] = (float) $row['amount'];
        }
        $expMap = [];
        foreach ($expenseTrend as $row) {
            $expMap[$row['day']] = (float) $row['amount'];
        }
        $allDays = array_unique(array_merge(array_keys($revMap), array_keys($expMap)));
        sort($allDays);
        $netTrend = [];
        foreach ($allDays as $day) {
            $netTrend[] = [
                'day' => $day,
                'amount' => round(($revMap[$day] ?? 0) - ($expMap[$day] ?? 0), 2),
            ];
        }

        return array_merge($base, [
            'module_ready' => true,
            'treasury_total' => round($treasuryTotal, 2),
            'treasury_mix' => [
                ['key' => 'cash', 'amount' => $base['balances']['cash']],
                ['key' => 'bank', 'amount' => $base['balances']['bank']],
                ['key' => 'mobile', 'amount' => $base['balances']['mobile_money']],
            ],
            'outstanding' => [
                'receivable' => $this->treasury->receivableBalance($storeId),
                'payable' => $this->treasury->payableBalance($storeId),
            ],
            'charts' => [
                'cash_in_trend' => $revenueTrend,
                'cash_out_trend' => $expenseTrend,
                'net_trend' => $netTrend,
            ],
            'insights' => [
                'avg_daily_in' => round($cashIn / $days, 2),
                'avg_daily_out' => round($cashOut / $days, 2),
                'in_out_ratio' => $cashOut > 0 ? round($cashIn / $cashOut, 2) : 0,
                'treasury_runway_days' => $cashOut > $cashIn ? (int) round($treasuryTotal / $dailyBurn) : null,
            ],
        ]);
    }

    public function inventoryAccounting(?int $storeId): array
    {
        $storeValue = $this->inventoryStoreValue($storeId);
        $warehouseValue = $this->inventoryWarehouseValue($storeId);
        $losses = $this->inventoryLossTotals($storeId);

        return [
            'inventory_value' => $storeValue,
            'warehouse_value' => $warehouseValue,
            'damaged_losses' => $losses['damaged'],
            'expired_losses' => $losses['expired'],
            'total_losses' => round($losses['damaged'] + $losses['expired'], 2),
            'total_value' => round($storeValue + $warehouseValue, 2),
        ];
    }

    public function inventoryAccountingPage(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        if (!AccountingSchema::ready()) {
            return ['module_ready' => false];
        }

        $from = $from ?? date('Y-m-01');
        $to = $to ?? date('Y-m-d');
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $base = $this->inventoryAccounting($storeId);
        $losses = $this->inventoryLossTotals($storeId, $from, $to);
        $base['damaged_losses'] = $losses['damaged'];
        $base['expired_losses'] = $losses['expired'];
        $base['total_losses'] = round($losses['damaged'] + $losses['expired'], 2);

        $storeValue = (float) ($base['inventory_value'] ?? 0);
        $warehouseValue = (float) ($base['warehouse_value'] ?? 0);
        $totalValue = (float) ($base['total_value'] ?? 0);
        $totalLosses = (float) ($base['total_losses'] ?? 0);
        $counts = $this->inventoryProductCounts($storeId);
        $topProducts = $this->inventoryTopProducts($storeId, 10);
        $byCategory = $this->inventoryByCategory($storeId);
        $lowStock = $this->inventoryLowStock($storeId, 15);

        return array_merge($base, [
            'module_ready' => true,
            'period' => ['from' => $from, 'to' => $to],
            'composition' => [
                ['key' => 'store', 'amount' => $storeValue],
                ['key' => 'warehouse', 'amount' => $warehouseValue],
            ],
            'charts' => [
                'by_category' => $byCategory,
                'top_products' => array_map(static fn ($r) => [
                    'label' => $r['name'],
                    'amount' => $r['value'],
                    'sku' => $r['sku'],
                ], $topProducts),
                'loss_trend' => $this->inventoryLossTrend($storeId, $from, $to),
            ],
            'top_products' => $topProducts,
            'low_stock' => $lowStock,
            'insights' => [
                'sku_count' => $counts['sku_count'],
                'units_on_hand' => $counts['units_on_hand'],
                'low_stock_count' => $counts['low_stock_count'],
                'avg_unit_value' => $counts['units_on_hand'] > 0
                    ? round($storeValue / $counts['units_on_hand'], 2)
                    : 0,
                'warehouse_share' => $totalValue > 0 ? round($warehouseValue / $totalValue * 100, 1) : 0,
                'loss_ratio' => $totalValue > 0 ? round($totalLosses / $totalValue * 100, 1) : 0,
            ],
        ]);
    }

    private function inventoryStoreValue(?int $storeId): float
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = 'SELECT COALESCE(SUM(stock_quantity * cost), 0) FROM products WHERE deleted_at IS NULL';
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return round((float) $stmt->fetchColumn(), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    private function inventoryWarehouseValue(?int $storeId): float
    {
        $db = Database::getInstance()->getConnection();
        try {
            $db->query('SELECT 1 FROM warehouse_inventory LIMIT 1');
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
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            return round((float) $stmt->fetchColumn(), 2);
        } catch (Throwable) {
            return 0.0;
        }
    }

    /** @return array{damaged: float, expired: float} */
    private function inventoryLossTotals(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        $db = Database::getInstance()->getConnection();
        $out = ['damaged' => 0.0, 'expired' => 0.0];
        foreach (['damaged' => ['damaged', 'damage'], 'expired' => ['expired']] as $key => $reasons) {
            try {
                $placeholders = implode(',', array_fill(0, count($reasons), '?'));
                $sql = "SELECT COALESCE(SUM(ABS(il.change_amount) * p.cost), 0)
                        FROM inventory_logs il
                        INNER JOIN products p ON p.id = il.product_id
                        WHERE il.reason IN ($placeholders)";
                $params = $reasons;
                if ($storeId !== null) {
                    $sql .= ' AND il.store_id = ?';
                    $params[] = $storeId;
                }
                if ($from !== null && $to !== null) {
                    $sql .= ' AND DATE(il.created_at) BETWEEN ? AND ?';
                    $params[] = $from;
                    $params[] = $to;
                }
                $stmt = $db->prepare($sql);
                $stmt->execute($params);
                $out[$key] = round((float) $stmt->fetchColumn(), 2);
            } catch (Throwable) {
                $out[$key] = 0.0;
            }
        }
        return $out;
    }

    /** @return array{sku_count: int, units_on_hand: int, low_stock_count: int} */
    private function inventoryProductCounts(?int $storeId): array
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = 'SELECT
                        COUNT(*) AS sku_count,
                        COALESCE(SUM(stock_quantity), 0) AS units_on_hand,
                        COALESCE(SUM(CASE WHEN stock_quantity <= min_stock_level THEN 1 ELSE 0 END), 0) AS low_stock_count
                    FROM products
                    WHERE deleted_at IS NULL AND stock_quantity > 0';
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            return [
                'sku_count' => (int) ($row['sku_count'] ?? 0),
                'units_on_hand' => (int) ($row['units_on_hand'] ?? 0),
                'low_stock_count' => (int) ($row['low_stock_count'] ?? 0),
            ];
        } catch (Throwable) {
            return ['sku_count' => 0, 'units_on_hand' => 0, 'low_stock_count' => 0];
        }
    }

    private function inventoryTopProducts(?int $storeId, int $limit = 10): array
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = 'SELECT id, sku, name, stock_quantity, cost,
                           (stock_quantity * cost) AS value
                    FROM products
                    WHERE deleted_at IS NULL AND stock_quantity > 0';
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $sql .= ' ORDER BY value DESC LIMIT ' . max(1, min(50, $limit));
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'sku' => $r['sku'],
                'name' => $r['name'],
                'quantity' => (int) $r['stock_quantity'],
                'cost' => round((float) $r['cost'], 2),
                'value' => round((float) $r['value'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function inventoryByCategory(?int $storeId): array
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = 'SELECT COALESCE(c.name, ?) AS category,
                           COALESCE(SUM(p.stock_quantity * p.cost), 0) AS amount
                    FROM products p
                    LEFT JOIN categories c ON c.id = p.category_id
                    WHERE p.deleted_at IS NULL AND p.stock_quantity > 0';
            $params = ['Uncategorized'];
            if ($storeId !== null) {
                $sql .= ' AND p.store_id = ?';
                $params[] = $storeId;
            }
            $sql .= ' GROUP BY c.id, c.name ORDER BY amount DESC LIMIT 12';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'category' => $r['category'],
                'amount' => round((float) $r['amount'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function inventoryLowStock(?int $storeId, int $limit = 15): array
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = 'SELECT id, sku, name, stock_quantity, min_stock_level, cost,
                           (stock_quantity * cost) AS value
                    FROM products
                    WHERE deleted_at IS NULL
                      AND stock_quantity <= min_stock_level';
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $sql .= ' ORDER BY stock_quantity ASC, name ASC LIMIT ' . max(1, min(50, $limit));
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'id' => (int) $r['id'],
                'sku' => $r['sku'],
                'name' => $r['name'],
                'quantity' => (int) $r['stock_quantity'],
                'min_level' => (int) $r['min_stock_level'],
                'cost' => round((float) $r['cost'], 2),
                'value' => round((float) $r['value'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function inventoryLossTrend(?int $storeId, string $from, string $to): array
    {
        $db = Database::getInstance()->getConnection();
        try {
            $sql = "SELECT DATE(il.created_at) AS day,
                           COALESCE(SUM(ABS(il.change_amount) * p.cost), 0) AS amount
                    FROM inventory_logs il
                    INNER JOIN products p ON p.id = il.product_id
                    WHERE il.reason IN ('damaged', 'damage', 'expired')
                      AND DATE(il.created_at) BETWEEN ? AND ?";
            $params = [$from, $to];
            if ($storeId !== null) {
                $sql .= ' AND il.store_id = ?';
                $params[] = $storeId;
            }
            $sql .= ' GROUP BY DATE(il.created_at) ORDER BY day ASC';
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'day' => $r['day'],
                'amount' => round((float) $r['amount'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }
}

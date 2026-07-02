<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../AccountingSchema.php';

class AccountRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(?int $storeId = null, ?string $type = null): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT * FROM acc_accounts WHERE is_active = 1 AND (store_id IS NULL';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' OR store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ')';
        if ($type !== null && $type !== 'all') {
            $sql .= ' AND account_type = ?';
            $params[] = $type;
        }
        $sql .= ' ORDER BY code';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function findByCode(string $code, ?int $storeId = null): ?array
    {
        if (!AccountingSchema::ready($this->db)) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM acc_accounts WHERE code = ? AND (store_id IS NULL OR store_id = ?) AND is_active = 1 ORDER BY store_id IS NULL DESC LIMIT 1'
        );
        $stmt->execute([$code, $storeId ?? 0]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function findById(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM acc_accounts WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_accounts (store_id, code, name, account_type, account_subtype, parent_id, normal_balance, is_system, description)
             VALUES (?, ?, ?, ?, ?, ?, ?, 0, ?)'
        );
        $stmt->execute([
            $data['store_id'] ?? null,
            $data['code'],
            $data['name'],
            $data['account_type'],
            $data['account_subtype'] ?? null,
            $data['parent_id'] ?? null,
            $data['normal_balance'] ?? ($data['account_type'] === 'asset' || $data['account_type'] === 'expense' ? 'debit' : 'credit'),
            $data['description'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listFiltered(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT a.*, p.name AS parent_name, p.code AS parent_code
                FROM acc_accounts a
                LEFT JOIN acc_accounts p ON p.id = a.parent_id
                WHERE a.is_active = 1 AND (a.store_id IS NULL';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' OR a.store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ')';
        if (!empty($filters['type']) && $filters['type'] !== 'all') {
            $sql .= ' AND a.account_type = ?';
            $params[] = $filters['type'];
        }
        if (!empty($filters['scope']) && $filters['scope'] === 'system') {
            $sql .= ' AND a.is_system = 1';
        } elseif (!empty($filters['scope']) && $filters['scope'] === 'custom') {
            $sql .= ' AND a.is_system = 0';
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (a.code LIKE ? OR a.name LIKE ? OR a.description LIKE ? OR a.account_subtype LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
        $sql .= ' ORDER BY a.code';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function chartOfAccountsPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $from = $filters['from'] ?? null;
        $to = $filters['to'] ?? date('Y-m-d');
        $accounts = $this->listFiltered($storeId, $filters);
        $balanceMap = $this->balances($storeId, $from ?: null, $to);

        $rows = array_map(static function (array $a) use ($balanceMap) {
            $a['balance'] = $balanceMap[(int) $a['id']] ?? 0.0;
            return $a;
        }, $accounts);

        $stats = $this->chartOfAccountsStats($rows);

        return [
            'module_ready' => true,
            'rows' => $rows,
            'stats' => $stats,
            'charts' => [
                'by_type' => $this->coaByType($rows),
                'top_accounts' => $this->coaTopAccounts($rows),
                'count_by_type' => $this->coaCountByType($rows),
            ],
            'insights' => [
                'account_count' => $stats['total_accounts'],
                'system_count' => $stats['system_count'],
                'custom_count' => $stats['custom_count'],
                'top_account' => $stats['top_account'] ?? '—',
            ],
            'account_types' => ['asset', 'liability', 'equity', 'revenue', 'expense'],
        ];
    }

    public function chartOfAccountsStats(array $rows): array
    {
        $byType = [
            'asset' => 0.0,
            'liability' => 0.0,
            'equity' => 0.0,
            'revenue' => 0.0,
            'expense' => 0.0,
        ];
        $systemCount = 0;
        $customCount = 0;
        $topAccount = null;
        $topBalance = 0.0;

        foreach ($rows as $row) {
            $type = $row['account_type'] ?? '';
            $bal = (float) ($row['balance'] ?? 0);
            if (isset($byType[$type])) {
                $byType[$type] += $bal;
            }
            if (!empty($row['is_system'])) {
                $systemCount++;
            } else {
                $customCount++;
            }
            if ($row['account_subtype'] !== 'header' && abs($bal) > abs($topBalance)) {
                $topBalance = $bal;
                $topAccount = ($row['code'] ?? '') . ' — ' . ($row['name'] ?? '');
            }
        }

        foreach ($byType as $k => $v) {
            $byType[$k] = round($v, 2);
        }

        return [
            'total_accounts' => count($rows),
            'system_count' => $systemCount,
            'custom_count' => $customCount,
            'asset_balance' => $byType['asset'],
            'liability_balance' => $byType['liability'],
            'equity_balance' => $byType['equity'],
            'revenue_balance' => $byType['revenue'],
            'expense_balance' => $byType['expense'],
            'top_account' => $topAccount,
        ];
    }

    private function coaByType(array $rows): array
    {
        $sums = [];
        foreach ($rows as $row) {
            if (($row['account_subtype'] ?? '') === 'header') {
                continue;
            }
            $type = $row['account_type'] ?? 'asset';
            $sums[$type] = ($sums[$type] ?? 0) + abs((float) ($row['balance'] ?? 0));
        }
        $out = [];
        foreach ($sums as $type => $amount) {
            if ($amount > 0) {
                $out[] = ['type' => $type, 'amount' => round($amount, 2)];
            }
        }
        usort($out, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
        return $out;
    }

    private function coaTopAccounts(array $rows): array
    {
        $items = [];
        foreach ($rows as $row) {
            if (($row['account_subtype'] ?? '') === 'header') {
                continue;
            }
            $bal = abs((float) ($row['balance'] ?? 0));
            if ($bal <= 0) {
                continue;
            }
            $items[] = [
                'label' => ($row['code'] ?? '') . ' ' . ($row['name'] ?? ''),
                'amount' => round($bal, 2),
                'type' => $row['account_type'] ?? 'asset',
            ];
        }
        usort($items, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
        return array_slice($items, 0, 12);
    }

    private function coaCountByType(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $type = $row['account_type'] ?? 'asset';
            $counts[$type] = ($counts[$type] ?? 0) + 1;
        }
        $out = [];
        foreach ($counts as $type => $count) {
            $out[] = ['type' => $type, 'count' => $count];
        }
        usort($out, static fn ($a, $b) => $b['count'] <=> $a['count']);
        return $out;
    }

    /**
     * @return array<string, float> account_id => balance (debit positive for assets/expenses)
     */
    public function balances(?int $storeId, ?string $from = null, ?string $to = null): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT jl.account_id, a.normal_balance, a.account_type,
                       COALESCE(SUM(jl.debit), 0) AS total_debit,
                       COALESCE(SUM(jl.credit), 0) AS total_credit
                FROM acc_journal_lines jl
                INNER JOIN acc_journal_entries je ON je.id = jl.journal_entry_id
                INNER JOIN acc_accounts a ON a.id = jl.account_id
                WHERE je.status = \'posted\'';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND je.store_id = ?';
            $params[] = $storeId;
        }
        if ($from) {
            $sql .= ' AND je.entry_date >= ?';
            $params[] = $from;
        }
        if ($to) {
            $sql .= ' AND je.entry_date <= ?';
            $params[] = $to;
        }
        $sql .= ' GROUP BY jl.account_id, a.normal_balance, a.account_type';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $out = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $debit = (float) $row['total_debit'];
            $credit = (float) $row['total_credit'];
            $bal = in_array($row['account_type'], ['asset', 'expense'], true)
                ? $debit - $credit
                : $credit - $debit;
            $out[(int) $row['account_id']] = round($bal, 2);
        }
        return $out;
    }
}

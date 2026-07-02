<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../AccountingSchema.php';

class TreasuryRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    // ── Cash ──
    public function listCashAccounts(?int $storeId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT * FROM acc_cash_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY name');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cashBalance(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(current_balance), 0) FROM acc_cash_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function addCashTransaction(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_cash_transactions (cash_account_id, store_id, transaction_type, amount, balance_after, reference, notes, transaction_date, created_by, journal_entry_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['cash_account_id'],
            (int) $data['store_id'],
            $data['transaction_type'],
            round((float) $data['amount'], 2),
            $data['balance_after'] ?? null,
            $data['reference'] ?? null,
            $data['notes'] ?? null,
            $data['transaction_date'] ?? date('Y-m-d'),
            $data['created_by'] ?? null,
            $data['journal_entry_id'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function updateCashBalance(int $id, float $balance): void
    {
        $this->db->prepare('UPDATE acc_cash_accounts SET current_balance = ? WHERE id = ?')->execute([round($balance, 2), $id]);
    }

    public function ensureDefaultCashAccount(int $storeId): int
    {
        $stmt = $this->db->prepare('SELECT id FROM acc_cash_accounts WHERE store_id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$storeId]);
        $id = $stmt->fetchColumn();
        if ($id) {
            return (int) $id;
        }
        $ins = $this->db->prepare(
            'INSERT INTO acc_cash_accounts (store_id, name, opening_balance, current_balance) VALUES (?, ?, 0, 0)'
        );
        $ins->execute([$storeId, 'Main Cash Register']);
        return (int) $this->db->lastInsertId();
    }

    public function createCashAccount(array $data): int
    {
        $bal = round((float) ($data['opening_balance'] ?? 0), 2);
        $stmt = $this->db->prepare(
            'INSERT INTO acc_cash_accounts (store_id, name, opening_balance, current_balance) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['store_id'],
            $data['name'],
            $bal,
            $bal,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listCashTransactions(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT t.*, c.name AS register_name, u.name AS created_by_name
                FROM acc_cash_transactions t
                INNER JOIN acc_cash_accounts c ON c.id = t.cash_account_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE c.is_active = 1';
        $params = [];
        $this->applyCashTxFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(500, (int) $filters['limit']));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function cashManagementPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $accounts = $this->listCashAccounts($storeId);
        $stats = $this->cashManagementStats($storeId, $filters);

        return [
            'module_ready' => true,
            'accounts' => $accounts,
            'rows' => $this->listCashTransactions($storeId, $filters),
            'stats' => $stats,
            'charts' => [
                'by_type' => $this->cashByType($storeId, $filters),
                'trend' => $this->cashTrend($storeId, $filters),
                'account_balances' => array_map(static fn ($a) => [
                    'label' => $a['name'],
                    'amount' => round((float) $a['current_balance'], 2),
                ], $accounts),
            ],
            'insights' => [
                'register_count' => $stats['register_count'],
                'avg_balance' => $stats['register_count'] > 0
                    ? round($stats['total_balance'] / $stats['register_count'], 2)
                    : 0,
                'in_out_ratio' => $stats['out_amount'] > 0
                    ? round($stats['in_amount'] / $stats['out_amount'], 2)
                    : 0,
                'top_register' => $stats['top_register'] ?? '—',
            ],
            'transaction_types' => ['deposit', 'withdrawal', 'opening', 'closing', 'transfer', 'sale', 'expense'],
        ];
    }

    public function cashManagementStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_balance' => 0, 'register_count' => 0,
                'in_amount' => 0, 'out_amount' => 0, 'net_flow' => 0, 'transaction_count' => 0,
                'top_register' => null,
            ];
        }

        $balSql = 'SELECT COALESCE(SUM(current_balance), 0), COUNT(*) FROM acc_cash_accounts WHERE is_active = 1';
        $balParams = [];
        if ($storeId !== null) {
            $balSql .= ' AND store_id = ?';
            $balParams[] = $storeId;
        }
        $stmt = $this->db->prepare($balSql);
        $stmt->execute($balParams);
        [$totalBalance, $registerCount] = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

        $inTypes = "'deposit','opening','sale'";
        $outTypes = "'withdrawal','closing','expense','transfer'";
        $txSql = "SELECT
                    COALESCE(SUM(CASE WHEN t.transaction_type IN ($inTypes) THEN t.amount ELSE 0 END), 0) AS in_amount,
                    COALESCE(SUM(CASE WHEN t.transaction_type IN ($outTypes) THEN t.amount ELSE 0 END), 0) AS out_amount,
                    COUNT(*) AS transaction_count
                  FROM acc_cash_transactions t
                  INNER JOIN acc_cash_accounts c ON c.id = t.cash_account_id
                  WHERE c.is_active = 1";
        $txParams = [];
        $this->applyCashTxFilters($txSql, $txParams, $storeId, $filters, false);
        $stmt = $this->db->prepare($txSql);
        $stmt->execute($txParams);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $inAmount = (float) ($tx['in_amount'] ?? 0);
        $outAmount = (float) ($tx['out_amount'] ?? 0);

        $topSql = 'SELECT name FROM acc_cash_accounts WHERE is_active = 1';
        $topParams = [];
        if ($storeId !== null) {
            $topSql .= ' AND store_id = ?';
            $topParams[] = $storeId;
        }
        $topSql .= ' ORDER BY current_balance DESC LIMIT 1';
        $stmt = $this->db->prepare($topSql);
        $stmt->execute($topParams);
        $topRegister = $stmt->fetchColumn() ?: null;

        return [
            'total_balance' => round((float) $totalBalance, 2),
            'register_count' => (int) $registerCount,
            'in_amount' => round($inAmount, 2),
            'out_amount' => round($outAmount, 2),
            'net_flow' => round($inAmount - $outAmount, 2),
            'transaction_count' => (int) ($tx['transaction_count'] ?? 0),
            'top_register' => $topRegister,
        ];
    }

    public function recordCashTransaction(array $data): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['status' => 'error', 'message' => 'Accounting module not ready'];
        }

        $accountId = (int) ($data['cash_account_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $type = $data['transaction_type'] ?? 'deposit';
        $allowed = ['deposit', 'withdrawal', 'opening', 'closing', 'transfer', 'sale', 'expense'];
        if (!in_array($type, $allowed, true)) {
            $type = 'deposit';
        }

        if ($accountId <= 0 || $amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid cash transaction'];
        }

        $stmt = $this->db->prepare('SELECT id, store_id, current_balance FROM acc_cash_accounts WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return ['status' => 'error', 'message' => 'Cash register not found'];
        }

        $cur = (float) $account['current_balance'];
        $isOut = in_array($type, ['withdrawal', 'closing', 'expense', 'transfer'], true);
        $newBal = $isOut ? $cur - $amount : $cur + $amount;
        if ($isOut && $newBal < -0.001) {
            return ['status' => 'error', 'message' => 'Insufficient cash balance'];
        }

        $this->db->beginTransaction();
        try {
            $txId = $this->addCashTransaction([
                'cash_account_id' => $accountId,
                'store_id' => (int) $account['store_id'],
                'transaction_type' => $type,
                'amount' => $amount,
                'balance_after' => round($newBal, 2),
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
                'transaction_date' => $data['transaction_date'] ?? date('Y-m-d'),
                'created_by' => $data['created_by'] ?? null,
                'journal_entry_id' => $data['journal_entry_id'] ?? null,
            ]);
            $this->updateCashBalance($accountId, $newBal);
            $this->db->commit();
            return ['status' => 'success', 'data' => ['id' => $txId, 'balance' => round($newBal, 2)]];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function cashByType(?int $storeId, array $filters): array
    {
        $sql = 'SELECT t.transaction_type AS type, COALESCE(SUM(t.amount), 0) AS amount, COUNT(*) AS count
                FROM acc_cash_transactions t
                INNER JOIN acc_cash_accounts c ON c.id = t.cash_account_id
                WHERE c.is_active = 1';
        $params = [];
        $this->applyCashTxFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY t.transaction_type HAVING amount > 0 ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'type' => $r['type'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function cashTrend(?int $storeId, array $filters): array
    {
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $inTypes = "'deposit','opening','sale'";
        $outTypes = "'withdrawal','closing','expense','transfer'";
        $sql = "SELECT t.transaction_date AS day,
                       COALESCE(SUM(CASE WHEN t.transaction_type IN ($inTypes) THEN t.amount ELSE 0 END), 0) AS in_amount,
                       COALESCE(SUM(CASE WHEN t.transaction_type IN ($outTypes) THEN t.amount ELSE 0 END), 0) AS out_amount
                FROM acc_cash_transactions t
                INNER JOIN acc_cash_accounts c ON c.id = t.cash_account_id
                WHERE c.is_active = 1 AND t.transaction_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['cash_account_id'])) {
            $sql .= ' AND t.cash_account_id = ?';
            $params[] = (int) $filters['cash_account_id'];
        }
        $sql .= ' GROUP BY t.transaction_date ORDER BY day ASC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'day' => $r['day'],
                'in_amount' => round((float) $r['in_amount'], 2),
                'out_amount' => round((float) $r['out_amount'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function applyCashTxFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeType = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND t.transaction_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND t.transaction_date <= ?';
            $params[] = $filters['to'];
        }
        if ($includeType && !empty($filters['type']) && $filters['type'] !== 'all') {
            $sql .= ' AND t.transaction_type = ?';
            $params[] = $filters['type'];
        } elseif ($includeType && !empty($filters['flow']) && $filters['flow'] !== 'all') {
            if ($filters['flow'] === 'in') {
                $sql .= " AND t.transaction_type IN ('deposit','opening','sale')";
            } elseif ($filters['flow'] === 'out') {
                $sql .= " AND t.transaction_type IN ('withdrawal','closing','expense','transfer')";
            }
        }
        if (!empty($filters['cash_account_id'])) {
            $sql .= ' AND t.cash_account_id = ?';
            $params[] = (int) $filters['cash_account_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (c.name LIKE ? OR t.reference LIKE ? OR t.notes LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    // ── Bank ──
    public function listBankAccounts(?int $storeId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT * FROM acc_bank_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY bank_name, account_name');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function bankBalance(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(current_balance), 0) FROM acc_bank_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function createBankAccount(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_bank_accounts (store_id, bank_name, account_name, account_number, account_id, currency, opening_balance, current_balance)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $bal = round((float) ($data['opening_balance'] ?? 0), 2);
        $stmt->execute([
            (int) $data['store_id'],
            $data['bank_name'],
            $data['account_name'],
            $data['account_number'] ?? null,
            $data['account_id'] ?? null,
            $data['currency'] ?? 'FCFA',
            $bal,
            $bal,
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listBankTransactions(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT t.*, b.bank_name, b.account_name, b.account_number, b.currency,
                       u.name AS created_by_name
                FROM acc_bank_transactions t
                INNER JOIN acc_bank_accounts b ON b.id = t.bank_account_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE b.is_active = 1';
        $params = [];
        $this->applyBankTxFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(500, (int) $filters['limit']));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function bankAccountsPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $accounts = $this->listBankAccounts($storeId);
        $stats = $this->bankAccountsStats($storeId, $filters);

        return [
            'module_ready' => true,
            'accounts' => $accounts,
            'rows' => $this->listBankTransactions($storeId, $filters),
            'stats' => $stats,
            'charts' => [
                'by_bank' => $this->bankByInstitution($storeId),
                'trend' => $this->bankTrend($storeId, $filters),
                'account_balances' => array_map(static fn ($a) => [
                    'label' => $a['account_name'],
                    'bank' => $a['bank_name'],
                    'amount' => round((float) $a['current_balance'], 2),
                ], $accounts),
            ],
            'insights' => [
                'account_count' => $stats['account_count'],
                'avg_balance' => $stats['account_count'] > 0
                    ? round($stats['total_balance'] / $stats['account_count'], 2)
                    : 0,
                'in_out_ratio' => $stats['out_amount'] > 0
                    ? round($stats['in_amount'] / $stats['out_amount'], 2)
                    : 0,
                'top_bank' => $stats['top_bank'] ?? '—',
            ],
            'transaction_types' => ['deposit', 'withdrawal', 'transfer', 'fee', 'reconciliation'],
        ];
    }

    public function bankAccountsStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_balance' => 0, 'account_count' => 0,
                'in_amount' => 0, 'out_amount' => 0, 'net_flow' => 0, 'transaction_count' => 0,
                'top_bank' => null,
            ];
        }

        $balSql = 'SELECT COALESCE(SUM(current_balance), 0), COUNT(*) FROM acc_bank_accounts WHERE is_active = 1';
        $balParams = [];
        if ($storeId !== null) {
            $balSql .= ' AND store_id = ?';
            $balParams[] = $storeId;
        }
        $stmt = $this->db->prepare($balSql);
        $stmt->execute($balParams);
        [$totalBalance, $accountCount] = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

        $txSql = "SELECT
                    COALESCE(SUM(CASE WHEN t.transaction_type IN ('deposit','reconciliation') THEN t.amount ELSE 0 END), 0) AS in_amount,
                    COALESCE(SUM(CASE WHEN t.transaction_type IN ('withdrawal','transfer','fee') THEN t.amount ELSE 0 END), 0) AS out_amount,
                    COUNT(*) AS transaction_count
                  FROM acc_bank_transactions t
                  INNER JOIN acc_bank_accounts b ON b.id = t.bank_account_id
                  WHERE b.is_active = 1";
        $txParams = [];
        $this->applyBankTxFilters($txSql, $txParams, $storeId, $filters, false);
        $stmt = $this->db->prepare($txSql);
        $stmt->execute($txParams);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $inAmount = (float) ($tx['in_amount'] ?? 0);
        $outAmount = (float) ($tx['out_amount'] ?? 0);

        $bankRows = $this->bankByInstitution($storeId);
        $topBank = $bankRows[0]['bank'] ?? null;

        return [
            'total_balance' => round((float) $totalBalance, 2),
            'account_count' => (int) $accountCount,
            'in_amount' => round($inAmount, 2),
            'out_amount' => round($outAmount, 2),
            'net_flow' => round($inAmount - $outAmount, 2),
            'transaction_count' => (int) ($tx['transaction_count'] ?? 0),
            'top_bank' => $topBank,
        ];
    }

    public function addBankTransaction(array $data): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['status' => 'error', 'message' => 'Accounting module not ready'];
        }

        $accountId = (int) ($data['bank_account_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $type = $data['transaction_type'] ?? 'deposit';
        $allowed = ['deposit', 'withdrawal', 'transfer', 'fee', 'reconciliation'];
        if (!in_array($type, $allowed, true)) {
            $type = 'deposit';
        }

        if ($accountId <= 0 || $amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid bank transaction'];
        }

        $stmt = $this->db->prepare('SELECT id, store_id, current_balance FROM acc_bank_accounts WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return ['status' => 'error', 'message' => 'Bank account not found'];
        }

        $cur = (float) $account['current_balance'];
        $isOut = in_array($type, ['withdrawal', 'transfer', 'fee'], true);
        $newBal = $isOut ? $cur - $amount : $cur + $amount;
        if ($isOut && $newBal < -0.001) {
            return ['status' => 'error', 'message' => 'Insufficient account balance'];
        }

        $this->db->beginTransaction();
        try {
            $ins = $this->db->prepare(
                'INSERT INTO acc_bank_transactions (bank_account_id, store_id, transaction_type, amount, reference, reconciled, transaction_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $accountId,
                (int) $account['store_id'],
                $type,
                $amount,
                $data['reference'] ?? null,
                !empty($data['reconciled']) ? 1 : 0,
                $data['transaction_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? null,
            ]);
            $txId = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE acc_bank_accounts SET current_balance = ? WHERE id = ?')
                ->execute([round($newBal, 2), $accountId]);
            $this->db->commit();
            return ['status' => 'success', 'data' => ['id' => $txId, 'balance' => round($newBal, 2)]];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function bankByInstitution(?int $storeId): array
    {
        $sql = 'SELECT bank_name AS bank, COALESCE(SUM(current_balance), 0) AS amount, COUNT(*) AS count
                FROM acc_bank_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY bank_name HAVING amount > 0 ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'bank' => $r['bank'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function bankTrend(?int $storeId, array $filters): array
    {
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $sql = "SELECT t.transaction_date AS day,
                       COALESCE(SUM(CASE WHEN t.transaction_type IN ('deposit','reconciliation') THEN t.amount ELSE 0 END), 0) AS in_amount,
                       COALESCE(SUM(CASE WHEN t.transaction_type IN ('withdrawal','transfer','fee') THEN t.amount ELSE 0 END), 0) AS out_amount
                FROM acc_bank_transactions t
                INNER JOIN acc_bank_accounts b ON b.id = t.bank_account_id
                WHERE b.is_active = 1 AND t.transaction_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['bank_account_id'])) {
            $sql .= ' AND t.bank_account_id = ?';
            $params[] = (int) $filters['bank_account_id'];
        }
        $sql .= ' GROUP BY t.transaction_date ORDER BY day ASC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'day' => $r['day'],
                'in_amount' => round((float) $r['in_amount'], 2),
                'out_amount' => round((float) $r['out_amount'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function applyBankTxFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeType = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND t.transaction_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND t.transaction_date <= ?';
            $params[] = $filters['to'];
        }
        if ($includeType && !empty($filters['type']) && $filters['type'] !== 'all') {
            $sql .= ' AND t.transaction_type = ?';
            $params[] = $filters['type'];
        } elseif ($includeType && !empty($filters['flow']) && $filters['flow'] !== 'all') {
            if ($filters['flow'] === 'in') {
                $sql .= " AND t.transaction_type IN ('deposit','reconciliation')";
            } elseif ($filters['flow'] === 'out') {
                $sql .= " AND t.transaction_type IN ('withdrawal','transfer','fee')";
            }
        }
        if (!empty($filters['bank_account_id'])) {
            $sql .= ' AND t.bank_account_id = ?';
            $params[] = (int) $filters['bank_account_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (b.bank_name LIKE ? OR b.account_name LIKE ? OR b.account_number LIKE ? OR t.reference LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    // ── Mobile Money ──
    public function listMobileAccounts(?int $storeId): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT * FROM acc_mobile_money_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql . ' ORDER BY provider, label');
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function mobileBalance(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = 'SELECT COALESCE(SUM(current_balance), 0) FROM acc_mobile_money_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function createMobileAccount(array $data): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO acc_mobile_money_accounts (store_id, provider, label, phone_number, account_id, current_balance)
             VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            (int) $data['store_id'],
            $data['provider'] ?? 'mtn',
            $data['label'],
            $data['phone_number'] ?? null,
            $data['account_id'] ?? null,
            round((float) ($data['current_balance'] ?? 0), 2),
        ]);
        return (int) $this->db->lastInsertId();
    }

    public function listMobileTransactions(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        $sql = 'SELECT t.*, m.label AS wallet_label, m.provider, m.phone_number,
                       u.name AS created_by_name
                FROM acc_mobile_money_transactions t
                INNER JOIN acc_mobile_money_accounts m ON m.id = t.mobile_account_id
                LEFT JOIN users u ON u.id = t.created_by
                WHERE m.is_active = 1';
        $params = [];
        $this->applyMobileTxFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY t.transaction_date DESC, t.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(500, (int) $filters['limit']));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function mobileMoneyPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $accounts = $this->listMobileAccounts($storeId);
        $stats = $this->mobileMoneyStats($storeId, $filters);

        return [
            'module_ready' => true,
            'accounts' => $accounts,
            'rows' => $this->listMobileTransactions($storeId, $filters),
            'stats' => $stats,
            'charts' => [
                'by_provider' => $this->mobileByProvider($storeId),
                'trend' => $this->mobileTrend($storeId, $filters),
                'wallet_balances' => array_map(static fn ($a) => [
                    'label' => $a['label'],
                    'amount' => round((float) $a['current_balance'], 2),
                    'provider' => $a['provider'],
                ], $accounts),
            ],
            'insights' => [
                'wallet_count' => $stats['wallet_count'],
                'avg_balance' => $stats['wallet_count'] > 0
                    ? round($stats['total_balance'] / $stats['wallet_count'], 2)
                    : 0,
                'in_out_ratio' => $stats['out_amount'] > 0
                    ? round($stats['in_amount'] / $stats['out_amount'], 2)
                    : 0,
                'top_provider' => $stats['top_provider'] ?? '—',
            ],
            'providers' => ['mtn', 'orange', 'moov', 'airtel', 'vodafone', 'other'],
        ];
    }

    public function mobileMoneyStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'total_balance' => 0, 'wallet_count' => 0,
                'in_amount' => 0, 'out_amount' => 0, 'net_flow' => 0, 'transaction_count' => 0,
                'top_provider' => null,
            ];
        }

        $balSql = 'SELECT COALESCE(SUM(current_balance), 0), COUNT(*) FROM acc_mobile_money_accounts WHERE is_active = 1';
        $balParams = [];
        if ($storeId !== null) {
            $balSql .= ' AND store_id = ?';
            $balParams[] = $storeId;
        }
        $stmt = $this->db->prepare($balSql);
        $stmt->execute($balParams);
        [$totalBalance, $walletCount] = $stmt->fetch(PDO::FETCH_NUM) ?: [0, 0];

        $txSql = "SELECT
                    COALESCE(SUM(CASE WHEN t.direction = 'in' THEN t.amount ELSE 0 END), 0) AS in_amount,
                    COALESCE(SUM(CASE WHEN t.direction = 'out' THEN t.amount ELSE 0 END), 0) AS out_amount,
                    COUNT(*) AS transaction_count
                  FROM acc_mobile_money_transactions t
                  INNER JOIN acc_mobile_money_accounts m ON m.id = t.mobile_account_id
                  WHERE m.is_active = 1";
        $txParams = [];
        $this->applyMobileTxFilters($txSql, $txParams, $storeId, $filters, false);
        $stmt = $this->db->prepare($txSql);
        $stmt->execute($txParams);
        $tx = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        $inAmount = (float) ($tx['in_amount'] ?? 0);
        $outAmount = (float) ($tx['out_amount'] ?? 0);

        $providerRows = $this->mobileByProvider($storeId);
        $topProvider = $providerRows[0]['provider'] ?? null;

        return [
            'total_balance' => round((float) $totalBalance, 2),
            'wallet_count' => (int) $walletCount,
            'in_amount' => round($inAmount, 2),
            'out_amount' => round($outAmount, 2),
            'net_flow' => round($inAmount - $outAmount, 2),
            'transaction_count' => (int) ($tx['transaction_count'] ?? 0),
            'top_provider' => $topProvider,
        ];
    }

    public function addMobileTransaction(array $data): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['status' => 'error', 'message' => 'Accounting module not ready'];
        }

        $accountId = (int) ($data['mobile_account_id'] ?? 0);
        $amount = round((float) ($data['amount'] ?? 0), 2);
        $direction = ($data['direction'] ?? 'in') === 'out' ? 'out' : 'in';

        if ($accountId <= 0 || $amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid mobile transaction'];
        }

        $stmt = $this->db->prepare('SELECT id, store_id, current_balance FROM acc_mobile_money_accounts WHERE id = ? AND is_active = 1 LIMIT 1');
        $stmt->execute([$accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$account) {
            return ['status' => 'error', 'message' => 'Wallet not found'];
        }

        $cur = (float) $account['current_balance'];
        $newBal = $direction === 'out' ? $cur - $amount : $cur + $amount;
        if ($direction === 'out' && $newBal < -0.001) {
            return ['status' => 'error', 'message' => 'Insufficient wallet balance'];
        }

        $this->db->beginTransaction();
        try {
            $ins = $this->db->prepare(
                'INSERT INTO acc_mobile_money_transactions (mobile_account_id, store_id, direction, amount, external_ref, reference, transaction_date, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            );
            $ins->execute([
                $accountId,
                (int) $account['store_id'],
                $direction,
                $amount,
                $data['external_ref'] ?? null,
                $data['reference'] ?? null,
                $data['transaction_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? null,
            ]);
            $txId = (int) $this->db->lastInsertId();
            $this->db->prepare('UPDATE acc_mobile_money_accounts SET current_balance = ? WHERE id = ?')
                ->execute([round($newBal, 2), $accountId]);
            $this->db->commit();
            return ['status' => 'success', 'data' => ['id' => $txId, 'balance' => round($newBal, 2)]];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    private function mobileByProvider(?int $storeId): array
    {
        $sql = 'SELECT provider, COALESCE(SUM(current_balance), 0) AS amount, COUNT(*) AS count
                FROM acc_mobile_money_accounts WHERE is_active = 1';
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $sql .= ' GROUP BY provider HAVING amount > 0 ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'provider' => $r['provider'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function mobileTrend(?int $storeId, array $filters): array
    {
        $from = $filters['from'] ?? date('Y-m-01');
        $to = $filters['to'] ?? date('Y-m-d');
        $sql = "SELECT t.transaction_date AS day,
                       COALESCE(SUM(CASE WHEN t.direction = 'in' THEN t.amount ELSE 0 END), 0) AS in_amount,
                       COALESCE(SUM(CASE WHEN t.direction = 'out' THEN t.amount ELSE 0 END), 0) AS out_amount
                FROM acc_mobile_money_transactions t
                INNER JOIN acc_mobile_money_accounts m ON m.id = t.mobile_account_id
                WHERE m.is_active = 1 AND t.transaction_date BETWEEN ? AND ?";
        $params = [$from, $to];
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['provider']) && $filters['provider'] !== 'all') {
            $sql .= ' AND m.provider = ?';
            $params[] = $filters['provider'];
        }
        $sql .= ' GROUP BY t.transaction_date ORDER BY day ASC';
        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
            return array_map(static fn ($r) => [
                'day' => $r['day'],
                'in_amount' => round((float) $r['in_amount'], 2),
                'out_amount' => round((float) $r['out_amount'], 2),
            ], $rows);
        } catch (Throwable) {
            return [];
        }
    }

    private function applyMobileTxFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeDirection = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND t.store_id = ?';
            $params[] = $storeId;
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND t.transaction_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND t.transaction_date <= ?';
            $params[] = $filters['to'];
        }
        if ($includeDirection && !empty($filters['direction']) && $filters['direction'] !== 'all') {
            $sql .= ' AND t.direction = ?';
            $params[] = $filters['direction'];
        }
        if (!empty($filters['provider']) && $filters['provider'] !== 'all') {
            $sql .= ' AND m.provider = ?';
            $params[] = $filters['provider'];
        }
        if (!empty($filters['wallet_id'])) {
            $sql .= ' AND t.mobile_account_id = ?';
            $params[] = (int) $filters['wallet_id'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (m.label LIKE ? OR m.phone_number LIKE ? OR t.reference LIKE ? OR t.external_ref LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    // ── Receivables / Payables ──
    public function receivableBalance(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(amount - amount_paid), 0) FROM acc_receivables WHERE status IN ('open','partial','overdue')";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function payableBalance(?int $storeId): float
    {
        if (!AccountingSchema::ready($this->db)) {
            return 0.0;
        }
        $sql = "SELECT COALESCE(SUM(amount - amount_paid), 0) FROM acc_payables WHERE status IN ('open','partial','overdue')";
        $params = [];
        if ($storeId !== null) {
            $sql .= ' AND store_id = ?';
            $params[] = $storeId;
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return round((float) $stmt->fetchColumn(), 2);
    }

    public function listReceivables(?int $storeId, ?string $status = null, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        if ($status !== null && $status !== '' && !isset($filters['status'])) {
            $filters['status'] = $status;
        }
        $sql = 'SELECT ar.*, c.name AS customer_name,
                       (ar.amount - ar.amount_paid) AS balance,
                       CASE WHEN ar.status NOT IN (\'paid\', \'written_off\') AND ar.due_date IS NOT NULL AND ar.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
                FROM acc_receivables ar
                LEFT JOIN customers c ON c.id = ar.customer_id WHERE 1=1';
        $params = [];
        $this->applyReceivableFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY ar.due_date ASC, ar.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(500, (int) $filters['limit']));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function receivablesPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $this->syncReceivableOverdueStatuses($storeId);

        $stats = $this->receivablesStats($storeId, $filters);
        $activeCount = $stats['open_count'] + $stats['partial_count'] + $stats['overdue_count'];

        return [
            'module_ready' => true,
            'rows' => $this->listReceivables($storeId, null, $filters),
            'stats' => $stats,
            'charts' => [
                'by_status' => $this->receivablesByStatus($storeId, $filters),
                'by_customer' => $this->receivablesByCustomer($storeId, $filters, 8),
                'aging' => $this->receivablesAging($storeId, $filters),
            ],
            'insights' => [
                'customer_count' => $stats['customer_count'],
                'avg_balance' => $activeCount > 0
                    ? round($stats['outstanding'] / $activeCount, 2)
                    : 0,
                'overdue_ratio' => $stats['outstanding'] > 0
                    ? round($stats['overdue_amount'] / $stats['outstanding'] * 100, 1)
                    : 0,
                'collected_ratio' => $stats['total_invoiced'] > 0
                    ? round($stats['total_paid'] / $stats['total_invoiced'] * 100, 1)
                    : 0,
            ],
        ];
    }

    public function receivablesStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'outstanding' => 0, 'total_invoiced' => 0, 'total_paid' => 0,
                'open_count' => 0, 'partial_count' => 0, 'overdue_count' => 0, 'paid_count' => 0,
                'written_off_count' => 0,
                'open_amount' => 0, 'partial_amount' => 0, 'overdue_amount' => 0, 'paid_amount' => 0,
                'written_off_amount' => 0, 'customer_count' => 0,
            ];
        }

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN ar.status IN ('open','partial','overdue') THEN ar.amount - ar.amount_paid ELSE 0 END), 0) AS outstanding,
                    COALESCE(SUM(ar.amount), 0) AS total_invoiced,
                    COALESCE(SUM(ar.amount_paid), 0) AS total_paid,
                    SUM(CASE WHEN ar.status = 'open' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN ar.status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                    SUM(CASE WHEN ar.status = 'overdue' THEN 1 ELSE 0 END) AS overdue_count,
                    SUM(CASE WHEN ar.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    SUM(CASE WHEN ar.status = 'written_off' THEN 1 ELSE 0 END) AS written_off_count,
                    COALESCE(SUM(CASE WHEN ar.status = 'open' THEN ar.amount - ar.amount_paid ELSE 0 END), 0) AS open_amount,
                    COALESCE(SUM(CASE WHEN ar.status = 'partial' THEN ar.amount - ar.amount_paid ELSE 0 END), 0) AS partial_amount,
                    COALESCE(SUM(CASE WHEN ar.status = 'overdue' THEN ar.amount - ar.amount_paid ELSE 0 END), 0) AS overdue_amount,
                    COALESCE(SUM(CASE WHEN ar.status = 'paid' THEN ar.amount ELSE 0 END), 0) AS paid_amount,
                    COALESCE(SUM(CASE WHEN ar.status = 'written_off' THEN ar.amount ELSE 0 END), 0) AS written_off_amount,
                    COUNT(DISTINCT ar.customer_id) AS customer_count
                FROM acc_receivables ar
                LEFT JOIN customers c ON c.id = ar.customer_id
                WHERE 1=1";
        $params = [];
        $this->applyReceivableFilters($sql, $params, $storeId, $filters, false);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'outstanding' => round((float) ($row['outstanding'] ?? 0), 2),
            'total_invoiced' => round((float) ($row['total_invoiced'] ?? 0), 2),
            'total_paid' => round((float) ($row['total_paid'] ?? 0), 2),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'partial_count' => (int) ($row['partial_count'] ?? 0),
            'overdue_count' => (int) ($row['overdue_count'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'written_off_count' => (int) ($row['written_off_count'] ?? 0),
            'open_amount' => round((float) ($row['open_amount'] ?? 0), 2),
            'partial_amount' => round((float) ($row['partial_amount'] ?? 0), 2),
            'overdue_amount' => round((float) ($row['overdue_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($row['paid_amount'] ?? 0), 2),
            'written_off_amount' => round((float) ($row['written_off_amount'] ?? 0), 2),
            'customer_count' => (int) ($row['customer_count'] ?? 0),
        ];
    }

    private function receivablesByStatus(?int $storeId, array $filters): array
    {
        $sql = "SELECT ar.status AS key_name,
                       COALESCE(SUM(CASE WHEN ar.status IN ('paid', 'written_off') THEN ar.amount ELSE ar.amount - ar.amount_paid END), 0) AS amount,
                       COUNT(*) AS count
                FROM acc_receivables ar
                LEFT JOIN customers c ON c.id = ar.customer_id
                WHERE ar.status IN ('open','partial','overdue','paid','written_off')";
        $params = [];
        $this->applyReceivableFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY ar.status ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'key' => $r['key_name'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function receivablesByCustomer(?int $storeId, array $filters, int $limit = 8): array
    {
        $sql = "SELECT COALESCE(c.name, '—') AS customer,
                       COALESCE(SUM(ar.amount - ar.amount_paid), 0) AS amount
                FROM acc_receivables ar
                LEFT JOIN customers c ON c.id = ar.customer_id
                WHERE ar.status IN ('open','partial','overdue')";
        $params = [];
        $this->applyReceivableFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY ar.customer_id, c.name HAVING amount > 0 ORDER BY amount DESC LIMIT ' . max(1, min(20, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'customer' => $r['customer'],
            'amount' => round((float) $r['amount'], 2),
        ], $rows);
    }

    private function receivablesAging(?int $storeId, array $filters): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN ar.due_date IS NULL OR ar.due_date >= CURDATE() THEN ar.amount - ar.amount_paid ELSE 0 END) AS current_amt,
                    SUM(CASE WHEN ar.due_date < CURDATE() AND ar.due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ar.amount - ar.amount_paid ELSE 0 END) AS days_30,
                    SUM(CASE WHEN ar.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ar.due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN ar.amount - ar.amount_paid ELSE 0 END) AS days_60,
                    SUM(CASE WHEN ar.due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN ar.amount - ar.amount_paid ELSE 0 END) AS days_90
                FROM acc_receivables ar
                LEFT JOIN customers c ON c.id = ar.customer_id
                WHERE ar.status IN ('open','partial','overdue')";
        $params = [];
        $this->applyReceivableFilters($sql, $params, $storeId, $filters, false);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            ['key' => 'current', 'amount' => round((float) ($row['current_amt'] ?? 0), 2)],
            ['key' => '1_30', 'amount' => round((float) ($row['days_30'] ?? 0), 2)],
            ['key' => '31_60', 'amount' => round((float) ($row['days_60'] ?? 0), 2)],
            ['key' => '60_plus', 'amount' => round((float) ($row['days_90'] ?? 0), 2)],
        ];
    }

    private function syncReceivableOverdueStatuses(?int $storeId): void
    {
        try {
            $sql = "UPDATE acc_receivables SET status = 'overdue'
                    WHERE status = 'open' AND due_date IS NOT NULL AND due_date < CURDATE()";
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable) {
        }
    }

    private function applyReceivableFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeStatus = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND ar.store_id = ?';
            $params[] = $storeId;
        }
        if ($includeStatus && !empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND ar.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND ar.due_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND ar.due_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (ar.invoice_no LIKE ? OR c.name LIKE ? OR ar.notes LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }

    public function listPayables(?int $storeId, ?string $status = null, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [];
        }
        if ($status !== null && $status !== '' && !isset($filters['status'])) {
            $filters['status'] = $status;
        }
        $sql = 'SELECT ap.*, s.name AS supplier_name,
                       (ap.amount - ap.amount_paid) AS balance,
                       CASE WHEN ap.status != \'paid\' AND ap.due_date IS NOT NULL AND ap.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
                FROM acc_payables ap
                LEFT JOIN suppliers s ON s.id = ap.supplier_id WHERE 1=1';
        $params = [];
        $this->applyPayableFilters($sql, $params, $storeId, $filters);
        $sql .= ' ORDER BY ap.due_date ASC, ap.id DESC';
        if (!empty($filters['limit'])) {
            $sql .= ' LIMIT ' . max(1, min(500, (int) $filters['limit']));
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function payablesPage(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return ['module_ready' => false];
        }

        $this->syncPayableOverdueStatuses($storeId);

        $stats = $this->payablesStats($storeId, $filters);
        $activeCount = $stats['open_count'] + $stats['partial_count'] + $stats['overdue_count'];

        return [
            'module_ready' => true,
            'rows' => $this->listPayables($storeId, null, $filters),
            'stats' => $stats,
            'charts' => [
                'by_status' => $this->payablesByStatus($storeId, $filters),
                'by_supplier' => $this->payablesBySupplier($storeId, $filters, 8),
                'aging' => $this->payablesAging($storeId, $filters),
            ],
            'insights' => [
                'supplier_count' => $stats['supplier_count'],
                'avg_balance' => $activeCount > 0
                    ? round($stats['outstanding'] / $activeCount, 2)
                    : 0,
                'overdue_ratio' => $stats['outstanding'] > 0
                    ? round($stats['overdue_amount'] / $stats['outstanding'] * 100, 1)
                    : 0,
                'paid_ratio' => $stats['total_invoiced'] > 0
                    ? round($stats['total_paid'] / $stats['total_invoiced'] * 100, 1)
                    : 0,
            ],
        ];
    }

    public function payablesStats(?int $storeId, array $filters = []): array
    {
        if (!AccountingSchema::ready($this->db)) {
            return [
                'outstanding' => 0, 'total_invoiced' => 0, 'total_paid' => 0,
                'open_count' => 0, 'partial_count' => 0, 'overdue_count' => 0, 'paid_count' => 0,
                'open_amount' => 0, 'partial_amount' => 0, 'overdue_amount' => 0, 'paid_amount' => 0,
                'supplier_count' => 0,
            ];
        }

        $sql = "SELECT
                    COALESCE(SUM(CASE WHEN ap.status IN ('open','partial','overdue') THEN ap.amount - ap.amount_paid ELSE 0 END), 0) AS outstanding,
                    COALESCE(SUM(ap.amount), 0) AS total_invoiced,
                    COALESCE(SUM(ap.amount_paid), 0) AS total_paid,
                    SUM(CASE WHEN ap.status = 'open' THEN 1 ELSE 0 END) AS open_count,
                    SUM(CASE WHEN ap.status = 'partial' THEN 1 ELSE 0 END) AS partial_count,
                    SUM(CASE WHEN ap.status = 'overdue' THEN 1 ELSE 0 END) AS overdue_count,
                    SUM(CASE WHEN ap.status = 'paid' THEN 1 ELSE 0 END) AS paid_count,
                    COALESCE(SUM(CASE WHEN ap.status = 'open' THEN ap.amount - ap.amount_paid ELSE 0 END), 0) AS open_amount,
                    COALESCE(SUM(CASE WHEN ap.status = 'partial' THEN ap.amount - ap.amount_paid ELSE 0 END), 0) AS partial_amount,
                    COALESCE(SUM(CASE WHEN ap.status = 'overdue' THEN ap.amount - ap.amount_paid ELSE 0 END), 0) AS overdue_amount,
                    COALESCE(SUM(CASE WHEN ap.status = 'paid' THEN ap.amount ELSE 0 END), 0) AS paid_amount,
                    COUNT(DISTINCT ap.supplier_id) AS supplier_count
                FROM acc_payables ap
                LEFT JOIN suppliers s ON s.id = ap.supplier_id
                WHERE 1=1";
        $params = [];
        $this->applyPayableFilters($sql, $params, $storeId, $filters, false);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'outstanding' => round((float) ($row['outstanding'] ?? 0), 2),
            'total_invoiced' => round((float) ($row['total_invoiced'] ?? 0), 2),
            'total_paid' => round((float) ($row['total_paid'] ?? 0), 2),
            'open_count' => (int) ($row['open_count'] ?? 0),
            'partial_count' => (int) ($row['partial_count'] ?? 0),
            'overdue_count' => (int) ($row['overdue_count'] ?? 0),
            'paid_count' => (int) ($row['paid_count'] ?? 0),
            'open_amount' => round((float) ($row['open_amount'] ?? 0), 2),
            'partial_amount' => round((float) ($row['partial_amount'] ?? 0), 2),
            'overdue_amount' => round((float) ($row['overdue_amount'] ?? 0), 2),
            'paid_amount' => round((float) ($row['paid_amount'] ?? 0), 2),
            'supplier_count' => (int) ($row['supplier_count'] ?? 0),
        ];
    }

    private function payablesByStatus(?int $storeId, array $filters): array
    {
        $sql = "SELECT ap.status AS key_name,
                       COALESCE(SUM(CASE WHEN ap.status = 'paid' THEN ap.amount ELSE ap.amount - ap.amount_paid END), 0) AS amount,
                       COUNT(*) AS count
                FROM acc_payables ap
                LEFT JOIN suppliers s ON s.id = ap.supplier_id
                WHERE ap.status IN ('open','partial','overdue','paid')";
        $params = [];
        $this->applyPayableFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY ap.status ORDER BY amount DESC';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'key' => $r['key_name'],
            'amount' => round((float) $r['amount'], 2),
            'count' => (int) $r['count'],
        ], $rows);
    }

    private function payablesBySupplier(?int $storeId, array $filters, int $limit = 8): array
    {
        $sql = "SELECT COALESCE(s.name, '—') AS supplier,
                       COALESCE(SUM(ap.amount - ap.amount_paid), 0) AS amount
                FROM acc_payables ap
                LEFT JOIN suppliers s ON s.id = ap.supplier_id
                WHERE ap.status IN ('open','partial','overdue')";
        $params = [];
        $this->applyPayableFilters($sql, $params, $storeId, $filters, false);
        $sql .= ' GROUP BY ap.supplier_id, s.name HAVING amount > 0 ORDER BY amount DESC LIMIT ' . max(1, min(20, $limit));
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn ($r) => [
            'supplier' => $r['supplier'],
            'amount' => round((float) $r['amount'], 2),
        ], $rows);
    }

    private function payablesAging(?int $storeId, array $filters): array
    {
        $sql = "SELECT
                    SUM(CASE WHEN ap.due_date IS NULL OR ap.due_date >= CURDATE() THEN ap.amount - ap.amount_paid ELSE 0 END) AS current_amt,
                    SUM(CASE WHEN ap.due_date < CURDATE() AND ap.due_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN ap.amount - ap.amount_paid ELSE 0 END) AS days_30,
                    SUM(CASE WHEN ap.due_date < DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND ap.due_date >= DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN ap.amount - ap.amount_paid ELSE 0 END) AS days_60,
                    SUM(CASE WHEN ap.due_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY) THEN ap.amount - ap.amount_paid ELSE 0 END) AS days_90
                FROM acc_payables ap
                LEFT JOIN suppliers s ON s.id = ap.supplier_id
                WHERE ap.status IN ('open','partial','overdue')";
        $params = [];
        $this->applyPayableFilters($sql, $params, $storeId, $filters, false);
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            ['key' => 'current', 'amount' => round((float) ($row['current_amt'] ?? 0), 2)],
            ['key' => '1_30', 'amount' => round((float) ($row['days_30'] ?? 0), 2)],
            ['key' => '31_60', 'amount' => round((float) ($row['days_60'] ?? 0), 2)],
            ['key' => '60_plus', 'amount' => round((float) ($row['days_90'] ?? 0), 2)],
        ];
    }

    private function syncPayableOverdueStatuses(?int $storeId): void
    {
        try {
            $sql = "UPDATE acc_payables SET status = 'overdue'
                    WHERE status = 'open' AND due_date IS NOT NULL AND due_date < CURDATE()";
            $params = [];
            if ($storeId !== null) {
                $sql .= ' AND store_id = ?';
                $params[] = $storeId;
            }
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
        } catch (Throwable) {
        }
    }

    private function applyPayableFilters(string &$sql, array &$params, ?int $storeId, array $filters, bool $includeStatus = true): void
    {
        if ($storeId !== null) {
            $sql .= ' AND ap.store_id = ?';
            $params[] = $storeId;
        }
        if ($includeStatus && !empty($filters['status']) && $filters['status'] !== 'all') {
            $sql .= ' AND ap.status = ?';
            $params[] = $filters['status'];
        }
        if (!empty($filters['from'])) {
            $sql .= ' AND ap.due_date >= ?';
            $params[] = $filters['from'];
        }
        if (!empty($filters['to'])) {
            $sql .= ' AND ap.due_date <= ?';
            $params[] = $filters['to'];
        }
        if (!empty($filters['search'])) {
            $sql .= ' AND (ap.invoice_no LIKE ? OR s.name LIKE ? OR ap.notes LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
            $params[] = $q;
        }
    }
}

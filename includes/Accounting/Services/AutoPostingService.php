<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../Repositories/AccountRepository.php';
require_once __DIR__ . '/../Repositories/JournalRepository.php';
require_once __DIR__ . '/../Repositories/TreasuryRepository.php';
require_once __DIR__ . '/../Repositories/AccountingAuditRepository.php';
require_once __DIR__ . '/../AccountingSchema.php';
require_once __DIR__ . '/JournalService.php';

/**
 * Automatic GL posting from POS, expenses, payments, purchases.
 */
class AutoPostingService
{
    private PDO $db;
    private AccountRepository $accounts;
    private JournalRepository $journal;
    private TreasuryRepository $treasury;
    private JournalService $journalService;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->accounts = new AccountRepository($this->db);
        $this->journal = new JournalRepository($this->db);
        $this->treasury = new TreasuryRepository($this->db);
        $this->journalService = new JournalService($this->db);
    }

    public function postSale(int $saleId, int $storeId, int $userId, float $total, string $paymentMethod, string $receiptNo, array $items = []): ?int
    {
        if (!AccountingSchema::ready($this->db)) {
            return null;
        }
        $existing = $this->db->prepare(
            "SELECT id FROM acc_journal_entries WHERE reference_type = 'sale' AND reference_id = ? LIMIT 1"
        );
        $existing->execute([$saleId]);
        if ($existing->fetchColumn()) {
            return null;
        }

        $assetCode = match ($paymentMethod) {
            'card' => '1020',
            'mobile_money' => '1030',
            default => '1010',
        };
        $asset = $this->accounts->findByCode($assetCode, $storeId);
        $revenue = $this->accounts->findByCode('4010', $storeId);
        if (!$asset || !$revenue) {
            return null;
        }

        $lines = [
            ['account_id' => (int) $asset['id'], 'debit' => $total, 'credit' => 0, 'memo' => 'Sale ' . $receiptNo],
            ['account_id' => (int) $revenue['id'], 'debit' => 0, 'credit' => $total, 'memo' => 'Product sales'],
        ];

        $cogsTotal = $this->estimateCogs($items);
        if ($cogsTotal > 0) {
            $cogs = $this->accounts->findByCode('5050', $storeId);
            $inventory = $this->accounts->findByCode('1040', $storeId);
            if ($cogs && $inventory) {
                $lines[] = ['account_id' => (int) $cogs['id'], 'debit' => $cogsTotal, 'credit' => 0, 'memo' => 'COGS'];
                $lines[] = ['account_id' => (int) $inventory['id'], 'debit' => 0, 'credit' => $cogsTotal, 'memo' => 'Inventory reduction'];
            }
        }

        $result = $this->journalService->post($storeId, [
            'entry_date' => date('Y-m-d'),
            'reference_type' => 'sale',
            'reference_id' => $saleId,
            'description' => 'POS sale ' . $receiptNo,
        ], $lines, $userId);

        if (($result['status'] ?? '') !== 'success') {
            return null;
        }
        $entryId = (int) ($result['data']['id'] ?? 0);

        if ($paymentMethod === 'cash') {
            $cashId = $this->treasury->ensureDefaultCashAccount($storeId);
            $stmt = $this->db->prepare('SELECT current_balance FROM acc_cash_accounts WHERE id = ?');
            $stmt->execute([$cashId]);
            $bal = (float) $stmt->fetchColumn() + $total;
            $this->treasury->updateCashBalance($cashId, $bal);
            $this->treasury->addCashTransaction([
                'cash_account_id' => $cashId,
                'store_id' => $storeId,
                'transaction_type' => 'sale',
                'amount' => $total,
                'balance_after' => $bal,
                'reference' => $receiptNo,
                'transaction_date' => date('Y-m-d'),
                'created_by' => $userId,
                'journal_entry_id' => $entryId,
            ]);
        }

        AccountingAuditRepository::log('auto_post_sale', $storeId, $userId, 'sale', $saleId, [
            'total' => $total,
            'payment_method' => $paymentMethod,
            'journal_entry_id' => $entryId,
        ]);

        return $entryId;
    }

    public function postExpense(int $expenseId, int $storeId, int $userId, float $amount, string $category, string $paymentMethod): ?int
    {
        if (!AccountingSchema::ready($this->db)) {
            return null;
        }
        $expenseAccount = $this->mapExpenseCategory($category, $storeId);
        $asset = $this->accounts->findByCode(match ($paymentMethod) {
            'bank' => '1020',
            'mobile_money' => '1030',
            default => '1010',
        }, $storeId);
        if (!$expenseAccount || !$asset) {
            return null;
        }
        $result = $this->journalService->post($storeId, [
            'entry_date' => date('Y-m-d'),
            'reference_type' => 'expense',
            'reference_id' => $expenseId,
            'description' => 'Expense: ' . $category,
        ], [
            ['account_id' => (int) $expenseAccount['id'], 'debit' => $amount, 'credit' => 0, 'memo' => $category],
            ['account_id' => (int) $asset['id'], 'debit' => 0, 'credit' => $amount, 'memo' => 'Payment'],
        ], $userId);
        return ($result['status'] ?? '') === 'success' ? (int) ($result['data']['id'] ?? 0) : null;
    }

    private function estimateCogs(array $items): float
    {
        if (!$items) {
            return 0.0;
        }
        $total = 0.0;
        $stmt = $this->db->prepare('SELECT cost FROM products WHERE id = ? LIMIT 1');
        foreach ($items as $item) {
            $pid = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            if ($pid <= 0 || $qty <= 0) {
                continue;
            }
            $stmt->execute([$pid]);
            $cost = (float) ($stmt->fetchColumn() ?: 0);
            $total += $cost * $qty;
        }
        return round($total, 2);
    }

    private function mapExpenseCategory(string $category, ?int $storeId): ?array
    {
        $map = [
            'rent' => '5010',
            'electricity' => '5020',
            'utilities' => '5020',
            'internet' => '5030',
            'salaries' => '5040',
            'payroll' => '5040',
            'transport' => '5060',
            'fuel' => '5060',
            'maintenance' => '5070',
            'marketing' => '5080',
        ];
        $key = strtolower(trim($category));
        $code = $map[$key] ?? '5090';
        return $this->accounts->findByCode($code, $storeId);
    }
}

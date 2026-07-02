<?php
declare(strict_types=1);

require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';
require_once __DIR__ . '/../Accounting/AccountingSchema.php';
require_once __DIR__ . '/../Accounting/Repositories/AccountRepository.php';
require_once __DIR__ . '/../Accounting/Repositories/JournalRepository.php';
require_once __DIR__ . '/../Accounting/Repositories/TreasuryRepository.php';
require_once __DIR__ . '/../Accounting/Repositories/ExpenseRepository.php';
require_once __DIR__ . '/../Accounting/Repositories/AccountingAuditRepository.php';
require_once __DIR__ . '/../Accounting/Services/AccountingDashboardService.php';
require_once __DIR__ . '/../Accounting/Services/JournalService.php';
require_once __DIR__ . '/../Accounting/Services/ExpenseAccountingService.php';
require_once __DIR__ . '/../Accounting/Services/FinancialReportService.php';
require_once __DIR__ . '/../Accounting/Services/AutoPostingService.php';

class AccountingController
{
    private AccountingDashboardService $dashboard;
    private JournalService $journal;
    private ExpenseAccountingService $expenses;
    private FinancialReportService $reports;
    private AccountRepository $accounts;
    private TreasuryRepository $treasury;
    private AccountingAuditRepository $audit;

    public function __construct()
    {
        $this->dashboard = new AccountingDashboardService();
        $this->journal = new JournalService();
        $this->expenses = new ExpenseAccountingService();
        $this->reports = new FinancialReportService();
        $this->accounts = new AccountRepository();
        $this->treasury = new TreasuryRepository();
        $this->audit = new AccountingAuditRepository();
    }

    public function handleRequest(string $method, array $path): void
    {
        if (!$this->canAccess()) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Access denied']);
            return;
        }

        AccountingSchema::ensure();

        $action = $path[1] ?? 'dashboard';
        $sub = $path[2] ?? null;
        $id = isset($path[3]) && is_numeric($path[3]) ? (int) $path[3] : (isset($path[2]) && is_numeric($path[2]) ? (int) $path[2] : null);

        if ($method === 'GET') {
            $this->handleGet($action, $sub, $id);
            return;
        }
        if ($method === 'POST') {
            $this->handlePost($action, $sub, $id);
            return;
        }
        http_response_code(405);
        echo json_encode(['status' => 'error', 'message' => 'Method not allowed']);
    }

    private function canAccess(): bool
    {
        $role = strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
        return in_array($role, ['super_admin', 'admin', 'accountant', 'manager'], true);
    }

    private function roleSlug(): string
    {
        return strtolower(str_replace(' ', '_', $_SESSION['role'] ?? ''));
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function storeId(): ?int
    {
        return StoreScope::activeStoreId();
    }

    private function handleGet(string $action, ?string $sub, ?int $id): void
    {
        $storeId = $this->storeId();
        $from = $_GET['from'] ?? date('Y-m-01');
        $to = $_GET['to'] ?? date('Y-m-d');

        switch ($action) {
            case 'dashboard':
                $data = $this->dashboard->dashboard($storeId, $from, $to);
                $ready = AccountingSchema::ready() && ($data['module_ready'] ?? true);
                echo json_encode(['status' => 'success', 'data' => $data, 'module_ready' => $ready]);
                break;
            case 'accounts':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->accounts->chartOfAccountsPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'journal':
                $filters = $_GET;
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'rows' => $this->journal->list($storeId, $filters),
                        'stats' => $this->journal->stats($storeId, $filters),
                        'reference_types' => $this->journal->referenceTypes($storeId),
                    ],
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'revenues':
                echo json_encode([
                    'status' => 'success',
                    'data' => (new JournalRepository())->revenuesPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'expenses':
                $filters = $_GET;
                echo json_encode([
                    'status' => 'success',
                    'data' => [
                        'rows' => $this->expenses->list($storeId, $filters),
                        'stats' => $this->expenses->stats($storeId, $filters),
                        'categories' => $this->expenses->categories($storeId),
                    ],
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'cash':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->treasury->cashManagementPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'banks':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->treasury->bankAccountsPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'mobile-money':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->treasury->mobileMoneyPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'receivables':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->treasury->receivablesPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'payables':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->treasury->payablesPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'inventory':
                $data = $this->reports->inventoryAccountingPage($storeId, $from, $to);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'cashflow':
                $data = $this->reports->cashFlowPage($storeId, $from, $to);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'balance-sheet':
                $data = $this->reports->balanceSheetPage($storeId, $to);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'profit-loss':
                $data = $this->reports->profitAndLossPage($storeId, $from, $to);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'reports':
                $type = $sub ?? 'hub';
                if ($type === 'hub') {
                    $data = $this->reports->hubSummary($storeId, $from, $to);
                } elseif ($type === 'balance-sheet') {
                    $data = $this->reports->balanceSheet($storeId, $to);
                } elseif ($type === 'cashflow') {
                    $data = $this->reports->cashFlow($storeId, $from, $to);
                } else {
                    $data = $this->reports->profitAndLoss($storeId, $from, $to);
                }
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            case 'analytics':
                $data = $this->dashboard->analytics($storeId, $from, $to);
                echo json_encode([
                    'status' => 'success',
                    'data' => $data,
                    'module_ready' => $data['module_ready'] ?? true,
                ]);
                break;
            case 'audit':
                echo json_encode([
                    'status' => 'success',
                    'data' => $this->audit->auditLogsPage($storeId, $_GET),
                    'module_ready' => AccountingSchema::ready(),
                ]);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }

    private function handlePost(string $action, ?string $sub, ?int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $storeId = (int) ($data['store_id'] ?? $this->storeId() ?? 0);
        $userId = $this->userId();

        switch ($action) {
            case 'accounts':
                if (!in_array($this->roleSlug(), ['admin', 'super_admin', 'accountant'], true)) {
                    http_response_code(403);
                    echo json_encode(['status' => 'error', 'message' => 'Not authorized']);
                    return;
                }
                $acctId = $this->accounts->create(array_merge($data, ['store_id' => $data['store_id'] ?? null]));
                AccountingAuditRepository::log('account_created', $storeId ?: null, $userId, 'account', $acctId);
                echo json_encode(['status' => 'success', 'data' => ['id' => $acctId]]);
                break;
            case 'journal':
                $result = $this->journal->post($storeId, $data, $data['lines'] ?? [], $userId);
                if (($result['status'] ?? '') !== 'success') {
                    http_response_code(400);
                }
                echo json_encode($result);
                break;
            case 'expenses':
                if ($sub === 'approve' && $id) {
                    $result = $this->expenses->approve($id, $userId, $this->roleSlug());
                } elseif ($sub === 'reject' && $id) {
                    $result = $this->expenses->reject($id, $userId, $this->roleSlug());
                } else {
                    $result = $this->expenses->create($data, $userId);
                }
                if (($result['status'] ?? '') !== 'success') {
                    http_response_code(400);
                }
                echo json_encode($result);
                break;
            case 'cash':
                if ($sub === 'transaction') {
                    $result = $this->treasury->recordCashTransaction(array_merge($data, [
                        'store_id' => $storeId,
                        'created_by' => $userId,
                    ]));
                    if (($result['status'] ?? '') === 'success') {
                        AccountingAuditRepository::log('cash_transaction', $storeId, $userId, 'cash_transaction', $result['data']['id'] ?? null);
                    }
                } else {
                    $cashId = $this->treasury->createCashAccount(array_merge($data, ['store_id' => $storeId]));
                    AccountingAuditRepository::log('cash_account_created', $storeId, $userId, 'cash_account', $cashId);
                    $result = ['status' => 'success', 'data' => ['id' => $cashId]];
                }
                if (($result['status'] ?? '') !== 'success') {
                    http_response_code(400);
                }
                echo json_encode($result);
                break;
            case 'banks':
                if ($sub === 'transaction') {
                    $result = $this->treasury->addBankTransaction(array_merge($data, [
                        'store_id' => $storeId,
                        'created_by' => $userId,
                    ]));
                } else {
                    $bankId = $this->treasury->createBankAccount(array_merge($data, ['store_id' => $storeId]));
                    AccountingAuditRepository::log('bank_account_created', $storeId, $userId, 'bank_account', $bankId);
                    $result = ['status' => 'success', 'data' => ['id' => $bankId]];
                }
                if (($result['status'] ?? '') !== 'success') {
                    http_response_code(400);
                }
                echo json_encode($result);
                break;
            case 'mobile-money':
                if ($sub === 'transaction') {
                    $result = $this->treasury->addMobileTransaction(array_merge($data, [
                        'store_id' => $storeId,
                        'created_by' => $userId,
                    ]));
                } else {
                    $mmId = $this->treasury->createMobileAccount(array_merge($data, ['store_id' => $storeId]));
                    AccountingAuditRepository::log('mobile_wallet_created', $storeId, $userId, 'mobile_account', $mmId);
                    $result = ['status' => 'success', 'data' => ['id' => $mmId]];
                }
                if (($result['status'] ?? '') !== 'success') {
                    http_response_code(400);
                }
                echo json_encode($result);
                break;
            case 'sync':
                $this->handleOfflineSync($data);
                break;
            default:
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
        }
    }

    private function handleOfflineSync(array $data): void
    {
        $items = $data['items'] ?? [];
        $synced = 0;
        foreach ($items as $item) {
            $uuid = $item['local_uuid'] ?? '';
            $action = $item['action'] ?? '';
            $payload = $item['payload'] ?? [];
            if (!$uuid || !$action) {
                continue;
            }
            try {
                if ($action === 'expense_create') {
                    $this->expenses->create($payload, $this->userId());
                } elseif ($action === 'journal_post') {
                    $this->journal->post(
                        (int) ($payload['store_id'] ?? 0),
                        $payload,
                        $payload['lines'] ?? [],
                        $this->userId()
                    );
                }
                $synced++;
            } catch (Throwable) {
                // continue
            }
        }
        echo json_encode(['status' => 'success', 'synced' => $synced]);
    }
}

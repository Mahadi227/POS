<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ExpenseRepository.php';
require_once __DIR__ . '/../Repositories/AccountingAuditRepository.php';
require_once __DIR__ . '/AutoPostingService.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';

class ExpenseAccountingService
{
    private ExpenseRepository $expenses;
    private AutoPostingService $posting;

    public function __construct()
    {
        $this->expenses = new ExpenseRepository();
        $this->posting = new AutoPostingService();
    }

    public function list(?int $storeId, array $filters = []): array
    {
        return $this->expenses->list($storeId, $filters);
    }

    public function stats(?int $storeId, array $filters = []): array
    {
        return $this->expenses->stats($storeId, $filters);
    }

    public function categories(?int $storeId): array
    {
        return $this->expenses->categories($storeId);
    }

    public function create(array $data, int $userId): array
    {
        $storeId = (int) ($data['store_id'] ?? StoreScope::activeStoreId() ?? 0);
        if (!$storeId) {
            return ['status' => 'error', 'message' => 'Invalid store'];
        }
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid amount'];
        }
        $id = $this->expenses->create(array_merge($data, [
            'store_id' => $storeId,
            'created_by' => $userId,
            'status' => 'pending',
        ]));
        AccountingAuditRepository::log('expense_created', $storeId, $userId, 'expense', $id, ['amount' => $amount]);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function approve(int $id, int $userId, string $role): array
    {
        if (!in_array($role, ['admin', 'super_admin', 'accountant'], true)) {
            return ['status' => 'error', 'message' => 'Not authorized to approve expenses'];
        }
        $exp = $this->expenses->find($id);
        if (!$exp || $exp['status'] !== 'pending') {
            return ['status' => 'error', 'message' => 'Expense not found or already processed'];
        }
        $entryId = $this->posting->postExpense(
            $id,
            (int) $exp['store_id'],
            $userId,
            (float) $exp['amount'],
            (string) $exp['category'],
            (string) ($exp['payment_method'] ?? 'cash')
        );
        $this->expenses->updateStatus($id, 'approved', $userId, $entryId);
        AccountingAuditRepository::log('expense_approved', (int) $exp['store_id'], $userId, 'expense', $id);
        return ['status' => 'success', 'message' => 'Expense approved', 'journal_entry_id' => $entryId];
    }

    public function reject(int $id, int $userId, string $role): array
    {
        if (!in_array($role, ['admin', 'super_admin', 'accountant'], true)) {
            return ['status' => 'error', 'message' => 'Not authorized'];
        }
        $exp = $this->expenses->find($id);
        if (!$exp || $exp['status'] !== 'pending') {
            return ['status' => 'error', 'message' => 'Expense not found'];
        }
        $this->expenses->updateStatus($id, 'rejected', $userId);
        AccountingAuditRepository::log('expense_rejected', (int) $exp['store_id'], $userId, 'expense', $id);
        return ['status' => 'success', 'message' => 'Expense rejected'];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/CashRegisterRepository.php';
require_once __DIR__ . '/../Repositories/CashRegisterSessionRepository.php';
require_once __DIR__ . '/../Repositories/CashMovementRepository.php';
require_once __DIR__ . '/../Repositories/CashReconciliationRepository.php';
require_once __DIR__ . '/../Repositories/CashTransferRepository.php';
require_once __DIR__ . '/../Repositories/CashRegisterLogRepository.php';
require_once __DIR__ . '/../CashRegisterSchema.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../CashRegisterNotifier.php';

class CashRegisterService
{
    private const VARIANCE_TOLERANCE = 500.0;

    private CashRegisterRepository $registers;
    private CashRegisterSessionRepository $sessions;
    private CashMovementRepository $movements;
    private CashReconciliationRepository $reconciliations;
    private CashTransferRepository $transfers;
    private CashRegisterLogRepository $logs;

    public function __construct()
    {
        $this->registers = new CashRegisterRepository();
        $this->sessions = new CashRegisterSessionRepository();
        $this->movements = new CashMovementRepository();
        $this->reconciliations = new CashReconciliationRepository();
        $this->transfers = new CashTransferRepository();
        $this->logs = new CashRegisterLogRepository();
    }

    public function moduleReady(): bool
    {
        return CashRegisterSchema::ready();
    }

    public function listRegisters(?int $storeId, ?string $status = null): array
    {
        return array_map(fn ($r) => $this->enrichRegister($r), $this->registers->list($storeId, $status));
    }

    public function getRegister(int $id): ?array
    {
        $row = $this->registers->findById($id);
        if (!$row) {
            return null;
        }
        $register = $this->enrichRegister($row);
        $register['sessions'] = $this->sessions->listByRegister($id, 15);
        $register['movements'] = $this->movements->list((int) $row['store_id'], ['register_id' => $id], 25);
        return $register;
    }

    /**
     * @return array{status: string, message?: string, data?: array<string, mixed>}
     */
    public function createRegister(array $data, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 007_cash_registers.sql'];
        }
        $storeId = (int) ($data['store_id'] ?? 0);
        if (!$storeId || !StoreScope::canAccessStore($storeId)) {
            return ['status' => 'error', 'message' => 'Invalid store'];
        }
        $code = trim((string) ($data['register_code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            return ['status' => 'error', 'message' => 'Code and name are required'];
        }
        $id = $this->registers->create([
            'store_id' => $storeId,
            'register_code' => $code,
            'name' => $name,
            'assigned_user_id' => $data['assigned_user_id'] ?? null,
            'status' => $data['status'] ?? 'active',
            'opening_balance' => $data['opening_balance'] ?? 0,
            'config' => $data['config'] ?? null,
        ]);
        CashRegisterLogRepository::log('register_created', $storeId, $id, $userId, 'cash_register', $id, ['name' => $name]);
        return ['status' => 'success', 'data' => $this->getRegister($id)];
    }

    public function updateRegister(int $id, array $data, int $userId): array
    {
        $existing = $this->registers->findById($id);
        if (!$existing || !StoreScope::canAccessStore((int) $existing['store_id'])) {
            return ['status' => 'error', 'message' => 'Register not found'];
        }
        $ok = $this->registers->update($id, $data);
        if (!$ok) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
        CashRegisterLogRepository::log('register_updated', (int) $existing['store_id'], $id, $userId, 'cash_register', $id);
        return ['status' => 'success', 'data' => $this->getRegister($id)];
    }

    public function deleteRegister(int $id, int $userId): array
    {
        $existing = $this->registers->findById($id);
        if (!$existing || !StoreScope::canAccessStore((int) $existing['store_id'])) {
            return ['status' => 'error', 'message' => 'Register not found'];
        }
        if ($this->sessions->findOpenByRegister($id)) {
            return ['status' => 'error', 'message' => 'Cannot delete register with open session'];
        }
        $this->registers->softDelete($id);
        CashRegisterLogRepository::log('register_deleted', (int) $existing['store_id'], $id, $userId, 'cash_register', $id);
        CashRegisterNotifier::registerInactive((int) $existing['store_id'], $id, (string) ($existing['name'] ?? ''));
        return ['status' => 'success', 'message' => 'Register deactivated'];
    }

    public function openSession(int $registerId, int $userId, array $data): array
    {
        $register = $this->registers->findById($registerId);
        if (!$register || !StoreScope::canAccessStore((int) $register['store_id'])) {
            return ['status' => 'error', 'message' => 'Register not found'];
        }
        if ($register['status'] !== 'active') {
            return ['status' => 'error', 'message' => 'Register is not active'];
        }
        if ($this->sessions->findOpenByRegister($registerId)) {
            return ['status' => 'error', 'message' => 'Register already has an open session'];
        }
        $opening = round((float) ($data['opening_balance'] ?? 0), 2);
        if ($opening < 0) {
            return ['status' => 'error', 'message' => 'Invalid opening balance'];
        }

        $sessionId = $this->sessions->create([
            'register_id' => $registerId,
            'store_id' => (int) $register['store_id'],
            'user_id' => $userId,
            'shift_type' => $data['shift_type'] ?? 'morning',
            'opening_balance' => $opening,
            'opening_notes' => $data['notes'] ?? null,
            'opened_by' => $userId,
            'cashier_shift_id' => $data['cashier_shift_id'] ?? null,
        ]);

        $this->registers->updateBalance($registerId, $opening);
        $this->movements->create([
            'store_id' => (int) $register['store_id'],
            'register_id' => $registerId,
            'session_id' => $sessionId,
            'movement_type' => 'opening_cash',
            'amount' => $opening,
            'balance_after' => $opening,
            'reason' => $data['notes'] ?? 'Session opening',
            'created_by' => $userId,
        ]);

        CashRegisterLogRepository::log('session_opened', (int) $register['store_id'], $registerId, $userId, 'cash_register_session', $sessionId);

        CashRegisterNotifier::registerOpened(
            (int) $register['store_id'],
            $registerId,
            $userId,
            $opening,
            (string) ($register['name'] ?? '')
        );

        return ['status' => 'success', 'data' => $this->sessions->findById($sessionId)];
    }

    public function closeSession(int $sessionId, int $userId, array $data): array
    {
        $session = $this->sessions->findById($sessionId);
        if (!$session || ($session['status'] ?? '') !== 'open') {
            return ['status' => 'error', 'message' => 'No open session'];
        }
        if (!StoreScope::canAccessStore((int) $session['store_id'])) {
            return ['status' => 'error', 'message' => 'Access denied'];
        }

        $expected = round((float) ($session['opening_balance'] ?? 0) + (float) ($session['cash_sales'] ?? 0), 2);
        $counted = round((float) ($data['counted_cash'] ?? 0), 2);
        $variance = round($counted - $expected, 2);

        $this->sessions->close($sessionId, [
            'closing_balance' => $counted,
            'expected_cash' => $expected,
            'counted_cash' => $counted,
            'variance' => $variance,
            'cash_sales' => $session['cash_sales'] ?? 0,
            'card_sales' => $session['card_sales'] ?? 0,
            'mobile_sales' => $session['mobile_sales'] ?? 0,
            'refunds' => $session['refunds'] ?? 0,
            'expenses' => $session['expenses'] ?? 0,
            'total_sales' => $session['total_sales'] ?? 0,
            'transaction_count' => $session['transaction_count'] ?? 0,
            'closing_notes' => $data['notes'] ?? null,
            'closed_by' => $userId,
        ]);

        $registerId = (int) $session['register_id'];
        $this->registers->updateBalance($registerId, $counted);
        $this->movements->create([
            'store_id' => (int) $session['store_id'],
            'register_id' => $registerId,
            'session_id' => $sessionId,
            'movement_type' => 'closing_cash',
            'amount' => $counted,
            'balance_after' => $counted,
            'reason' => $data['notes'] ?? 'Session closing',
            'created_by' => $userId,
        ]);

        $reconId = $this->reconciliations->create([
            'store_id' => (int) $session['store_id'],
            'register_id' => $registerId,
            'session_id' => $sessionId,
            'expected_cash' => $expected,
            'physical_cash' => $counted,
            'difference' => $variance,
            'status' => abs($variance) < self::VARIANCE_TOLERANCE ? 'approved' : 'pending',
            'notes' => $data['notes'] ?? null,
        ]);

        CashRegisterLogRepository::log('session_closed', (int) $session['store_id'], $registerId, $userId, 'cash_register_session', $sessionId, [
            'variance' => $variance,
            'reconciliation_id' => $reconId,
        ]);

        CashRegisterNotifier::registerClosed(
            (int) $session['store_id'],
            $registerId,
            $userId,
            $variance,
            (string) ($session['register_name'] ?? '')
        );

        if (abs($variance) >= self::VARIANCE_TOLERANCE) {
            CashRegisterNotifier::cashDifferenceDetected(
                (int) $session['store_id'],
                $registerId,
                $variance,
                $reconId
            );
        }

        return [
            'status' => 'success',
            'data' => [
                'session' => $this->sessions->findById($sessionId),
                'reconciliation_id' => $reconId,
                'variance' => $variance,
            ],
        ];
    }

    public function listSessions(?int $storeId, ?string $status = null): array
    {
        return $this->sessions->list($storeId, $status);
    }

    public function listMovements(?int $storeId, array $filters = []): array
    {
        return $this->movements->list($storeId, $filters);
    }

    public function listReconciliations(?int $storeId, ?string $status = null): array
    {
        return $this->reconciliations->list($storeId, $status);
    }

    public function reviewReconciliation(int $id, string $decision, int $userId, string $role, ?string $note = null): array
    {
        if (!in_array($decision, ['approved', 'rejected'], true)) {
            return ['status' => 'error', 'message' => 'Invalid decision'];
        }
        $ok = $this->reconciliations->review($id, $decision, $userId, $role, $note);
        if (!$ok) {
            return ['status' => 'error', 'message' => 'Reconciliation not found or already reviewed'];
        }
        CashRegisterLogRepository::log('reconciliation_' . $decision, null, null, $userId, 'cash_reconciliation', $id);
        return ['status' => 'success', 'message' => 'Reconciliation updated'];
    }

    public function createTransfer(array $data, int $userId): array
    {
        $storeId = (int) ($data['store_id'] ?? StoreScope::activeStoreId() ?? 0);
        if (!$storeId || !StoreScope::canAccessStore($storeId)) {
            return ['status' => 'error', 'message' => 'Invalid store'];
        }
        $amount = round((float) ($data['amount'] ?? 0), 2);
        if ($amount <= 0) {
            return ['status' => 'error', 'message' => 'Invalid amount'];
        }
        $id = $this->transfers->create(array_merge($data, [
            'store_id' => $storeId,
            'requested_by' => $userId,
        ]));
        CashRegisterLogRepository::log('transfer_requested', $storeId, $data['from_register_id'] ?? null, $userId, 'cash_transfer', $id);
        if ($amount >= 100000) {
            CashRegisterNotifier::largeWithdrawal($storeId, (int) ($data['from_register_id'] ?? 0), $amount, $userId);
        }
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function listTransfers(?int $storeId, ?string $status = null): array
    {
        return $this->transfers->list($storeId, $status);
    }

    public function approveTransfer(int $id, int $userId): array
    {
        $ok = $this->transfers->updateStatus($id, 'approved', $userId, 'approved_by');
        return $ok
            ? ['status' => 'success', 'message' => 'Transfer approved']
            : ['status' => 'error', 'message' => 'Transfer not found'];
    }

    public function completeTransfer(int $id, int $userId): array
    {
        $ok = $this->transfers->updateStatus($id, 'completed', $userId, 'received_by');
        return $ok
            ? ['status' => 'success', 'message' => 'Transfer completed']
            : ['status' => 'error', 'message' => 'Transfer not found'];
    }

    public function listLogs(?int $storeId): array
    {
        return $this->logs->list($storeId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listNotifications(?int $storeId, ?string $since = null): array
    {
        if (!$this->moduleReady()) {
            return [];
        }
        return array_map(function (array $row) {
            $details = [];
            if (!empty($row['details'])) {
                $decoded = json_decode((string) $row['details'], true);
                if (is_array($decoded)) {
                    $details = $decoded;
                }
            }
            return [
                'id' => (int) $row['id'],
                'action' => (string) $row['action'],
                'message' => (string) ($details['message'] ?? $row['action']),
                'severity' => (string) ($details['severity'] ?? 'info'),
                'register_id' => $row['register_id'] ?? null,
                'register_name' => $row['register_name'] ?? null,
                'user_name' => $row['user_name'] ?? null,
                'created_at' => $row['created_at'],
                'entity_type' => $row['entity_type'] ?? null,
                'entity_id' => $row['entity_id'] ?? null,
            ];
        }, $this->logs->listNotifications($storeId, $since));
    }

    public function syncOfflineMovements(array $items, int $userId): array
    {
        $synced = 0;
        foreach ($items as $item) {
            if (empty($item['local_uuid'])) {
                continue;
            }
            $this->movements->create([
                'store_id' => (int) ($item['store_id'] ?? StoreScope::activeStoreId()),
                'register_id' => $item['register_id'] ?? null,
                'session_id' => $item['session_id'] ?? null,
                'movement_type' => $item['movement_type'] ?? 'adjustment',
                'amount' => $item['amount'] ?? 0,
                'reason' => $item['reason'] ?? 'Offline sync',
                'created_by' => $userId,
                'sync_status' => 'synced',
                'local_uuid' => $item['local_uuid'],
            ]);
            $synced++;
        }
        return ['status' => 'success', 'synced' => $synced];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichRegister(array $row): array
    {
        $isOpen = (int) ($row['is_session_open'] ?? 0) > 0;
        return array_merge($row, [
            'session_status' => $isOpen ? 'open' : 'closed',
            'open_session_id' => !empty($row['open_session_id']) ? (int) $row['open_session_id'] : null,
            'current_balance' => round((float) ($row['current_balance'] ?? 0), 2),
            'opening_balance' => round((float) ($row['opening_balance'] ?? 0), 2),
        ]);
    }

    public function listRegistersForCashier(int $userId, int $storeId): array
    {
        if (!$this->moduleReady()) {
            return [];
        }
        $all = $this->registers->list($storeId, 'active');
        return array_values(array_filter($all, function (array $r) use ($userId) {
            $assigned = $r['assigned_user_id'] ?? null;
            return $assigned === null || (int) $assigned === $userId;
        }));
    }

    public function openSessionLinkedToShift(int $registerId, int $userId, float $opening, int $shiftId, ?string $notes = null): array
    {
        return $this->openSession($registerId, $userId, [
            'opening_balance' => $opening,
            'notes' => $notes,
            'cashier_shift_id' => $shiftId,
        ]);
    }

    public function closeSessionByShift(int $shiftId, int $userId, float $countedCash, ?string $notes = null): ?array
    {
        if (!$this->moduleReady()) {
            return null;
        }
        $session = $this->sessions->findOpenByShift($shiftId);
        if (!$session) {
            return null;
        }
        return $this->closeSession((int) $session['id'], $userId, [
            'counted_cash' => $countedCash,
            'notes' => $notes,
        ]);
    }

    public function recordSaleToSession(?int $sessionId, float $amount, string $method = 'cash'): void
    {
        if (!$sessionId || $amount <= 0 || !$this->moduleReady()) {
            return;
        }
        $this->sessions->incrementSale($sessionId, round($amount, 2), $method);
    }
}

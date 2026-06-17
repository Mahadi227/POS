<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ShiftRepository.php';
require_once __DIR__ . '/../../CashRegister/CashRegisterSchema.php';
require_once __DIR__ . '/../../CashRegister/Services/CashRegisterService.php';

class CashierShiftService
{
    private const VARIANCE_TOLERANCE = 500.0;

    private ShiftRepository $repo;

    public function __construct(?ShiftRepository $repo = null)
    {
        $this->repo = $repo ?? new ShiftRepository();
    }

    public function tableReady(): bool
    {
        return $this->repo->tableExists();
    }

    public function currentShift(int $userId, int $storeId): ?array
    {
        $row = $this->repo->findOpenByUser($userId, $storeId);
        if (!$row) {
            return null;
        }
        return $this->enrichShift($row);
    }

    /**
     * @return array{status: string, message?: string, data?: array<string, mixed>}
     */
    public function openShift(int $userId, int $storeId, float $openingFloat, ?string $notes = null, ?int $registerId = null): array
    {
        if (!$this->repo->tableExists()) {
            return [
                'status'  => 'error',
                'message' => 'Module shifts indisponible — exécutez la migration 005.',
            ];
        }

        if ($this->repo->findOpenByUser($userId, $storeId)) {
            return ['status' => 'error', 'message' => 'Un shift est déjà ouvert pour ce caissier.'];
        }

        if ($openingFloat < 0) {
            return ['status' => 'error', 'message' => 'Le fond de caisse ne peut pas être négatif.'];
        }

        if ($storeId <= 0) {
            return ['status' => 'error', 'message' => 'Magasin invalide — reconnectez-vous ou contactez un administrateur.'];
        }

        $id = $this->repo->createShift($storeId, $userId, round($openingFloat, 2), $notes);
        $row = $this->repo->findById($id);
        $enriched = $row ? $this->enrichShift($row) : null;

        if ($registerId && CashRegisterSchema::ready()) {
            $crService = new CashRegisterService();
            $sessionResult = $crService->openSessionLinkedToShift($registerId, $userId, $openingFloat, $id, $notes);
            if (($sessionResult['status'] ?? '') === 'success') {
                $sessionId = (int) ($sessionResult['data']['id'] ?? 0);
                if ($sessionId > 0) {
                    $this->repo->linkRegister($id, $registerId, $sessionId);
                    $row = $this->repo->findById($id);
                    $enriched = $row ? $this->enrichShift($row) : $enriched;
                }
            }
        }

        return [
            'status'  => 'success',
            'message' => 'Shift ouvert',
            'data'    => $enriched,
        ];
    }

    /**
     * @return array{status: string, message?: string, data?: array<string, mixed>}
     */
    public function closeShift(int $userId, int $storeId, float $countedCash, ?string $notes = null): array
    {
        if (!$this->repo->tableExists()) {
            return [
                'status'  => 'error',
                'message' => 'Module shifts indisponible — exécutez la migration 005.',
            ];
        }

        $row = $this->repo->findOpenByUser($userId, $storeId);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Aucun shift ouvert à clôturer.'];
        }

        if ($countedCash < 0) {
            return ['status' => 'error', 'message' => 'Le comptage caisse ne peut pas être négatif.'];
        }

        $enriched = $this->enrichShift($row);
        $expected = (float) ($enriched['expected_cash'] ?? 0);
        $counted = round($countedCash, 2);
        $variance = round($counted - $expected, 2);

        $ok = $this->repo->closeShift((int) $row['id'], $expected, $counted, $variance, $notes);
        if (!$ok) {
            return ['status' => 'error', 'message' => 'Impossible de clôturer le shift.'];
        }

        $closed = $this->repo->findById((int) $row['id']);
        $data = $closed ? $this->enrichShift($closed) : null;
        if ($data) {
            $data['reconciliation_status'] = $this->reconciliationStatus($variance, true);
        }

        if (CashRegisterSchema::ready()) {
            $crService = new CashRegisterService();
            $crService->closeSessionByShift((int) $row['id'], $userId, $counted, $notes);
        }

        return [
            'status'  => 'success',
            'message' => 'Shift clôturé',
            'data'    => $data,
        ];
    }

    public function recordSale(int $userId, int $storeId, float $amount, string $method = 'cash'): void
    {
        if ($amount <= 0 || !$this->repo->tableExists()) {
            return;
        }
        $row = $this->repo->findOpenByUser($userId, $storeId);
        if ($row) {
            $this->repo->incrementTotals((int) $row['id'], round($amount, 2));
            if (CashRegisterSchema::ready() && !empty($row['session_id'])) {
                (new CashRegisterService())->recordSaleToSession((int) $row['session_id'], $amount, $method);
            }
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function availableRegisters(int $userId, int $storeId): array
    {
        if (!CashRegisterSchema::ready()) {
            return [];
        }
        return (new CashRegisterService())->listRegistersForCashier($userId, $storeId);
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichShift(array $row): array
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $storeId = (int) ($row['store_id'] ?? 0);
        $openedAt = (string) ($row['opened_at'] ?? '');
        $closedAt = !empty($row['closed_at']) ? (string) $row['closed_at'] : null;
        $openingFloat = (float) ($row['opening_float'] ?? 0);

        $cashSales = $this->repo->cashSalesForShift($userId, $storeId, $openedAt, $closedAt);
        $expectedCash = round($openingFloat + $cashSales, 2);

        $countedRaw = $row['counted_cash'] ?? null;
        $countedCash = ($countedRaw !== null && $countedRaw !== '') ? (float) $countedRaw : null;
        $variance = $countedCash !== null ? round($countedCash - $expectedCash, 2) : null;
        $isClosed = ($row['status'] ?? '') === 'closed';

        return array_merge($row, [
            'cash_sales'              => round($cashSales, 2),
            'expected_cash'           => $expectedCash,
            'counted_cash'            => $countedCash,
            'variance'                => $variance,
            'reconciliation_status'   => $this->reconciliationStatus($variance, $isClosed),
        ]);
    }

    private function reconciliationStatus(?float $variance, bool $isClosed): string
    {
        if (!$isClosed) {
            return 'open';
        }
        if ($variance === null) {
            return 'open';
        }
        if (abs($variance) < self::VARIANCE_TOLERANCE) {
            return 'balanced';
        }
        return $variance < 0 ? 'short' : 'over';
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/ShiftRepository.php';

class ShiftService
{
    private ShiftRepository $repo;

    public function __construct(?ShiftRepository $repo = null)
    {
        $this->repo = $repo ?? new ShiftRepository();
    }

    public function listOpen(?int $storeId): array
    {
        return $this->repo->listOpen($storeId);
    }

    public function tableExists(): bool
    {
        return $this->repo->tableExists();
    }

    /**
     * Cash drawer reconciliation — expected vs counted by shift.
     *
     * @return array<string, mixed>
     */
    public function cashReconciliation(?int $storeId, string $filter = 'open'): array
    {
        $allowed = ['open', 'closed', 'variance', 'all'];
        if (!in_array($filter, $allowed, true)) {
            $filter = 'open';
        }

        if (!$this->repo->tableExists()) {
            return [
                'summary' => [
                    'open_shifts' => 0,
                    'total_expected' => 0.0,
                    'total_counted' => 0.0,
                    'total_variance' => 0.0,
                    'variance_count' => 0,
                ],
                'items'  => [],
                'filter' => $filter,
            ];
        }

        $scope = $filter === 'variance' ? 'all' : $filter;
        $rows = $this->repo->listForReconciliation($storeId, $scope);
        $items = array_map(fn (array $row) => $this->enrichReconciliationRow($row), $rows);

        if ($filter === 'variance') {
            $items = array_values(array_filter(
                $items,
                fn (array $row) => ($row['reconciliation_status'] ?? '') !== 'open'
                    && abs((float) ($row['variance'] ?? 0)) >= 500
            ));
        }

        $openCount = 0;
        $totalExpected = 0.0;
        $totalCounted = 0.0;
        $totalVariance = 0.0;
        $varianceCount = 0;

        foreach ($items as $row) {
            if (($row['status'] ?? '') === 'open') {
                $openCount++;
            }
            $totalExpected += (float) ($row['expected_cash'] ?? 0);
            if ($row['counted_cash'] !== null) {
                $totalCounted += (float) $row['counted_cash'];
                $totalVariance += (float) ($row['variance'] ?? 0);
                if (abs((float) ($row['variance'] ?? 0)) >= 500) {
                    $varianceCount++;
                }
            }
        }

        return [
            'summary' => [
                'open_shifts'    => $openCount,
                'total_expected' => round($totalExpected, 2),
                'total_counted'  => round($totalCounted, 2),
                'total_variance' => round($totalVariance, 2),
                'variance_count' => $varianceCount,
            ],
            'items'  => $items,
            'filter' => $filter,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function enrichReconciliationRow(array $row): array
    {
        $userId = (int) ($row['user_id'] ?? 0);
        $storeId = (int) ($row['store_id'] ?? 0);
        $openedAt = (string) ($row['opened_at'] ?? '');
        $closedAt = !empty($row['closed_at']) ? (string) $row['closed_at'] : null;

        $cashSales = $this->repo->cashSalesForShift($userId, $storeId, $openedAt, $closedAt);
        $openingFloat = (float) ($row['opening_float'] ?? 0);
        $expectedCash = round($openingFloat + $cashSales, 2);

        $countedRaw = $row['counted_cash'] ?? null;
        $countedCash = ($countedRaw !== null && $countedRaw !== '') ? (float) $countedRaw : null;
        $variance = $countedCash !== null ? round($countedCash - $expectedCash, 2) : null;

        $status = 'open';
        if ($countedCash !== null) {
            if (abs((float) $variance) < 500) {
                $status = 'balanced';
            } elseif ((float) $variance < 0) {
                $status = 'short';
            } else {
                $status = 'over';
            }
        }

        return [
            'id'                    => (int) ($row['id'] ?? 0),
            'cashier_name'          => (string) ($row['cashier_name'] ?? ''),
            'status'                => (string) ($row['status'] ?? 'open'),
            'opened_at'             => $openedAt,
            'closed_at'             => $closedAt,
            'opening_float'         => $openingFloat,
            'cash_sales'            => round($cashSales, 2),
            'expected_cash'         => $expectedCash,
            'counted_cash'          => $countedCash,
            'variance'              => $variance,
            'total_sales'           => (float) ($row['total_sales'] ?? 0),
            'transaction_count'     => (int) ($row['transaction_count'] ?? 0),
            'reconciliation_status' => $status,
        ];
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/BatchTrackingRepository.php';
require_once __DIR__ . '/../WmsSchema.php';

class ExpiryReportService
{
    private BatchTrackingRepository $batches;

    public function __construct(?PDO $db = null)
    {
        $this->batches = new BatchTrackingRepository($db);
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function parseStatus(array $input): ?string
    {
        $status = isset($input['status']) ? trim((string) $input['status']) : 'at_risk';
        if ($status === '' || $status === 'all') {
            return 'at_risk';
        }
        return in_array($status, ['at_risk', 'expiring_soon', 'expired'], true) ? $status : 'at_risk';
    }

    public function report(array $input, int $limit, int $offset): array
    {
        $wh = (int) ($input['warehouse_id'] ?? 0) ?: null;
        $days = max(1, min(365, (int) ($input['days'] ?? 30)));
        $search = isset($input['q']) ? trim((string) $input['q']) : null;
        $search = $search !== '' ? $search : null;
        $status = $this->parseStatus($input);

        $summary = array_merge(
            $this->batches->expirySummary($wh, $days),
            [
                'total_batches' => $this->batches->count($wh, $status, $search, $days, 'expiry'),
                'days' => $days,
            ]
        );

        return [
            'filters' => [
                'warehouse_id' => $wh,
                'days' => $days,
                'status' => $status,
                'q' => $search,
            ],
            'summary' => $summary,
            'breakdown' => $this->batches->expiryBreakdown($wh, $days, $search),
            'chart' => [
                'trend' => $this->batches->expiryTrendChart($wh, $days, $search, $status),
                'warehouse' => $this->batches->expiryWarehouseBreakdown($wh, $days, $search, $status),
                'urgency' => $this->batches->expiryUrgencyBreakdown($wh, $days, $search, $status),
            ],
            'total' => $this->batches->count($wh, $status, $search, $days, 'expiry'),
            'data' => $this->batches->list($wh, $status, $search, $days, $limit, $offset, 'expiry'),
        ];
    }
}

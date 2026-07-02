<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/InventoryReportRepository.php';
require_once __DIR__ . '/InventoryReportService.php';
require_once __DIR__ . '/../WmsSchema.php';

class DamageReportService
{
    private InventoryReportRepository $repo;
    private InventoryReportService $report;

    public function __construct(?PDO $db = null)
    {
        $this->repo = new InventoryReportRepository($db);
        $this->report = new InventoryReportService();
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function parseFilters(array $input): array
    {
        $filters = $this->report->parseFilters($input);
        if (empty($input['date_from']) && empty($input['date_to'])) {
            $filters['date_from'] = date('Y-m-d', strtotime('-30 days'));
            $filters['date_to'] = date('Y-m-d');
        }
        return $filters;
    }

    public function report(array $input, int $limit, int $offset): array
    {
        $filters = $this->parseFilters($input);
        $days = max(7, min(90, (int) ($input['days'] ?? 30)));

        return [
            'filters' => $filters,
            'summary' => $this->repo->damageSummary($filters),
            'breakdown' => [
                'warehouse' => $this->repo->damageWarehouseBreakdown($filters),
                'type' => $this->repo->damageTypeBreakdown($filters),
            ],
            'chart' => [
                'trend' => $this->repo->damageTrend($filters, $days),
            ],
            'total' => $this->repo->countDamaged($filters),
            'data' => $this->repo->listDamaged($filters, $limit, $offset),
        ];
    }
}

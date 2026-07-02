<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/WarehousePerformanceRepository.php';
require_once __DIR__ . '/InventoryReportService.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehousePerformanceService
{
    private WarehousePerformanceRepository $repo;
    private InventoryReportService $inventoryReport;

    public function __construct(?PDO $db = null)
    {
        $this->repo = new WarehousePerformanceRepository($db);
        $this->inventoryReport = new InventoryReportService();
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function parseFilters(array $input): array
    {
        $filters = $this->inventoryReport->parseFilters($input);
        $period = (string) ($input['period'] ?? '');
        if ($period !== '' && empty($input['date_from']) && empty($input['date_to'])) {
            $days = match ($period) {
                'week' => 7,
                'year' => 365,
                default => 30,
            };
            $filters['date_from'] = date('Y-m-d', strtotime('-' . ($days - 1) . ' days'));
            $filters['date_to'] = date('Y-m-d');
        }
        if (!empty($input['q'])) {
            $filters['q'] = trim((string) $input['q']);
        }
        return $filters;
    }

    public function report(array $input, int $limit, int $offset): array
    {
        $filters = $this->parseFilters($input);
        return [
            'filters' => $filters,
            'summary' => $this->repo->summary($filters),
            'charts' => $this->repo->charts($filters),
            'total' => $this->repo->countRows($filters),
            'data' => $this->repo->listRows($filters, $limit, $offset),
        ];
    }
}

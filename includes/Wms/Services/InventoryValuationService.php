<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/InventoryReportRepository.php';
require_once __DIR__ . '/InventoryReportService.php';
require_once __DIR__ . '/../WmsSchema.php';

class InventoryValuationService
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

    public function report(array $input, int $limit, int $offset): array
    {
        $filters = $this->report->parseFilters($input);
        $method = $filters['valuation_method'] ?? 'weighted';
        $summary = $this->repo->valuation($filters, $method);
        $dash = $this->repo->dashboardSummary($filters);
        $charts = $this->repo->charts($filters);
        $summary = array_merge($summary, [
            'line_count' => $this->repo->countValuationLines($filters),
            'total_qty' => (int) ($dash['total_qty'] ?? 0),
            'product_count' => (int) ($dash['total_products'] ?? 0),
        ]);

        return [
            'filters' => $filters,
            'summary' => $summary,
            'breakdown' => $charts['category_distribution'] ?? [],
            'charts' => [
                'value_trend' => $charts['value_trend'] ?? [],
                'category_distribution' => $charts['category_distribution'] ?? [],
                'warehouse_comparison' => $charts['warehouse_comparison'] ?? [],
            ],
            'total' => $this->repo->countValuationLines($filters),
            'data' => $this->repo->listValuationLines($filters, $method, $limit, $offset),
        ];
    }
}

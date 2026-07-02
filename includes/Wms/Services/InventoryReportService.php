<?php
declare(strict_types=1);

require_once __DIR__ . '/../Repositories/InventoryReportRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseLogRepository.php';
require_once __DIR__ . '/../WmsSchema.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';

class InventoryReportService
{
    private InventoryReportRepository $repo;

    public function __construct(?PDO $db = null)
    {
        $this->repo = new InventoryReportRepository($db);
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function parseFilters(array $input): array
    {
        $storeId = (int) ($input['store_id'] ?? 0) ?: StoreScope::activeStoreId();
        if (StoreScope::isGlobalView() && empty($input['store_id'])) {
            $storeId = null;
        }
        return array_filter([
            'warehouse_id' => (int) ($input['warehouse_id'] ?? 0) ?: null,
            'store_id' => $storeId,
            'category_id' => (int) ($input['category_id'] ?? 0) ?: null,
            'supplier_id' => (int) ($input['supplier_id'] ?? 0) ?: null,
            'product_id' => (int) ($input['product_id'] ?? 0) ?: null,
            'stock_status' => ($input['stock_status'] ?? '') !== '' && ($input['stock_status'] ?? '') !== 'all'
                ? (string) $input['stock_status'] : null,
            'movement_type' => ($input['movement_type'] ?? $input['type'] ?? '') !== '' && ($input['movement_type'] ?? $input['type'] ?? '') !== 'all'
                ? (string) ($input['movement_type'] ?? $input['type']) : null,
            'date_from' => !empty($input['date_from']) ? (string) $input['date_from'] : null,
            'date_to' => !empty($input['date_to']) ? (string) $input['date_to'] : null,
            'q' => isset($input['q']) ? trim((string) $input['q']) : null,
            'zone' => !empty($input['zone']) ? trim((string) $input['zone']) : null,
            'aisle' => !empty($input['aisle']) ? trim((string) $input['aisle']) : null,
            'rack' => !empty($input['rack']) ? trim((string) $input['rack']) : null,
            'shelf' => !empty($input['shelf']) ? trim((string) $input['shelf']) : null,
            'bin' => !empty($input['bin']) ? trim((string) $input['bin']) : null,
            'batch_number' => !empty($input['batch_number']) ? trim((string) $input['batch_number']) : null,
            'serial_number' => !empty($input['serial_number']) ? trim((string) $input['serial_number']) : null,
            'valuation_method' => in_array($input['valuation_method'] ?? '', ['fifo', 'weighted', 'lifo'], true)
                ? (string) $input['valuation_method'] : 'weighted',
            'expiry_days' => max(1, min(365, (int) ($input['expiry_days'] ?? 90))),
        ], static fn ($v) => $v !== null && $v !== '');
    }

    public function handleTab(string $tab, array $filters, int $limit, int $offset): array
    {
        return match ($tab) {
            'inventory' => [
                'summary' => $this->repo->dashboardSummary($filters),
                'total' => $this->repo->countInventory($filters),
                'data' => $this->repo->listInventory($filters, $limit, $offset),
            ],
            'movements' => [
                'summary' => $this->repo->movementSummary($filters),
                'total' => $this->repo->countMovements($filters),
                'data' => $this->repo->listMovements($filters, $limit, $offset),
            ],
            'low_stock' => [
                'summary' => ['low_stock' => $this->repo->countLowStock($filters)],
                'total' => $this->repo->countLowStock($filters),
                'data' => $this->repo->listLowStock($filters, $limit, $offset),
            ],
            'out_of_stock' => [
                'summary' => ['out_of_stock' => $this->repo->countOutOfStock($filters)],
                'total' => $this->repo->countOutOfStock($filters),
                'data' => $this->repo->listOutOfStock($filters, $limit, $offset),
            ],
            'expiry' => [
                'summary' => ['expiry_rows' => $this->repo->countExpiry($filters, (int) ($filters['expiry_days'] ?? 90))],
                'total' => $this->repo->countExpiry($filters, (int) ($filters['expiry_days'] ?? 90)),
                'data' => $this->repo->listExpiry($filters, (int) ($filters['expiry_days'] ?? 90), $limit, $offset),
            ],
            'damaged' => [
                'summary' => ['damaged_rows' => $this->repo->countDamaged($filters)],
                'total' => $this->repo->countDamaged($filters),
                'data' => $this->repo->listDamaged($filters, $limit, $offset),
            ],
            'valuation' => [
                'summary' => $this->repo->valuation($filters, $filters['valuation_method'] ?? 'weighted'),
                'total' => 0,
                'data' => [],
            ],
            'performance' => [
                'summary' => $this->repo->performance($filters),
                'total' => 0,
                'data' => [],
            ],
            'filters' => [
                'summary' => null,
                'total' => 0,
                'data' => $this->repo->filterOptions($filters['store_id'] ?? StoreScope::activeStoreId()),
            ],
            default => [
                'summary' => $this->repo->dashboardSummary($filters),
                'charts' => $this->repo->charts($filters),
                'total' => $this->repo->countInventory($filters),
                'data' => [],
            ],
        };
    }

    public function logAudit(int $userId, ?int $warehouseId, array $details): void
    {
        WarehouseLogRepository::log('inventory_report', $warehouseId, $userId, 'inventory_report', null, $details);
    }

    public function alerts(array $filters): array
    {
        $summary = $this->repo->dashboardSummary($filters);
        $alerts = [];
        if (($summary['low_stock'] ?? 0) > 0) {
            $alerts[] = ['type' => 'low_stock', 'count' => $summary['low_stock']];
        }
        if (($summary['out_of_stock'] ?? 0) > 0) {
            $alerts[] = ['type' => 'out_of_stock', 'count' => $summary['out_of_stock']];
        }
        if (($summary['expired_qty'] ?? 0) > 0) {
            $alerts[] = ['type' => 'expired', 'count' => $summary['expired_qty']];
        }
        return $alerts;
    }
}

<?php
declare(strict_types=1);

require_once __DIR__ . '/../WmsSchema.php';
require_once __DIR__ . '/../Repositories/WarehouseRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseInventoryRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseLocationRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseTransferRepository.php';
require_once __DIR__ . '/../Repositories/GoodsReceiptRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseDispatchRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseRequestRepository.php';
require_once __DIR__ . '/../Repositories/BatchTrackingRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseAuditRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseMovementRepository.php';
require_once __DIR__ . '/../Repositories/PurchaseOrderRepository.php';
require_once __DIR__ . '/../Repositories/WarehouseLogRepository.php';
require_once __DIR__ . '/../Repositories/StoreNetworkRepository.php';
require_once __DIR__ . '/../WmsNotifier.php';
require_once __DIR__ . '/../../Notifications/NotificationEvents.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../../Helpers/WmsLedgerHelper.php';
require_once __DIR__ . '/../../Database/Database.php';

class WmsService
{
    private WarehouseRepository $warehouses;
    private WarehouseInventoryRepository $inventory;
    private WarehouseLocationRepository $locations;
    private WarehouseTransferRepository $transfers;
    private GoodsReceiptRepository $receipts;
    private WarehouseDispatchRepository $dispatches;
    private WarehouseRequestRepository $requests;
    private BatchTrackingRepository $batches;
    private WarehouseAuditRepository $audits;
    private WarehouseMovementRepository $movements;
    private PurchaseOrderRepository $purchaseOrders;
    private WarehouseLogRepository $logs;
    private StoreNetworkRepository $storeNetwork;
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->warehouses = new WarehouseRepository($this->db);
        $this->inventory = new WarehouseInventoryRepository($this->db);
        $this->locations = new WarehouseLocationRepository($this->db);
        $this->transfers = new WarehouseTransferRepository($this->db);
        $this->receipts = new GoodsReceiptRepository($this->db);
        $this->dispatches = new WarehouseDispatchRepository($this->db);
        $this->requests = new WarehouseRequestRepository($this->db);
        $this->batches = new BatchTrackingRepository($this->db);
        $this->audits = new WarehouseAuditRepository($this->db);
        $this->movements = new WarehouseMovementRepository($this->db);
        $this->purchaseOrders = new PurchaseOrderRepository($this->db);
        $this->logs = new WarehouseLogRepository();
        $this->storeNetwork = new StoreNetworkRepository($this->db);
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function listWarehouses(
        ?int $storeId,
        ?string $status = null,
        ?string $q = null,
        ?string $type = null,
        ?int $filterStoreId = null,
        int $limit = 200,
        int $offset = 0
    ): array {
        return $this->warehouses->list($storeId, $status, $q, $type, $filterStoreId, $limit, $offset);
    }

    public function countWarehouses(
        ?int $storeId,
        ?string $status = null,
        ?string $q = null,
        ?string $type = null,
        ?int $filterStoreId = null
    ): int {
        return $this->warehouses->count($storeId, $status, $q, $type, $filterStoreId);
    }

    public function warehouseSummary(?int $storeId): array
    {
        return $this->warehouses->countSummary($storeId);
    }

    public function warehouseNetworkSummary(?int $storeId): array
    {
        return $this->warehouses->networkSummary($storeId);
    }

    public function getWarehouse(int $id): ?array
    {
        $row = $this->warehouses->findById($id);
        if (!$row) {
            return null;
        }
        $row['inventory'] = $this->inventory->listByWarehouse($id);
        $row['locations'] = $this->locations->list($id);
        return $row;
    }

    public function createWarehouse(array $data, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        $code = trim((string) ($data['warehouse_code'] ?? ''));
        $name = trim((string) ($data['name'] ?? ''));
        if ($code === '' || $name === '') {
            return ['status' => 'error', 'message' => 'Code and name required'];
        }
        $id = $this->warehouses->create($data);
        WarehouseLogRepository::log('warehouse_created', $id, $userId, 'warehouse', $id, ['name' => $name]);
        return ['status' => 'success', 'data' => $this->warehouses->findById($id)];
    }

    public function updateWarehouse(int $id, array $data, int $userId): array
    {
        if (!$this->warehouses->findById($id)) {
            return ['status' => 'error', 'message' => 'Warehouse not found'];
        }
        $this->warehouses->update($id, $data);
        WarehouseLogRepository::log('warehouse_updated', $id, $userId, 'warehouse', $id);
        return ['status' => 'success', 'data' => $this->warehouses->findById($id)];
    }

    public function deleteWarehouse(int $id, int $userId): array
    {
        if (!$this->warehouses->findById($id)) {
            return ['status' => 'error', 'message' => 'Warehouse not found'];
        }
        $this->warehouses->softDelete($id);
        WarehouseLogRepository::log('warehouse_deleted', $id, $userId, 'warehouse', $id);
        return ['status' => 'success'];
    }

    public function listInventory(int $warehouseId, ?string $search = null, ?string $stockFilter = null): array
    {
        if ($warehouseId <= 0) {
            return [];
        }
        return $this->inventory->listByWarehouse($warehouseId, $search, $stockFilter);
    }

    public function inventorySummary(int $warehouseId): array
    {
        if ($warehouseId <= 0) {
            return ['sku_count' => 0, 'total_units' => 0, 'total_value' => 0, 'low_stock' => 0, 'out_of_stock' => 0];
        }
        return $this->inventory->summary($warehouseId);
    }

    public function getInventoryItem(int $warehouseId, int $productId): ?array
    {
        if ($warehouseId <= 0 || $productId <= 0) {
            return null;
        }
        $row = $this->inventory->findDetail($warehouseId, $productId);
        if (!$row) {
            return null;
        }
        $row['movements'] = $this->movements->list($warehouseId, ['product_id' => $productId], 15);
        return $row;
    }

    public function listProducts(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search = null,
        ?string $stockFilter = null,
        ?int $categoryId = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->inventory->listProductCatalog($warehouseId, $storeId, $search, $stockFilter, $categoryId, $limit, $offset);
    }

    public function countProducts(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search = null,
        ?string $stockFilter = null,
        ?int $categoryId = null
    ): int {
        return $this->inventory->countProductCatalog($warehouseId, $storeId, $search, $stockFilter, $categoryId);
    }

    public function productsSummary(?int $warehouseId, ?int $storeId): array
    {
        return $this->inventory->productCatalogSummary($warehouseId, $storeId);
    }

    public function getProductCatalog(int $productId, ?int $storeId): ?array
    {
        if ($productId <= 0) {
            return null;
        }
        return $this->inventory->findProductCatalog($productId, $storeId);
    }

    public function listProductCategories(?int $storeId): array
    {
        return $this->inventory->listCategoriesForCatalog($storeId);
    }

    public function listStockLevels(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search = null,
        ?string $levelFilter = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->inventory->listStockLevels($warehouseId, $storeId, $search, $levelFilter, $limit, $offset);
    }

    public function countStockLevels(
        ?int $warehouseId,
        ?int $storeId,
        ?string $search = null,
        ?string $levelFilter = null
    ): int {
        return $this->inventory->countStockLevels($warehouseId, $storeId, $search, $levelFilter);
    }

    public function stockLevelsSummary(?int $warehouseId, ?int $storeId): array
    {
        return $this->inventory->stockLevelsSummary($warehouseId, $storeId);
    }

    public function listLocations(int $warehouseId, ?string $search = null, ?string $status = null, ?string $zone = null, int $limit = 200, int $offset = 0): array
    {
        return $this->locations->list($warehouseId, $search, $status, $zone, $limit, $offset);
    }

    public function countLocations(int $warehouseId, ?string $search = null, ?string $status = null, ?string $zone = null): int
    {
        return $this->locations->count($warehouseId, $search, $status, $zone);
    }

    public function locationSummary(int $warehouseId): array
    {
        return $this->locations->summary($warehouseId);
    }

    public function locationZoneBreakdown(int $warehouseId): array
    {
        return $this->locations->zoneBreakdown($warehouseId);
    }

    public function createLocation(array $data, int $userId): array
    {
        $id = $this->locations->create($data);
        WarehouseLogRepository::log('location_created', (int) $data['warehouse_id'], $userId, 'warehouse_location', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function listMovements(?int $warehouseId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->movements->list($warehouseId, $filters, $limit, $offset);
    }

    public function countMovements(?int $warehouseId, array $filters = []): int
    {
        return $this->movements->count($warehouseId, $filters);
    }

    public function movementSummary(?int $warehouseId, array $filters = []): array
    {
        return $this->movements->summary($warehouseId, $filters);
    }

    public function movementBreakdown(?int $warehouseId, array $filters = []): array
    {
        return $this->movements->breakdownByType($warehouseId, $filters);
    }

    public function movementTrend(?int $warehouseId, array $filters = [], int $days = 30): array
    {
        return $this->movements->movementTrend($warehouseId, $filters, $days);
    }

    public function listAdjustments(?int $warehouseId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        $filters['scope'] = 'adjustments';
        return $this->movements->list($warehouseId, $filters, $limit, $offset);
    }

    public function countAdjustments(?int $warehouseId, array $filters = []): int
    {
        $filters['scope'] = 'adjustments';
        return $this->movements->count($warehouseId, $filters);
    }

    public function adjustmentSummary(?int $warehouseId, array $filters = []): array
    {
        $filters['scope'] = 'adjustments';
        return $this->movements->summary($warehouseId, $filters);
    }

    public function adjustmentBreakdown(?int $warehouseId, array $filters = []): array
    {
        $filters['scope'] = 'adjustments';
        return $this->movements->breakdownByType($warehouseId, $filters);
    }

    public function createAdjustment(array $data, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }

        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        $quantity = (int) ($data['quantity'] ?? 0);
        $movementType = (string) ($data['movement_type'] ?? 'adjustment');
        $notes = trim((string) ($data['notes'] ?? ''));

        if (!in_array($movementType, WarehouseMovementRepository::ADJUSTMENT_TYPES, true)) {
            return ['status' => 'error', 'message' => 'Invalid adjustment type'];
        }
        if ($warehouseId <= 0 || $productId <= 0 || $quantity === 0) {
            return ['status' => 'error', 'message' => 'Invalid adjustment data'];
        }

        $inv = $this->inventory->find($warehouseId, $productId);
        $unitCost = (float) ($inv['unit_cost'] ?? 0);
        if ($unitCost <= 0) {
            $pStmt = $this->db->prepare('SELECT cost FROM products WHERE id = ? LIMIT 1');
            $pStmt->execute([$productId]);
            $unitCost = (float) ($pStmt->fetchColumn() ?: 0);
        }

        $currentQty = (int) ($inv['quantity'] ?? 0);
        if ($quantity < 0 && $currentQty + $quantity < 0) {
            return ['status' => 'error', 'message' => 'Insufficient stock'];
        }

        $this->db->beginTransaction();
        try {
            $result = WmsLedgerHelper::applyMovement(
                $this->db,
                $warehouseId,
                $productId,
                $quantity,
                $movementType,
                $unitCost,
                $userId,
                'manual_adjustment',
                null,
                $notes !== '' ? $notes : null
            );
            WarehouseLogRepository::log('stock_adjusted', $warehouseId, $userId, 'warehouse_movement', (int) $result['movement_id'], [
                'product_id' => $productId,
                'quantity' => $quantity,
                'movement_type' => $movementType,
            ]);
            $this->db->commit();
            return ['status' => 'success', 'data' => $result];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function listTransfers(
        ?string $status,
        ?int $warehouseId,
        ?string $search = null,
        ?string $direction = null,
        int $limit = 50,
        int $offset = 0,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        return $this->transfers->list($status, $warehouseId, $search, $direction, $limit, $offset, $transferType, $storeId, $dateFrom, $dateTo);
    }

    public function countTransfers(
        ?string $status,
        ?int $warehouseId,
        ?string $search = null,
        ?string $direction = null,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        return $this->transfers->count($status, $warehouseId, $search, $direction, $transferType, $storeId, $dateFrom, $dateTo);
    }

    public function transferStatusBreakdown(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $direction = null,
        ?string $transferType = null,
        ?int $storeId = null,
        ?string $status = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        return $this->transfers->statusBreakdown($warehouseId, $search, $direction, $transferType, $storeId, $status, $dateFrom, $dateTo);
    }

    public function transferSummary(?int $warehouseId = null): array
    {
        return $this->transfers->summary($warehouseId);
    }

    public function transferReportSummary(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null,
        ?string $direction = null
    ): array {
        return $this->transfers->reportSummary($warehouseId, $search, $dateFrom, $dateTo, $transferType, $direction);
    }

    public function transferTrend(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null,
        ?string $direction = null,
        int $days = 30
    ): array {
        return $this->transfers->transferTrend($warehouseId, $search, $dateFrom, $dateTo, $transferType, $direction, $days);
    }

    public function branchTransferSummary(?int $storeId = null, ?string $search = null): array
    {
        return $this->transfers->branchSummary($storeId, $search);
    }

    public function approvalTransferSummary(?int $warehouseId = null, ?string $search = null, ?string $transferType = null): array
    {
        return $this->transfers->approvalSummary($warehouseId, $search, $transferType);
    }

    public function approvalTransferTypeBreakdown(?int $warehouseId = null, ?string $search = null, ?string $transferType = null): array
    {
        return $this->transfers->approvalTypeBreakdown($warehouseId, $search, $transferType);
    }

    public function historyTransferSummary(
        ?int $warehouseId = null,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $transferType = null
    ): array {
        return $this->transfers->historySummary($warehouseId, $search, $dateFrom, $dateTo, $transferType);
    }

    public function incomingTransferSummary(?int $warehouseId = null, ?string $search = null): array
    {
        return $this->transfers->incomingSummary($warehouseId, $search);
    }

    public function outgoingTransferSummary(?int $warehouseId = null, ?string $search = null): array
    {
        return $this->transfers->outgoingSummary($warehouseId, $search);
    }

    public function getTransfer(int $id): ?array
    {
        return $this->transfers->findById($id);
    }

    public function createTransfer(array $data, array $items, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        if (empty($items)) {
            return ['status' => 'error', 'message' => 'Items required'];
        }
        $type = (string) ($data['transfer_type'] ?? '');
        if (!in_array($type, ['warehouse_to_warehouse', 'warehouse_to_store', 'store_to_warehouse', 'branch_to_branch'], true)) {
            return ['status' => 'error', 'message' => 'Invalid transfer type'];
        }
        if ($type === 'warehouse_to_warehouse' && (empty($data['from_warehouse_id']) || empty($data['to_warehouse_id']))) {
            return ['status' => 'error', 'message' => 'Source and destination warehouses required'];
        }
        if ($type === 'warehouse_to_store' && (empty($data['from_warehouse_id']) || empty($data['to_store_id']))) {
            return ['status' => 'error', 'message' => 'Warehouse and store required'];
        }
        if ($type === 'store_to_warehouse' && (empty($data['from_store_id']) || empty($data['to_warehouse_id']))) {
            return ['status' => 'error', 'message' => 'Store and warehouse required'];
        }
        if ($type === 'branch_to_branch' && (empty($data['from_store_id']) || empty($data['to_store_id']))) {
            return ['status' => 'error', 'message' => 'Source and destination stores required'];
        }
        $data['requested_by'] = $userId;
        $id = $this->transfers->create($data, $items);
        WarehouseLogRepository::log('transfer_requested', $data['from_warehouse_id'] ?? null, $userId, 'warehouse_transfer', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function approveTransfer(int $id, int $userId): array
    {
        $row = $this->transfers->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Transfer not found'];
        }
        $this->transfers->updateStatus($id, 'approved', $userId);
        WmsNotifier::transferApproved((int) ($row['from_warehouse_id'] ?? 0), (string) $row['transfer_number']);
        return ['status' => 'success'];
    }

    public function completeTransfer(int $id, int $userId): array
    {
        $row = $this->transfers->findById($id);
        if (!$row || ($row['status'] ?? '') !== 'approved') {
            return ['status' => 'error', 'message' => 'Transfer not ready'];
        }
        $fromWh = (int) ($row['from_warehouse_id'] ?? 0);
        $toWh = (int) ($row['to_warehouse_id'] ?? 0);
        $this->db->beginTransaction();
        try {
            foreach ($row['items'] as $item) {
                $qty = (int) ($item['quantity_requested'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $pid = (int) $item['product_id'];
                if ($fromWh) {
                    WmsLedgerHelper::applyMovement($this->db, $fromWh, $pid, -$qty, 'transfer_out', $cost, $userId, 'warehouse_transfer', $id);
                }
                if ($toWh) {
                    WmsLedgerHelper::applyMovement($this->db, $toWh, $pid, $qty, 'transfer_in', $cost, $userId, 'warehouse_transfer', $id);
                }
            }
            $this->transfers->updateStatus($id, 'completed', $userId, 'received_by');
            $this->db->commit();
            WmsNotifier::transferApproved($toWh ?: $fromWh, (string) $row['transfer_number']);
            return ['status' => 'success'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function rejectTransfer(int $id, int $userId): array
    {
        $row = $this->transfers->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Transfer not found'];
        }
        $this->transfers->updateStatus($id, 'rejected', $userId);
        WmsNotifier::transferRejected((int) ($row['from_warehouse_id'] ?? 0), (string) $row['transfer_number']);
        return ['status' => 'success'];
    }

    public function listReceipts(?int $warehouseId, ?string $status, ?string $search = null, int $limit = 50, int $offset = 0, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        $rows = $this->receipts->list($warehouseId, $status, $search, $limit, $offset, $dateFrom, $dateTo);
        return array_map([$this, 'formatReceiptRow'], $rows);
    }

    public function countReceipts(?int $warehouseId, ?string $status, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): int
    {
        return $this->receipts->count($warehouseId, $status, $search, $dateFrom, $dateTo);
    }

    public function receiptSummary(?int $warehouseId, ?string $status, ?string $search = null): array
    {
        return $this->receipts->summary($warehouseId, $status, $search);
    }

    public function receiptStatusBreakdown(?int $warehouseId, ?string $search = null): array
    {
        return $this->receipts->statusBreakdown($warehouseId, $search);
    }

    public function receiptTrend(?int $warehouseId, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null, int $days = 30): array
    {
        return $this->receipts->receiptTrend($warehouseId, $search, $dateFrom, $dateTo, $days);
    }

    public function incomingDeliverySummary(?int $warehouseId, ?string $search = null): array
    {
        return $this->receipts->incomingSummary($warehouseId, $search);
    }

    public function inspectionQueueSummary(?int $warehouseId, ?string $search = null): array
    {
        return $this->receipts->inspectionSummary($warehouseId, $search);
    }

    public function historyReceiptSummary(?int $warehouseId, ?string $search = null, ?string $dateFrom = null, ?string $dateTo = null): array
    {
        return $this->receipts->historySummary($warehouseId, $search, $dateFrom, $dateTo);
    }

    public function saveReceiptInspection(int $id, array $items, int $userId): array
    {
        $row = $this->receipts->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Receipt not found'];
        }
        if (!in_array($row['status'], ['pending', 'inspecting'], true)) {
            return ['status' => 'error', 'message' => 'Inspection not allowed for this receipt'];
        }
        $this->receipts->saveInspectionItems($id, $items);
        return ['status' => 'success', 'data' => $this->receipts->findById($id)];
    }

    public function updateReceiptStatus(int $id, string $newStatus, int $userId): array
    {
        $row = $this->receipts->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Receipt not found'];
        }
        $current = (string) ($row['status'] ?? '');
        $allowed = [
            'pending' => ['inspecting', 'rejected'],
            'inspecting' => ['accepted', 'rejected'],
            'accepted' => ['rejected'],
        ];
        if (!in_array($newStatus, $allowed[$current] ?? [], true)) {
            return ['status' => 'error', 'message' => 'Invalid status transition'];
        }
        if (!$this->receipts->updateStatus($id, $newStatus, $userId)) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
        return ['status' => 'success', 'data' => $this->receipts->findById($id)];
    }

    public function getReceipt(int $id): ?array
    {
        $row = $this->receipts->findById($id);
        return $row ? $this->formatReceiptRow($row) : null;
    }

    /** @param array<string, mixed> $row */
    private function formatReceiptRow(array $row): array
    {
        $supplier = trim((string) ($row['supplier_name'] ?? ''));
        if ($supplier === '') {
            $supplier = $this->extractReceiptSupplierFromNotes((string) ($row['notes'] ?? ''));
        }
        $row['supplier_name'] = $supplier !== '' ? $supplier : null;
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['warehouse_id'] = (int) ($row['warehouse_id'] ?? 0);
        $row['supplier_id'] = !empty($row['supplier_id']) ? (int) $row['supplier_id'] : null;
        $row['purchase_order_id'] = !empty($row['purchase_order_id']) ? (int) $row['purchase_order_id'] : null;
        $row['total_items'] = (int) ($row['total_items'] ?? 0);
        $row['total_value'] = round((float) ($row['total_value'] ?? 0), 2);
        if (!empty($row['items']) && is_array($row['items'])) {
            $row['items'] = array_map(static function (array $item): array {
                $item['id'] = (int) ($item['id'] ?? 0);
                $item['quantity_received'] = (int) ($item['quantity_received'] ?? 0);
                $item['unit_cost'] = round((float) ($item['unit_cost'] ?? 0), 4);
                return $item;
            }, $row['items']);
        }
        return $row;
    }

    private function extractReceiptSupplierFromNotes(string $notes): string
    {
        if ($notes === '') {
            return '';
        }
        if (preg_match('/^(?:Supplier|Fournisseur)\s*:\s*(.+?)(?:\r?\n|$)/iu', $notes, $m)) {
            return trim($m[1]);
        }
        return '';
    }

    public function listPurchaseOrders(?int $warehouseId, ?string $status, ?string $search, int $limit = 50, int $offset = 0): array
    {
        return $this->purchaseOrders->list($warehouseId, $status, $search, $limit, $offset);
    }

    public function countPurchaseOrders(?int $warehouseId, ?string $status, ?string $search): int
    {
        return $this->purchaseOrders->count($warehouseId, $status, $search);
    }

    public function purchaseOrderSummary(?int $warehouseId, ?string $status, ?string $search): array
    {
        return $this->purchaseOrders->summary($warehouseId, $status, $search);
    }

    public function purchaseOrderStatusBreakdown(?int $warehouseId, ?string $search): array
    {
        return $this->purchaseOrders->statusBreakdown($warehouseId, $search);
    }

    public function getPurchaseOrder(int $id): ?array
    {
        return $this->purchaseOrders->findById($id);
    }

    public function createPurchaseOrder(array $data, array $items, int $userId): array
    {
        if (!$this->moduleReady() || !$this->purchaseOrders->tableReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        if (empty($items)) {
            return ['status' => 'error', 'message' => 'At least one line item required'];
        }
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        if ($warehouseId <= 0) {
            return ['status' => 'error', 'message' => 'Warehouse required'];
        }
        try {
            $supplierId = $this->purchaseOrders->resolveSupplierId(
                isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                (string) ($data['supplier_name'] ?? '')
            );
        } catch (InvalidArgumentException $e) {
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
        $data['supplier_id'] = $supplierId;
        $data['created_by'] = $userId;
        $data['store_id'] = $data['store_id'] ?? ($_SESSION['store_id'] ?? null);
        $data['status'] = in_array($data['status'] ?? '', ['draft', 'pending'], true) ? $data['status'] : 'pending';
        $id = $this->purchaseOrders->create($data, $items);
        WarehouseLogRepository::log('po_created', $warehouseId, $userId, 'purchase_order', $id);
        return ['status' => 'success', 'data' => $this->purchaseOrders->findById($id)];
    }

    public function updatePurchaseOrderStatus(int $id, string $newStatus, int $userId): array
    {
        $po = $this->purchaseOrders->findById($id);
        if (!$po) {
            return ['status' => 'error', 'message' => 'Purchase order not found'];
        }
        $current = (string) ($po['status'] ?? '');
        $allowed = [
            'draft' => ['pending', 'cancelled'],
            'pending' => ['approved', 'cancelled'],
            'approved' => ['cancelled'],
            'partial' => ['cancelled'],
        ];
        if (!in_array($newStatus, $allowed[$current] ?? [], true)) {
            return ['status' => 'error', 'message' => 'Invalid status transition'];
        }
        if (!$this->purchaseOrders->updateStatus($id, $newStatus, $userId)) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
        WarehouseLogRepository::log('po_status_' . $newStatus, (int) $po['warehouse_id'], $userId, 'purchase_order', $id);
        return ['status' => 'success', 'data' => $this->purchaseOrders->findById($id)];
    }

    public function createGrnFromPurchaseOrder(int $poId, int $userId): array
    {
        $po = $this->purchaseOrders->findById($poId);
        if (!$po) {
            return ['status' => 'error', 'message' => 'Purchase order not found'];
        }
        if (!in_array($po['status'], ['approved', 'partial'], true)) {
            return ['status' => 'error', 'message' => 'PO must be approved before receiving'];
        }
        $items = [];
        foreach ($po['items'] ?? [] as $line) {
            $remaining = (int) ($line['quantity_ordered'] ?? 0) - (int) ($line['quantity_received'] ?? 0);
            if ($remaining <= 0) {
                continue;
            }
            $items[] = [
                'product_id' => (int) $line['product_id'],
                'quantity_expected' => $remaining,
                'quantity_received' => $remaining,
                'unit_cost' => (float) ($line['unit_cost'] ?? 0),
            ];
        }
        if (!$items) {
            return ['status' => 'error', 'message' => 'Nothing left to receive on this PO'];
        }
        $result = $this->createReceipt([
            'warehouse_id' => (int) $po['warehouse_id'],
            'supplier_id' => (int) $po['supplier_id'],
            'purchase_order_id' => $poId,
            'notes' => trim('GRN from ' . ($po['po_number'] ?? 'PO') . ($po['notes'] ? ' — ' . $po['notes'] : '')),
            'status' => 'pending',
        ], $items, $userId);
        if (($result['status'] ?? '') === 'success' && $po['status'] === 'approved') {
            $this->purchaseOrders->updateStatus($poId, 'partial', $userId);
        }
        return $result;
    }

    public function createReceipt(array $data, array $items, int $userId): array
    {
        if (empty($items)) {
            return ['status' => 'error', 'message' => 'Items required'];
        }
        $warehouseId = (int) ($data['warehouse_id'] ?? 0);
        $resolved = $this->resolveReceiptLineProducts($items, $warehouseId, $userId);
        if (($resolved['status'] ?? '') !== 'success') {
            return $resolved;
        }
        $items = $resolved['items'];

        $seenProductIds = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            if (isset($seenProductIds[$productId])) {
                return ['status' => 'error', 'message' => 'Duplicate product on receipt lines'];
            }
            $seenProductIds[$productId] = true;
        }

        $total = 0.0;
        foreach ($items as $item) {
            $total += ((int) ($item['quantity_received'] ?? $item['quantity'] ?? 0)) * (float) ($item['unit_cost'] ?? 0);
        }
        $data['received_by'] = $userId;
        $data['total_value'] = $total;
        if (!empty($data['supplier_name']) || !empty($data['supplier_id'])) {
            try {
                $data['supplier_id'] = $this->purchaseOrders->resolveSupplierId(
                    isset($data['supplier_id']) ? (int) $data['supplier_id'] : null,
                    (string) ($data['supplier_name'] ?? '')
                );
            } catch (InvalidArgumentException) {
                unset($data['supplier_id']);
            }
        }
        $id = $this->receipts->create($data, $items);
        WmsNotifier::incomingDelivery((int) $data['warehouse_id'], 'GRN#' . $id);
        return ['status' => 'success', 'data' => $this->receipts->findById($id)];
    }

    /**
     * Match GRN lines to catalog products or create new entries for typed names.
     */
    private function resolveReceiptLineProducts(array $items, int $warehouseId, int $userId): array
    {
        if ($warehouseId <= 0) {
            return ['status' => 'error', 'message' => 'Warehouse required'];
        }
        $warehouse = $this->warehouses->findById($warehouseId);
        if (!$warehouse) {
            return ['status' => 'error', 'message' => 'Warehouse not found'];
        }
        $storeId = (int) ($warehouse['store_id'] ?? 0);
        if ($storeId <= 0) {
            $active = StoreScope::activeStoreId();
            $storeId = $active !== null && $active > 0 ? $active : StoreScope::resolveStoreId($this->db);
        }

        foreach ($items as $i => $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $name = trim((string) ($item['product_name'] ?? ''));

            if ($productId > 0) {
                continue;
            }
            if ($name === '') {
                return ['status' => 'error', 'message' => 'Each line needs a product'];
            }

            $existingId = $this->findProductIdByName($name, $storeId);
            if ($existingId > 0) {
                $items[$i]['product_id'] = $existingId;
                unset($items[$i]['product_name']);
                continue;
            }

            $unitCost = (float) ($item['unit_cost'] ?? 0);
            $newId = $this->createQuickProduct($name, $unitCost, $storeId);
            if ($newId <= 0) {
                return ['status' => 'error', 'message' => 'Could not create product: ' . $name];
            }
            $items[$i]['product_id'] = $newId;
            unset($items[$i]['product_name']);
            WarehouseLogRepository::log('product_quick_created', $warehouseId, $userId, 'product', $newId);
        }

        return ['status' => 'success', 'items' => $items];
    }

    private function findProductIdByName(string $name, int $storeId): int
    {
        $sql = 'SELECT id FROM products WHERE deleted_at IS NULL AND LOWER(TRIM(name)) = LOWER(?)';
        $params = [$name];
        if ($storeId > 0) {
            $sql .= ' AND (store_id = ? OR store_id IS NULL)';
            $params[] = $storeId;
        }
        $sql .= ' ORDER BY id ASC LIMIT 1';
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) ($stmt->fetchColumn() ?: 0);
    }

    private function createQuickProduct(string $name, float $unitCost, int $storeId): int
    {
        $price = $unitCost > 0 ? $unitCost : 0.0;
        $cost = $unitCost > 0 ? $unitCost : 0.0;
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $sku = 'WH-' . date('ymd') . '-' . strtoupper(bin2hex(random_bytes(2)));
            try {
                $stmt = $this->db->prepare(
                    'INSERT INTO products (name, sku, price, cost, stock_quantity, min_stock_level, unit, store_id)
                     VALUES (?, ?, ?, ?, 0, 5, ?, ?)'
                );
                $stmt->execute([
                    $name,
                    $sku,
                    $price,
                    $cost,
                    'piece',
                    $storeId > 0 ? $storeId : null,
                ]);
                return (int) $this->db->lastInsertId();
            } catch (PDOException $e) {
                if ($attempt >= 4 || stripos($e->getMessage(), 'Duplicate') === false) {
                    return 0;
                }
            }
        }
        return 0;
    }

    public function completeReceipt(int $id, int $userId): array
    {
        $row = $this->receipts->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Receipt not found'];
        }
        $whId = (int) $row['warehouse_id'];
        $this->db->beginTransaction();
        try {
            foreach ($row['items'] as $item) {
                $qty = (int) ($item['quantity_received'] ?? 0);
                $cost = (float) ($item['unit_cost'] ?? 0);
                $batchId = null;
                if (!empty($item['batch_number'])) {
                    $batchId = $this->batches->create([
                        'warehouse_id' => $whId,
                        'product_id' => (int) $item['product_id'],
                        'batch_number' => $item['batch_number'],
                        'expiry_date' => $item['expiry_date'] ?? null,
                        'quantity' => $qty,
                        'unit_cost' => $cost,
                        'barcode' => $item['barcode'] ?? null,
                    ]);
                }
                WmsLedgerHelper::applyMovement(
                    $this->db, $whId, (int) $item['product_id'], $qty, 'receipt_in', $cost, $userId,
                    'goods_receipt', $id, null, $item['location_id'] ?? null, $batchId
                );
            }
            $this->receipts->updateStatus($id, 'completed', $userId);
            if (!empty($row['purchase_order_id'])) {
                $this->purchaseOrders->syncFromGrn((int) $row['purchase_order_id'], $row['items'] ?? []);
            }
            $this->db->commit();
            WmsNotifier::purchaseReceived($whId, (string) $row['grn_number'], (float) $row['total_value']);
            return ['status' => 'success'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function listDispatches(
        ?int $warehouseId,
        ?string $status,
        ?string $search = null,
        int $limit = 150,
        int $offset = 0,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        return $this->dispatches->list($warehouseId, $status, $search, $limit, $offset, $dateFrom, $dateTo);
    }

    public function countDispatches(
        ?int $warehouseId,
        ?string $status,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): int {
        return $this->dispatches->count($warehouseId, $status, $search, $dateFrom, $dateTo);
    }

    public function dispatchStatusBreakdown(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        ?string $statusScope = null
    ): array {
        return $this->dispatches->statusBreakdown($warehouseId, $search, $dateFrom, $dateTo, $statusScope);
    }

    public function dispatchHistorySummary(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        return $this->dispatches->historySummary($warehouseId, $search, $dateFrom, $dateTo);
    }

    public function dispatchPackingSummary(?int $warehouseId = null): array
    {
        return $this->dispatches->packingSummary($warehouseId);
    }

    public function dispatchPickingSummary(?int $warehouseId = null): array
    {
        return $this->dispatches->pickingSummary($warehouseId);
    }

    public function dispatchShippingSummary(?int $warehouseId = null): array
    {
        return $this->dispatches->shippingSummary($warehouseId);
    }

    public function dispatchDeliverySummary(?int $warehouseId = null): array
    {
        return $this->dispatches->deliverySummary($warehouseId);
    }

    public function dispatchSummary(?int $warehouseId = null): array
    {
        return $this->dispatches->summary($warehouseId);
    }

    public function dispatchReportSummary(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null
    ): array {
        return $this->dispatches->reportSummary($warehouseId, $search, $dateFrom, $dateTo);
    }

    public function dispatchTrend(
        ?int $warehouseId,
        ?string $search = null,
        ?string $dateFrom = null,
        ?string $dateTo = null,
        int $days = 30
    ): array {
        return $this->dispatches->dispatchTrend($warehouseId, $search, $dateFrom, $dateTo, $days);
    }

    public function getDispatch(int $id): ?array
    {
        return $this->dispatches->findById($id);
    }

    public function createDispatch(array $data, array $items, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        $wh = (int) ($data['from_warehouse_id'] ?? 0);
        if ($wh <= 0) {
            return ['status' => 'error', 'message' => 'Warehouse required'];
        }
        if (empty($data['to_store_id']) && empty($data['to_warehouse_id'])) {
            return ['status' => 'error', 'message' => 'Destination required'];
        }
        if (!$items) {
            return ['status' => 'error', 'message' => 'At least one item required'];
        }
        $data['created_by'] = $userId;
        $id = $this->dispatches->create($data, $items);
        WarehouseLogRepository::log('dispatch_created', $wh, $userId, 'warehouse_dispatch', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function updateDispatchStatus(int $id, string $status, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        $row = $this->dispatches->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Dispatch not found'];
        }
        $current = (string) ($row['status'] ?? '');
        $next = strtolower(trim($status));
        $allowed = [
            'draft' => ['picking'],
            'picking' => ['packed'],
            'dispatched' => ['delivered'],
            'in_transit' => ['delivered'],
        ];
        if (!in_array($next, $allowed[$current] ?? [], true)) {
            return ['status' => 'error', 'message' => 'Invalid status transition'];
        }
        $receivedBy = $next === 'delivered' ? $userId : null;
        if (!$this->dispatches->updateStatus($id, $next, $receivedBy)) {
            return ['status' => 'error', 'message' => 'Update failed'];
        }
        $whId = (int) ($row['from_warehouse_id'] ?? 0);
        $logAction = $next === 'delivered' ? 'dispatch_delivered' : 'dispatch_' . $next;
        WarehouseLogRepository::log($logAction, $whId, $userId, 'warehouse_dispatch', $id);
        return ['status' => 'success', 'data' => ['id' => $id, 'status' => $next]];
    }

    public function dispatchOut(int $id, int $userId): array
    {
        $stmt = $this->db->prepare(
            'SELECT d.*, di.product_id, di.quantity, di.unit_cost FROM warehouse_dispatches d
             INNER JOIN warehouse_dispatch_items di ON di.dispatch_id = d.id WHERE d.id = ?'
        );
        $stmt->execute([$id]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows) {
            return ['status' => 'error', 'message' => 'Dispatch not found'];
        }
        $whId = (int) $rows[0]['from_warehouse_id'];
        $this->db->beginTransaction();
        try {
            foreach ($rows as $r) {
                WmsLedgerHelper::applyMovement(
                    $this->db, $whId, (int) $r['product_id'], -(int) $r['quantity'],
                    'dispatch_out', (float) $r['unit_cost'], $userId, 'warehouse_dispatch', $id
                );
            }
            $this->dispatches->updateStatus($id, 'dispatched');
            WarehouseLogRepository::log('dispatch_out', $whId, $userId, 'warehouse_dispatch', $id);
            $this->db->commit();
            return ['status' => 'success'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function listRequests(
        ?int $storeId,
        ?string $status,
        ?int $warehouseId = null,
        ?string $search = null,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->requests->list($storeId, $status, $warehouseId, $search, $limit, $offset);
    }

    public function countRequests(
        ?int $storeId,
        ?string $status,
        ?int $warehouseId = null,
        ?string $search = null
    ): int {
        return $this->requests->count($storeId, $status, $warehouseId, $search);
    }

    public function requestStatusBreakdown(
        ?int $storeId = null,
        ?int $warehouseId = null,
        ?string $search = null
    ): array {
        return $this->requests->statusBreakdown($storeId, $warehouseId, $search);
    }

    public function requestSummary(?int $storeId = null, ?int $warehouseId = null): array
    {
        return $this->requests->summary($storeId, $warehouseId);
    }

    public function getRequest(int $id): ?array
    {
        return $this->requests->findById($id);
    }

    public function createRequest(array $data, array $items, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        if (empty($data['store_id']) || empty($data['warehouse_id'])) {
            return ['status' => 'error', 'message' => 'Store and warehouse required'];
        }
        if (!$items) {
            return ['status' => 'error', 'message' => 'At least one item required'];
        }
        $data['requested_by'] = $userId;
        $id = $this->requests->create($data, $items);
        WarehouseLogRepository::log('request_created', (int) $data['warehouse_id'], $userId, 'warehouse_request', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function approveRequest(int $id, int $userId, string $role = 'manager'): array
    {
        $row = $this->requests->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Request not found'];
        }
        if ($role === 'warehouse' && $row['status'] !== 'manager_approved') {
            return ['status' => 'error', 'message' => 'Manager approval required first'];
        }
        if ($role === 'manager' && $row['status'] !== 'pending') {
            return ['status' => 'error', 'message' => 'Request is not pending'];
        }
        $status = $role === 'warehouse' ? 'warehouse_approved' : 'manager_approved';
        $this->requests->updateStatus($id, $status, $userId, $role);
        WarehouseLogRepository::log('request_approved', (int) $row['warehouse_id'], $userId, 'warehouse_request', $id, ['role' => $role]);
        return ['status' => 'success'];
    }

    public function rejectRequest(int $id, int $userId): array
    {
        $row = $this->requests->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Request not found'];
        }
        if (!in_array($row['status'], ['pending', 'manager_approved'], true)) {
            return ['status' => 'error', 'message' => 'Cannot reject this request'];
        }
        $this->requests->updateStatus($id, 'rejected', $userId, 'manager');
        WarehouseLogRepository::log('request_rejected', (int) $row['warehouse_id'], $userId, 'warehouse_request', $id);
        return ['status' => 'success'];
    }

    public function listBatches(
        ?int $warehouseId,
        ?string $status,
        ?string $search = null,
        int $days = 30,
        int $limit = 50,
        int $offset = 0,
        ?string $scope = null,
        ?string $strategy = null
    ): array {
        return $this->batches->list($warehouseId, $status, $search, $days, $limit, $offset, $scope, $strategy);
    }

    public function countBatches(
        ?int $warehouseId,
        ?string $status,
        ?string $search = null,
        int $days = 30,
        ?string $scope = null
    ): int {
        return $this->batches->count($warehouseId, $status, $search, $days, $scope);
    }

    public function batchStatusBreakdown(
        ?int $warehouseId = null,
        ?string $search = null,
        int $days = 30,
        ?string $scope = null
    ): array {
        return $this->batches->statusBreakdown($warehouseId, $search, $days, $scope);
    }

    public function serialSummary(?int $warehouseId = null): array
    {
        return $this->batches->serialSummary($warehouseId);
    }

    public function fifoSummary(?int $warehouseId = null): array
    {
        return $this->batches->fifoSummary($warehouseId);
    }

    public function fifoStrategyBreakdown(?int $warehouseId = null, ?string $search = null): array
    {
        return $this->batches->fifoStrategyBreakdown($warehouseId, $search);
    }

    public function expiryBreakdown(
        ?int $warehouseId = null,
        int $days = 30,
        ?string $search = null
    ): array {
        return $this->batches->expiryBreakdown($warehouseId, $days, $search);
    }

    public function expirySummary(?int $warehouseId = null, int $days = 30): array
    {
        return $this->batches->expirySummary($warehouseId, $days);
    }

    public function batchSummary(?int $warehouseId = null): array
    {
        return $this->batches->summary($warehouseId);
    }

    public function getBatch(int $id): ?array
    {
        return $this->batches->findById($id);
    }

    public function createBatch(array $data, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        $wh = (int) ($data['warehouse_id'] ?? 0);
        $productId = (int) ($data['product_id'] ?? 0);
        $batchNumber = trim((string) ($data['batch_number'] ?? ''));
        if ($wh <= 0 || $productId <= 0 || $batchNumber === '') {
            return ['status' => 'error', 'message' => 'Warehouse, product and batch number required'];
        }
        $qty = (int) ($data['quantity'] ?? 0);
        $cost = (float) ($data['unit_cost'] ?? 0);
        $this->db->beginTransaction();
        try {
            $id = $this->batches->create($data);
            if ($qty > 0) {
                WmsLedgerHelper::applyMovement(
                    $this->db, $wh, $productId, $qty, 'receipt_in', $cost, $userId,
                    'batch_tracking', $id, 'Batch created', null, $id
                );
            }
            WarehouseLogRepository::log('batch_created', $wh, $userId, 'batch_tracking', $id);
            $this->db->commit();
            return ['status' => 'success', 'data' => ['id' => $id]];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function updateBatchStatus(int $id, string $status, int $userId): array
    {
        $row = $this->batches->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Batch not found'];
        }
        if (!$this->batches->updateStatus($id, $status)) {
            return ['status' => 'error', 'message' => 'Invalid status'];
        }
        WarehouseLogRepository::log('batch_status_updated', (int) $row['warehouse_id'], $userId, 'batch_tracking', $id, ['status' => $status]);
        return ['status' => 'success'];
    }

    public function listAudits(
        ?int $warehouseId,
        ?string $status,
        ?string $search = null,
        ?string $auditType = null,
        int $limit = 150,
        int $offset = 0
    ): array {
        return $this->audits->list($warehouseId, $status, $search, $auditType, $limit, $offset);
    }

    public function countAudits(?int $warehouseId, ?string $status, ?string $search = null, ?string $auditType = null): int
    {
        return $this->audits->count($warehouseId, $status, $search, $auditType);
    }

    public function auditStatusBreakdown(?int $warehouseId, ?string $search = null, ?string $auditType = null): array
    {
        return $this->audits->statusBreakdown($warehouseId, $search, $auditType);
    }

    public function auditSummary(?int $warehouseId = null): array
    {
        return $this->audits->summary($warehouseId);
    }

    public function getAudit(int $id): ?array
    {
        return $this->audits->findById($id);
    }

    public function createAudit(array $data, array $items, int $userId): array
    {
        if (!$this->moduleReady()) {
            return ['status' => 'error', 'message' => 'Run migration 008_wms.sql'];
        }
        $wh = (int) ($data['warehouse_id'] ?? 0);
        if ($wh <= 0) {
            return ['status' => 'error', 'message' => 'Warehouse required'];
        }
        if (!$items) {
            return ['status' => 'error', 'message' => 'At least one count line required'];
        }
        $prepared = [];
        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            if ($productId <= 0) {
                continue;
            }
            $inv = $this->inventory->find($wh, $productId);
            $prepared[] = [
                'product_id' => $productId,
                'system_qty' => (int) ($inv['quantity'] ?? 0),
                'counted_qty' => (int) ($item['counted_qty'] ?? 0),
                'unit_cost' => (float) ($inv['unit_cost'] ?? 0),
            ];
        }
        if (!$prepared) {
            return ['status' => 'error', 'message' => 'Valid product lines required'];
        }
        $data['conducted_by'] = $userId;
        $id = $this->audits->create($data, $prepared);
        WarehouseLogRepository::log('audit_created', $wh, $userId, 'warehouse_audit', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function submitAudit(int $id, int $userId): array
    {
        $row = $this->audits->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Audit not found'];
        }
        if (!in_array($row['status'], ['draft', 'in_progress'], true)) {
            return ['status' => 'error', 'message' => 'Audit cannot be submitted'];
        }
        $this->audits->updateStatus($id, 'pending_approval', $userId);
        WarehouseLogRepository::log('audit_submitted', (int) $row['warehouse_id'], $userId, 'warehouse_audit', $id);
        return ['status' => 'success'];
    }

    public function approveAudit(int $id, int $userId): array
    {
        $row = $this->audits->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Audit not found'];
        }
        if ($row['status'] !== 'pending_approval') {
            return ['status' => 'error', 'message' => 'Audit is not pending approval'];
        }
        $wh = (int) $row['warehouse_id'];
        $this->db->beginTransaction();
        try {
            foreach ($row['items'] as $item) {
                $variance = (int) ($item['variance_qty'] ?? 0);
                if ($variance === 0) {
                    continue;
                }
                WmsLedgerHelper::applyMovement(
                    $this->db, $wh, (int) $item['product_id'], $variance, 'adjustment',
                    (float) ($item['unit_cost'] ?? 0), $userId, 'warehouse_audit', $id, 'Inventory audit adjustment'
                );
            }
            $this->audits->updateStatus($id, 'approved', $userId);
            WarehouseLogRepository::log('audit_approved', $wh, $userId, 'warehouse_audit', $id);
            $this->db->commit();
            return ['status' => 'success'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function rejectAudit(int $id, int $userId): array
    {
        $row = $this->audits->findById($id);
        if (!$row) {
            return ['status' => 'error', 'message' => 'Audit not found'];
        }
        if ($row['status'] !== 'pending_approval') {
            return ['status' => 'error', 'message' => 'Audit is not pending approval'];
        }
        $this->audits->updateStatus($id, 'rejected', $userId);
        WarehouseLogRepository::log('audit_rejected', (int) $row['warehouse_id'], $userId, 'warehouse_audit', $id);
        return ['status' => 'success'];
    }

    public function listLogs(?int $warehouseId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        return $this->logs->list($warehouseId, $filters, $limit, $offset);
    }

    public function countLogs(?int $warehouseId, array $filters = []): int
    {
        return $this->logs->count($warehouseId, $filters);
    }

    public function logSummary(?int $warehouseId, array $filters = []): array
    {
        return $this->logs->summary($warehouseId, $filters);
    }

    public function logBreakdown(?int $warehouseId, array $filters = []): array
    {
        return $this->logs->breakdownByAction($warehouseId, $filters);
    }

    public function logActions(?int $warehouseId = null): array
    {
        return $this->logs->distinctActions($warehouseId);
    }

    public function getLog(int $id): ?array
    {
        $row = $this->logs->findById($id);
        if (!$row) {
            return null;
        }
        if (!empty($row['details'])) {
            $decoded = json_decode((string) $row['details'], true);
            $row['details_parsed'] = is_array($decoded) ? $decoded : null;
        }
        return $row;
    }

    public function listNotifications(?int $warehouseId, ?string $since): array
    {
        return array_map(function (array $row) {
            $details = json_decode((string) ($row['details'] ?? '{}'), true) ?: [];
            return [
                'id' => (int) $row['id'],
                'action' => $row['action'],
                'message' => (string) ($details['message'] ?? $row['action']),
                'severity' => (string) ($details['severity'] ?? 'info'),
                'warehouse_name' => $row['warehouse_name'] ?? null,
                'created_at' => $row['created_at'],
            ];
        }, $this->logs->listNotifications($warehouseId, $since));
    }

    public function syncOffline(array $items, int $userId): array
    {
        $synced = 0;
        foreach ($items as $item) {
            $type = $item['type'] ?? '';
            if ($type === 'receipt' && !empty($item['data'])) {
                $this->createReceipt(array_merge($item['data'], ['sync_status' => 'synced', 'local_uuid' => $item['local_uuid'] ?? null]), $item['items'] ?? [], $userId);
                $synced++;
            } elseif ($type === 'transfer' && !empty($item['data'])) {
                $this->createTransfer(array_merge($item['data'], ['sync_status' => 'synced', 'local_uuid' => $item['local_uuid'] ?? null]), $item['items'] ?? [], $userId);
                $synced++;
            }
        }
        if ($synced > 0) {
            NotificationEvents::offlineSyncComplete($userId);
        }
        return ['status' => 'success', 'synced' => $synced];
    }

    public function listStoreNetwork(
        ?int $scopeStoreId,
        ?string $search,
        ?string $status,
        int $limit = 50,
        int $offset = 0
    ): array {
        return $this->storeNetwork->list($scopeStoreId, $search, $status, $limit, $offset);
    }

    public function countStoreNetwork(?int $scopeStoreId, ?string $search, ?string $status): int
    {
        return $this->storeNetwork->count($scopeStoreId, $search, $status);
    }

    public function storeNetworkSummary(?int $scopeStoreId): array
    {
        return $this->storeNetwork->summary($scopeStoreId);
    }

    public function storeNetworkWarehouses(int $storeId): array
    {
        return $this->storeNetwork->warehousesForStore($storeId);
    }
}

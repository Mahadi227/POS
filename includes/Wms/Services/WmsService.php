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
require_once __DIR__ . '/../Repositories/WarehouseLogRepository.php';
require_once __DIR__ . '/../WmsNotifier.php';
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
    private WarehouseLogRepository $logs;
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
        $this->logs = new WarehouseLogRepository();
    }

    public function moduleReady(): bool
    {
        return WmsSchema::ready();
    }

    public function listWarehouses(?int $storeId, ?string $status = null, ?string $q = null): array
    {
        return $this->warehouses->list($storeId, $status, $q);
    }

    public function warehouseSummary(?int $storeId): array
    {
        return $this->warehouses->countSummary($storeId);
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

    public function listLocations(int $warehouseId): array
    {
        return $this->locations->list($warehouseId);
    }

    public function createLocation(array $data, int $userId): array
    {
        $id = $this->locations->create($data);
        WarehouseLogRepository::log('location_created', (int) $data['warehouse_id'], $userId, 'warehouse_location', $id);
        return ['status' => 'success', 'data' => ['id' => $id]];
    }

    public function listMovements(?int $warehouseId, array $filters = []): array
    {
        return $this->movements->list($warehouseId, $filters);
    }

    public function movementSummary(?int $warehouseId, array $filters = []): array
    {
        return $this->movements->summary($warehouseId, $filters);
    }

    public function movementBreakdown(?int $warehouseId, array $filters = []): array
    {
        return $this->movements->breakdownByType($warehouseId, $filters);
    }

    public function listTransfers(?string $status, ?int $warehouseId, ?string $search = null): array
    {
        return $this->transfers->list($status, $warehouseId, $search);
    }

    public function transferSummary(?int $warehouseId = null): array
    {
        return $this->transfers->summary($warehouseId);
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

    public function listReceipts(?int $warehouseId, ?string $status): array
    {
        return $this->receipts->list($warehouseId, $status);
    }

    public function getReceipt(int $id): ?array
    {
        return $this->receipts->findById($id);
    }

    public function createReceipt(array $data, array $items, int $userId): array
    {
        if (empty($items)) {
            return ['status' => 'error', 'message' => 'Items required'];
        }
        $total = 0.0;
        foreach ($items as $item) {
            $total += ((int) ($item['quantity_received'] ?? $item['quantity'] ?? 0)) * (float) ($item['unit_cost'] ?? 0);
        }
        $data['received_by'] = $userId;
        $data['total_value'] = $total;
        $id = $this->receipts->create($data, $items);
        WmsNotifier::incomingDelivery((int) $data['warehouse_id'], 'GRN#' . $id);
        return ['status' => 'success', 'data' => $this->receipts->findById($id)];
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
            $this->db->commit();
            WmsNotifier::purchaseReceived($whId, (string) $row['grn_number'], (float) $row['total_value']);
            return ['status' => 'success'];
        } catch (Throwable $e) {
            $this->db->rollBack();
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    public function listDispatches(?int $warehouseId, ?string $status, ?string $search = null): array
    {
        return $this->dispatches->list($warehouseId, $status, $search);
    }

    public function dispatchSummary(?int $warehouseId = null): array
    {
        return $this->dispatches->summary($warehouseId);
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

    public function listRequests(?int $storeId, ?string $status, ?int $warehouseId = null, ?string $search = null): array
    {
        return $this->requests->list($storeId, $status, $warehouseId, $search);
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

    public function listBatches(?int $warehouseId, ?string $status, ?string $search = null, int $days = 30): array
    {
        return $this->batches->list($warehouseId, $status, $search, $days);
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

    public function listAudits(?int $warehouseId, ?string $status, ?string $search = null, ?string $auditType = null): array
    {
        return $this->audits->list($warehouseId, $status, $search, $auditType);
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

    public function listLogs(?int $warehouseId, array $filters = []): array
    {
        return $this->logs->list($warehouseId, $filters);
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
        return ['status' => 'success', 'synced' => $synced];
    }
}

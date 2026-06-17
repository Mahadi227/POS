<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/InventoryLedgerHelper.php';
require_once __DIR__ . '/../Repositories/ApprovalRepository.php';
require_once __DIR__ . '/../../CashRegister/CashRegisterNotifier.php';

class ReturnApprovalService
{
    private PDO $db;
    private ApprovalRepository $approvals;

    public function __construct(?PDO $db = null, ?ApprovalRepository $approvals = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        $this->approvals = $approvals ?? new ApprovalRepository();
    }

    /**
     * Queue a return for manager approval (cashier submit).
     *
     * @return array{status: string, message?: string, approval_id?: int, refund_total?: float}
     */
    public function queueReturnRequest(array $data, int $userId, int $storeId, callable $canAccessSale): array
    {
        if (empty($data['sale_id']) || empty($data['items']) || !is_array($data['items'])) {
            return ['status' => 'error', 'message' => 'sale_id et items requis'];
        }

        if (!$this->approvals->tableExists()) {
            return ['status' => 'error', 'message' => 'Module approbations non disponible — exécutez la migration supervision.'];
        }

        $parsed = $this->parseReturnInput($data, $userId, $storeId, $canAccessSale);
        if ($parsed['status'] !== 'success') {
            return $parsed;
        }

        $sale = $parsed['sale'];
        $refundTotal = $parsed['refund_total'];
        $notes = trim((string) ($data['notes'] ?? ''));
        $reasonLabel = (string) ($data['reason'] ?? 'customer_request');

        $approvalId = $this->approvals->create([
            'store_id'       => (int) ($sale['store_id'] ?? $storeId),
            'type'           => 'return',
            'reference_type' => 'sale',
            'reference_id'   => (int) $sale['id'],
            'requested_by'   => $userId,
            'amount'         => $refundTotal,
            'reason'         => $notes !== '' ? $notes : $reasonLabel,
            'payload'        => [
                'sale_id'       => (int) $sale['id'],
                'items'         => $data['items'],
                'reason'        => $reasonLabel,
                'refund_method' => (string) ($data['refund_method'] ?? 'cash'),
                'notes'         => $notes,
                'receipt_no'    => (string) ($sale['receipt_no'] ?? ''),
            ],
        ]);

        return [
            'status'      => 'success',
            'message'     => 'Demande de retour envoyée — en attente d\'approbation manager',
            'approval_id' => $approvalId,
            'refund_total'=> $refundTotal,
            'pending'     => true,
        ];
    }

    /**
     * Execute an approved return request.
     *
     * @return array{status: string, message?: string}
     */
    public function executePendingReturn(array $approval): array
    {
        $payload = $approval['payload'] ?? [];
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }

        $data = [
            'sale_id'       => (int) ($payload['sale_id'] ?? $approval['reference_id'] ?? 0),
            'items'         => $payload['items'] ?? [],
            'reason'        => $payload['reason'] ?? 'customer_request',
            'refund_method' => $payload['refund_method'] ?? 'cash',
            'notes'         => $payload['notes'] ?? '',
        ];

        $userId = (int) ($approval['requested_by'] ?? 0);
        $storeId = (int) ($approval['store_id'] ?? 0);

        $parsed = $this->parseReturnInput($data, $userId, $storeId, fn () => true);
        if ($parsed['status'] !== 'success') {
            return $parsed;
        }

        return $this->processReturnTransaction(
            $parsed['sale'],
            $parsed['return_lines'],
            $parsed['restock_lines'],
            $parsed['damaged_lines'],
            $userId
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function parseReturnInput(array $data, int $userId, int $storeId, callable $canAccessSale): array
    {
        $saleId = (int) $data['sale_id'];

        $saleStmt = $this->db->prepare(
            'SELECT * FROM sales WHERE id = ? AND deleted_at IS NULL LIMIT 1'
        );
        $saleStmt->execute([$saleId]);
        $sale = $saleStmt->fetch(PDO::FETCH_ASSOC);

        if (!$sale || !$canAccessSale($sale)) {
            return ['status' => 'error', 'message' => 'Vente introuvable'];
        }

        if (($sale['status'] ?? '') === 'cancelled') {
            return ['status' => 'error', 'message' => 'Ce ticket a déjà été annulé / retourné'];
        }

        $itemsStmt = $this->db->prepare(
            'SELECT product_id, quantity FROM sale_items WHERE sale_id = ?'
        );
        $itemsStmt->execute([$saleId]);
        $soldByProduct = [];
        foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $soldByProduct[(int) $row['product_id']] = (int) $row['quantity'];
        }

        $returnReason = (string) ($data['reason'] ?? 'customer_request');
        $defaultDamaged = $returnReason === 'defective';

        $returnLines = [];
        $restockLines = [];
        $damagedLines = [];
        foreach ($data['items'] as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $qty = (int) ($line['quantity'] ?? 0);
            if ($productId <= 0 || $qty <= 0) {
                continue;
            }
            if (!isset($soldByProduct[$productId])) {
                return ['status' => 'error', 'message' => 'Article invalide pour ce ticket'];
            }
            $returnLines[$productId] = ($returnLines[$productId] ?? 0) + $qty;

            $condition = (string) ($line['condition'] ?? '');
            if ($condition === '') {
                $condition = $defaultDamaged ? 'damaged' : 'restock';
            }
            if ($condition === 'damaged') {
                $damagedLines[$productId] = ($damagedLines[$productId] ?? 0) + $qty;
            } else {
                $restockLines[$productId] = ($restockLines[$productId] ?? 0) + $qty;
            }
        }

        if (empty($returnLines)) {
            return ['status' => 'error', 'message' => 'Sélectionnez au moins un article à retourner'];
        }

        foreach ($returnLines as $productId => $qty) {
            if ($qty > $soldByProduct[$productId]) {
                return ['status' => 'error', 'message' => 'Quantité retournée supérieure à la quantité vendue'];
            }
        }

        $refundTotal = 0.0;
        $priceStmt = $this->db->prepare(
            'SELECT unit_price FROM sale_items WHERE sale_id = ? AND product_id = ? LIMIT 1'
        );
        foreach ($returnLines as $productId => $qty) {
            $priceStmt->execute([$saleId, $productId]);
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            $refundTotal += $qty * (float) ($row['unit_price'] ?? 0);
        }

        return [
            'status'        => 'success',
            'sale'          => $sale,
            'return_lines'  => $returnLines,
            'restock_lines' => $restockLines,
            'damaged_lines' => $damagedLines,
            'refund_total'  => round($refundTotal, 2),
        ];
    }

    /**
     * @param array<int, int> $returnLines
     * @param array<int, int> $restockLines
     * @param array<int, int> $damagedLines
     */
    private function processReturnTransaction(
        array $sale,
        array $returnLines,
        array $restockLines,
        array $damagedLines,
        int $userId
    ): array {
        $saleId = (int) $sale['id'];
        $effectiveStoreId = (int) ($sale['store_id'] ?? 0);
        $refundTotal = 0.0;
        $priceStmt = $this->db->prepare(
            'SELECT unit_price FROM sale_items WHERE sale_id = ? AND product_id = ? LIMIT 1'
        );
        foreach ($returnLines as $productId => $qty) {
            $priceStmt->execute([$saleId, $productId]);
            $row = $priceStmt->fetch(PDO::FETCH_ASSOC);
            $refundTotal += $qty * (float) ($row['unit_price'] ?? 0);
        }
        $refundTotal = round($refundTotal, 2);

        try {
            $this->db->beginTransaction();

            $stockStmt = $this->db->prepare(
                'UPDATE products SET stock_quantity = stock_quantity + ? WHERE id = ?'
            );
            $logStmt = $this->db->prepare(
                'INSERT INTO inventory_logs (store_id, product_id, user_id, change_amount, reason)
                 VALUES (?, ?, ?, ?, ?)'
            );

            $receiptLabel = (string) ($sale['receipt_no'] ?? $sale['receipt_number'] ?? '');

            foreach ($restockLines as $productId => $qty) {
                $stockStmt->execute([$qty, $productId]);
                $logStmt->execute([$effectiveStoreId, $productId, $userId, $qty, 'restock']);
                $logId = (int) $this->db->lastInsertId();
                InventoryLedgerHelper::syncLogToLedger(
                    $this->db,
                    $logId,
                    $productId,
                    $qty,
                    'restock',
                    $userId,
                    $effectiveStoreId
                );
            }

            foreach ($damagedLines as $productId => $qty) {
                InventoryLedgerHelper::recordReturnDamage(
                    $this->db,
                    $productId,
                    $qty,
                    $userId,
                    $effectiveStoreId,
                    $saleId,
                    $receiptLabel
                );
            }

            $itemsStmt = $this->db->prepare(
                'SELECT product_id, quantity FROM sale_items WHERE sale_id = ?'
            );
            $itemsStmt->execute([$saleId]);
            $soldByProduct = [];
            foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $soldByProduct[(int) $row['product_id']] = (int) $row['quantity'];
            }

            $fullReturn = true;
            foreach ($soldByProduct as $productId => $soldQty) {
                if (($returnLines[$productId] ?? 0) < $soldQty) {
                    $fullReturn = false;
                    break;
                }
            }

            if ($fullReturn) {
                $this->db->prepare("UPDATE sales SET status = 'cancelled' WHERE id = ?")->execute([$saleId]);
            }

            $this->db->commit();

            CashRegisterNotifier::largeRefund(
                $effectiveStoreId,
                0,
                $refundTotal,
                $userId
            );

            return [
                'status'  => 'success',
                'message' => $fullReturn ? 'Retour approuvé — ticket annulé' : 'Retour partiel approuvé',
            ];
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            return ['status' => 'error', 'message' => 'Erreur lors du traitement du retour'];
        }
    }
}

<?php
/**
 * Sync pull/push + délégation surveillance (SyncMonitorController).
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/SyncSchemaMigrator.php';
require_once __DIR__ . '/SyncMonitorController.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class SyncController
{
    private PDO $db;
    private SyncMonitorController $monitor;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        SyncSchemaMigrator::ensure($this->db);
        $this->monitor = new SyncMonitorController();
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? null;

        $monitorActions = [
            'monitor', 'branches', 'queue', 'failed', 'conflicts', 'heartbeat', 'report',
        ];
        if (in_array($action, $monitorActions, true)
            || ($action === 'queue' && isset($path[3]))
            || ($action === 'conflicts' && isset($path[3]))) {
            $this->monitor->handleRequest($method, $path);
            return;
        }

        if ($method === 'GET' && $action === 'pull') {
            $this->pullData();
        } elseif ($method === 'POST' && $action === 'push') {
            $this->pushData();
        } else {
            http_response_code(405);
            echo json_encode(['status' => 'error', 'message' => 'Method or action not allowed']);
        }
    }

    private function pullData(): void
    {
        $since = $_GET['since'] ?? '2000-01-01 00:00:00';
        $currentTimestamp = date('Y-m-d H:i:s');

        try {
            $storeFilter = '';
            $params = [$since];
            [$scopeSql, $scopeParams] = StoreScope::sqlFilter($this->db, 'store_id', 'p');
            $storeFilter = $scopeSql;
            $params = array_merge($params, $scopeParams);

            $deleted = $this->hasColumn('products', 'deleted_at') ? ' AND p.deleted_at IS NULL' : '';
            $updated = $this->hasColumn('products', 'updated_at')
                ? 'p.updated_at >= ?'
                : 'p.created_at >= ?';

            $stmt = $this->db->prepare("SELECT p.* FROM products p WHERE {$updated}{$deleted}{$storeFilter}");
            $stmt->execute($params);
            $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'data'   => [
                    'products'           => $products,
                    'current_timestamp'  => $currentTimestamp,
                ],
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
        }
    }

    private function pushData(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        if (!isset($data['sales']) || !is_array($data['sales'])) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Invalid payload']);
            return;
        }

        $syncedUuids = [];
        $failed = [];

        foreach ($data['sales'] as $offlineSale) {
            $uuid = $offlineSale['local_uuid'] ?? '';
            $payload = $offlineSale['payload'] ?? $offlineSale;
            $storeId = (int) ($payload['store_id'] ?? 0);

            try {
                $result = $this->processOfflineSale($uuid, $payload);
                if ($result === 'synced') {
                    $syncedUuids[] = $uuid;
                    $this->monitor->touchStoreSync($storeId, true);
                } elseif ($result === 'duplicate') {
                    $syncedUuids[] = $uuid;
                }
            } catch (Throwable $e) {
                $msg = $e->getMessage();
                $failed[] = ['local_uuid' => $uuid, 'error' => $msg];
                $this->monitor->enqueue('sale_push', $storeId, [
                    'local_uuid' => $uuid,
                    'payload'    => $payload,
                ], 'failed', $uuid, $msg);
                $this->recordOfflineFailure($storeId, $uuid, $payload, $msg, 'failed');
            }
        }

        echo json_encode([
            'status'       => empty($failed) ? 'success' : 'partial',
            'message'      => count($syncedUuids) . ' vente(s) synchronisée(s)',
            'synced_uuids' => $syncedUuids,
            'failed'       => $failed,
        ]);
    }

    /** @return 'synced'|'duplicate' */
    private function processOfflineSale(string $uuid, array $payload): string
    {
        if ($uuid === '') {
            throw new InvalidArgumentException('local_uuid manquant');
        }

        $check = $this->db->prepare('SELECT id, status FROM offline_transactions WHERE local_uuid = ? LIMIT 1');
        $check->execute([$uuid]);
        $existing = $check->fetch(PDO::FETCH_ASSOC);
        if ($existing && $existing['status'] === 'synced') {
            return 'duplicate';
        }

        $receiptNo = $payload['receipt_no'] ?? '';
        if ($receiptNo !== '') {
            $dup = $this->db->prepare('SELECT id FROM sales WHERE receipt_no = ? LIMIT 1');
            $dup->execute([$receiptNo]);
            if ($dup->fetchColumn()) {
                $this->recordOfflineFailure(
                    (int) $payload['store_id'],
                    $uuid,
                    $payload,
                    'Ticket déjà existant: ' . $receiptNo,
                    'conflict'
                );
                $this->monitor->enqueue('sale_push', (int) $payload['store_id'], [
                    'local_uuid' => $uuid,
                    'payload'    => $payload,
                ], 'conflict', $uuid, 'Doublon ticket');
                return 'duplicate';
            }
        }

        $this->db->beginTransaction();

        $storeId = (int) ($payload['store_id'] ?? 1);
        $userId = (int) ($payload['user_id'] ?? 1);

        $stmt = $this->db->prepare(
            'INSERT INTO sales (receipt_no, store_id, user_id, customer_id, total, tax, discount, status, synced_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)'
        );
        $stmt->execute([
            $receiptNo,
            $storeId,
            $userId,
            $payload['customer_id'] ?? null,
            $payload['total'],
            $payload['tax'] ?? 0,
            $payload['discount'] ?? 0,
            'completed',
        ]);
        $saleId = (int) $this->db->lastInsertId();

        if ($this->tableExists('payments')) {
            $pay = $this->db->prepare(
                'INSERT INTO payments (sale_id, method, provider, transaction_ref, amount, status)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $pay->execute([
                $saleId,
                $payload['payment_method'] ?? 'cash',
                $payload['payment_provider'] ?? null,
                $payload['payment_ref'] ?? null,
                $payload['total'],
                'success',
            ]);
        }

        $itemStmt = $this->db->prepare(
            'INSERT INTO sale_items (sale_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)'
        );
        $stockStmt = $this->db->prepare('UPDATE products SET stock_quantity = stock_quantity - ? WHERE id = ?');

        foreach ($payload['items'] ?? [] as $item) {
            $subtotal = $item['quantity'] * $item['unit_price'];
            $itemStmt->execute([$saleId, $item['product_id'], $item['quantity'], $item['unit_price'], $subtotal]);
            $stockStmt->execute([$item['quantity'], $item['product_id']]);
        }

        if ($existing) {
            $this->db->prepare(
                "UPDATE offline_transactions SET status = 'synced', synced_at = NOW(), payload = ?, error_message = NULL WHERE local_uuid = ?"
            )->execute([json_encode($payload), $uuid]);
        } else {
            $this->db->prepare(
                "INSERT INTO offline_transactions (store_id, local_uuid, payload, status, synced_at)
                 VALUES (?, ?, ?, 'synced', NOW())"
            )->execute([$storeId, $uuid, json_encode($payload)]);
        }

        $this->db->commit();
        return 'synced';
    }

    private function recordOfflineFailure(int $storeId, string $uuid, array $payload, string $error, string $status): void
    {
        if (!$this->tableExists('offline_transactions')) {
            return;
        }

        $check = $this->db->prepare('SELECT id FROM offline_transactions WHERE local_uuid = ?');
        $check->execute([$uuid]);
        if ($check->fetchColumn()) {
            $this->db->prepare(
                'UPDATE offline_transactions SET status = ?, error_message = ?, conflict_reason = ? WHERE local_uuid = ?'
            )->execute([
                $status,
                $error,
                $status === 'conflict' ? $error : null,
                $uuid,
            ]);
        } else {
            $this->db->prepare(
                'INSERT INTO offline_transactions (store_id, local_uuid, payload, status, error_message, conflict_reason)
                 VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([
                $storeId,
                $uuid,
                json_encode($payload),
                $status,
                $error,
                $status === 'conflict' ? $error : null,
            ]);
        }
    }

    private function hasColumn(string $table, string $column): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table, $column]);
        return (bool) $stmt->fetchColumn();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}

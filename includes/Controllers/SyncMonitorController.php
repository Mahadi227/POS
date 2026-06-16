<?php
/**
 * Surveillance synchronisation hors ligne — file, échecs, conflits, succursales.
 */
require_once __DIR__ . '/../Database/Database.php';
require_once __DIR__ . '/../Database/SyncSchemaMigrator.php';
require_once __DIR__ . '/../Middleware/AuthMiddleware.php';
require_once __DIR__ . '/../Helpers/StoreScope.php';

class SyncMonitorController
{
    private PDO $db;

    /** POS heartbeat every ~60s — mark offline after 3 missed beats + buffer */
    private const HEARTBEAT_ONLINE_MINUTES = 5;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        SyncSchemaMigrator::ensure($this->db);
    }

    public function handleRequest(string $method, array $path): void
    {
        $action = $path[1] ?? null;
        $sub = $path[2] ?? null;
        $id = isset($sub) && is_numeric($sub) ? (int) $sub : null;

        if ($method === 'POST' && $action === 'heartbeat') {
            $this->heartbeat();
            return;
        }

        if ($method === 'POST' && $action === 'report') {
            $this->reportFailure();
            return;
        }

        AuthMiddleware::apiProtect(['admin', 'manager', 'super_admin']);

        if ($method === 'GET' && ($action === 'monitor' || $action === null)) {
            $this->dashboard();
            return;
        }

        if ($method === 'GET' && $action === 'branches') {
            $this->listBranches();
            return;
        }

        if ($method === 'GET' && $action === 'queue') {
            $this->listQueue();
            return;
        }

        if ($method === 'GET' && $action === 'failed') {
            $this->listFailed();
            return;
        }

        if ($method === 'GET' && $action === 'conflicts') {
            $this->listConflicts();
            return;
        }

        if ($method === 'POST' && $action === 'queue' && $id > 0 && ($path[3] ?? '') === 'retry') {
            $this->retryQueueItem($id);
            return;
        }

        if ($method === 'POST' && $action === 'conflicts' && $id > 0 && ($path[3] ?? '') === 'resolve') {
            $this->resolveConflict($id);
            return;
        }

        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint not found']);
    }

    /** Signal caisse en ligne (cashier / POS). */
    private function heartbeat(): void
    {
        AuthMiddleware::apiProtect(['cashier', 'admin', 'manager', 'super_admin', 'staff']);

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $storeId = (int) ($data['store_id'] ?? StoreScope::activeStoreId() ?? $_SESSION['store_id'] ?? 0);
        $pending = max(0, (int) ($data['pending_count'] ?? 0));
        $isOnline = !isset($data['is_online']) || (bool) $data['is_online'];

        if ($storeId <= 0) {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'store_id requis']);
            return;
        }

        if (!StoreScope::canAccessStore($this->db, $storeId)) {
            http_response_code(403);
            echo json_encode(['status' => 'error', 'message' => 'Succursale non autorisée']);
            return;
        }

        try {
            $stmt = $this->db->prepare(
                'INSERT INTO store_sync_status (store_id, is_online, last_seen_at, pending_local_count)
                 VALUES (?, ?, NOW(), ?)
                 ON DUPLICATE KEY UPDATE
                    is_online = VALUES(is_online),
                    last_seen_at = NOW(),
                    pending_local_count = VALUES(pending_local_count)'
            );
            $stmt->execute([$storeId, $isOnline ? 1 : 0, $pending]);

            $userId = (int) ($_SESSION['user_id'] ?? 0);
            if ($userId > 0) {
                $page = substr(trim((string) ($data['page'] ?? '')), 0, 120);
                $presence = $this->db->prepare(
                    'INSERT INTO cashier_presence (user_id, store_id, is_online, last_seen_at, last_page)
                     VALUES (?, ?, ?, NOW(), ?)
                     ON DUPLICATE KEY UPDATE
                        is_online = VALUES(is_online),
                        last_seen_at = NOW(),
                        last_page = VALUES(last_page)'
                );
                $presence->execute([$userId, $storeId, $isOnline ? 1 : 0, $page !== '' ? $page : null]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Heartbeat enregistré']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    /** Rapport d'échec sync depuis la caisse. */
    private function reportFailure(): void
    {
        AuthMiddleware::apiProtect(['cashier', 'admin', 'manager', 'super_admin', 'staff']);

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $storeId = (int) ($data['store_id'] ?? StoreScope::activeStoreId() ?? $_SESSION['store_id'] ?? 0);
        $uuid = trim($data['local_uuid'] ?? '');
        $payload = $data['payload'] ?? [];
        $error = substr(trim($data['error_message'] ?? 'Échec synchronisation'), 0, 500);

        if ($storeId <= 0 || $uuid === '') {
            http_response_code(400);
            echo json_encode(['status' => 'error', 'message' => 'Données invalides']);
            return;
        }

        try {
            $this->enqueue('sale_push_failed', $storeId, [
                'local_uuid' => $uuid,
                'payload'    => $payload,
                'error'      => $error,
            ], 'failed', $uuid, $error);

            $check = $this->db->prepare('SELECT id FROM offline_transactions WHERE local_uuid = ? LIMIT 1');
            $check->execute([$uuid]);
            if (!$check->fetchColumn()) {
                $stmt = $this->db->prepare(
                    'INSERT INTO offline_transactions (store_id, local_uuid, payload, status, error_message)
                     VALUES (?, ?, ?, ?, ?)'
                );
                $stmt->execute([$storeId, $uuid, json_encode($payload), 'failed', $error]);
            } else {
                $this->db->prepare(
                    'UPDATE offline_transactions SET status = ?, error_message = ? WHERE local_uuid = ?'
                )->execute(['failed', $error, $uuid]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Échec enregistré']);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    private function dashboard(): void
    {
        $this->expireStaleOnlineFlags();
        $stats = $this->computeStats();
        $chart = $this->syncActivityChart(7);

        echo json_encode([
            'status' => 'success',
            'data'   => [
                'stats' => $stats,
                'chart' => $chart,
            ],
        ]);
    }

    private function computeStats(): array
    {
        [$scope, $params] = StoreScope::sqlFilter($this->db, 'store_id', 'x');

        $pendingQueue = $this->countQueue('pending', $scope, $params);
        $failedQueue = $this->countQueue('failed', $scope, $params);
        $conflictQueue = $this->countQueue('conflict', $scope, $params);

        $pendingOffline = 0;
        $failedOffline = 0;
        $conflictOffline = 0;

        if ($this->tableExists('offline_transactions')) {
            $pendingOffline = $this->countOffline('pending', $scope, $params);
            $failedOffline = $this->countOffline('failed', $scope, $params);
            $conflictOffline = $this->countOffline('conflict', $scope, $params);
        }

        $offlineBranches = 0;
        $onlineBranches = 0;
        $degradedBranches = 0;
        $unknownBranches = 0;
        $branches = $this->buildBranchRows();
        foreach ($branches as $b) {
            switch ($b['connectivity']) {
                case 'online':
                    $onlineBranches++;
                    break;
                case 'degraded':
                    $degradedBranches++;
                    break;
                case 'unknown':
                    $unknownBranches++;
                    break;
                default:
                    $offlineBranches++;
            }
        }

        return [
            'pending_queue'      => $pendingQueue,
            'failed_queue'       => $failedQueue,
            'conflict_queue'     => $conflictQueue,
            'pending_offline'    => $pendingOffline,
            'failed_offline'     => $failedOffline,
            'conflict_offline'   => $conflictOffline,
            'online_branches'    => $onlineBranches,
            'offline_branches'   => $offlineBranches,
            'degraded_branches'  => $degradedBranches,
            'unknown_branches'   => $unknownBranches,
            'total_branches'     => count($branches),
            'synced_today'       => $this->countSyncedToday($scope, $params),
            'heartbeat_threshold_minutes' => self::HEARTBEAT_ONLINE_MINUTES,
        ];
    }

    private function countQueue(string $status, string $scope, array $params): int
    {
        if (!$this->tableExists('synchronization_queue')) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM synchronization_queue x WHERE x.status = ?{$scope}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$status], $params));
        return (int) $stmt->fetchColumn();
    }

    private function countOffline(string $status, string $scope, array $params): int
    {
        $sql = "SELECT COUNT(*) FROM offline_transactions x WHERE x.status = ?{$scope}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge([$status], $params));
        return (int) $stmt->fetchColumn();
    }

    private function countSyncedToday(string $scope, array $params): int
    {
        if (!$this->tableExists('offline_transactions')) {
            return 0;
        }
        $sql = "SELECT COUNT(*) FROM offline_transactions x
                WHERE x.status = 'synced' AND DATE(x.synced_at) = CURDATE(){$scope}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    /** @return array{labels: string[], synced: int[], failed: int[]} */
    private function syncActivityChart(int $days): array
    {
        $labels = [];
        $synced = [];
        $failed = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i days"));
            $labels[] = date('d/m', strtotime($d));

            if ($this->tableExists('offline_transactions')) {
                [$scope, $params] = StoreScope::sqlFilter($this->db, 'store_id', 'x');
                $stmt = $this->db->prepare(
                    "SELECT COUNT(*) FROM offline_transactions x
                     WHERE x.status = 'synced' AND DATE(x.synced_at) = ?{$scope}"
                );
                $stmt->execute(array_merge([$d], $params));
                $synced[] = (int) $stmt->fetchColumn();

                $stmt2 = $this->db->prepare(
                    "SELECT COUNT(*) FROM offline_transactions x
                     WHERE x.status IN ('failed','conflict') AND DATE(x.created_at) = ?{$scope}"
                );
                $stmt2->execute(array_merge([$d], $params));
                $failed[] = (int) $stmt2->fetchColumn();
            } else {
                $synced[] = 0;
                $failed[] = 0;
            }
        }

        return compact('labels', 'synced', 'failed');
    }

    private function listBranches(): void
    {
        $this->expireStaleOnlineFlags();
        echo json_encode(['status' => 'success', 'data' => $this->buildBranchRows()]);
    }

    /** @return list<array<string, mixed>> */
    private function buildBranchRows(): array
    {
        $allowed = StoreScope::accessibleStoreIds($this->db);
        $sql = 'SELECT s.id, s.name';
        if ($this->hasColumn('stores', 'code')) {
            $sql .= ', s.code';
        }
        $sql .= ' FROM stores s WHERE 1=1';
        if ($this->hasColumn('stores', 'deleted_at')) {
            $sql .= ' AND s.deleted_at IS NULL';
        }
        $params = [];
        if ($allowed !== null && $allowed !== []) {
            $ph = implode(',', array_fill(0, count($allowed), '?'));
            $sql .= " AND s.id IN ({$ph})";
            $params = $allowed;
        } elseif ($allowed === []) {
            return [];
        }
        $sql .= ' ORDER BY s.name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $stores = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $rows = [];
        $thresholdMin = self::HEARTBEAT_ONLINE_MINUTES;

        foreach ($stores as $s) {
            $sid = (int) $s['id'];
            $status = $this->getStoreSyncStatus($sid);
            $pendingQ = $this->storeQueueCount($sid, ['pending']);
            $failedQ = $this->storeQueueCount($sid, ['failed', 'conflict']);
            $pendingOff = $this->storeOfflineCount($sid, 'pending');
            $conflictOff = $this->storeOfflineCount($sid, 'conflict');

            $conn = $this->resolveConnectivity(
                $status,
                $pendingQ,
                $pendingOff,
                $failedQ,
                $conflictOff,
                $thresholdMin
            );

            $rows[] = [
                'store_id'           => $sid,
                'name'               => $s['name'],
                'code'               => $s['code'] ?? null,
                'connectivity'       => $conn['connectivity'],
                'is_online'          => $conn['is_online'],
                'last_seen_at'       => $status['last_seen_at'] ?? null,
                'last_sync_at'       => $status['last_sync_at'] ?? $this->lastOfflineSync($sid),
                'pending_local'      => (int) ($status['pending_local_count'] ?? 0),
                'queue_pending'      => $pendingQ,
                'queue_failed'       => $failedQ,
                'offline_pending'    => $pendingOff,
                'offline_conflicts'  => $conflictOff,
                'minutes_since_seen' => $conn['minutes_since_seen'],
            ];
        }

        return $rows;
    }

    /**
     * Online = recent POS heartbeat only (not server-side sync).
     *
     * @return array{connectivity: string, is_online: bool, minutes_since_seen: ?int}
     */
    private function resolveConnectivity(
        array $status,
        int $pendingQ,
        int $pendingOff,
        int $failedQ,
        int $conflictOff,
        int $thresholdMin
    ): array {
        $lastSeen = $status['last_seen_at'] ?? null;
        if (!$lastSeen) {
            return [
                'connectivity'       => 'unknown',
                'is_online'          => false,
                'minutes_since_seen' => null,
            ];
        }

        // Use MySQL clock — PHP and MySQL timezones often differ on XAMPP.
        $minsSinceSeen = array_key_exists('minutes_since_seen_db', $status) && $status['minutes_since_seen_db'] !== null
            ? max(0, (int) $status['minutes_since_seen_db'])
            : max(0, (int) floor((time() - strtotime($lastSeen)) / 60));

        if ($minsSinceSeen > $thresholdMin) {
            return [
                'connectivity'       => 'offline',
                'is_online'          => false,
                'minutes_since_seen' => $minsSinceSeen,
            ];
        }

        $reportedOnline = (int) ($status['is_online'] ?? 0) === 1;
        if (!$reportedOnline) {
            return [
                'connectivity'       => 'degraded',
                'is_online'          => true,
                'minutes_since_seen' => $minsSinceSeen,
            ];
        }

        if ($pendingQ + $pendingOff > 0 || $failedQ + $conflictOff > 0) {
            return [
                'connectivity'       => 'degraded',
                'is_online'          => true,
                'minutes_since_seen' => $minsSinceSeen,
            ];
        }

        return [
            'connectivity'       => 'online',
            'is_online'          => true,
            'minutes_since_seen' => $minsSinceSeen,
        ];
    }

    /** Clear stale is_online flags when heartbeat expired. */
    private function expireStaleOnlineFlags(): void
    {
        if (!$this->tableExists('store_sync_status')) {
            return;
        }
        try {
            $mins = self::HEARTBEAT_ONLINE_MINUTES;
            $this->db->exec(
                "UPDATE store_sync_status
                 SET is_online = 0
                 WHERE is_online = 1
                 AND (last_seen_at IS NULL OR last_seen_at < DATE_SUB(NOW(), INTERVAL {$mins} MINUTE))"
            );
        } catch (PDOException $e) {
            error_log('SyncMonitorController::expireStaleOnlineFlags — ' . $e->getMessage());
        }
    }

    private function getStoreSyncStatus(int $storeId): array
    {
        if (!$this->tableExists('store_sync_status')) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT *,
                    TIMESTAMPDIFF(MINUTE, last_seen_at, NOW()) AS minutes_since_seen_db
             FROM store_sync_status
             WHERE store_id = ?
             LIMIT 1'
        );
        $stmt->execute([$storeId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    private function lastOfflineSync(int $storeId): ?string
    {
        if (!$this->tableExists('offline_transactions')) {
            return null;
        }
        $stmt = $this->db->prepare(
            "SELECT MAX(synced_at) FROM offline_transactions WHERE store_id = ? AND status = 'synced'"
        );
        $stmt->execute([$storeId]);
        $v = $stmt->fetchColumn();
        return $v ?: null;
    }

    private function storeQueueCount(int $storeId, array $statuses): int
    {
        if (!$this->tableExists('synchronization_queue')) {
            return 0;
        }
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) FROM synchronization_queue WHERE store_id = ? AND status IN ({$ph})"
        );
        $stmt->execute(array_merge([$storeId], $statuses));
        return (int) $stmt->fetchColumn();
    }

    private function storeOfflineCount(int $storeId, string $status): int
    {
        if (!$this->tableExists('offline_transactions')) {
            return 0;
        }
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM offline_transactions WHERE store_id = ? AND status = ?'
        );
        $stmt->execute([$storeId, $status]);
        return (int) $stmt->fetchColumn();
    }

    private function listQueue(): void
    {
        echo json_encode(['status' => 'success', 'data' => $this->fetchQueueItems(['pending'])]);
    }

    private function listFailed(): void
    {
        $items = array_merge(
            $this->fetchQueueItems(['failed']),
            $this->fetchOfflineItems(['failed'])
        );
        usort($items, static fn ($a, $b) => strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0));
        echo json_encode(['status' => 'success', 'data' => array_slice($items, 0, 200)]);
    }

    private function listConflicts(): void
    {
        $items = array_merge(
            $this->fetchQueueItems(['conflict']),
            $this->fetchOfflineItems(['conflict'])
        );
        usort($items, static fn ($a, $b) => strtotime($b['created_at'] ?? 0) - strtotime($a['created_at'] ?? 0));
        echo json_encode(['status' => 'success', 'data' => array_slice($items, 0, 200)]);
    }

    /** @return list<array<string, mixed>> */
    private function fetchQueueItems(array $statuses): array
    {
        if (!$this->tableExists('synchronization_queue')) {
            return [];
        }

        [$scope, $params] = StoreScope::sqlFilter($this->db, 'store_id', 'q');
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT q.*, s.name AS store_name
                FROM synchronization_queue q
                LEFT JOIN stores s ON s.id = q.store_id
                WHERE q.status IN ({$ph}){$scope}
                ORDER BY q.created_at DESC
                LIMIT 150";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($statuses, $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($r) {
            $payload = json_decode($r['payload'] ?? '{}', true) ?: [];
            return [
                'id'            => (int) $r['id'],
                'source'        => 'queue',
                'store_id'      => (int) $r['store_id'],
                'store_name'    => $r['store_name'] ?? '',
                'action'        => $r['action'],
                'status'        => $r['status'],
                'local_uuid'    => $r['local_uuid'] ?? ($payload['local_uuid'] ?? null),
                'receipt_no'    => $payload['payload']['receipt_no'] ?? $payload['receipt_no'] ?? null,
                'error_message' => $r['error_message'] ?? ($payload['error'] ?? null),
                'retry_count'   => (int) ($r['retry_count'] ?? 0),
                'created_at'    => $r['created_at'],
            ];
        }, $rows);
    }

    /** @return list<array<string, mixed>> */
    private function fetchOfflineItems(array $statuses): array
    {
        if (!$this->tableExists('offline_transactions')) {
            return [];
        }

        [$scope, $params] = StoreScope::sqlFilter($this->db, 'store_id', 'o');
        $ph = implode(',', array_fill(0, count($statuses), '?'));
        $sql = "SELECT o.*, s.name AS store_name
                FROM offline_transactions o
                LEFT JOIN stores s ON s.id = o.store_id
                WHERE o.status IN ({$ph}){$scope}
                ORDER BY o.created_at DESC
                LIMIT 150";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(array_merge($statuses, $params));
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        return array_map(function ($r) {
            $payload = json_decode($r['payload'] ?? '{}', true) ?: [];
            return [
                'id'              => (int) $r['id'],
                'source'          => 'offline',
                'store_id'        => (int) $r['store_id'],
                'store_name'      => $r['store_name'] ?? '',
                'action'          => 'offline_sale',
                'status'          => $r['status'],
                'local_uuid'      => $r['local_uuid'],
                'receipt_no'      => $payload['receipt_no'] ?? null,
                'error_message'   => $r['error_message'] ?? $r['conflict_reason'] ?? null,
                'retry_count'     => 0,
                'created_at'      => $r['created_at'],
            ];
        }, $rows);
    }

    private function retryQueueItem(int $id): void
    {
        if (!$this->tableExists('synchronization_queue')) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'File inexistante']);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM synchronization_queue WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || !StoreScope::canAccessStore($this->db, (int) $row['store_id'])) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Élément introuvable']);
            return;
        }

        $retry = (int) ($row['retry_count'] ?? 0) + 1;
        $this->db->prepare(
            "UPDATE synchronization_queue SET status = 'pending', retry_count = ?, error_message = NULL, resolved_at = NULL WHERE id = ?"
        )->execute([$retry, $id]);

        if (!empty($row['local_uuid']) && $this->tableExists('offline_transactions')) {
            $this->db->prepare(
                "UPDATE offline_transactions SET status = 'pending', error_message = NULL WHERE local_uuid = ?"
            )->execute([$row['local_uuid']]);
        }

        echo json_encode(['status' => 'success', 'message' => 'Remis en file pour synchronisation']);
    }

    private function resolveConflict(int $id): void
    {
        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $source = $data['source'] ?? 'offline';
        $action = $data['action'] ?? 'dismiss';

        if ($source === 'queue' && $this->tableExists('synchronization_queue')) {
            $stmt = $this->db->prepare('SELECT * FROM synchronization_queue WHERE id = ? AND status = ? LIMIT 1');
            $stmt->execute([$id, 'conflict']);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$row || !StoreScope::canAccessStore($this->db, (int) $row['store_id'])) {
                http_response_code(404);
                echo json_encode(['status' => 'error', 'message' => 'Conflit introuvable']);
                return;
            }
            $newStatus = $action === 'retry' ? 'pending' : 'synced';
            $this->db->prepare(
                'UPDATE synchronization_queue SET status = ?, resolved_at = NOW(), error_message = ? WHERE id = ?'
            )->execute([
                $newStatus,
                $action === 'dismiss' ? 'Résolu manuellement (ignoré)' : null,
                $id,
            ]);
            echo json_encode(['status' => 'success', 'message' => 'Conflit file traité']);
            return;
        }

        if (!$this->tableExists('offline_transactions')) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Table offline introuvable']);
            return;
        }

        $stmt = $this->db->prepare('SELECT * FROM offline_transactions WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row || $row['status'] !== 'conflict' || !StoreScope::canAccessStore($this->db, (int) $row['store_id'])) {
            http_response_code(404);
            echo json_encode(['status' => 'error', 'message' => 'Conflit introuvable']);
            return;
        }

        $userId = (int) ($_SESSION['user_id'] ?? 0);
        if ($action === 'retry') {
            $this->db->prepare(
                "UPDATE offline_transactions SET status = 'pending', error_message = NULL, conflict_reason = NULL WHERE id = ?"
            )->execute([$id]);
            echo json_encode(['status' => 'success', 'message' => 'Transaction remise en attente']);
            return;
        }

        $this->db->prepare(
            'UPDATE offline_transactions SET status = ?, resolved_at = NOW(), resolved_by = ?, conflict_reason = ? WHERE id = ?'
        )->execute([
            'synced',
            $userId > 0 ? $userId : null,
            'Résolu manuellement: ' . $action,
            $id,
        ]);

        echo json_encode(['status' => 'success', 'message' => 'Conflit marqué comme résolu']);
    }

    public function enqueue(
        string $action,
        int $storeId,
        array $payload,
        string $status = 'pending',
        ?string $localUuid = null,
        ?string $errorMessage = null
    ): ?int {
        if (!$this->tableExists('synchronization_queue')) {
            return null;
        }

        $cols = ['store_id', 'action', 'payload', 'status'];
        $vals = [$storeId, $action, json_encode($payload), $status];
        $placeholders = ['?', '?', '?', '?'];

        if ($this->hasColumn('synchronization_queue', 'local_uuid') && $localUuid) {
            $cols[] = 'local_uuid';
            $vals[] = $localUuid;
            $placeholders[] = '?';
        }
        if ($this->hasColumn('synchronization_queue', 'error_message') && $errorMessage) {
            $cols[] = 'error_message';
            $vals[] = $errorMessage;
            $placeholders[] = '?';
        }

        $sql = 'INSERT INTO synchronization_queue (' . implode(', ', $cols) . ') VALUES (' . implode(', ', $placeholders) . ')';
        $this->db->prepare($sql)->execute($vals);
        return (int) $this->db->lastInsertId();
    }

    public function touchStoreSync(int $storeId, bool $success = true): void
    {
        if (!$this->tableExists('store_sync_status')) {
            return;
        }
        // Only record sync time — do not mark terminal online (heartbeat-only).
        $this->db->prepare(
            'INSERT INTO store_sync_status (store_id, is_online, last_sync_at)
             VALUES (?, 0, ?)
             ON DUPLICATE KEY UPDATE last_sync_at = IF(?, NOW(), last_sync_at)'
        )->execute([$storeId, $success ? date('Y-m-d H:i:s') : null, $success ? 1 : 0]);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
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
}

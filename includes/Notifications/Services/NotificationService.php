<?php
declare(strict_types=1);

require_once __DIR__ . '/../NotificationSchemaMigrator.php';
require_once __DIR__ . '/../Repositories/NotificationRepository.php';
require_once __DIR__ . '/../Repositories/NotificationTemplateRepository.php';
require_once __DIR__ . '/../Repositories/NotificationPreferenceRepository.php';
require_once __DIR__ . '/../Repositories/NotificationLogRepository.php';
require_once __DIR__ . '/NotificationAnalyticsService.php';
require_once __DIR__ . '/NotificationDeliveryService.php';
require_once __DIR__ . '/../../Database/Database.php';

class NotificationService
{
    private PDO $db;
    private NotificationRepository $repo;
    private NotificationTemplateRepository $templates;
    private NotificationPreferenceRepository $prefs;
    private NotificationLogRepository $logs;
    private NotificationAnalyticsService $analytics;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        NotificationSchemaMigrator::ensure($this->db);
        $this->repo = new NotificationRepository($this->db);
        $this->templates = new NotificationTemplateRepository($this->db);
        $this->prefs = new NotificationPreferenceRepository($this->db);
        $this->logs = new NotificationLogRepository($this->db);
        $this->analytics = new NotificationAnalyticsService($this->db);
    }

    public function isReady(): bool
    {
        return NotificationSchemaMigrator::isReady($this->db);
    }

    public function list(int $userId, array $filters = []): array
    {
        $limit = min(100, max(1, (int) ($filters['limit'] ?? 50)));
        $offset = max(0, (int) ($filters['offset'] ?? 0));
        $queryFilters = $filters;
        unset($queryFilters['limit'], $queryFilters['offset']);
        $rows = $this->repo->listForUser($userId, $queryFilters, $limit, $offset);
        return array_map([$this, 'formatRow'], $rows);
    }

    public function count(int $userId, array $filters = []): int
    {
        $queryFilters = $filters;
        unset($queryFilters['limit'], $queryFilters['offset']);
        return $this->repo->countForUser($userId, $queryFilters);
    }

    public function unreadCount(int $userId, array $filters = []): int
    {
        return $this->repo->countUnread($userId, $filters);
    }

    public function markRead(int $userId, array $ids): int
    {
        $n = $this->repo->markRead($userId, $ids);
        foreach ($ids as $id) {
            $this->logs->log((int) $id, $userId, 'read', 'success', 'in_app');
        }
        return $n;
    }

    public function markAllRead(int $userId, array $filters = []): int
    {
        $n = $this->repo->markAllRead($userId, $filters);
        $this->logs->log(null, $userId, 'mark_all_read', 'success', 'in_app');
        return $n;
    }

    public function archive(int $userId, array $ids, bool $archive = true): int
    {
        return $this->repo->archive($userId, $ids, $archive);
    }

    public function pin(int $userId, int $id, bool $pinned): bool
    {
        return $this->repo->pin($userId, $id, $pinned);
    }

    public function delete(int $userId, array $ids): int
    {
        return $this->repo->softDelete($userId, $ids);
    }

    public function restore(int $userId, array $ids): int
    {
        return $this->repo->restore($userId, $ids);
    }

    public function getPreferences(int $userId): array
    {
        return $this->prefs->get($userId);
    }

    public function savePreferences(int $userId, array $data): void
    {
        $this->prefs->save($userId, $data);
        $this->logs->log(null, $userId, 'preferences_updated', 'success');
    }

    public function getMeta(): array
    {
        return [
            'categories' => $this->templates->listCategories(),
            'types' => $this->templates->listTypes(),
            'channels' => $this->templates->listChannels(),
            'templates' => $this->templates->listAll(),
        ];
    }

    public function analytics(?int $storeId = null): array
    {
        return $this->analytics->dashboard($storeId);
    }

    public function logs(array $filters = []): array
    {
        return $this->logs->list($filters);
    }

    public function processQueue(): array
    {
        return (new NotificationDeliveryService($this->db))->processQueue();
    }

    public function syncOffline(int $userId, array $localStates): array
    {
        $uuids = array_column($localStates, 'uuid');
        $server = $this->repo->syncBatch($userId, $uuids);
        $serverMap = [];
        foreach ($server as $row) {
            $serverMap[$row['uuid']] = $row;
        }
        foreach ($localStates as $local) {
            $uuid = $local['uuid'] ?? '';
            if ($uuid && isset($serverMap[$uuid]) && !empty($local['is_read'])) {
                $this->repo->markRead($userId, [(int) $serverMap[$uuid]['id']]);
            }
        }
        return $this->list($userId, ['limit' => 100]);
    }

    private function formatRow(array $row): array
    {
        $payload = $row['payload'] ?? null;
        if (is_string($payload)) {
            $payload = json_decode($payload, true) ?: [];
        }
        return [
            'id' => (int) $row['id'],
            'uuid' => $row['uuid'],
            'type' => $row['type_slug'],
            'category' => $row['category_slug'],
            'module' => $row['module'],
            'priority' => $row['priority'],
            'severity' => $row['severity'],
            'title' => $row['title'],
            'message' => $row['message'],
            'action_url' => $row['action_url'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] ? (int) $row['entity_id'] : null,
            'warehouse_id' => !empty($row['warehouse_id']) ? (int) $row['warehouse_id'] : null,
            'warehouse_name' => $row['warehouse_name'] ?? null,
            'is_read' => (bool) $row['is_read'],
            'is_archived' => (bool) $row['is_archived'],
            'is_pinned' => (bool) $row['is_pinned'],
            'created_at' => $row['created_at'],
            'read_at' => $row['read_at'],
            'payload' => $payload,
        ];
    }
}

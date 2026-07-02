<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Notifications/Services/NotificationService.php';
require_once __DIR__ . '/../../Notifications/Repositories/NotificationTemplateRepository.php';
require_once __DIR__ . '/../../Notifications/NotificationSchemaMigrator.php';
require_once __DIR__ . '/../../Database/Database.php';

class WarehouseNotificationService
{
    private const MODULES = ['warehouse', 'inventory'];

    private NotificationService $notifications;
    private NotificationTemplateRepository $templates;
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance()->getConnection();
        $this->notifications = new NotificationService();
        $this->templates = new NotificationTemplateRepository($this->db);
    }

    public function isReady(): bool
    {
        return $this->notifications->isReady();
    }

    /** @return array<string, mixed> */
    public function parseFilters(?int $warehouseId, array $query): array
    {
        $filters = [
            'warehouse_scope' => true,
            'limit' => min(100, max(1, (int) ($query['limit'] ?? 25))),
            'offset' => max(0, (int) ($query['offset'] ?? 0)),
        ];
        if ($warehouseId) {
            $filters['warehouse_id'] = $warehouseId;
        }
        if (!empty($query['unread'])) {
            $filters['unread'] = true;
        }
        if (!empty($query['archived'])) {
            $filters['archived'] = true;
        }
        if (!empty($query['pinned'])) {
            $filters['pinned'] = true;
        }
        if (!empty($query['module']) && in_array($query['module'], self::MODULES, true)) {
            $filters['module'] = $query['module'];
        }
        if (!empty($query['category'])) {
            $filters['category'] = (string) $query['category'];
        }
        if (!empty($query['priority'])) {
            $filters['priority'] = (string) $query['priority'];
        }
        if (!empty($query['search'])) {
            $filters['search'] = trim((string) $query['search']);
        }
        if (!empty($query['since'])) {
            $filters['since'] = (string) $query['since'];
        }
        return $filters;
    }

    /** @return array<string, mixed> */
    public function list(int $userId, ?int $warehouseId, array $query): array
    {
        $filters = $this->parseFilters($warehouseId, $query);
        $limit = (int) $filters['limit'];
        $offset = (int) $filters['offset'];
        unset($filters['limit'], $filters['offset']);

        $items = $this->notifications->list($userId, array_merge($filters, [
            'limit' => $limit,
            'offset' => $offset,
        ]));
        $statsBase = $this->statsBaseFilters($warehouseId, $filters);

        return [
            'items' => $items,
            'total' => $this->notifications->count($userId, $filters),
            'unread_count' => $this->notifications->count($userId, array_merge($statsBase, ['unread' => true])),
            'stats' => [
                'total' => $this->notifications->count($userId, $filters),
                'unread' => $this->notifications->count($userId, array_merge($statsBase, ['unread' => true])),
                'critical' => $this->notifications->count($userId, array_merge($statsBase, ['critical' => true])),
                'today' => $this->notifications->count($userId, array_merge($statsBase, ['today' => true])),
            ],
            'warehouse_id' => $warehouseId,
            'scope' => 'warehouse',
        ];
    }

    public function unreadCount(int $userId, ?int $warehouseId): int
    {
        $filters = ['warehouse_scope' => true];
        if ($warehouseId) {
            $filters['warehouse_id'] = $warehouseId;
        }
        return $this->notifications->count($userId, array_merge($filters, ['unread' => true]));
    }

    /** @return array<string, mixed> */
    public function meta(): array
    {
        $categories = array_values(array_filter(
            $this->templates->listCategories(),
            static fn (array $c) => in_array($c['module'] ?? '', self::MODULES, true)
        ));
        return [
            'categories' => $categories,
            'modules' => self::MODULES,
        ];
    }

    public function markRead(int $userId, array $ids): int
    {
        return $this->notifications->markRead($userId, $ids);
    }

    public function markAllRead(int $userId, ?int $warehouseId): int
    {
        $filters = ['warehouse_scope' => true];
        if ($warehouseId) {
            $filters['warehouse_id'] = $warehouseId;
        }
        return $this->notifications->markAllRead($userId, $filters);
    }

    public function archive(int $userId, array $ids, bool $archive = true): int
    {
        return $this->notifications->archive($userId, $ids, $archive);
    }

    public function pin(int $userId, int $id, bool $pinned): bool
    {
        return $this->notifications->pin($userId, $id, $pinned);
    }

    /** @param array<string, mixed> $filters */
    private function statsBaseFilters(?int $warehouseId, array $filters): array
    {
        $base = ['warehouse_scope' => true];
        if ($warehouseId) {
            $base['warehouse_id'] = $warehouseId;
        }
        foreach (['module', 'category', 'priority', 'search'] as $key) {
            if (!empty($filters[$key])) {
                $base[$key] = $filters[$key];
            }
        }
        if (!empty($filters['archived'])) {
            $base['archived'] = true;
        }
        if (!empty($filters['pinned'])) {
            $base['pinned'] = true;
        }
        return $base;
    }
}

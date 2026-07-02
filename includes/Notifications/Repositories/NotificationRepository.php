<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';

class NotificationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    /** @return array{0: string, 1: array<int, mixed>} */
    private function storeScopeSql(string $alias = 'n'): array
    {
        if (StoreScope::isGlobalView()) {
            return ['', []];
        }

        $col = "{$alias}.store_id";
        $active = StoreScope::activeStoreId();
        if ($active !== null) {
            return [" AND ({$col} IS NULL OR {$col} = ?)", [$active]];
        }

        $allowed = StoreScope::accessibleStoreIds($this->db);
        if ($allowed === null || $allowed === []) {
            return ['', []];
        }
        if (count($allowed) === 1) {
            return [" AND ({$col} IS NULL OR {$col} = ?)", [$allowed[0]]];
        }

        $placeholders = implode(',', array_fill(0, count($allowed), '?'));
        return [" AND ({$col} IS NULL OR {$col} IN ({$placeholders}))", $allowed];
    }

    public function create(array $row): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO notifications
                (uuid, user_id, template_slug, type_slug, category_slug, module, priority, severity,
                 title, message, payload, action_url, entity_type, entity_id,
                 store_id, branch_id, warehouse_id, expires_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $row['uuid'],
            $row['user_id'],
            $row['template_slug'] ?? null,
            $row['type_slug'] ?? 'info',
            $row['category_slug'],
            $row['module'] ?? 'system',
            $row['priority'] ?? 'normal',
            $row['severity'] ?? 'info',
            $row['title'],
            $row['message'],
            isset($row['payload']) ? json_encode($row['payload'], JSON_UNESCAPED_UNICODE) : null,
            $row['action_url'] ?? null,
            $row['entity_type'] ?? null,
            $row['entity_id'] ?? null,
            $row['store_id'] ?? null,
            $row['branch_id'] ?? null,
            $row['warehouse_id'] ?? null,
            $row['expires_at'] ?? null,
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array{0: string[], 1: array<int, mixed>, 2: string, 3: string} */
    private function buildWhere(int $userId, array $filters): array
    {
        [$storeScope, $storeParams] = $this->storeScopeSql('n');
        $where = ['n.user_id = ?', 'n.deleted_at IS NULL'];
        $params = [$userId];
        $params = array_merge($params, $storeParams);

        if (!empty($filters['warehouse_scope'])) {
            $where[] = "n.module IN ('warehouse', 'inventory')";
        }
        if (!empty($filters['unread'])) {
            $where[] = 'n.is_read = 0';
        }
        if (!empty($filters['archived'])) {
            $where[] = 'n.is_archived = 1';
        } else {
            $where[] = 'n.is_archived = 0';
        }
        if (!empty($filters['pinned'])) {
            $where[] = 'n.is_pinned = 1';
        }
        if (!empty($filters['category'])) {
            $where[] = 'n.category_slug = ?';
            $params[] = $filters['category'];
        }
        if (!empty($filters['module'])) {
            $where[] = 'n.module = ?';
            $params[] = $filters['module'];
        }
        if (!empty($filters['warehouse_id'])) {
            $where[] = '(n.warehouse_id IS NULL OR n.warehouse_id = ?)';
            $params[] = (int) $filters['warehouse_id'];
        }
        if (!empty($filters['priority'])) {
            $where[] = 'n.priority = ?';
            $params[] = $filters['priority'];
        }
        if (!empty($filters['since'])) {
            $where[] = 'n.created_at > ?';
            $params[] = $filters['since'];
        }
        if (!empty($filters['today'])) {
            $where[] = 'DATE(n.created_at) = CURDATE()';
        }
        if (!empty($filters['critical'])) {
            $where[] = "(n.severity IN ('critical', 'error') OR n.priority = 'critical')";
        }
        if (!empty($filters['search'])) {
            $where[] = '(n.title LIKE ? OR n.message LIKE ?)';
            $q = '%' . $filters['search'] . '%';
            $params[] = $q;
            $params[] = $q;
        }

        $order = !empty($filters['archived'])
            ? 'n.archived_at DESC'
            : 'n.is_pinned DESC, n.created_at DESC';

        return [$where, $params, $order, $storeScope];
    }

    public function listForUser(int $userId, array $filters = [], int $limit = 50, int $offset = 0): array
    {
        [$where, $params, $order, $storeScope] = $this->buildWhere($userId, $filters);
        $join = !empty($filters['warehouse_scope'])
            ? ' LEFT JOIN warehouses w ON w.id = n.warehouse_id'
            : '';
        $cols = !empty($filters['warehouse_scope'])
            ? 'n.*, w.name AS warehouse_name'
            : 'n.*';

        $sql = "SELECT {$cols} FROM notifications n{$join} WHERE " . implode(' AND ', $where) . $storeScope
            . " ORDER BY {$order} LIMIT " . (int) $limit . ' OFFSET ' . (int) $offset;
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function countForUser(int $userId, array $filters = []): int
    {
        [$where, $params, , $storeScope] = $this->buildWhere($userId, $filters);
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM notifications n WHERE ' . implode(' AND ', $where) . $storeScope
        );
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function countUnread(int $userId, array $filters = []): int
    {
        return $this->countForUser($userId, array_merge($filters, ['unread' => true]));
    }

    public function markRead(int $userId, array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$userId], $storeParams);
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_read = 1, read_at = NOW()
             WHERE id IN ({$placeholders}) AND user_id = ? AND is_read = 0{$storeScope}"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function markAllRead(int $userId, array $filters = []): int
    {
        if ($filters === []) {
            [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
            $stmt = $this->db->prepare(
                "UPDATE notifications SET is_read = 1, read_at = NOW()
                 WHERE user_id = ? AND is_read = 0 AND deleted_at IS NULL{$storeScope}"
            );
            $stmt->execute(array_merge([$userId], $storeParams));
            return $stmt->rowCount();
        }

        [$where, $params, , $storeScope] = $this->buildWhere($userId, $filters);
        $where[] = 'n.is_read = 0';
        $stmt = $this->db->prepare(
            'UPDATE notifications n SET n.is_read = 1, n.read_at = NOW() WHERE ' . implode(' AND ', $where) . $storeScope
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function archive(int $userId, array $ids, bool $archive = true): int
    {
        if (!$ids) {
            return 0;
        }
        [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge([$archive ? 1 : 0], $ids, [$userId], $storeParams);
        $stmt = $this->db->prepare(
            "UPDATE notifications SET is_archived = ?, archived_at = " . ($archive ? 'NOW()' : 'NULL') . "
             WHERE id IN ({$placeholders}) AND user_id = ?{$storeScope}"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function pin(int $userId, int $id, bool $pinned): bool
    {
        [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
        $stmt = $this->db->prepare(
            'UPDATE notifications SET is_pinned = ?, pinned_at = ' . ($pinned ? 'NOW()' : 'NULL') . '
             WHERE id = ? AND user_id = ?' . $storeScope
        );
        return $stmt->execute(array_merge([$pinned ? 1 : 0, $id, $userId], $storeParams));
    }

    public function softDelete(int $userId, array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$userId], $storeParams);
        $stmt = $this->db->prepare(
            "UPDATE notifications SET deleted_at = NOW() WHERE id IN ({$placeholders}) AND user_id = ?{$storeScope}"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function restore(int $userId, array $ids): int
    {
        if (!$ids) {
            return 0;
        }
        [$storeScope, $storeParams] = $this->storeScopeSql('notifications');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $params = array_merge($ids, [$userId], $storeParams);
        $stmt = $this->db->prepare(
            "UPDATE notifications SET deleted_at = NULL WHERE id IN ({$placeholders}) AND user_id = ?{$storeScope}"
        );
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    public function findByUuid(string $uuid, int $userId): ?array
    {
        [$storeScope, $storeParams] = $this->storeScopeSql('n');
        $stmt = $this->db->prepare(
            "SELECT n.* FROM notifications n WHERE n.uuid = ? AND n.user_id = ?{$storeScope} LIMIT 1"
        );
        $stmt->execute(array_merge([$uuid, $userId], $storeParams));
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function syncBatch(int $userId, array $uuids): array
    {
        if (!$uuids) {
            return [];
        }
        [$storeScope, $storeParams] = $this->storeScopeSql('n');
        $placeholders = implode(',', array_fill(0, count($uuids), '?'));
        $params = array_merge($uuids, [$userId], $storeParams);
        $stmt = $this->db->prepare(
            "SELECT n.* FROM notifications n WHERE n.uuid IN ({$placeholders}) AND n.user_id = ?{$storeScope}"
        );
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }
}

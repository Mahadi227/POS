<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../../Helpers/StoreScope.php';
require_once __DIR__ . '/../ManagerAuth.php';

class AuditService
{
    public static function log(string $action, ?string $entityType = null, ?int $entityId = null, ?array $details = null): void
    {
        if (!self::tableExists()) {
            return;
        }

        $stmt = Database::getInstance()->getConnection()->prepare(
            'INSERT INTO manager_audit_log (store_id, user_id, action, entity_type, entity_id, details, ip_address)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            StoreScope::activeStoreId(),
            ManagerAuth::currentUserId(),
            $action,
            $entityType,
            $entityId,
            $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
        ]);
    }

    public static function tableExists(): bool
    {
        try {
            Database::getInstance()->getConnection()->query('SELECT 1 FROM manager_audit_log LIMIT 1');
            return true;
        } catch (Throwable $e) {
            return false;
        }
    }

    /** @return list<array<string, mixed>> */
    public static function recent(?int $storeId, int $limit = 100): array
    {
        $result = self::trail($storeId, ['limit' => $limit]);
        return $result['items'] ?? [];
    }

    /**
     * Manager audit trail with filters and summary.
     *
     * @param array<string, mixed> $options
     * @return array<string, mixed>
     */
    public static function trail(?int $storeId, array $options = []): array
    {
        if (!self::tableExists()) {
            return [
                'module_ready' => false,
                'period'       => 'today',
                'filter'       => 'all',
                'summary'      => [
                    'total'         => 0,
                    'approved'      => 0,
                    'rejected'      => 0,
                    'unique_users'  => 0,
                ],
                'items' => [],
            ];
        }

        $period = (string) ($options['period'] ?? 'today');
        $from = isset($options['from']) ? (string) $options['from'] : null;
        $to = isset($options['to']) ? (string) $options['to'] : null;
        $filter = (string) ($options['filter'] ?? 'all');
        $search = trim((string) ($options['q'] ?? ''));
        $limit = max(1, min(500, (int) ($options['limit'] ?? 200)));

        [$periodKey, $fromTs, $toTs, $useDateFilter] = self::resolvePeriod($period, $from, $to);

        $db = Database::getInstance()->getConnection();
        $where = ['1=1'];
        $params = [];

        if ($storeId !== null) {
            $where[] = 'l.store_id = ?';
            $params[] = $storeId;
        }

        if ($useDateFilter && $fromTs && $toTs) {
            $where[] = 'l.created_at >= ? AND l.created_at <= ?';
            $params[] = $fromTs;
            $params[] = $toTs;
        }

        if ($filter === 'approved') {
            $where[] = 'l.action = ?';
            $params[] = 'approval_approved';
        } elseif ($filter === 'rejected') {
            $where[] = 'l.action = ?';
            $params[] = 'approval_rejected';
        }

        if ($search !== '') {
            $like = '%' . $search . '%';
            $where[] = '(l.action LIKE ? OR l.entity_type LIKE ? OR l.details LIKE ? OR u.name LIKE ?)';
            array_push($params, $like, $like, $like, $like);
        }

        $whereSql = implode(' AND ', $where);

        $summaryStmt = $db->prepare(
            "SELECT
                COUNT(*) AS total,
                COALESCE(SUM(CASE WHEN l.action = 'approval_approved' THEN 1 ELSE 0 END), 0) AS approved,
                COALESCE(SUM(CASE WHEN l.action = 'approval_rejected' THEN 1 ELSE 0 END), 0) AS rejected,
                COUNT(DISTINCT l.user_id) AS unique_users
             FROM manager_audit_log l
             INNER JOIN users u ON u.id = l.user_id
             WHERE {$whereSql}"
        );
        $summaryStmt->execute($params);
        $summaryRow = $summaryStmt->fetch(PDO::FETCH_ASSOC) ?: [];

        $listStmt = $db->prepare(
            "SELECT l.*, u.name AS user_name
             FROM manager_audit_log l
             INNER JOIN users u ON u.id = l.user_id
             WHERE {$whereSql}
             ORDER BY l.created_at DESC
             LIMIT {$limit}"
        );
        $listStmt->execute($params);
        $items = array_map([self::class, 'enrichRow'], $listStmt->fetchAll(PDO::FETCH_ASSOC) ?: []);

        return [
            'module_ready' => true,
            'period'       => $periodKey,
            'from'         => $useDateFilter && $fromTs ? substr($fromTs, 0, 10) : null,
            'to'           => $useDateFilter && $toTs ? substr($toTs, 0, 10) : null,
            'filter'       => $filter,
            'summary'      => [
                'total'        => (int) ($summaryRow['total'] ?? 0),
                'approved'     => (int) ($summaryRow['approved'] ?? 0),
                'rejected'     => (int) ($summaryRow['rejected'] ?? 0),
                'unique_users' => (int) ($summaryRow['unique_users'] ?? 0),
            ],
            'items' => $items,
        ];
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private static function enrichRow(array $row): array
    {
        $detailsRaw = $row['details'] ?? null;
        $details = null;
        if (is_string($detailsRaw) && $detailsRaw !== '') {
            $decoded = json_decode($detailsRaw, true);
            $details = is_array($decoded) ? $decoded : $detailsRaw;
        }

        return [
            'id'          => (int) ($row['id'] ?? 0),
            'user_id'     => (int) ($row['user_id'] ?? 0),
            'user_name'   => (string) ($row['user_name'] ?? ''),
            'action'      => (string) ($row['action'] ?? ''),
            'entity_type' => (string) ($row['entity_type'] ?? ''),
            'entity_id'   => isset($row['entity_id']) ? (int) $row['entity_id'] : null,
            'details'     => $details,
            'ip_address'  => (string) ($row['ip_address'] ?? ''),
            'created_at'  => $row['created_at'] ?? null,
        ];
    }

    /** @return array{0: string, 1: ?string, 2: ?string, 3: bool} */
    private static function resolvePeriod(string $period, ?string $dateFrom, ?string $dateTo): array
    {
        if ($period === 'all') {
            return ['all', null, null, false];
        }

        if ($period === 'custom') {
            $fromDay = self::parseDate($dateFrom);
            $toDay = self::parseDate($dateTo);
            if ($fromDay === null || $toDay === null) {
                return self::resolvePeriod('today', null, null);
            }
            if ($fromDay > $toDay) {
                [$fromDay, $toDay] = [$toDay, $fromDay];
            }

            return ['custom', $fromDay . ' 00:00:00', $toDay . ' 23:59:59', true];
        }

        if (!in_array($period, ['today', 'week', 'month'], true)) {
            $period = 'today';
        }

        [$period, $from, $to] = self::periodBounds($period);

        return [$period, $from, $to, true];
    }

    private static function parseDate(?string $value): ?string
    {
        if (!$value || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return null;
        }

        $ts = strtotime($value);

        return $ts === false ? null : date('Y-m-d', $ts);
    }

    /** @return array{0: string, 1: string, 2: string} */
    private static function periodBounds(string $period): array
    {
        $end = date('Y-m-d 23:59:59');

        switch ($period) {
            case 'week':
                $start = date('Y-m-d 00:00:00', strtotime('-6 days'));
                break;
            case 'month':
                $start = date('Y-m-d 00:00:00', strtotime('-29 days'));
                break;
            case 'today':
            default:
                $period = 'today';
                $start = date('Y-m-d 00:00:00');
                break;
        }

        return [$period, $start, $end];
    }
}

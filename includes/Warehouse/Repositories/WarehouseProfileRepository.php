<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WarehouseProfileSchema.php';

class WarehouseProfileRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
        WarehouseProfileSchema::ensure($this->db);
    }

    public function findUser(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT u.*, r.name AS role_name,
                    w.name AS warehouse_name, w.warehouse_code AS warehouse_code,
                    st.name AS store_name,
                    br.name AS branch_name,
                    sup.name AS supervisor_name
             FROM users u
             INNER JOIN roles r ON r.id = u.role_id
             LEFT JOIN warehouses w ON w.id = u.warehouse_id
             LEFT JOIN stores st ON st.id = u.store_id
             LEFT JOIN stores br ON br.id = u.branch_id
             LEFT JOIN users sup ON sup.id = u.supervisor_id
             WHERE u.id = ? AND u.deleted_at IS NULL
             LIMIT 1"
        );
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    public function getPreferences(int $userId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM user_preferences WHERE user_id = ? LIMIT 1');
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            return $row;
        }
        return $this->defaultPreferences($userId);
    }

    public function savePreferences(int $userId, array $prefs): void
    {
        $existing = $this->getPreferences($userId);
        $merged = array_merge($existing, $prefs);
        $stmt = $this->db->prepare(
            'INSERT INTO user_preferences
                (user_id, theme, date_format, time_format, items_per_page, dashboard_layout,
                 default_warehouse_view, warehouse_notif_dashboard, warehouse_notif_low_stock,
                 warehouse_notif_transfer, warehouse_notif_receiving, warehouse_notif_dispatch, two_factor_enabled)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE
                theme = VALUES(theme),
                date_format = VALUES(date_format),
                time_format = VALUES(time_format),
                items_per_page = VALUES(items_per_page),
                dashboard_layout = VALUES(dashboard_layout),
                default_warehouse_view = VALUES(default_warehouse_view),
                warehouse_notif_dashboard = VALUES(warehouse_notif_dashboard),
                warehouse_notif_low_stock = VALUES(warehouse_notif_low_stock),
                warehouse_notif_transfer = VALUES(warehouse_notif_transfer),
                warehouse_notif_receiving = VALUES(warehouse_notif_receiving),
                warehouse_notif_dispatch = VALUES(warehouse_notif_dispatch),
                two_factor_enabled = VALUES(two_factor_enabled)'
        );
        $stmt->execute([
            $userId,
            $merged['theme'] ?? 'system',
            $merged['date_format'] ?? 'Y-m-d',
            $merged['time_format'] ?? '24h',
            max(10, min(200, (int) ($merged['items_per_page'] ?? 50))),
            $merged['dashboard_layout'] ?? 'standard',
            $merged['default_warehouse_view'] ?? 'assigned',
            (int) ($merged['warehouse_notif_dashboard'] ?? 1),
            (int) ($merged['warehouse_notif_low_stock'] ?? 1),
            (int) ($merged['warehouse_notif_transfer'] ?? 1),
            (int) ($merged['warehouse_notif_receiving'] ?? 1),
            (int) ($merged['warehouse_notif_dispatch'] ?? 1),
            (int) ($merged['two_factor_enabled'] ?? 0),
        ]);
    }

    public function updateProfile(int $userId, array $data): void
    {
        $stmt = $this->db->prepare(
            'UPDATE users SET
                first_name = ?, last_name = ?, name = ?, phone = ?,
                address = ?, emergency_contact = ?, language = ?, timezone = ?,
                updated_at = NOW()
             WHERE id = ? AND deleted_at IS NULL'
        );
        $stmt->execute([
            $data['first_name'],
            $data['last_name'],
            $data['name'],
            $data['phone'],
            $data['address'],
            $data['emergency_contact'],
            $data['language'],
            $data['timezone'],
            $userId,
        ]);
    }

    public function updatePassword(int $userId, string $hash): void
    {
        $stmt = $this->db->prepare('UPDATE users SET password_hash = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$hash, $userId]);
    }

    public function updateAvatar(int $userId, ?string $path): void
    {
        $stmt = $this->db->prepare('UPDATE users SET avatar_path = ?, updated_at = NOW() WHERE id = ?');
        $stmt->execute([$path, $userId]);
    }

    public function passwordHash(int $userId): ?string
    {
        $stmt = $this->db->prepare('SELECT password_hash FROM users WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$userId]);
        $hash = $stmt->fetchColumn();
        return $hash !== false ? (string) $hash : null;
    }

    public function listLoginHistory(int $userId, ?string $search, int $limit, int $offset): array
    {
        $rows = [];
        if ($this->tableExists('security_audit_logs')) {
            $where = "user_id = ? AND action LIKE 'login%'";
            $params = [$userId];
            if ($search) {
                $where .= ' AND (ip_address LIKE ? OR browser LIKE ? OR os_name LIKE ? OR device_type LIKE ? OR status LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, array_fill(0, 5, $like));
            }
            $sql = "SELECT id, created_at, ip_address, browser, os_name AS os, device_type AS device,
                           user_agent, status, 'security' AS source
                    FROM security_audit_logs
                    WHERE {$where}
                    ORDER BY created_at DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        }
        if (!$rows && $this->tableExists('login_activity')) {
            $where = 'user_id = ?';
            $params = [$userId];
            if ($search) {
                $where .= ' AND (ip_address LIKE ? OR user_agent LIKE ? OR status LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, [$like, $like, $like]);
            }
            $sql = "SELECT id, created_at, ip_address, user_agent, status, 'legacy' AS source
                    FROM login_activity
                    WHERE {$where}
                    ORDER BY created_at DESC
                    LIMIT {$limit} OFFSET {$offset}";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $rows = array_map(function (array $row) {
                $parsed = self::parseUa($row['user_agent'] ?? '');
                $row['browser'] = $parsed['browser'];
                $row['os'] = $parsed['os'];
                $row['device'] = $parsed['device'];
                return $row;
            }, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
        return $rows;
    }

    public function countLoginHistory(int $userId, ?string $search): int
    {
        if ($this->tableExists('security_audit_logs')) {
            $where = "user_id = ? AND action LIKE 'login%'";
            $params = [$userId];
            if ($search) {
                $where .= ' AND (ip_address LIKE ? OR browser LIKE ? OR os_name LIKE ? OR device_type LIKE ? OR status LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, array_fill(0, 5, $like));
            }
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM security_audit_logs WHERE {$where}");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
        if ($this->tableExists('login_activity')) {
            $where = 'user_id = ?';
            $params = [$userId];
            if ($search) {
                $where .= ' AND (ip_address LIKE ? OR user_agent LIKE ? OR status LIKE ?)';
                $like = '%' . $search . '%';
                $params = array_merge($params, [$like, $like, $like]);
            }
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM login_activity WHERE {$where}");
            $stmt->execute($params);
            return (int) $stmt->fetchColumn();
        }
        return 0;
    }

    public function listActivities(int $userId, int $limit, int $offset): array
    {
        $items = [];
        if ($this->tableExists('warehouse_logs')) {
            $stmt = $this->db->prepare(
                "SELECT id, action, entity_type, entity_id, details, created_at, 'warehouse' AS source
                 FROM warehouse_logs
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute([$userId]);
            $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
        if ($this->tableExists('user_activity_logs')) {
            $stmt = $this->db->prepare(
                "SELECT id, action, status, ip_address, created_at, 'account' AS source
                 FROM user_activity_logs
                 WHERE user_id = ?
                 ORDER BY created_at DESC
                 LIMIT {$limit} OFFSET {$offset}"
            );
            $stmt->execute([$userId]);
            $items = array_merge($items, $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
        }
        usort($items, static fn ($a, $b) => strcmp($b['created_at'] ?? '', $a['created_at'] ?? ''));
        return array_slice($items, 0, $limit);
    }

    public function performanceStats(int $userId, string $roleSlug, ?int $warehouseId): array
    {
        $since = date('Y-m-d H:i:s', strtotime('-30 days'));
        $stats = ['role' => $roleSlug, 'period_days' => 30, 'metrics' => []];

        if ($this->tableExists('goods_receipts')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS cnt,
                        COALESCE(SUM(total_items), 0) AS units,
                        COALESCE(AVG(TIMESTAMPDIFF(MINUTE, created_at, received_at)), 0) AS avg_minutes
                 FROM goods_receipts
                 WHERE received_by = ? AND received_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $r = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['metrics']['receipts'] = (int) ($r['cnt'] ?? 0);
            $stats['metrics']['units_received'] = (int) ($r['units'] ?? 0);
            $stats['metrics']['avg_receiving_minutes'] = round((float) ($r['avg_minutes'] ?? 0), 1);
        }

        if ($this->tableExists('purchase_orders')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM purchase_orders WHERE created_by = ? AND created_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $stats['metrics']['purchase_orders'] = (int) $stmt->fetchColumn();
        }

        if ($this->tableExists('warehouse_dispatches')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status IN ('dispatched','in_transit','delivered') THEN 1 ELSE 0 END) AS completed
                 FROM warehouse_dispatches
                 WHERE created_by = ? AND created_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $d = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['metrics']['dispatches'] = (int) ($d['total'] ?? 0);
            $stats['metrics']['deliveries_completed'] = (int) ($d['completed'] ?? 0);
        }

        if ($this->tableExists('warehouse_transfers')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM warehouse_transfers
                 WHERE (requested_by = ? OR approved_by = ? OR received_by = ?) AND created_at >= ?"
            );
            $stmt->execute([$userId, $userId, $userId, $since]);
            $stats['metrics']['transfers'] = (int) $stmt->fetchColumn();
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM warehouse_transfers WHERE approved_by = ? AND created_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $stats['metrics']['transfers_approved'] = (int) $stmt->fetchColumn();
        }

        if ($this->tableExists('warehouse_stock_movements')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM warehouse_stock_movements
                 WHERE created_by = ? AND movement_type = 'adjustment' AND created_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $stats['metrics']['adjustments'] = (int) $stmt->fetchColumn();
        }

        if ($this->tableExists('warehouse_audits')) {
            $stmt = $this->db->prepare(
                "SELECT COUNT(*) FROM warehouse_audits WHERE conducted_by = ? AND created_at >= ?"
            );
            $stmt->execute([$userId, $since]);
            $stats['metrics']['stock_counts'] = (int) $stmt->fetchColumn();
        }

        if ($warehouseId && $this->tableExists('warehouse_inventory')) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(DISTINCT product_id) AS products, COALESCE(SUM(stock_value), 0) AS value
                 FROM warehouse_inventory WHERE warehouse_id = ?'
            );
            $stmt->execute([$warehouseId]);
            $inv = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['metrics']['products_managed'] = (int) ($inv['products'] ?? 0);
            $stats['metrics']['inventory_value'] = round((float) ($inv['value'] ?? 0), 2);
        }

        if ($this->tableExists('warehouse_logs')) {
            $stmt = $this->db->prepare(
                'SELECT COUNT(*) FROM warehouse_logs WHERE user_id = ? AND created_at >= ?'
            );
            $stmt->execute([$userId, $since]);
            $stats['metrics']['warehouse_actions'] = (int) $stmt->fetchColumn();
        }

        return $stats;
    }

    private function defaultPreferences(int $userId): array
    {
        return [
            'user_id' => $userId,
            'theme' => 'system',
            'date_format' => 'Y-m-d',
            'time_format' => '24h',
            'items_per_page' => 50,
            'dashboard_layout' => 'standard',
            'default_warehouse_view' => 'assigned',
            'warehouse_notif_dashboard' => 1,
            'warehouse_notif_low_stock' => 1,
            'warehouse_notif_transfer' => 1,
            'warehouse_notif_receiving' => 1,
            'warehouse_notif_dispatch' => 1,
            'two_factor_enabled' => 0,
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }

    /** @return array{browser: string, os: string, device: string} */
    private static function parseUa(string $ua): array
    {
        $browser = 'Unknown';
        $os = 'Unknown';
        $device = 'desktop';
        if (preg_match('/Mobile|Android|iPhone/i', $ua)) {
            $device = 'mobile';
        } elseif (preg_match('/Tablet|iPad/i', $ua)) {
            $device = 'tablet';
        }
        if (preg_match('/Firefox/i', $ua)) {
            $browser = 'Firefox';
        } elseif (preg_match('/Chrome/i', $ua)) {
            $browser = 'Chrome';
        } elseif (preg_match('/Safari/i', $ua)) {
            $browser = 'Safari';
        } elseif (preg_match('/Edge/i', $ua)) {
            $browser = 'Edge';
        }
        if (preg_match('/Windows/i', $ua)) {
            $os = 'Windows';
        } elseif (preg_match('/Mac OS/i', $ua)) {
            $os = 'macOS';
        } elseif (preg_match('/Linux/i', $ua)) {
            $os = 'Linux';
        } elseif (preg_match('/Android/i', $ua)) {
            $os = 'Android';
        } elseif (preg_match('/iPhone|iPad/i', $ua)) {
            $os = 'iOS';
        }
        return compact('browser', 'os', 'device');
    }
}

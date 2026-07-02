<?php
declare(strict_types=1);

require_once __DIR__ . '/../../Database/Database.php';
require_once __DIR__ . '/../WmsSchema.php';

class WarehouseLocationRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance()->getConnection();
    }

    public function list(int $warehouseId, ?string $search = null, ?string $status = null, ?string $zone = null, int $limit = 200, int $offset = 0): array
    {
        if (!WmsSchema::ready() || $warehouseId <= 0) {
            return [];
        }
        [$where, $params] = $this->filterClause($warehouseId, $search, $status, $zone);
        $limit = min(200, max(1, $limit));
        $offset = max(0, $offset);
        $sql = "SELECT wl.* FROM warehouse_locations wl
                WHERE 1=1 {$where}
                ORDER BY wl.zone, wl.aisle, wl.rack, wl.shelf, wl.bin
                LIMIT {$limit} OFFSET {$offset}";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    public function count(int $warehouseId, ?string $search = null, ?string $status = null, ?string $zone = null): int
    {
        if (!WmsSchema::ready() || $warehouseId <= 0) {
            return 0;
        }
        [$where, $params] = $this->filterClause($warehouseId, $search, $status, $zone);
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM warehouse_locations wl WHERE 1=1 {$where}");
        $stmt->execute($params);
        return (int) $stmt->fetchColumn();
    }

    public function summary(int $warehouseId): array
    {
        if (!WmsSchema::ready() || $warehouseId <= 0) {
            return [
                'total' => 0,
                'active' => 0,
                'inactive' => 0,
                'full' => 0,
                'capacity_total' => 0,
                'zones' => 0,
            ];
        }
        $stmt = $this->db->prepare(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) AS active,
                SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) AS inactive,
                SUM(CASE WHEN status = 'full' THEN 1 ELSE 0 END) AS full,
                COALESCE(SUM(capacity_units), 0) AS capacity_total,
                COUNT(DISTINCT zone) AS zones
             FROM warehouse_locations
             WHERE warehouse_id = ?"
        );
        $stmt->execute([$warehouseId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        return [
            'total' => (int) ($row['total'] ?? 0),
            'active' => (int) ($row['active'] ?? 0),
            'inactive' => (int) ($row['inactive'] ?? 0),
            'full' => (int) ($row['full'] ?? 0),
            'capacity_total' => (int) ($row['capacity_total'] ?? 0),
            'zones' => (int) ($row['zones'] ?? 0),
        ];
    }

    /** @return list<array{zone: string, count: int, capacity: int}> */
    public function zoneBreakdown(int $warehouseId): array
    {
        if (!WmsSchema::ready() || $warehouseId <= 0) {
            return [];
        }
        $stmt = $this->db->prepare(
            "SELECT zone,
                    COUNT(*) AS count,
                    COALESCE(SUM(capacity_units), 0) AS capacity
             FROM warehouse_locations
             WHERE warehouse_id = ?
             GROUP BY zone
             ORDER BY zone"
        );
        $stmt->execute([$warehouseId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        return array_map(static fn (array $r) => [
            'zone' => (string) ($r['zone'] ?? ''),
            'count' => (int) ($r['count'] ?? 0),
            'capacity' => (int) ($r['capacity'] ?? 0),
        ], $rows);
    }

    public function create(array $data): int
    {
        $code = (string) ($data['location_code'] ?? $this->buildCode($data));
        $stmt = $this->db->prepare(
            "INSERT INTO warehouse_locations (warehouse_id, zone, aisle, rack, shelf, bin, location_code, capacity_units, status)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            (int) $data['warehouse_id'],
            (string) ($data['zone'] ?? 'A'),
            $data['aisle'] ?? null,
            $data['rack'] ?? null,
            $data['shelf'] ?? null,
            $data['bin'] ?? null,
            $code,
            (int) ($data['capacity_units'] ?? 0),
            (string) ($data['status'] ?? 'active'),
        ]);
        return (int) $this->db->lastInsertId();
    }

    /** @return array{0: string, 1: list<mixed>} */
    private function filterClause(int $warehouseId, ?string $search, ?string $status, ?string $zone): array
    {
        $where = ' AND wl.warehouse_id = ?';
        $params = [$warehouseId];

        if ($search !== null && $search !== '') {
            $like = '%' . $search . '%';
            $where .= ' AND (wl.location_code LIKE ? OR wl.zone LIKE ? OR wl.aisle LIKE ? OR wl.rack LIKE ? OR wl.shelf LIKE ? OR wl.bin LIKE ?)';
            array_push($params, $like, $like, $like, $like, $like, $like);
        }

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where .= ' AND wl.status = ?';
            $params[] = $status;
        }

        if ($zone !== null && $zone !== '' && $zone !== 'all') {
            $where .= ' AND wl.zone = ?';
            $params[] = $zone;
        }

        return [$where, $params];
    }

    private function buildCode(array $data): string
    {
        return implode('-', array_filter([
            $data['zone'] ?? 'A',
            $data['aisle'] ?? null,
            $data['rack'] ?? null,
            $data['shelf'] ?? null,
            $data['bin'] ?? null,
        ]));
    }
}

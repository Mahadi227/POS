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

    public function list(int $warehouseId): array
    {
        if (!WmsSchema::ready()) {
            return [];
        }
        $stmt = $this->db->prepare(
            'SELECT * FROM warehouse_locations WHERE warehouse_id = ? ORDER BY zone, aisle, rack, shelf, bin'
        );
        $stmt->execute([$warehouseId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
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

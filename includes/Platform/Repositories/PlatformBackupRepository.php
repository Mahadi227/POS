<?php
declare(strict_types=1);

require_once __DIR__ . '/../SaaSPhase16Migrator.php';
require_once __DIR__ . '/../Services/PlatformBackupService.php';

final class PlatformBackupRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        SaaSPhase16Migrator::ensure($db);
    }

    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'recent' => $this->list(20, 0),
            'storage_dir' => PlatformBackupService::storageDir(),
        ];
    }

    /** @return array<string, int|float> */
    public function stats(): array
    {
        if (!$this->tableExists()) {
            return [
                'total' => 0, 'completed' => 0, 'failed' => 0, 'running' => 0,
                'today' => 0, 'total_size' => 0,
            ];
        }

        $row = $this->db->query(
            "SELECT COUNT(*) AS total,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) AS failed,
                    SUM(CASE WHEN status = 'running' THEN 1 ELSE 0 END) AS running,
                    SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) AS today,
                    COALESCE(SUM(CASE WHEN status = 'completed' THEN size_bytes ELSE 0 END), 0) AS total_size
             FROM platform_backups"
        )->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'completed' => (int) ($row['completed'] ?? 0),
            'failed' => (int) ($row['failed'] ?? 0),
            'running' => (int) ($row['running'] ?? 0),
            'today' => (int) ($row['today'] ?? 0),
            'total_size' => (int) ($row['total_size'] ?? 0),
        ];
    }

    /** @return array<int, array<string, mixed>> */
    public function list(int $limit = 50, int $offset = 0, ?string $status = null, ?string $scope = null): array
    {
        if (!$this->tableExists()) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($status !== null && $status !== '') {
            $where[] = 'pb.status = ?';
            $params[] = $status;
        }
        if ($scope !== null && $scope !== '') {
            $where[] = 'pb.scope = ?';
            $params[] = $scope;
        }

        $sql = 'SELECT pb.id, pb.label, pb.scope, pb.tenant_id, pb.status, pb.size_bytes,
                       pb.storage, pb.triggered_by, pb.error_message, pb.started_at, pb.completed_at,
                       pb.created_at, t.name AS tenant_name, pu.name AS triggered_by_name
                FROM platform_backups pb
                LEFT JOIN tenants t ON t.id = pb.tenant_id
                LEFT JOIN platform_users pu ON pu.id = pb.triggered_by
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY pb.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        return array_map([$this, 'formatRow'], $stmt->fetchAll(PDO::FETCH_ASSOC) ?: []);
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }

        $stmt = $this->db->prepare(
            'SELECT pb.*, t.name AS tenant_name, pu.name AS triggered_by_name
             FROM platform_backups pb
             LEFT JOIN tenants t ON t.id = pb.tenant_id
             LEFT JOIN platform_users pu ON pu.id = pb.triggered_by
             WHERE pb.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ? $this->formatRow($row) : null;
    }

    public function create(string $label, string $scope, ?int $tenantId, int $userId): int
    {
        $stmt = $this->db->prepare(
            'INSERT INTO platform_backups (label, scope, tenant_id, status, triggered_by)
             VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([$label, $scope, $tenantId, 'pending', $userId]);
        return (int) $this->db->lastInsertId();
    }

    public function delete(int $id): bool
    {
        if (!$this->tableExists()) {
            return false;
        }

        $row = $this->findById($id);
        if (!$row) {
            return false;
        }

        $path = (string) ($row['file_path'] ?? '');
        if ($path !== '' && is_file($path)) {
            @unlink($path);
        }

        $stmt = $this->db->prepare('DELETE FROM platform_backups WHERE id = ?');
        $stmt->execute([$id]);

        return $stmt->rowCount() > 0;
    }

    /** @param array<string, mixed> $row */
    private function formatRow(array $row): array
    {
        $row['id'] = (int) ($row['id'] ?? 0);
        $row['tenant_id'] = isset($row['tenant_id']) ? (int) $row['tenant_id'] : null;
        $row['size_bytes'] = isset($row['size_bytes']) ? (int) $row['size_bytes'] : null;
        $row['triggered_by'] = isset($row['triggered_by']) ? (int) $row['triggered_by'] : null;
        unset($row['file_path']);
        return $row;
    }

    /** @return array<string, mixed>|null */
    public function findFileMeta(int $id): ?array
    {
        if (!$this->tableExists()) {
            return null;
        }
        $stmt = $this->db->prepare(
            'SELECT id, label, status, file_path, scope FROM platform_backups WHERE id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function tableExists(): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute(['platform_backups']);
        return (bool) $stmt->fetchColumn();
    }
}

<?php
declare(strict_types=1);

final class PlatformUpdateRepository
{
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /** @return array<string, mixed> */
    public function dashboard(): array
    {
        return [
            'stats' => $this->stats(),
            'current_version' => $this->currentVersion(),
            'releases' => $this->listReleases(20, 0, null, null),
            'migrations' => $this->listMigrations(15),
        ];
    }

    /** @return array<string, int|string|null> */
    public function stats(): array
    {
        $stats = [
            'total' => 0,
            'released' => 0,
            'scheduled' => 0,
            'draft' => 0,
            'migrations' => 0,
            'maintenance' => 0,
        ];

        if ($this->tableExists('platform_releases')) {
            $row = $this->db->query(
                "SELECT COUNT(*) AS total,
                        SUM(CASE WHEN status = 'released' THEN 1 ELSE 0 END) AS released,
                        SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled,
                        SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) AS draft,
                        SUM(CASE WHEN requires_maintenance = 1 AND status IN ('scheduled','draft') THEN 1 ELSE 0 END) AS maintenance
                 FROM platform_releases"
            )->fetch(PDO::FETCH_ASSOC) ?: [];
            $stats['total'] = (int) ($row['total'] ?? 0);
            $stats['released'] = (int) ($row['released'] ?? 0);
            $stats['scheduled'] = (int) ($row['scheduled'] ?? 0);
            $stats['draft'] = (int) ($row['draft'] ?? 0);
            $stats['maintenance'] = (int) ($row['maintenance'] ?? 0);
        }

        if ($this->tableExists('schema_migrations')) {
            $stats['migrations'] = (int) $this->db->query('SELECT COUNT(*) FROM schema_migrations')->fetchColumn();
        }

        return $stats;
    }

    public function currentVersion(): ?string
    {
        if (!$this->tableExists('platform_releases')) {
            return null;
        }
        $stmt = $this->db->query(
            "SELECT version FROM platform_releases
             WHERE status = 'released'
             ORDER BY published_at DESC, id DESC LIMIT 1"
        );
        $v = $stmt->fetchColumn();
        return $v !== false ? (string) $v : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listReleases(
        int $limit = 50,
        int $offset = 0,
        ?string $search = null,
        ?string $status = null,
        ?string $type = null
    ): array {
        if (!$this->tableExists('platform_releases')) {
            return [];
        }

        $where = ['1=1'];
        $params = [];

        if ($search !== null && $search !== '') {
            $where[] = '(r.version LIKE ? OR r.title LIKE ? OR r.summary LIKE ? OR r.migration_version LIKE ?)';
            $like = '%' . $search . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if ($status !== null && $status !== '') {
            $where[] = 'r.status = ?';
            $params[] = $status;
        }
        if ($type !== null && $type !== '') {
            $where[] = 'r.release_type = ?';
            $params[] = $type;
        }

        $userJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pu ON pu.id = r.released_by'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pu ON 1=0';

        $sql = 'SELECT r.id, r.version, r.title, r.summary, r.changelog, r.release_type, r.status,
                       r.migration_version, r.requires_maintenance, r.published_at, r.created_at, r.updated_at,
                       pu.name AS released_by_name
                FROM platform_releases r
                ' . $userJoin . '
                WHERE ' . implode(' AND ', $where) . '
                ORDER BY r.published_at DESC, r.id DESC
                LIMIT ? OFFSET ?';

        $stmt = $this->db->prepare($sql);
        $i = 1;
        foreach ($params as $p) {
            $stmt->bindValue($i++, $p);
        }
        $stmt->bindValue($i++, $limit, PDO::PARAM_INT);
        $stmt->bindValue($i, $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        foreach ($rows as &$row) {
            $row['requires_maintenance'] = (int) ($row['requires_maintenance'] ?? 0);
        }
        unset($row);

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function listMigrations(int $limit = 50): array
    {
        if (!$this->tableExists('schema_migrations')) {
            return [];
        }

        $linked = [];
        if ($this->tableExists('platform_releases')) {
            $stmt = $this->db->query(
                'SELECT migration_version, version AS release_version, status AS release_status
                 FROM platform_releases
                 WHERE migration_version IS NOT NULL AND migration_version != \'\''
            );
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $r) {
                $linked[(string) $r['migration_version']] = $r;
            }
        }

        $stmt = $this->db->prepare(
            'SELECT version, applied_at FROM schema_migrations ORDER BY applied_at DESC LIMIT ?'
        );
        $stmt->bindValue(1, $limit, PDO::PARAM_INT);
        $stmt->execute();

        $rows = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $m) {
            $ver = (string) ($m['version'] ?? '');
            $link = $linked[$ver] ?? null;
            $rows[] = [
                'version' => $ver,
                'applied_at' => $m['applied_at'] ?? null,
                'release_version' => $link['release_version'] ?? null,
                'release_status' => $link['release_status'] ?? null,
            ];
        }

        return $rows;
    }

    public function findById(int $id): ?array
    {
        if (!$this->tableExists('platform_releases')) {
            return null;
        }

        $userJoin = $this->tableExists('platform_users')
            ? 'LEFT JOIN platform_users pu ON pu.id = r.released_by'
            : 'LEFT JOIN (SELECT NULL AS id, NULL AS name) pu ON 1=0';

        $stmt = $this->db->prepare(
            'SELECT r.*, pu.name AS released_by_name
             FROM platform_releases r
             ' . $userJoin . '
             WHERE r.id = ? LIMIT 1'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            return null;
        }
        $row['requires_maintenance'] = (int) ($row['requires_maintenance'] ?? 0);
        return $row;
    }

    /** @param array<string, mixed> $data */
    public function create(array $data, ?int $userId = null): int
    {
        if (!$this->tableExists('platform_releases')) {
            return 0;
        }

        $stmt = $this->db->prepare(
            'INSERT INTO platform_releases
             (version, title, summary, changelog, release_type, status, migration_version, requires_maintenance, released_by)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) ($data['version'] ?? '')),
            trim((string) ($data['title'] ?? '')),
            trim((string) ($data['summary'] ?? '')),
            trim((string) ($data['changelog'] ?? '')),
            $this->validType((string) ($data['release_type'] ?? 'minor')),
            $this->validStatus((string) ($data['status'] ?? 'draft')),
            ($data['migration_version'] ?? '') !== '' ? trim((string) $data['migration_version']) : null,
            !empty($data['requires_maintenance']) ? 1 : 0,
            $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /** @param array<string, mixed> $data */
    public function update(int $id, array $data): bool
    {
        if (!$this->tableExists('platform_releases')) {
            return false;
        }

        $row = $this->findById($id);
        if (!$row || ($row['status'] ?? '') === 'released') {
            return false;
        }

        $stmt = $this->db->prepare(
            'UPDATE platform_releases SET
                version = ?, title = ?, summary = ?, changelog = ?,
                release_type = ?, status = ?, migration_version = ?, requires_maintenance = ?
             WHERE id = ?'
        );
        $stmt->execute([
            trim((string) ($data['version'] ?? $row['version'])),
            trim((string) ($data['title'] ?? $row['title'])),
            trim((string) ($data['summary'] ?? $row['summary'])),
            trim((string) ($data['changelog'] ?? $row['changelog'])),
            $this->validType((string) ($data['release_type'] ?? $row['release_type'])),
            $this->validStatus((string) ($data['status'] ?? $row['status'])),
            ($data['migration_version'] ?? $row['migration_version']) !== null
                && ($data['migration_version'] ?? $row['migration_version']) !== ''
                ? trim((string) ($data['migration_version'] ?? $row['migration_version']))
                : null,
            !empty($data['requires_maintenance']) ? 1 : 0,
            $id,
        ]);

        return $stmt->rowCount() > 0;
    }

    public function publish(int $id, ?int $userId = null): bool
    {
        if (!$this->tableExists('platform_releases')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE platform_releases SET
                status = 'released',
                published_at = COALESCE(published_at, NOW()),
                released_by = COALESCE(released_by, ?),
                updated_at = NOW()
             WHERE id = ? AND status IN ('draft','scheduled')"
        );
        $stmt->execute([$userId, $id]);
        return $stmt->rowCount() > 0;
    }

    public function delete(int $id): bool
    {
        if (!$this->tableExists('platform_releases')) {
            return false;
        }

        $stmt = $this->db->prepare(
            "DELETE FROM platform_releases WHERE id = ? AND status = 'draft'"
        );
        $stmt->execute([$id]);
        return $stmt->rowCount() > 0;
    }

    private function validType(string $type): string
    {
        return in_array($type, ['major', 'minor', 'patch', 'hotfix', 'migration'], true) ? $type : 'minor';
    }

    private function validStatus(string $status): string
    {
        return in_array($status, ['draft', 'scheduled', 'released', 'rolled_back'], true) ? $status : 'draft';
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare(
            'SELECT 1 FROM information_schema.TABLES
             WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? LIMIT 1'
        );
        $stmt->execute([$table]);
        return (bool) $stmt->fetchColumn();
    }
}
